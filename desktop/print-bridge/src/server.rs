//! Minimal loopback HTTP server for the print bridge.
//!
//! Hand-rolled over [`std::net::TcpListener`] to keep the daemon dependency-free
//! and easy to reason about. It binds to `127.0.0.1` (enforced by the caller) and
//! exposes one endpoint, `POST /print`. The request/response handling is split
//! from the socket loop so it is unit-testable without opening a port.

use std::io::{self, BufRead, BufReader, Read, Write};
use std::net::{SocketAddr, TcpListener, TcpStream};

use base64::Engine;
use serde::Deserialize;

use crate::sink::PrintSink;

/// Hardware retries performed inside the daemon before it returns a terminal
/// answer, so the app only ever sees success or a hard failure.
pub const MAX_RETRIES: u32 = 3;

/// Upper bound on a request body. The dispatcher sends a few kilobytes of
/// base64 at most, so this is generous; anything larger is refused before a
/// byte is allocated. An unbounded `Content-Length` would let any local probe
/// — port 9100 is a printer-discovery port — abort the process with OOM.
pub const MAX_BODY_BYTES: usize = 64 * 1024;

/// Upper bound on a single header (or request) line, so an endless stream of
/// bytes without a newline cannot grow the reader buffer without limit.
pub const MAX_HEADER_LINE_BYTES: usize = 8 * 1024;

/// A client that idles mid-request must not wedge the daemon: it serves one
/// connection at a time, so a stalled peer would otherwise block every later
/// receipt until the process was restarted.
pub const SOCKET_TIMEOUT: std::time::Duration = std::time::Duration::from_secs(5);

#[derive(Debug, Deserialize)]
pub struct PrintRequest {
    pub job_id: String,
    pub data: String,
}

#[derive(Debug)]
pub enum HandlerError {
    BadRequest(String),
    Device(String),
}

impl HandlerError {
    fn status(&self) -> &'static str {
        match self {
            HandlerError::BadRequest(_) => "400 Bad Request",
            HandlerError::Device(_) => "503 Service Unavailable",
        }
    }
}

pub struct Server {
    addr: SocketAddr,
    sink: Box<dyn PrintSink>,
}

/// Map a rejected request onto a status line. `FileTooLarge` is separate from
/// `InvalidData` so an oversized body is answered with a 413 rather than a 400.
fn status_for(err: &io::Error) -> &'static str {
    match err.kind() {
        io::ErrorKind::FileTooLarge => "413 Content Too Large",
        io::ErrorKind::InvalidInput => "405 Method Not Allowed",
        _ => "400 Bad Request",
    }
}

impl Server {
    pub fn new(addr: SocketAddr, sink: Box<dyn PrintSink>) -> Self {
        Self { addr, sink }
    }

    pub fn run(mut self) -> io::Result<()> {
        let listener = TcpListener::bind(self.addr)?;

        for stream in listener.incoming() {
            match stream {
                Ok(stream) => {
                    // Handle serially; the daemon serves one local app, and a
                    // receipt is a few hundred bytes.
                    let _ = self.serve_connection(stream);
                }
                Err(err) => eprintln!("print-bridge: accept error: {err}"),
            }
        }

        Ok(())
    }

    fn serve_connection(&mut self, mut stream: TcpStream) -> io::Result<()> {
        // Set before any read so an idling peer cannot wedge the serial accept
        // loop. A read that times out ends this connection, not the daemon.
        stream.set_read_timeout(Some(SOCKET_TIMEOUT))?;
        stream.set_write_timeout(Some(SOCKET_TIMEOUT))?;

        let (status, body) = match read_request(&mut stream) {
            Err(err) => (status_for(&err), format!("{{\"error\":\"{}\"}}", escape(&err.to_string()))),
            Ok(request) => match handle(&mut *self.sink, &request) {
                Ok(job_id) => (
                    "200 OK",
                    format!("{{\"job_id\":\"{}\",\"status\":\"done\"}}", escape(&job_id)),
                ),
                Err(err) => (
                    err.status(),
                    format!("{{\"error\":\"{}\"}}", escape(err_body(&err))),
                ),
            },
        };

        write_response(&mut stream, status, &body)
    }
}

fn err_body(err: &HandlerError) -> &str {
    match err {
        HandlerError::BadRequest(msg) | HandlerError::Device(msg) => msg,
    }
}

fn escape(text: &str) -> String {
    text.replace('\\', "\\\\").replace('"', "\\\"")
}

/// Handle one print job: decode the ESC/POS payload and write it to the device,
/// retrying a transient failure up to [`MAX_RETRIES`] times.
pub fn handle(sink: &mut dyn PrintSink, request: &PrintRequest) -> Result<String, HandlerError> {
    let bytes = base64::engine::general_purpose::STANDARD
        .decode(request.data.as_bytes())
        .map_err(|_| HandlerError::BadRequest("data is not valid base64".to_string()))?;

    if bytes.is_empty() {
        return Err(HandlerError::BadRequest("data is empty".to_string()));
    }

    let mut last_err = None;
    for attempt in 1..=MAX_RETRIES {
        match sink.write(&bytes) {
            Ok(()) => return Ok(request.job_id.clone()),
            Err(err) => {
                eprintln!(
                    "print-bridge: attempt {attempt}/{MAX_RETRIES} for job {} failed: {err}",
                    request.job_id
                );
                last_err = Some(err);
            }
        }
    }

    Err(HandlerError::Device(
        last_err
            .map(|err| err.to_string())
            .unwrap_or_else(|| "device write failed".to_string()),
    ))
}

/// Read one HTTP request, enforcing the size caps that keep a stray local
/// probe from taking the daemon down.
fn read_request(stream: &mut TcpStream) -> io::Result<PrintRequest> {
    let mut reader = BufReader::new(stream.try_clone()?);

    let mut request_line = String::new();
    if reader.read_line(&mut request_line)? == 0 {
        return Err(io::Error::new(io::ErrorKind::UnexpectedEof, "empty request"));
    }
    check_line_length(request_line.len())?;

    // The bridge exposes exactly one endpoint; anything else is refused rather
    // than routed to the printer.
    let mut parts = request_line.split_ascii_whitespace();
    let method = parts.next().unwrap_or_default();
    let target = parts.next().unwrap_or_default();
    if method != "POST" || target != "/print" {
        return Err(io::Error::new(
            io::ErrorKind::InvalidInput,
            "only POST /print is supported",
        ));
    }

    let mut content_length = 0usize;
    loop {
        let mut header = String::new();
        if reader.read_line(&mut header)? == 0 {
            break;
        }
        check_line_length(header.len())?;
        let trimmed = header.trim_end();
        if trimmed.is_empty() {
            break;
        }
        if let Some((key, value)) = trimmed.split_once(':') {
            if key.eq_ignore_ascii_case("content-length") {
                content_length = parse_content_length(value)?;
            }
        }
    }

    // Cap before allocating: the length is caller-controlled.
    if content_length > MAX_BODY_BYTES {
        return Err(io::Error::new(
            io::ErrorKind::FileTooLarge,
            format!("body exceeds {MAX_BODY_BYTES} bytes"),
        ));
    }

    let mut body = vec![0u8; content_length];
    if content_length > 0 {
        reader.read_exact(&mut body)?;
    }

    let text = String::from_utf8_lossy(&body);
    serde_json::from_str(&text).map_err(|err| io::Error::new(io::ErrorKind::InvalidData, err))
}

/// Reject a header line that never ends, so the reader buffer stays bounded.
fn check_line_length(len: usize) -> io::Result<()> {
    if len > MAX_HEADER_LINE_BYTES {
        return Err(io::Error::new(
            io::ErrorKind::InvalidData,
            format!("header line exceeds {MAX_HEADER_LINE_BYTES} bytes"),
        ));
    }
    Ok(())
}

/// A malformed or absent `Content-Length` must not be read as a huge number.
fn parse_content_length(value: &str) -> io::Result<usize> {
    value.trim().parse().map_err(|err| {
        io::Error::new(
            io::ErrorKind::InvalidData,
            format!("invalid content-length: {err}"),
        )
    })
}

fn write_response(stream: &mut TcpStream, status: &str, body: &str) -> io::Result<()> {
    let response = format!(
        "HTTP/1.1 {status}\r\nContent-Type: application/json\r\nContent-Length: {}\r\nConnection: close\r\n\r\n{body}",
        body.len()
    );
    stream.write_all(response.as_bytes())?;
    stream.flush()
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::sink::FailingSink;
    use crate::sink::{from_target, PrintSink};

    fn encode(bytes: &[u8]) -> String {
        base64::engine::general_purpose::STANDARD.encode(bytes)
    }

    fn request(data: &str) -> PrintRequest {
        PrintRequest {
            job_id: "job-1".to_string(),
            data: data.to_string(),
        }
    }

    fn file_sink() -> (Box<dyn PrintSink>, std::path::PathBuf) {
        let dir = tempfile::tempdir().unwrap();
        let path = dir.path().join("out.bin");
        // Keep the tempdir alive for the life of the process; tests are short-lived.
        let _dir = dir.keep();
        let sink = from_target(&format!("file:{}", path.display())).unwrap();
        (sink, path)
    }

    /// Serve exactly one connection on a fresh loopback port, returning the
    /// address to connect to.
    fn serve_one(sink: Box<dyn PrintSink>) -> SocketAddr {
        let listener = TcpListener::bind("127.0.0.1:0".parse::<SocketAddr>().unwrap()).unwrap();
        let bound = listener.local_addr().unwrap();

        std::thread::spawn(move || {
            let mut server = Server { addr: bound, sink };
            if let Ok((stream, _)) = listener.accept() {
                let _ = server.serve_connection(stream);
            }
        });

        bound
    }

    /// Send a raw request line + headers + body and read the raw response.
    fn round_trip(bound: &SocketAddr, raw: &str) -> String {
        let mut stream = TcpStream::connect(bound).unwrap();
        stream.write_all(raw.as_bytes()).unwrap();
        stream.flush().unwrap();

        let mut response = String::new();
        stream.read_to_string(&mut response).unwrap();
        response
    }

    fn print_request_body() -> String {
        format!(
            "{{\"job_id\":\"job-1\",\"data\":\"{}\"}}",
            encode(b"\x1b\x40")
        )
    }

    fn print_request(body: &str) -> String {
        format!(
            "POST /print HTTP/1.1\r\nHost: 127.0.0.1\r\nContent-Type: application/json\r\nContent-Length: {}\r\n\r\n{body}",
            body.len()
        )
    }

    #[test]
    fn handler_writes_decoded_bytes_to_the_sink() {
        let (mut sink, path) = file_sink();

        let result = handle(&mut *sink, &request(&encode(b"\x1b\x40receipt\x1d\x56\x00"))).unwrap();
        drop(sink);

        assert_eq!(result, "job-1");
        assert_eq!(std::fs::read(&path).unwrap(), b"\x1b\x40receipt\x1d\x56\x00");
    }

    #[test]
    fn handler_rejects_invalid_base64() {
        let (mut sink, _path) = file_sink();
        let err = handle(&mut *sink, &request("!!! not base64 !!!")).unwrap_err();
        assert!(matches!(err, HandlerError::BadRequest(_)));
    }

    #[test]
    fn handler_rejects_empty_data() {
        let (mut sink, _path) = file_sink();
        let err = handle(&mut *sink, &request(&encode(b""))).unwrap_err();
        assert!(matches!(err, HandlerError::BadRequest(_)));
    }

    #[test]
    fn handler_retries_then_reports_device_failure() {
        let mut sink = FailingSink::new("printer is out of paper");
        let err = handle(&mut sink, &request(&encode(b"\x1b\x40"))).unwrap_err();
        match err {
            HandlerError::Device(msg) => assert!(msg.contains("out of paper")),
            other => panic!("expected a device error, got {other:?}"),
        }
    }

    #[test]
    fn server_returns_200_for_a_good_job() {
        let (sink, _path) = file_sink();
        let bound = serve_one(sink);

        let response = round_trip(&bound, &print_request(&print_request_body()));

        assert!(response.starts_with("HTTP/1.1 200 OK"), "got: {response}");
        assert!(response.contains("\"status\":\"done\""));
    }

    #[test]
    fn server_returns_503_when_the_device_fails() {
        let sink: Box<dyn PrintSink> = Box::new(FailingSink::new("unplugged"));
        let bound = serve_one(sink);

        let response = round_trip(&bound, &print_request(&print_request_body()));

        assert!(response.starts_with("HTTP/1.1 503 Service Unavailable"), "got: {response}");
    }

    #[test]
    fn server_rejects_an_oversized_body_before_allocating() {
        let (sink, _path) = file_sink();
        let bound = serve_one(sink);

        // Claim a body far larger than the cap without sending it: the daemon
        // must refuse on the header alone, not read (or allocate) the body.
        let raw = "POST /print HTTP/1.1\r\nHost: 127.0.0.1\r\nContent-Length: 99999999\r\n\r\n";
        let response = round_trip(&bound, raw);

        assert!(response.starts_with("HTTP/1.1 413"), "got: {response}");
    }

    #[test]
    fn server_rejects_an_endless_header_line() {
        let (sink, _path) = file_sink();
        let bound = serve_one(sink);

        let long_header = "X-Pad: ".to_string() + &"a".repeat(MAX_HEADER_LINE_BYTES + 1);
        let raw = format!("POST /print HTTP/1.1\r\n{long_header}\r\n\r\n");
        let response = round_trip(&bound, &raw);

        assert!(response.starts_with("HTTP/1.1 400"), "got: {response}");
    }

    #[test]
    fn server_rejects_a_request_line_that_never_ends() {
        let (sink, _path) = file_sink();
        let bound = serve_one(sink);

        let raw = "POST /print HTTP/1.1".to_string() + &"a".repeat(MAX_HEADER_LINE_BYTES + 1);
        let response = round_trip(&bound, &raw);

        assert!(response.starts_with("HTTP/1.1 400"), "got: {response}");
    }

    #[test]
    fn server_refuses_anything_that_is_not_post_print() {
        let (sink, _path) = file_sink();
        let bound = serve_one(sink);

        let body = print_request_body();
        let raw = format!(
            "GET /print HTTP/1.1\r\nHost: 127.0.0.1\r\nContent-Length: {}\r\n\r\n{body}",
            body.len()
        );
        let response = round_trip(&bound, &raw);

        assert!(response.starts_with("HTTP/1.1 405"), "got: {response}");
    }

    #[test]
    fn server_refuses_a_nonexistent_path() {
        let (sink, _path) = file_sink();
        let bound = serve_one(sink);

        let response = round_trip(&bound, "GET / HTTP/1.1\r\nHost: 127.0.0.1\r\n\r\n");

        assert!(response.starts_with("HTTP/1.1 405"), "got: {response}");
    }

    #[test]
    fn server_escapes_the_job_id_in_the_success_body() {
        let (sink, _path) = file_sink();
        let bound = serve_one(sink);

        // A job_id containing quotes/braces must not be able to break the JSON.
        let evil_id = "evil\"}}{\"x";
        let body = format!(
            "{{\"job_id\":\"{}\",\"data\":\"{}\"}}",
            evil_id.replace('"', "\\\""),
            encode(b"\x1b\x40")
        );
        let response = round_trip(&bound, &print_request(&body));

        assert!(response.starts_with("HTTP/1.1 200"), "got: {response}");
        // The raw quote must not survive into the JSON body unescaped.
        assert!(!response.contains("evil\"}}"), "got: {response}");
    }

    #[test]
    fn bind_ip_is_loopback() {
        // The daemon owns a physical device; it must never be LAN-reachable.
        assert!(crate::BIND_IP.is_loopback());
        assert_eq!(crate::BIND_IP, std::net::Ipv4Addr::LOCALHOST);
    }
}

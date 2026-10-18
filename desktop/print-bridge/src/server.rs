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
        let request = read_request(&mut stream)?;

        let (status, body) = match handle(&mut *self.sink, &request) {
            Ok(job_id) => ("200 OK", format!("{{\"job_id\":\"{job_id}\",\"status\":\"done\"}}")),
            Err(err) => (err.status(), format!("{{\"error\":\"{}\"}}", escape(err_body(&err)))),
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

fn read_request(stream: &mut TcpStream) -> io::Result<PrintRequest> {
    let mut reader = BufReader::new(stream.try_clone()?);

    let mut request_line = String::new();
    reader.read_line(&mut request_line)?;

    let mut content_length = 0usize;
    loop {
        let mut header = String::new();
        if reader.read_line(&mut header)? == 0 {
            break;
        }
        let trimmed = header.trim_end();
        if trimmed.is_empty() {
            break;
        }
        if let Some((key, value)) = trimmed.split_once(':') {
            if key.eq_ignore_ascii_case("content-length") {
                content_length = value.trim().parse().unwrap_or(0);
            }
        }
    }

    let mut body = vec![0u8; content_length];
    if content_length > 0 {
        reader.read_exact(&mut body)?;
    }

    let text = String::from_utf8_lossy(&body);
    serde_json::from_str(&text).map_err(|err| io::Error::new(io::ErrorKind::InvalidData, err))
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
        let addr: SocketAddr = "127.0.0.1:0".parse().unwrap();
        let listener = TcpListener::bind(addr).unwrap();
        let bound = listener.local_addr().unwrap();

        std::thread::spawn(move || {
            let mut server = Server { addr: bound, sink };
            if let Ok((stream, _)) = listener.accept() {
                let _ = server.serve_connection(stream);
            }
        });

        let mut stream = TcpStream::connect(bound).unwrap();
        let body = format!(
            "{{\"job_id\":\"job-1\",\"data\":\"{}\"}}",
            encode(b"\x1b\x40")
        );
        let raw = format!(
            "POST /print HTTP/1.1\r\nHost: 127.0.0.1\r\nContent-Type: application/json\r\nContent-Length: {}\r\n\r\n{body}",
            body.len()
        );
        stream.write_all(raw.as_bytes()).unwrap();

        let mut response = String::new();
        stream.read_to_string(&mut response).unwrap();
        assert!(response.starts_with("HTTP/1.1 200 OK"), "got: {response}");
        assert!(response.contains("\"status\":\"done\""));
    }

    #[test]
    fn server_returns_503_when_the_device_fails() {
        let sink: Box<dyn PrintSink> = Box::new(FailingSink::new("unplugged"));
        let addr: SocketAddr = "127.0.0.1:0".parse().unwrap();
        let listener = TcpListener::bind(addr).unwrap();
        let bound = listener.local_addr().unwrap();

        std::thread::spawn(move || {
            let mut server = Server { addr: bound, sink };
            if let Ok((stream, _)) = listener.accept() {
                let _ = server.serve_connection(stream);
            }
        });

        let mut stream = TcpStream::connect(bound).unwrap();
        let body = format!(
            "{{\"job_id\":\"job-1\",\"data\":\"{}\"}}",
            encode(b"\x1b\x40")
        );
        let raw = format!(
            "POST /print HTTP/1.1\r\nHost: 127.0.0.1\r\nContent-Type: application/json\r\nContent-Length: {}\r\n\r\n{body}",
            body.len()
        );
        stream.write_all(raw.as_bytes()).unwrap();

        let mut response = String::new();
        stream.read_to_string(&mut response).unwrap();
        assert!(response.starts_with("HTTP/1.1 503 Service Unavailable"), "got: {response}");
    }
}

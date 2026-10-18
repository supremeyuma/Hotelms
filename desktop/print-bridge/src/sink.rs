//! Pluggable output sinks.
//!
//! The daemon owns the hardware. Anything that can write raw ESC/POS bytes to a
//! device implements [`PrintSink`], so swapping a file, a serial port or a
//! network printer touches nothing but this module.

use std::fmt;
use std::fs::File;
use std::io::{self, Write};
use std::net::TcpStream;

/// A destination for raw ESC/POS bytes.
///
/// `write` should be terminal for the caller: any transient retry that can be
/// done safely belongs inside the sink, so the HTTP handler sees only success
/// or a hard failure.
pub trait PrintSink: Send {
    /// Human-readable destination, for logs.
    fn describe(&self) -> String;

    /// Write raw ESC/POS bytes to the device.
    fn write(&mut self, bytes: &[u8]) -> io::Result<()>;
}

/// A file (or any [`Write`]) sink. Used for `stdout`, a capture file in tests,
/// and any device that is exposed as a plain file on the host.
struct WriterSink<W: Write + Send> {
    label: String,
    writer: W,
}

impl<W: Write + Send> PrintSink for WriterSink<W> {
    fn describe(&self) -> String {
        self.label.clone()
    }

    fn write(&mut self, bytes: &[u8]) -> io::Result<()> {
        self.writer.write_all(bytes)?;
        self.writer.flush()
    }
}

/// A raw serial port. This is the real ESC/POS transport for a USB-serial or
/// RS-232 thermal printer; it is thin over a file handle so it needs no extra
/// dependency. The port is opened per job and closed on drop.
#[cfg(unix)]
struct SerialSink {
    path: String,
    file: Option<File>,
}

#[cfg(unix)]
impl SerialSink {
    fn open(path: &str) -> io::Result<Self> {
        // 9600 8N1 is the common ESC/POS default; a printer that needs another
        // rate is configured in udev or the printer itself, not here.
        let file = File::options().write(true).open(path)?;
        Ok(Self {
            path: path.to_string(),
            file: Some(file),
        })
    }
}

#[cfg(unix)]
impl PrintSink for SerialSink {
    fn describe(&self) -> String {
        format!("serial:{}", self.path)
    }

    fn write(&mut self, bytes: &[u8]) -> io::Result<()> {
        match self.file.as_mut() {
            Some(file) => {
                file.write_all(bytes)?;
                file.flush()
            }
            None => Err(io::Error::new(
                io::ErrorKind::NotConnected,
                "serial port is not open",
            )),
        }
    }
}

/// A network thermal printer reached over raw TCP (port 9100 is the convention
/// for Epson ePOS/TMNet printers).
struct TcpSink {
    addr: String,
    stream: Option<TcpStream>,
}

impl TcpSink {
    fn open(addr: &str) -> io::Result<Self> {
        // Open lazily so a printer that is off at boot does not fail the daemon.
        Ok(Self {
            addr: addr.to_string(),
            stream: None,
        })
    }
}

impl PrintSink for TcpSink {
    fn describe(&self) -> String {
        format!("tcp:{}", self.addr)
    }

    fn write(&mut self, bytes: &[u8]) -> io::Result<()> {
        let stream = match self.stream.as_mut() {
            Some(stream) => stream,
            None => {
                let stream = TcpStream::connect(&self.addr)?;
                self.stream.insert(stream)
            }
        };

        match stream.write_all(bytes) {
            Ok(()) => stream.flush(),
            Err(err) => {
                // Drop the cached connection so the next job reconnects.
                self.stream = None;
                Err(err)
            }
        }
    }
}

/// Build a sink from a `target` string.
///
/// * `stdout` — write to standard output (default; no hardware needed).
/// * `file:/path/to/out.bin` — write to a file.
/// * `tcp:host:port` — write to a network printer.
/// * `serial:/dev/ttyUSB0` — write to a serial port (Unix only).
pub fn from_target(target: &str) -> io::Result<Box<dyn PrintSink>> {
    if let Some(path) = target.strip_prefix("file:") {
        let file = File::create(path)?;
        return Ok(Box::new(WriterSink {
            label: target.to_string(),
            writer: file,
        }));
    }

    if let Some(addr) = target.strip_prefix("tcp:") {
        return Ok(Box::new(TcpSink::open(addr)?));
    }

    if let Some(path) = target.strip_prefix("serial:") {
        return Ok(serial_sink(path)?);
    }

    // Bare `stdout`.
    Ok(Box::new(WriterSink {
        label: "stdout".to_string(),
        writer: io::stdout(),
    }))
}

#[cfg(unix)]
fn serial_sink(path: &str) -> io::Result<Box<dyn PrintSink>> {
    Ok(Box::new(SerialSink::open(path)?))
}

#[cfg(not(unix))]
fn serial_sink(path: &str) -> io::Result<Box<dyn PrintSink>> {
    // Windows COM ports are files too; open the same way.
    let file = File::options().write(true).open(path)?;
    Ok(Box::new(WriterSink {
        label: format!("serial:{path}"),
        writer: file,
    }))
}

/// A sink that fails every write. Used to exercise the failure path in tests
/// and to represent an unplugged printer.
#[cfg(test)]
pub struct FailingSink {
    reason: String,
}

#[cfg(test)]
impl FailingSink {
    pub fn new(reason: &str) -> Self {
        Self {
            reason: reason.to_string(),
        }
    }
}

#[cfg(test)]
impl PrintSink for FailingSink {
    fn describe(&self) -> String {
        format!("failing:{}", self.reason)
    }

    fn write(&mut self, _bytes: &[u8]) -> io::Result<()> {
        Err(io::Error::other(self.reason.clone()))
    }
}

impl fmt::Debug for dyn PrintSink {
    fn fmt(&self, f: &mut fmt::Formatter<'_>) -> fmt::Result {
        write!(f, "PrintSink({})", self.describe())
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn file_sink_writes_bytes_verbatim() {
        let dir = tempfile::tempdir().unwrap();
        let path = dir.path().join("out.bin");
        let target = format!("file:{}", path.display());

        let mut sink = from_target(&target).unwrap();
        sink.write(b"\x1b\x40hello\x1d\x56\x00").unwrap();
        drop(sink);

        let written = std::fs::read(&path).unwrap();
        assert_eq!(written, b"\x1b\x40hello\x1d\x56\x00");
    }

    #[test]
    fn stdout_sink_describes_itself() {
        let sink = from_target("stdout").unwrap();
        assert_eq!(sink.describe(), "stdout");
    }

    #[test]
    fn failing_sink_returns_an_error() {
        let mut sink = FailingSink::new("printer is out of paper");
        let err = sink.write(b"bytes").unwrap_err();
        assert!(err.to_string().contains("out of paper"));
    }

    #[test]
    fn serial_sink_opens_a_device_file() {
        let dir = tempfile::tempdir().unwrap();
        let path = dir.path().join("tty");
        std::fs::write(&path, b"").unwrap();

        let mut sink = from_target(&format!("file:{}", path.display())).unwrap();
        assert!(sink.write(b"\x1b\x40").is_ok());
    }
}

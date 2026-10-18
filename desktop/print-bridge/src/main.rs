//! Local ESC/POS print bridge.
//!
//! The app never talks to a printer. It POSTs a job here and this daemon owns
//! every byte written to the device. It listens on `127.0.0.1` only — it holds
//! a physical device and must never be reachable from the LAN. Hardware retry
//! (a transient USB/serial failure) happens inside the sink, so the caller only
//! ever sees a terminal answer.
//!
//! `POST /print` accepts `{"job_id": "...", "data": "<base64 ESC/POS>"}` and
//! returns `200` on success or a `5xx`/`4xx` on failure.

mod server;
mod sink;

use std::io;
use std::net::{Ipv4Addr, SocketAddr};

use server::Server;

/// Default listen port, matching `config/pos.php` `print.bridge_url`.
pub const DEFAULT_PORT: u16 = 9100;

/// The bridge is deliberately bound to loopback. A printer bridge reachable by
/// every guest device on the network is a real and embarrassing exposure.
pub const BIND_IP: Ipv4Addr = Ipv4Addr::LOCALHOST;

fn main() {
    if let Err(err) = run() {
        eprintln!("print-bridge: {err}");
        std::process::exit(1);
    }
}

fn run() -> io::Result<()> {
    let port = std::env::var("PRINT_BRIDGE_PORT")
        .ok()
        .and_then(|raw| raw.parse().ok())
        .unwrap_or(DEFAULT_PORT);
    let target = std::env::var("PRINT_BRIDGE_TARGET").unwrap_or_else(|_| "stdout".to_string());

    let sink = sink::from_target(&target)?;
    let addr = SocketAddr::new(BIND_IP.into(), port);

    println!("print-bridge: listening on {addr} (loopback only) -> {target}");
    Server::new(addr, sink).run()
}

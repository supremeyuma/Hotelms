//! LAN discovery and pairing (spec 9.3).
//!
//! A UDP broadcast probe on a fixed port precedes the pairing QR code and
//! manual IP entry fallbacks. mDNS is avoided because it is unreliable on
//! Windows networks and often blocked by group policy.
//!
//! Clients broadcast a fixed probe payload; the server role answers with a
//! self-describing line identifying itself. Because broadcast is blocked often
//! enough that discovery alone is not a setup path, the QR code and manual IP
//! entry both remain required fallbacks.

use std::net::{IpAddr, SocketAddr, UdpSocket};
use std::time::{Duration, Instant};

use anyhow::Context;
use serde::Deserialize;

/// The fixed UDP port every MooreLife server listens on for probes.
pub const PROBE_PORT: u16 = 48211;

/// Broadcast by clients. A constant, not a secret: anyone on the LAN can learn
/// that a server exists. The pairing credential is what actually authorises.
pub const PROBE_PAYLOAD: &[u8] = b"HOTELMS_DISCOVER_V1";
/// Reply prefix from servers, followed by one line of JSON.
const RESPONSE_MAGIC: &[u8] = b"HOTELMS_PROBE_V1 ";

const RECEIVE_BUFFER_SIZE: usize = 512;

#[derive(Debug, Clone, PartialEq, Eq)]
pub struct DiscoveredServer {
    pub address: SocketAddr,
    pub name: String,
    pub version: String,
    pub http_port: u16,
}

#[derive(Debug, Clone, PartialEq, Eq, Deserialize)]
struct ProbeReply {
    name: String,
    version: String,
    port: u16,
}

/// Broadcast the probe payload and collect every reply that arrives within
/// `timeout`. Replies whose payload does not start with our magic are ignored
/// — the socket is shared with unrelated LAN traffic.
pub fn probe(timeout: Duration) -> anyhow::Result<Vec<DiscoveredServer>> {
    let socket = bind_broadcast_socket()?;
    socket
        .send_to(
            PROBE_PAYLOAD,
            SocketAddr::from(([255, 255, 255, 255], PROBE_PORT)),
        )
        .context("failed to broadcast the discovery probe")?;
    collect_replies(&socket, timeout)
}

/// Bind the probe socket. `SO_BROADCAST` is required for the broadcast to be
/// sent at all; some Windows configurations drop it by default.
fn bind_broadcast_socket() -> anyhow::Result<UdpSocket> {
    let socket = UdpSocket::bind(SocketAddr::from(([0, 0, 0, 0], 0)))
        .context("failed to bind the discovery socket")?;
    socket
        .set_broadcast(true)
        .context("failed to enable broadcast")?;
    socket
        .set_read_timeout(Some(Duration::from_millis(100)))
        .context("failed to set the discovery receive timeout")?;
    Ok(socket)
}

/// Collect replies until `timeout` elapses, parsing each into a discovered
/// server. Uses the caller's socket so tests can probe a loopback listener
/// without broadcasting on the shared LAN.
fn collect_replies(socket: &UdpSocket, timeout: Duration) -> anyhow::Result<Vec<DiscoveredServer>> {
    let deadline = Instant::now() + timeout;
    let mut found = Vec::new();
    let mut buf = [0u8; RECEIVE_BUFFER_SIZE];

    while Instant::now() < deadline {
        match socket.recv_from(&mut buf) {
            Ok((len, from)) => {
                if let Some(reply) = parse_probe(&buf[..len]) {
                    found.push(DiscoveredServer {
                        address: from,
                        name: reply.name,
                        version: reply.version,
                        http_port: reply.port,
                    });
                }
            }
            Err(e)
                if e.kind() == std::io::ErrorKind::WouldBlock
                    || e.kind() == std::io::ErrorKind::TimedOut =>
            {
                continue;
            }
            Err(e) => return Err(e).context("failed to receive a probe reply"),
        }
    }

    Ok(found)
}

/// Parse one probe reply: the magic prefix followed by a single JSON object.
fn parse_probe(payload: &[u8]) -> Option<ProbeReply> {
    let stripped = payload.strip_prefix(RESPONSE_MAGIC)?;
    let reply: ProbeReply = serde_json::from_slice(stripped).ok()?;
    Some(reply)
}

/// Start answering probes on `PROBE_PORT` as the server role. Returns once the
/// listener is bound; the answer loop runs on a detached thread for the life
/// of the process.
pub fn respond(name: &str, version: &str, http_port: u16) -> anyhow::Result<()> {
    respond_on(
        SocketAddr::from(([0, 0, 0, 0], PROBE_PORT)),
        name,
        version,
        http_port,
    )?;
    Ok(())
}

/// Start answering probes on `addr`. The bound address is returned so callers
/// that asked for an ephemeral port can learn what was actually bound.
fn respond_on(
    addr: SocketAddr,
    name: &str,
    version: &str,
    http_port: u16,
) -> anyhow::Result<SocketAddr> {
    let socket = UdpSocket::bind(addr).with_context(|| format!("failed to bind {addr}"))?;
    let bound = socket
        .local_addr()
        .context("failed to read the bound address")?;

    let name = name.to_string();
    let version = version.to_string();
    std::thread::spawn(move || serve_forever(socket, name, version, http_port));
    Ok(bound)
}

/// Serve probe requests until the socket fails or the process exits.
fn serve_forever(socket: UdpSocket, name: String, version: String, http_port: u16) {
    let mut buf = [0u8; RECEIVE_BUFFER_SIZE];
    let reply_body = serde_json::json!({
        "name": name,
        "version": version,
        "port": http_port,
    });
    let mut reply = Vec::with_capacity(RESPONSE_MAGIC.len() + reply_body.to_string().len());
    reply.extend_from_slice(RESPONSE_MAGIC);
    reply.extend_from_slice(reply_body.to_string().as_bytes());

    while let Ok((len, from)) = socket.recv_from(&mut buf) {
        if buf[..len] == *PROBE_PAYLOAD {
            let _ = socket.send_to(&reply, from);
        }
    }
}

/// The pairing URI a QR code encodes: everything a paired device needs to reach
/// this server without broadcast, in a form it can also re-type by hand.
pub fn pairing_uri(ip: IpAddr, name: &str, version: &str, http_port: u16) -> String {
    format!("moorelife://pair?v=1&host={ip}&port={http_port}&name={name}&version={version}")
}

/// This machine's own LAN addresses, for the pairing QR code. IPv4 only:
/// office and home networks are still overwhelmingly v4, and a QR target is a
/// device on the same LAN. Loopback is included so a single-machine install
/// can still demo the pairing link.
pub fn lan_addresses() -> anyhow::Result<Vec<IpAddr>> {
    let mut found = Vec::new();
    for (_interface, ip) in local_ip_address::list_afinet_netifas()? {
        if let IpAddr::V4(v4) = ip {
            if v4.is_private() || v4.is_loopback() {
                found.push(IpAddr::V4(v4));
            }
        }
    }
    found.sort();
    found.dedup();
    Ok(found)
}

#[cfg(test)]
mod tests {
    use super::*;

    /// Bind a responder on the loopback and confirm a probe finds it — the
    /// full protocol, unicast, on one machine.
    #[test]
    fn responder_answers_a_unicast_probe() {
        let addr = respond_on(
            SocketAddr::from(([127, 0, 0, 1], 0)),
            "MooreLife",
            "0.1.0",
            8000,
        )
        .expect("responder binds");

        let socket =
            UdpSocket::bind(SocketAddr::from(([127, 0, 0, 1], 0))).expect("probe socket binds");
        socket.send_to(PROBE_PAYLOAD, addr).expect("probe is sent");

        let mut buf = [0u8; RECEIVE_BUFFER_SIZE];
        let (len, _from) = socket
            .recv_from(&mut buf)
            .expect("responder replies within the receive timeout");

        let parsed = parse_probe(&buf[..len]).expect("reply parses");
        assert_eq!(
            parsed,
            ProbeReply {
                name: "MooreLife".into(),
                version: "0.1.0".into(),
                port: 8000,
            }
        );
    }

    #[test]
    fn parse_probe_ignores_unrelated_traffic() {
        assert!(parse_probe(b"garbage from another protocol").is_none());
        assert!(parse_probe(b"HOTELMS_PROBE_V1 not-json").is_none());
    }

    #[test]
    fn pairing_uri_carries_everything_a_device_needs() {
        let uri = pairing_uri("192.168.1.20".parse().unwrap(), "MooreLife", "0.1.0", 8000);
        assert_eq!(
            uri,
            "moorelife://pair?v=1&host=192.168.1.20&port=8000&name=MooreLife&version=0.1.0"
        );
    }
}

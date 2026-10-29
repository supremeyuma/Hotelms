//! Path hygiene for the child processes setup spawns.
//!
//! Windows hands out *verbatim* (extended-length) paths — `\\?\C:\...` — from
//! APIs such as `std::fs::canonicalize`, some installer helpers, and anything
//! that round-trips a `PathBuf` through a tool that canonicalises. PHP's stream
//! layer does not resolve that form: given
//! `--root=\\?\C:\app\public`, FrankenPHP starts, answers the readiness check,
//! and then every request dies with
//!
//! ```text
//! Fatal error: Failed opening required '\\?\C:\app\public\index.php'
//! ```
//!
//! even though the file is present. The server role therefore hands PHP the
//! plain `C:\...` form. Only the prefix is removed — the path is otherwise left
//! exactly as resolved, so nothing is silently rewritten beyond that.

use std::path::{Path, PathBuf};

/// Drop a Windows verbatim prefix (`\\?\`) from `path`, leaving every other
/// path untouched. UNC verbatim paths (`\\?\UNC\server\share`) become
/// `\\server\share`, which is the form PHP and Postgres understand.
pub fn simplify(path: &Path) -> PathBuf {
    let raw = path.as_os_str().to_string_lossy();

    if let Some(rest) = raw.strip_prefix(r"\\?\UNC\") {
        return PathBuf::from(format!(r"\\{rest}"));
    }

    if let Some(rest) = raw.strip_prefix(r"\\?\") {
        return PathBuf::from(rest);
    }

    path.to_path_buf()
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn a_plain_path_is_returned_unchanged() {
        let path = Path::new(r"C:\Users\ana\AppData\Roaming\com.moorelife.desktop\app");
        assert_eq!(simplify(path), path);
    }

    #[test]
    fn a_verbatim_drive_path_loses_only_the_prefix() {
        let path = Path::new(r"\\?\C:\Users\ana\repos\hotelms\public");
        assert_eq!(
            simplify(path),
            PathBuf::from(r"C:\Users\ana\repos\hotelms\public")
        );
    }

    #[test]
    fn a_verbatim_unc_path_becomes_a_plain_unc_path() {
        let path = Path::new(r"\\?\UNC\fileserver\share\hotelms\public");
        assert_eq!(
            simplify(path),
            PathBuf::from(r"\\fileserver\share\hotelms\public")
        );
    }

    #[test]
    fn a_posix_path_is_returned_unchanged() {
        let path = Path::new("/opt/moorelife/public");
        assert_eq!(simplify(path), path);
    }
}

# MooreLife Desktop

A Tauri v2 shell that packages the MooreLife hotel management system as one
installer. The same application becomes a **LAN server**, a **POS terminal**, or
a **staff client**, chosen by an explicit first question at setup. One bundled
FrankenPHP runtime serves every role; only `.env` differs.

## Runtime decisions

Three decisions were fixed before packaging work began. Each is recorded here so
it is not re-derived.

### Gate A — image extension: `gd`

The application generates thumbnails with Intervention Image. The bundled
runtime uses **`gd`**, which ships inside the PHP build (`ext/php_gd.dll`) and
needs no host installation. `imagick` was rejected: it requires ImageMagick DLLs
alongside the binary, the exact host dependency a single-binary runtime exists
to avoid.

**Finding:** `intervention/image` was referenced by
`app/Jobs/GenerateImageThumbnailsJob.php` but absent from `composer.json` /
`composer.lock`, so thumbnail generation failed silently (the exception is
caught and logged). The package was added and the job uses the gd driver.

### Gate B — embedded Postgres: vendored archives

The LAN-server role unpacks a **vendored Postgres archive per target platform**
into the application data directory, runs `initdb` on first run, and supervises
`postgres` as a child process. No installer is required. The alternatives were
rejected: a Rust crate that downloads binaries at setup adds a network
dependency during installation, and requiring a system Postgres reintroduces the
installer requirement the embedded runtime exists to remove.

**Vendored build:** EnterpriseDB `postgresql-16.6-1-windows-x64-binaries.zip`
(PostgreSQL 16.6), pinned in `resources/postgres/VERSION` and hashed in
`resources/postgres/checksums.txt`. It extracts to `pgsql/`; `postgres::start`
takes that directory and finds `bin/postgres[.exe]` beneath it.

**Supervision caveat (verified):** the supervisor runs `postgres[.exe]`
*directly*, not `pg_ctl start`. `pg_ctl start` daemonises and exits, so its
child handle is not the running server and cannot be waited on, restarted, or
killed. Holding the real server process is what makes restart and crash
detection meaningful. Cluster creation uses `initdb -U hotelms` with
scram-sha-256 and a `--pwfile`, matching the `hotelms`/`hotelms` credentials the
server wizard writes into `.env`. `ensure_database` creates the application
database (the superuser role already exists from `initdb`).

The unpack-archive-into-the-data-directory step belongs to the server wizard;
the archive is bundled as a resource in the packaging task, not committed here.

### Gate C — FrankenPHP Windows extensions: PASS

Verified against **FrankenPHP 1.13.0** (`frankenphp-windows-x86_64.zip`), PHP
**8.5.11 ZTS**. All required extensions load from the bundled DLLs, including
`pdo_pgsql` via the bundled `libpq.dll`. The embedded-Postgres server role is
therefore viable.

Verify any target with:

```
scripts/verify-runtime.ps1 -RuntimeDir resources/bin/<target-triple>
```

Two findings shape the runtime code:

1. **`frankenphp.exe php-cli` is unavailable below PHP 8.6.** FrankenPHP 1.13.0
   answers `php-cli` with "this functionality is not available ... with a php
   version lower than 8.6". CLI work — `artisan`, `migrate`, `seed` — must use
   the `php.exe` that ships inside the archive, not `frankenphp.exe php-cli`.
2. **The bundle needs a `php.ini`.** Shared extensions live under `ext/`, and
   the shipped `php.ini-production` has every `extension=` line commented out.
   A `php.ini` with `extension_dir = "ext"` and the required extensions enabled
   must ship with the runtime, or `php -m` reports them missing even though the
   DLLs are present.

## Unsigned builds

No code-signing certificate is available. Windows SmartScreen and macOS
Gatekeeper will warn on first launch. Builds are unsigned by design; document
this in the release notes before distributing.

## Development

```
npm install
npm run tauri dev
```

Build a bundle: `npm run tauri build`.

# Verifies that a bundled FrankenPHP runtime provides every PHP extension the
# application requires. Run against each target's extracted runtime directory so
# a missing extension fails CI rather than a customer's first run.
#
# Usage:
#   scripts/verify-runtime.ps1 -RuntimeDir resources/bin/x86_64-pc-windows-msvc
#
# The check uses the PHP binary that ships inside the FrankenPHP archive, not
# `frankenphp.exe php-cli`. FrankenPHP 1.13.0 (PHP 8.5) answers `php-cli` with
# "this functionality is not available ... with a php version lower than 8.6",
# so the bundled php.exe (Windows) / php (macOS, Linux) is the CLI entry point.
# It also points PHP at the runtime's own php.ini, because every shared
# extension under ext/ is disabled by default in the shipped php.ini-production
# and a bare `php -m` would report them missing even though the DLLs exist.

param(
    [Parameter(Mandatory)] [string] $RuntimeDir
)

$required = @('pdo_sqlite', 'pdo_pgsql', 'bcmath', 'intl', 'zip')

$php = Join-Path $RuntimeDir 'php.exe'
if (-not (Test-Path -LiteralPath $php)) {
    $php = Join-Path $RuntimeDir 'php'
}

if (-not (Test-Path -LiteralPath $php)) {
    Write-Error "Bundled PHP binary not found under $RuntimeDir"
    exit 1
}

$ini = Join-Path $RuntimeDir 'php.ini'
if (-not (Test-Path -LiteralPath $ini)) {
    Write-Error "php.ini not found under $RuntimeDir; shared extensions cannot load"
    exit 1
}

$env:PHPRC = (Resolve-Path -LiteralPath $RuntimeDir).Path

$modules = & $php -m
$missing = $required | Where-Object { $_ -notin $modules }

if ($missing) {
    Write-Error ("Runtime is missing required extensions: " + ($missing -join ', '))
    exit 1
}

Write-Host "All required extensions present."

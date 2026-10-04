# Phase 1 — Postgres Portability Implementation Plan

**Goal:** Ensure all 144 migrations run fresh against PostgreSQL, make driver-specific paths conditional, and add schema parity verification.

**Architecture:** No behavior changes; portability only.

**Tech Stack:** Laravel 12, PHP 8.2+, PostgreSQL 14+, SQLite 3.

**Prerequisite:** Phase 0 complete.

## Constraints
- Do not modify migration effects; make paths conditional.
- One commit per migration fix.
- Verify SQLite and MySQL still work after changes.
- No Postgres instance required to write fixes if they're conditional; but verification needs one.

## Task 1: Audit migrations for Postgres incompatibilities
Identify concrete issues.

### Steps
- [ ] Search for MySQL-only constructs: SHOW INDEX, ENGINE, AUTO_INCREMENT, unsigned, zerofill, datetime\(\d+\), 	imestamp\(\d+\), backticks assumptions, ->enum()->change() specifics
- [ ] Check migrations that query information_schema/SHOW INDEX (e.g. 2026_04_10_160000_harden_inventory_ledger_tables.php)
- [ ] Check enum definitions and json handling
- [ ] Document fixes needed

## Task 2: Fix driver-specific paths
- [ ] 2026_04_10_160000_harden_inventory_ledger_tables.php: replace MySQL-only index inspection with driver-aware logic (pgSQL uses pg_indexes or just skip/adjust)
- [ ] Other migrations using MySQL-specific queries: conditionalize by $driver = DB::getDriverName()
- [ ] Preserve existing behavior for mysql/sqlite, add pgsql cases

## Task 3: Verify migrations on available databases
- [ ] SQLite: migrate:fresh completes all 144 (already confirmed in Phase 0)
- [ ] MySQL: verify still clean
- [ ] PostgreSQL: if available, run migrate:fresh --database=pgsql --force; if not, record as "pending external verification" with exact steps

## Task 4: Commit and report
- One commit per fix; final verification report.

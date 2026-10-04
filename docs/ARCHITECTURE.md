# Architecture Decision Record

## ADR-001: Supersede the shared-hosting-only constraint

**Date:** 2026-10-04
**Status:** Accepted
**Supersedes:** 	echnical layout.txt.txt lines 15, 40-47, 54-59

### Context

	echnical layout.txt.txt mandates Hostinger shared hosting and forbids long-running processes, Docker on production, Redis, and socket-based broadcasting. deploy.sh:5 hard-codes a Hostinger path.

The desktop, LAN-server, offline and cloud-sync programme requires an always-on on-premise server holding authoritative data. That is incompatible with the above.

### Decision

The production topology changes to a Docker deployment on a VPS managed with Coolify, with Postgres. The repository was already prepared for this: Dockerfile (multi-stage, 108 lines), docker/entrypoint.sh:20-37 defining web, worker and scheduler roles, and docker/nginx/default.conf:2 naming the Coolify service.

A dedicated LAN server node runs on operator hardware and synchronizes with the cloud. POSIX terminals run offline against local SQLite.

Shared-hosting deployment is **not removed**. deploy.sh and public/.htaccess remain valid and remain a fallback.

### Consequences

- Long-running queue workers and a scheduler are available, replacing the minutely-cron model at 	echnical layout.txt.txt:40-47.
# Phase 2 — Sync Engine, Headless Implementation Plan

**Goal:** Build sync primitives as a self-contained library (tables + HLC + trait + merge + policies). No domain tables touched. Tested against two SQLite DBs.

## Deliverables
- Migrations: sync_nodes, sync_changes, sync_cursors, sync_conflicts
- Classes: HLC implementation, Syncable trait, pure merge logic, 5-class policy catalogue
- Tests exercising trait against sync tables only

## Tasks
1. Create migration for sync_nodes
2. Create migration for sync_changes
3. Create migration for sync_cursors
4. Create migration for sync_conflicts
5. Implement Hybrid Logical Clock (HLC)
6. Implement Syncable trait
7. Implement merge/policy logic
8. Tests in isolation (two SQLite DBs)
9. Verify no domain table changes

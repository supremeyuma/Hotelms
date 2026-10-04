# Phase 4 — Runtime and Sync Node Role Implementation Plan

**Goal:** Apply sync trait to domain tables (Class 1, 2, 4), add sync fields (sync_hlc, sync_origin_node_id, sync_deleted_at), convert InventoryService to movement-authoritative shape, add rebuild command with rebuild==incremental test, add sync CLI commands and scheduled worker.

## Tasks
1. Identify Class 1, 2, 4 tables and add sync fields via migrations
2. Add Syncable to relevant models
3. InventoryService changes (movement-authoritative) + rebuild command + test
4. Sync CLI commands and scheduled worker
5. Verify with multi-process simulation on SQLite

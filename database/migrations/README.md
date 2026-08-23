# database/migrations

**This directory is intentionally empty in Phase 2.**

The existing Supabase PostgreSQL schema (~65 tables, RLS enabled on all of
them) is the source of truth for MediConnect India and is managed outside
Laravel's migration system. Per project rules:

- No migration in this directory should recreate, alter, or drop any
  existing table, policy, function, or trigger.
- Any genuinely new table/column needed for a future module must go
  through the explicit approval workflow (explain why → what changes →
  security impact → data impact → rollback plan → wait for approval)
  before a migration file is added here.

If/when an approved migration is added, it should be additive only
(new tables Laravel owns exclusively — e.g. Laravel's own `sessions` or
`cache` tables if that driver is ever chosen) and never touch the
existing healthcare schema.

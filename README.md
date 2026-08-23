# MediConnect India

Multi-tenant healthcare platform for India — patients, doctors, hospitals/
clinics, appointments, clinical records, labs, pharmacy, billing, and
platform administration.

**Stack:** Laravel + Blade + Tailwind CSS + JavaScript (no React/Next/Node
backend). Database: existing Supabase PostgreSQL project (source of truth,
managed outside this repo's migrations — see `database/migrations/README.md`).

## Status

**Phase 2 — Laravel application foundation.** No feature modules (Patient,
Doctor, Facility, Appointments, Clinical, Lab, Pharmacy, Billing, Admin) are
implemented yet. See `MIGRATION_PROGRESS.md` for the live status and
`LARAVEL_MIGRATION_PLAN.md` for the full phased plan.

## Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
npm run build   # or: npm run dev
php artisan serve
```

You will need to fill in your own Supabase connection details in your local
`.env` (never commit it — see `.gitignore`). This repository does not
contain, and will never contain, real credentials.

## Database

The application connects to an **existing** Supabase PostgreSQL project.
This repo does not create, reset, or own that schema. See:

- `DATABASE_MAPPING.md` — table → Eloquent model mapping
- `CURRENT_ARCHITECTURE.md` — full read-only audit of the Supabase schema
- `config/database.php` — connection config, with an explicit open
  architectural question about how Laravel should authenticate against
  Supabase's Row-Level Security policies (not yet resolved — see the
  comment block in that file)

Row-Level Security is enabled on every table in the schema. Nothing in this
codebase should attempt to bypass it (e.g. via a service-role connection)
without an explicit, documented approval step.

## Testing

```bash
php artisan test
```

## Contributing / safety notes for this project specifically

- Never commit `.env`, credentials, or tokens.
- Never introduce a migration that alters the existing Supabase schema
  without going through the approval process described in
  `database/migrations/README.md`.
- Never point the app's database connection at a Supabase service-role
  key "to make something work" — flag it and ask instead.

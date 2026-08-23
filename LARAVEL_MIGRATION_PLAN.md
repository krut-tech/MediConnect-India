# MediConnect India — LARAVEL_MIGRATION_PLAN.md

Based on the read-only audit (`CURRENT_ARCHITECTURE.md`): there is no existing application code anywhere (GitHub is empty, Vercel has no project). This document therefore plans a **fresh build of the Laravel/Blade application layer against the existing, real Supabase schema** — not a migration of working code, since none exists.

The database schema and RLS design are treated as authoritative and are not to be altered except through the explicit approval gate described in the original brief.

---

## Phase 0 — Prerequisites (before any code)

- Pull and read full RLS policy bodies for the highest-stakes tables first: `patients`, `encounters`, `staff_assignments`, `documents`, `clinical_notes`.
- Clarify the `patients` write-path mechanism (Edge Function vs. Postgres RPC vs. not-yet-built).
- Decide the Laravel↔Supabase Auth integration approach (see Section 12 in the original brief) — this gates everything else, since every Eloquent query's RLS behavior depends on how `auth.uid()` gets set per request.
- Connect GitHub properly as the backup target (done) and push an initial baseline commit (even just this audit + a Laravel skeleton) so work stops living only in Supabase.

## Phase 1 — Laravel Foundation + Design System (UI-1)

- `composer create-project laravel/laravel` scaffold.
- Configure `.env.example` (no real secrets) for: Postgres/Supabase connection, Supabase Auth JWT verification, Supabase Storage (for `documents`).
- Tailwind + Vite pipeline.
- Design tokens: colors, typography, spacing, component base classes (buttons, inputs, cards, tables, badges, alerts, modals) per the brief's healthcare-appropriate, accessible, low-animation direction.
- Base Blade layout (navbar, sidebar, content slot), loading/empty/error states.
- **Milestone commit:** `feat: initialize Laravel application` → `feat: create MediConnect design system`.

## Phase 2 — Authentication (UI-2)

- Wire Laravel to validate Supabase Auth sessions/JWTs rather than replacing them.
- Map `auth.users` → `public.users` → `staff_assignments`/`patients` for role resolution.
- Protected route middleware per role.
- **Milestone commit:** `feat: implement authentication`.

## Phase 3 — Role-based dashboards (UI-3)

- One dashboard shell per top-level role family (platform admin, facility admin, doctor, patient, lab, pharmacy, front-desk/billing) driven by `roles`/`role_permissions` data, not hardcoded per-role Blade forks where avoidable.

## Phase 4 — Core modules (UI-4 through UI-13)

In the order given in the original brief: Patient → Doctor → Hospital/Facility → Appointments → Clinical/EHR → Laboratory → Pharmacy → Billing → Notifications/Search → Admin/Super Admin.

Each module follows: Controller → Form Request (validation) → Policy (authorization, mirroring but not replacing RLS) → Blade views → JS where genuinely needed → tests.

Because the `patients` table has no general UPDATE policy for staff, the Patient module's write actions are the first real test of the RPC/Edge-function integration decided in Phase 0 — worth building early and getting right rather than routing around it.

## Phase 5 — Responsiveness, Accessibility, Performance (UI-14–16)

Per the breakpoints and checks listed in the original brief (320px–1920px, N+1 query checks, eager loading, indexes already exist in the schema so this is mostly a Laravel-side query discipline exercise).

## Phase 6 — Testing (UI-17)

Full workflow test pass per the critical-workflow list in the original brief (registration → login → patient/doctor profile → appointments → clinical encounter → labs → pharmacy → billing → admin). PASS/FAIL/BLOCKED/NOT TESTED tracking, no claimed passes without an actual run.

## Phase 7 — Vercel deployment

Create the Vercel project fresh (none exists today) linked to the GitHub repo, configure env vars via Vercel's UI/CLI (values never pass through chat), deploy, verify.

---

## Feature Migration Map

Since no existing implementation was found, the "Existing Feature → Current Implementation" columns from the original brief's template are mostly **N/A — to be built new**, with the Supabase schema as the only existing dependency. Populated as:

| Feature | Current Implementation | Supabase Dependency | Laravel Equivalent | Status |
|---|---|---|---|---|
| Auth/login | None found | `auth.users`, `public.users`, `staff_assignments` | Laravel + Supabase JWT middleware | **REBUILD** (nothing to reuse) |
| Patient registration | None found | `patients` (RPC/Edge-gated writes) | Controller calling RPC, not raw Eloquent write | **REBUILD** |
| Doctor profile/availability | None found | `doctor_profiles`, `appt_availability` | Standard CRUD module | **REBUILD** |
| Appointments | None found | `appt_bookings`, `appt_waitlist`, `telemed_sessions` | Booking flow + calendar UI | **REBUILD** |
| Clinical/EHR | None found | `encounters`, `diagnoses`, `clinical_notes`, `vitals_log`, etc. | Encounter-centric module | **REBUILD** |
| Labs | None found | `lab_orders`, `lab_results` | Order/result workflow | **REBUILD** |
| Pharmacy | None found | `pharma_*` tables | Prescription/dispense workflow | **REBUILD** |
| Billing | None found | `bill_*` tables | Invoice/payment module | **REBUILD** |
| Admin/RBAC | None found | `roles`, `permissions`, `role_permissions`, `admin_scope` | Data-driven admin panel | **REBUILD** |
| Notifications | None found | `notification_*` tables | Event → delivery pipeline | **REBUILD**, likely queue-driven |

*(REUSE/ADAPT/IMPROVE categories from the original template don't currently apply — there's nothing built yet to reuse, adapt, or improve. This table should be revisited if local/offline code surfaces that wasn't visible to this audit.)*

---

## GitHub backup strategy going forward

Per the original brief's continuous-backup rule: every milestone above ends with implement → test → review → git diff → secret scan (`GitHub:run_secret_scanning` is available) → commit → push → verify remote → update `MIGRATION_PROGRESS.md`. First real commit should establish the Laravel skeleton itself, since right now there is nothing to lose but also nothing backed up.

---

## Recommended next step

1. You confirm the corrected understanding (no existing frontend to preserve — see `CURRENT_ARCHITECTURE.md` §5).
2. Approve pulling full RLS policy text for the sensitive tables (still read-only, no changes).
3. Approve Phase 1 (Laravel skeleton + design system) as the first implementation step, with the first commit pushed to `krut-tech/MediConnect-India` on a branch (`feature/laravel-blade-migration` or, given the repo is empty, arguably just `main` directly — your call).

No implementation has started. Waiting for your go-ahead per the STOP condition in the original brief.

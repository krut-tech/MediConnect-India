# MediConnect India — MIGRATION_PROGRESS.md

**Current phase:** Phase 6 — Appointment Engine + Schedule/Leave/Blocked-Period management. This session's addition (cancellation audit trail, leave audit trail, preserved-not-hidden leave-conflict resolution, and the start of the global search/filter system) plus everything from every prior Phase 6 session is implemented and pushed. **PHASE 6 STATUS: NEEDS FIXES** (code-complete for the scope actually attempted; several large spec items remain explicitly deferred, listed below, not silently skipped; automated tests remain written but not executable in-session; Render/production browser click-through verification still outstanding). **PHASE 6.1 = NOT STARTED. PHASE 7 = NOT STARTED.**

## Phase 6 — Correction: Cancellation/Leave Audit Trail + Search/Filter (2026-08-31, this session)

### Scope of this session, stated up front
Continued directly from the prior session's own "NEEDS DECISION" / "DEFERRED" list (immediately below in this file). You explicitly approved the schema change that session had been blocked on (cancellation/resolution-state columns on `appt_bookings`, audit columns on `staff_leave`), and picked item 11 (global search/filter) as this session's next focus. Both are covered below. The remaining deferred items (global identity sweep across all ~15 record types, doctor/staff creation-flow audit, leave self-service edit/withdraw, role-hierarchy navigation views, full data-scale/performance review, full cross-role/cross-facility security re-check, production browser click-through) were **NOT attempted this session** — see Deferred Items below.

### Database change (approved, applied, verified live before any code was written)
Migration `phase6_cancellation_and_leave_audit_columns`, additive only, applied via Supabase MCP to project `cfuzzkodegaupdcvqqnr` and re-verified live via `information_schema.columns` immediately after:
- `appt_bookings`: `cancelled_by uuid REFERENCES users(id)`, `cancelled_at timestamptz`, `cancellation_reason text`, `resolution_state text` (CHECK: null or one of `rescheduled`/`cancelled_by_facility`/`pending_reschedule`), `resolution_note text`, `resolved_by uuid REFERENCES users(id)`, `resolved_at timestamptz`.
- `staff_leave`: `requested_by uuid REFERENCES users(id)`, `leave_type text`, `reason text`, `reviewed_by uuid REFERENCES users(id)`, `reviewed_at timestamptz`, `decision_reason text`, `created_at timestamptz NOT NULL DEFAULT now()`, `updated_at timestamptz NOT NULL DEFAULT now()` (this table had no timestamps before). Existing rows backfilled: `requested_by` set from each row's own `staff_assignments.user_id`.
- No table, index, RLS policy, or existing column was touched, dropped, or renamed. No duplicate of anything already live was created.

### What was built/fixed this session

**Cancellation audit (finishes spec item 4):**
- `AppointmentController::cancel()` now records `cancelled_by` (`Auth::id()`), `cancelled_at` (`now()`), and an optional `cancellation_reason` alongside the existing status change. Status-transition rules (`completed`/`no_show`/`cancelled` cannot be re-cancelled) and RLS-scoped affected-row-count discipline are unchanged.
- `AppointmentBooking` model: new columns added to `$fillable` + `cancelledByUser()`/`resolvedByUser()` relations.

**Leave audit trail + preserved appointment resolution (finishes spec items 5-8):**
- `LeaveController::store()` now records `requested_by`; `approve()`/`reject()` now record `reviewed_by`/`reviewed_at`/`decision_reason` via a shared `updateStatus()`.
- **Real bug found and fixed:** `affectedAppointments()` was matching `appt_bookings.status` against `'booked'`/`'confirmed'` — but `'confirmed'` does not exist anywhere in the live `appt_bookings_status_check` constraint (the real active statuses are `'booked'`/`'checked_in'`). This meant the conflict check built in the previous session could never actually match a real row for any booking not literally in `'booked'` status the instant this ran. Fixed to check the schema's real active statuses (confirmed live via `pg_constraint` before writing the fix).
- On a **confirmed** approval (`?confirm=1`) that has affected appointments, each affected booking is now marked `resolution_state = 'pending_reschedule'` with `resolution_note`/`resolved_by`/`resolved_at` set — additive metadata only. The booking's own `status`/`scheduled_at`/`doctor_user_id` are never changed by this: no automatic cancellation, no automatic reschedule to a different doctor/time. Automatically moving a patient's appointment without their consent is exactly the "unsafe unapproved behavior" the standing project rules call out, so it remains deliberately not built — a real reschedule/notify workflow stays a separately-scoped future item.
- Already-resolved bookings (`resolution_state IS NOT NULL`) are excluded from future conflict checks so re-approving the same leave never double-flags a booking staff has already started resolving.
- `StaffLeave` model: new audit columns added to `$fillable`, `$timestamps` turned on (was `false` — the table had no timestamp columns before this session), `requestedByUser()`/`reviewedByUser()` relations added.
- `StoreLeaveRequest` accepts optional `leave_type`/`reason` (free text — this app has no approved fixed leave-type taxonomy, so no `in:` rule was invented for one).
- `resources/views/leave/index.blade.php` — surfaces leave type, reason, decided-by/when, and decision reason; conflict-warning copy updated to describe the new "needs follow-up" marking instead of the previous "will remain entirely unmodified" wording.
- `resources/views/appointments/index.blade.php` — shows a "Needs follow-up (doctor leave)" / "Rescheduled" / "Cancelled by facility" badge driven by `resolution_state`, and the cancellation reason (truncated, full text on hover) for cancelled bookings, so an affected/cancelled appointment is never silently invisible.

**Global search/filter — item 11, started this session (NOT complete across all ~15 modules):**
- `AppointmentController::index()` — added `q` (matches doctor name, patient name, or patient MRN via `ilike`), `status`, `date_from`, `date_to` query filters, layered strictly on top of the existing RLS-scoped `AppointmentBooking::query()` — RLS still decides row visibility first; filters only narrow within that set. Existing `paginate(15)` kept, now `->appends()` so filters survive pagination. View updated with a filter form.
- `PatientController::index()` — `q` now also matches patient name (previously MRN-only), plus a new optional `facility_id` filter. Same RLS-first discipline; existing pagination kept.
- `DoctorController::index()` already had name search + pagination from an earlier session (verified this session, not re-built) — **left unchanged**, not because it's complete (registration-number/specialty search would still be a genuine improvement) but to conserve this session's scope for what was explicitly asked.
- **NOT done this session:** `FacilityController`, `staff_assignments`/staff directory (no dedicated staff-list screen currently exists to add search to — confirmed by inspection, not assumed), and leave's own search/filter (spec item 10 — leave currently has no `q`/status/date filter UI at all, only the existing role-based visibility). Role-hierarchical hierarchy navigation (items 12-13), data-scale/performance review (item 14), and the global "missing data" identity sweep (item 17, spec item 1) were **not attempted** this session.

### Testing / verification results (this session)

| Check | Result | Notes |
|---|---|---|
| Live schema check (`information_schema.columns`) for `appt_bookings`/`staff_leave` before and after the migration | **PASS** | Confirmed all 15 new columns present with correct types/constraints post-migration |
| Live RLS policy re-check (`pg_policies` for `appt_bookings`/`staff_leave`) before writing controller code | **PASS** | Confirmed `appt_bookings_update_own` (patient can cancel own booking) and `staff_leave_facility_admin`/`_insert_own`/`_select_own` unchanged from prior sessions — no policy was touched this session |
| Manual review of every changed file's raw pushed content (re-fetched via GitHub after each write) | **PASS** | All 9 changed/added files re-fetched and read after writing |
| `appt_bookings_status_check` constraint cross-check that surfaced the `'confirmed'`-never-matches bug | **PASS** | `pg_get_constraintdef` on the live constraint, read-only |
| `php -l` / `composer install` / `php artisan test` | **NOT RUN** | Same standing sandbox limitation as every prior session in this file — no PHP CLI, no packagist access |
| `npm install` / `vite build` | **NOT RUN** | No CSS/JS changed — new view content uses only existing design-system component tags/classes |
| Render deployment status for this session's commits | **NOT CHECKED** | Same outstanding item carried forward from every prior session |
| Production click-through (real browser, real login, real leave/appointment fixture) | **NOT DONE / NOT VERIFIED** | Needs a real browser session and real seeded data outside this sandbox — never claimed otherwise |

**No runtime "PASS" is claimed for anything not actually executed or actually observed**, consistent with this file's existing convention.

### CRITICAL / HIGH / MEDIUM / LOW (this session's own honest triage)
- **CRITICAL:** none introduced. The real `'confirmed'`-vs-`'checked_in'` bug found and fixed this session was itself a CRITICAL-severity gap in the previous session's conflict detection (it could never have matched a real row) — now fixed.
- **HIGH:** the global identity-resolution sweep (item 1) and the remainder of the global search/filter system (item 11 — Facility/staff/leave list pages, plus Doctor search depth) remain unstarted/incomplete; both are explicitly requested as whole-application requirements.
- **MEDIUM:** leave CRUD is still request-and-decide only (staff cannot edit/withdraw their own pending request — `staff_leave` genuinely has no such RLS policy today; adding one is a policy change, not attempted without separate approval). Leave-specific search/filter UI (item 10) not built.
- **LOW:** none newly identified this session.

### SAFE / NEEDS DECISION / DEFERRED
- **SAFE:** every change this session is additive (new columns, new optional query filters, new optional form fields) — no existing route, policy, or working behavior was altered in a breaking way; the confirmed no-conflict leave-approval path and the ordinary cancel path behave exactly as before, just with more metadata recorded.
- **NEEDS DECISION (new, this session):** self-service leave edit/withdraw (item 9) needs a `staff_update_own`/`staff_delete_own`-style RLS policy on `staff_leave` that does not exist today — a policy change requiring your explicit approval, same standing rule as the schema change earlier.
- **DEFERRED (unstarted or partially started, not scoped to completion this session):** item 1 (global identity sweep), item 2 (doctor/staff creation-flow audit), item 9 (leave edit/withdraw — see NEEDS DECISION above), item 10 (leave search/filter), item 11 (Facility/staff-directory/leave search — Appointment and Patient search were built this session), items 12-13 (role-hierarchy navigation views), item 14 (data-scale/performance review), item 16 (cross-role/cross-facility security re-check beyond what RLS already, structurally, enforces), item 17 (global "missing data" sweep beyond the spot-fixes made to the Appointments/Leave views this session).

### Known Limitations (carried forward + new)
- `resolution_state = 'pending_reschedule'` is visible metadata only — there is still no automated notification to the patient, and no automated reschedule/alternate-doctor suggestion flow. Resolving it remains a manual staff action (contact the patient, cancel via the existing action, or book a new slot separately) until a real reschedule/notify workflow is separately approved and built.
- Leave still does not affect `appt_available_slots()` for *new* bookings during an approved period — unchanged, still a separately-scoped, not-yet-built item, flagged since before this session.
- A staff member still cannot edit or withdraw their own leave request once submitted (see NEEDS DECISION above) — this is a real, live authorization boundary, not an oversight.
- Search across Appointment/Patient uses `ilike` without new indexes; acceptable at this project's current data volume (near-zero rows in every table per every prior session's live row-count checks) but would need `pg_trgm`/GIN indexes revisited before real production data volume — noted here rather than silently deferred, per item 14's own "reason about scale" requirement, though a full scale/performance review was not otherwise performed this session.

**PHASE 6.1 = NOT STARTED. PHASE 7 = NOT STARTED.**

---

## Phase 6 — Correction: Leave-Approval Conflict Detection (2026-08-30)

### Scope of this session, stated up front
The instruction this session started from was a very large, ~20-item production-correction spec (global identity resolution audit, doctor/staff creation flow audit, appointment identity display audit, patient cancellation authorization, doctor-leave conflict handling, leave conflict policy, patient protection/rescheduling, leave CRUD, leave search/filtering, a global record search+filter system across ~15 modules, role-specific data views, category/hierarchy navigation, data-scale/performance review, cross-role/cross-facility security re-check, a global "missing data" sweep, and more). Before writing anything, the actual current repository state (this file) and live schema were audited, per the standing instruction. That audit found most of those ~20 items had not been started, and one — doctor leave overlapping already-booked appointments — was already an explicitly documented, flagged gap (LeaveController's own prior docblock: *"a staff_leave row does not itself block appt_available_slots() computation or cancel existing bookings"*).

Given the size of the full spec, this session deliberately scoped to that one already-flagged, well-defined, schema-compatible gap (spec items 5-6: detect and surface appointments affected by an approved/approving leave) rather than attempting a shallow pass across all ~20 items and risking exactly the kind of "claim something is implemented merely because a table exists" failure the spec itself warns against. **Every other item from that spec is listed as NOT ATTEMPTED below, not silently dropped.**

### What was built/fixed this session
- **`LeaveController::approve()`** now computes `affectedAppointments()` (a new private, read-only method) before changing a leave request's status: active (`booked`/`confirmed`) `appt_bookings` rows for that leave's doctor, whose `scheduled_at` falls inside `leave_start`..`leave_end`. If any exist and the request wasn't already confirmed (`?confirm=1`), the approval is **not applied** — the admin is sent back to `/leave` with a conflict summary (total + per-date counts) and a "confirm and approve anyway" action. This matches the requested `leave requested -> conflict detected -> review affected appointments -> approve` flow using the workflow style the spec itself allows for when the existing design doesn't support automatic resolution ("implement the safest compatible behavior and document the limitation").

  **Correction (2026-08-31 session, above):** the `'confirmed'` status check in this method never matched a real row — the live schema's actual active statuses are `'booked'`/`'checked_in'`, not `'confirmed'`. Fixed in the following session; see that section above.
- **`resources/views/leave/index.blade.php`** — added the conflict-summary alert + confirm-anyway form, in the existing design system, no new components.
- **`tests/Feature/Phase6FinalizationTest.php`** — 3 new structural tests (method signature of `approve()`, existence/visibility of `affectedAppointments()`, unauthenticated-redirect on the approve route). **WRITTEN, NOT RUN** — same standing sandbox limitation as every prior phase (no `php` binary this session either). A real DB-fixture test of "approving withholds the status update when a conflict exists" needs a live doctor/leave/booking row under real RLS, which — like `SupabaseRlsContextTest.php`'s own documented case — is NOT POSSIBLE to build safely against the live project without a dedicated test database; stated here rather than faked.

### Explicitly NOT done this session, and why (schema-safety, not oversight)
- **Auto-cancelling, auto-rescheduling, or relabeling affected appointments** (spec items 7-8). **Resolved in the 2026-08-31 session above** — schema change approved, `resolution_state` metadata built, without automatic cancel/reschedule.
- **Patient-initiated cancellation with `cancelled_by`/`cancelled_at`/`cancellation_reason`** (spec item 4). **Resolved in the 2026-08-31 session above.**
- **Global identity-resolution audit** (spec item 1, across ~15 record types). Spot-checked only: `leave/index.blade.php` (pre-existing, unchanged by this session) already follows the correct pattern (`{{ $row->staffAssignment?->user?->full_name ?? 'Name on file missing' }}`) rather than showing a raw UUID/"Unknown". A full repository-wide sweep across every listed record type was NOT performed this session — still outstanding, see the 2026-08-31 section above.
- **Doctor/staff creation-flow audit** (item 2), **leave CRUD expansion** (item 9), **leave search/filtering** (item 10), **the global record search+filter system** (item 11, ~15 modules — started 2026-08-31, see above), **role-specific hierarchical data views** (items 12-13), **data-scale/performance review** (item 14), **cross-role/cross-facility security re-check** (item 16), and **the global "missing data" sweep** (item 17) were **NOT attempted this session**.

**Testing/verification table and full detail for this 2026-08-30 session preserved below, unchanged.**

| Check | Result | Notes |
|---|---|---|
| Live schema check (`information_schema.columns`) for `staff_leave`/`appt_bookings`/`appt_availability`/`staff_assignments`) before writing any code | **PASS** | Read-only; confirmed no resolution-state/cancellation columns existed at the time, which is what scoped this session away from items 7-8 (later approved and built 2026-08-31) |
| Manual review of every changed file's raw pushed content (re-fetched via GitHub, not assumed) | **PASS** | `LeaveController.php` and `leave/index.blade.php` both re-fetched and read after writing |
| Route/controller signature cross-check | **PASS** | Reviewed by hand |
| Supabase security advisors (`get_advisors`, security) re-run before/after | **PASS** | Identical set to every prior phase — nothing new introduced |
| RLS re-verification for the new `affectedAppointments()` query | **PASS (read-only reasoning, not executed)** | Relies on already-live `appt_bookings_select_own/_doctor/_facility_staff` policies, unchanged by this session |
| `php -l` / `composer install` / `php artisan test` / `npm install` / `vite build` | **NOT RUN** | Same standing sandbox limitation as every prior phase |
| Render deployment status / production click-through | **NOT CHECKED / NOT DONE** | Outstanding, carried forward |

### SAFE / NEEDS DECISION / DEFERRED (as of 2026-08-30, since updated above)
- **NEEDS DECISION** (cancellation/resolution-state columns) — **approved and resolved 2026-08-31, see above.**
- **DEFERRED (unstarted as of this session):** items 1, 2, 9, 10, 11, 12, 13, 14, 16, 17 — status of each updated in the 2026-08-31 section above.

**PHASE 6.1 = NOT STARTED. PHASE 7 = NOT STARTED.**

---

## Phase 6 — Finalization Report (2026-08-30)

Continued from the existing GitHub state — not a restart. Audited before writing anything: `AvailabilityController::edit()/update()` already existed but were unrouted with no UI (item 1's actual gap); no `blocked_period` table existed anywhere, and `AvailabilityController`'s own prior docblock already pointed to `LeaveController(staff_leave)` as the intended home for both leave AND blocked-period concerns (items 2+3) — confirmed against live `pg_policies`/`list_tables` before building anything, so no duplicate table/controller was created.

### What was built/fixed this session
- **Item 1 (Schedule Edit):** wired `GET /schedule/{availability}/edit` + `PATCH /schedule/{availability}`, added `resources/views/availability/edit.blade.php`, added Edit links to the schedule list (desktop table + mobile cards).
- **Items 2+3 (Leave / Blocked-period management):** new `App\Models\StaffLeave` (existing `public.staff_leave` table, unchanged), `StoreLeaveRequest`, `LeaveController` (index/store/approve/reject), `resources/views/leave/index.blade.php`, `/leave` routes. One table/controller covers both concerns — the schema has no reason-taxonomy column to split on, and building two would have duplicated the same three columns and the same RLS. Own-request visibility via `staff_leave_select_own`; facility-wide review/approve/reject via `staff_leave_facility_admin` (verified live, unchanged, both already existed before this session).
- **Schedule/Availability sidebar navigation:** "My Schedule" (doctor + published profile) and "Leave & Blocked Periods" (any active staff assignment) added to `sidebar.blade.php`, then found missing from `mobile-nav.blade.php` (out of sync) and added there too in a follow-up commit.
- **Role/Access Matrix:** `ROLE_ACCESS_MATRIX.md` added, built from live `roles` (19 rows) + `pg_policies` queries (shown in the file itself), not hand-guessed. States the two-layer model this app already uses (route middleware = UX only, RLS = real authorization) and one known real gap (approved leave doesn't yet affect live slot computation).
- **Real production bug found and fixed (repository production-issue sweep):** `AvailabilityController::update()`/`destroy()` (pre-existing, from an earlier session) redirected via `route('doctors.schedule', ['doctor' => $availability->doctor_user_id])` — but `{doctor}` on that route binds `DoctorProfile::id` (its own `gen_random_uuid()` primary key), not `users.id`. Confirmed live via `information_schema` + `pg_constraint` (`doctor_profiles.user_id` is UNIQUE, so exactly one profile per user) before fixing. Every schedule Edit-save or Remove was 404ing immediately after success. Fixed via a new `resolveDoctorProfileId()` helper; the new `edit.blade.php` (written this session, before the bug was caught) had inherited the same wrong-id pattern in its breadcrumb/Cancel link and was fixed in the same pass.
- **Two real mistakes made and self-caught this session** (same "re-fetch and read back what was actually pushed" discipline this file already documents from prior phases): `routes/web.php`'s first push HTML-entity-encoded the PHP (`&lt;?php`) — caught and fixed immediately. `leave/index.blade.php`'s first push had literal `&amp;` where Blade's `{{ }}` escaping would have double-encoded it to `&amp;amp;` on screen — caught and fixed immediately.
- **A third real mistake, in this file itself:** the first push of this Finalization Report section overwrote the ENTIRE rest of this file (every earlier phase's history below this point) instead of only prepending — a `create_or_update_file` call sent only the new section with nothing appended after it. Caught immediately by re-fetching this file's raw content right after the push (same discipline as every other file this session) and restored in an immediate follow-up commit with the full prior history reattached unchanged below. Stated here rather than silently fixed, per this file's own convention of documenting real mistakes.
- **Tests:** `tests/Feature/Phase6FinalizationTest.php` added — route registration, unauthenticated-redirect, controller/method-existence, `StoreLeaveRequest` field-allowlist checks. **WRITTEN, NOT RUN**.

*(Full remaining detail for this 2026-08-30 Finalization session, and every earlier phase's history — Phase 5.2, Phase 5 Step 3, Phase 4, Phase 3 Milestones 1-3, Phase 2, Phase 0/1 — is unchanged from before and preserved below this point.)*

## Known gap carried forward (unchanged by this phase)
- `doctor_specialties` (pivot table linking `doctor_user_id` to the `specialties` catalog via `specialty_id`) was **not** wired this phase — it's a genuinely separate, catalog-linked concept from `doctor_profiles.specialties` (this table's own free-text array column), flagged rather than silently folded in.
- The DB-level RLS contract test gap (two different UUIDs, asserted under real Postgres RLS) flagged since Phase 5 Step 3 remains unresolved and out of scope here, same as it was for Phase 5.1.
- **Session-storage durability across container replacement** (`SESSION_DRIVER=file`, no persistent disk) — flagged since Phase 4, and directly responsible for the logout 419 documented in the Phase 5.2 section below. Still unresolved.

## Known gaps / open decisions requiring your input
1. **Supabase connection architecture** — resolved in Phase 5 Steps 1–3 (Option A, direct connection, per-transaction RLS context).
2. **`patients` write path** — registration still genuinely blocked at the database (zero INSERT policies, zero deployed Edge Functions).
3. **`doctor_specialties`** (catalog-linked pivot) not wired.
4. **Session-storage durability** across container replacement — needs a deliberate choice (Render persistent disk, DB-backed sessions, or Redis); unresolved.
5. Local/offline workspace still not inspected.
6. **This session's (2026-08-31) new NEEDS DECISION item:** a self-service leave edit/withdraw RLS policy on `staff_leave` — needed for spec item 9, not built without your explicit approval.

## Git
- Repo: `krut-tech/MediConnect-India`
- Main branch, deployed to Render (`mediconnect-india` service, auto-deploy on push)

## Next task
This session's (2026-08-31) cancellation/leave audit trail and the start of the global search/filter system are code-complete and pushed. The remaining deferred items are itemized in that section's "DEFERRED" list above — global identity sweep, doctor/staff creation-flow audit, leave self-service edit/withdraw (needs a new RLS policy decision), leave-specific search/filter, Facility/staff-directory search, role-hierarchy navigation views, data-scale/performance review, and the full cross-role/cross-facility security re-check. **PHASE 6.1 and PHASE 7 have NOT been started.** Render deployment status and a real browser click-through of this session's changes remain **NOT VERIFIED** — outside this sandbox.

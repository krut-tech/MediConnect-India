# Role / Access Matrix — Phase 6 Finalization

Generated from live data verified this session:
`select id, code, label, is_platform_role from roles order by id;`
(19 rows — see below) and `pg_policies` for every table this phase
touches. This is a snapshot, not a second source of truth — if it ever
disagrees with `pg_policies` or `routes/web.php`, those are correct and
this file is stale.

## What actually gates access

Two independent layers, and this app never substitutes one for the
other:

1. **Route middleware** (`routes/web.php`) — coarse, UX-level only.
   `'role'` (`EnsureUserHasRole`) requires *any* active
   `staff_assignments` row, of *any* role, for a route to be reached at
   all. It cannot and does not distinguish which role.
2. **Row-Level Security** (Postgres, `pg_policies`) — the actual,
   enforced authorization for every read/write. This is true even for
   routes with no `'role'` gate (e.g. `/doctors/{doctor}/book`,
   `/appointments`): RLS is what limits what a plain patient can
   actually see/do there.

Nav links (`sidebar.blade.php` / `mobile-nav.blade.php`) are a third,
even coarser layer — hiding a link never grants or removes access;
it only avoids showing a link that would 403 or silently return
nothing.

## The 19 roles (live, `roles` table)

| id | code | label | platform-tier |
|----|------|-------|----------------|
| 1 | patient | Patient | no |
| 2 | doctor | Doctor | no |
| 3 | receptionist | Receptionist | no |
| 4 | hospital_admin | Hospital Admin | no |
| 5 | lab_tech | Laboratory | no |
| 6 | pharmacist | Pharmacy | no |
| 7 | billing_staff | Billing Staff | no |
| 8 | inventory_manager | Inventory Manager | no |
| 9 | nurse | Nurse | no |
| 10 | ot_staff | Operation Theatre Staff | no |
| 11 | icu_staff | ICU Staff | no |
| 12 | emergency_staff | Emergency Staff | no |
| 13 | insurance_coordinator | Insurance Coordinator | no |
| 14 | ambulance_operator | Ambulance Operator/Driver | no |
| 15 | super_admin | Super Admin | **yes** |
| 16 | national_admin | National Admin | **yes** |
| 17 | state_admin | State Admin | **yes** |
| 18 | district_admin | District Admin | **yes** |
| 19 | city_admin | City Admin | **yes** |

A "plain patient" below means: signed in, but no row in
`staff_assignments` at all (the `patient` role, id 1, is not actually
assigned via `staff_assignments` in this schema — a patient's identity
comes from `public.patients`, not a staff role).

## Feature access, by phase-6-relevant screen

Only the screens this phase (Workstream 2 through Finalization)
touches. Earlier-phase screens (Facilities, Login/Register) are out of
scope for this table.

| Screen / route | Route gate | Real (RLS) authorization | Who can actually do what |
|---|---|---|---|
| `/doctors`, `/doctors/{doctor}` | none | `doctor_profiles_select_public` (SELECT) | Anyone signed in. Public directory — no role distinction. |
| `/my-doctor-profile` (GET/PATCH) | none | `doctor_profiles_write_own` (ALL) | Anyone signed in may create/edit **their own** doctor profile row. Not restricted to role `doctor` at the RLS layer — nav link is (see below). |
| `/doctors/{doctor}/book`, `/appointments` (index/store), `/appointments/{id}/cancel` | none | `appt_bookings_select_own` / `_doctor` / `_facility_staff`; `appt_bookings_insert`; `appt_bookings_update_own` / `_doctor` / `_facility_staff` | Patient: own bookings only. Doctor: bookings where they're the doctor. Facility staff: bookings within their facility. No cross-facility visibility for non-platform roles. |
| `/appointments/create` | `'role'` (any staff) | same as above, plus this is the entry point that hands off to `/doctors/{doctor}/book` | Any staff role can start booking on someone else's behalf; **not** available to a plain patient (by design — they use `/doctors/{doctor}/book` directly for themselves). |
| `/doctors/{doctor}/schedule` (index/store), `/schedule/{id}/edit,update,destroy` | `'role'` (any staff) | `appt_availability_write_doctor` (ALL, write); `appt_availability_select_public` (SELECT, public read) | Write: doctor themselves, in-scope `hospital_admin`, or platform-tier admin — exact predicate is in the live policy, not duplicated here to avoid drift. Nav link ("My Schedule") only shows for role `doctor` with a published profile — a `hospital_admin` managing a *different* doctor's schedule reaches it by URL, not nav, and RLS still governs the write either way. |
| `/leave` (index/store/approve/reject) | `'role'` (any staff) | `staff_leave_insert_own` (INSERT); `staff_leave_select_own` (SELECT); `staff_leave_facility_admin` (ALL) | Any staff role can file their own leave/blocked-period request and see only their own by default. A `hospital_admin` (RLS-verified via the facility-admin predicate) additionally sees and can approve/reject every request at their facility. No staff role can edit or withdraw their **own** submitted request — only an admin can change its status (no `staff_update_own`/`staff_delete_own` policy exists). |
| `/patients`, `/patients/{id}` | `'role'` (any staff) | `patients_select_own` / `_assigned_doctor` / `_registering_facility` / `_super_admin`; `patients_update_own` / `_assigned_doctor` | Patient: own record. Assigned doctor: their patients. Registering facility staff: patients registered there. `super_admin`: all. |

## Known gaps (stated, not silently worked around)

- **Leave vs. live slot computation**: an approved leave/blocked-period
  row does **not** currently affect `appt_available_slots()` or cancel
  existing bookings during that window. A doctor could still show as
  bookable, or keep an existing booking, during approved leave.
  Deferred — flagged in `LeaveController`'s class docblock and here.
- **This matrix is a snapshot.** `role_permissions` (14 rows, verified
  live) is a separate, currently-unused-by-application-code table —
  no controller in this app queries it for authorization decisions.
  If a future phase wires it up, this file should be regenerated from
  it rather than hand-maintained in parallel.

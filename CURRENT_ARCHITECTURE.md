# MediConnect India — Current Architecture (Read-Only Audit)

**Audit date:** 2026-08-23
**Method:** Connected GitHub / Supabase / Vercel connectors. No production or destructive actions taken.

---

## 1. GitHub

- **Repository:** `krut-tech/MediConnect-India`, default branch `main`.
- **Contents:** exactly one file — `README.md`, containing only `# MediConnect-India`.
- No application code, SQL files, migrations, tests, or documentation exist in the repo.

**Conclusion:** The repository is effectively empty. GitHub is currently providing zero backup coverage for this project.

---

## 2. Supabase

Three projects exist on the account; the relevant one:

| Field | Value |
|---|---|
| Name | `mediconnect-india` |
| Project ref | `cfuzzkodegaupdcvqqnr` |
| Region | `ap-south-1` |
| Status | `ACTIVE_HEALTHY` |
| Postgres | 17.6.1.155 |
| Created | 2026-08-10 |

### 2.1 Schema (`public`)

A large, well-designed multi-tenant healthcare schema — ~65 tables, **all with 0 rows** (no data at risk). Groups:

- **Identity/RBAC:** `roles`, `permissions`, `role_permissions`, `users` (1:1 `auth.users`), `user_devices`, `admin_scope`, `staff_assignments`.
- **Tenancy:** `facility_groups` → `facilities` (RLS isolation boundary) → `departments` → `wards` → `beds`; plus `facility_specialties`, `facility_services`, `facility_insurers`, `specialties`, `services_catalog`, `insurers`.
- **Patients/clinical:** `patients` (no general staff UPDATE policy by design — "Decision W4", relies on a scoped Edge Function/RPC), `doctor_profiles`, `doctor_specialties`, `patient_provider_links`, `encounters`, `diagnoses`, `clinical_notes`, `vitals_log`, `procedures`, `patient_allergies`, `immunizations`, `patient_medical_history_notes`, `patient_medications`.
- **Appointments/telemedicine:** `appt_availability`, `appt_bookings`, `appt_waitlist`, `telemed_sessions`.
- **Pharmacy/labs:** `drug_catalog`, `pharma_prescriptions(_items)`, `pharma_dispenses`, `pharma_inventory`, `lab_test_catalog`, `lab_orders`, `lab_results`.
- **Inpatient:** `admissions`, `bed_assignments`.
- **Billing:** `bill_invoices`, `bill_line_items`, `bill_payments`, `subscription_plans`, `facility_subscriptions`, `platform_invoices`.
- **Inventory:** `inv_items`, `inv_vendors`, `inv_purchase_orders`, `inv_stock_movements`.
- **Staffing:** `staff_shifts`, `staff_leave`.
- **Consent/privacy:** `consent_purposes`, `consent_records`, `erasure_requests`, `retention_policies`, `emergency_access_log`, `document_access_log`.
- **Audit:** `audit_log`, partitioned monthly (`audit_log_2026_08` … `audit_log_2027_08`, `audit_log_default`). Trigger-populated, append-only — comment states only `audit_trigger_fn()` (SECURITY DEFINER) writes to it, no role has direct INSERT/UPDATE/DELETE.
- **Notifications:** `notification_events`, `notification_preferences`, `notification_templates`, `notification_delivery_log`.
- **Documents:** `documents`, `document_access_log`.

### 2.2 RLS

**Every one of the ~65 tables has RLS enabled** (`rls_enabled: true` on all, confirmed via schema listing). Individual policy bodies (USING/WITH CHECK clauses) were not dumped in this pass — recommended as the very next read-only step before designing how Laravel authenticates against this data.

### 2.3 Edge Functions

**None deployed.** This is a gap relative to the schema comment on `patients`, which references a scoped Edge Function/RPC for registration — it either doesn't exist yet or lives as a plain database RPC. Needs clarification before building the patient-write flow.

---

## 3. Vercel

- Team: `krut-tech's projects` (Hobby plan).
- Only project on the team: `codevault` (linked to `krut-tech/vault`).
- **No MediConnect India project exists on Vercel.** Nothing to inspect or preserve here — deployment config will be created fresh.

---

## 4. Local/cloud workspace

Not inspected — no access outside the three connectors above. If wireframes, PRD, or draft code exist only on your machine, they aren't reflected here.

---

## 5. Summary

| Component | State |
|---|---|
| GitHub repo | Empty except README |
| Supabase schema | Extensive, RLS everywhere, 0 rows |
| Edge Functions | None |
| Vercel | No project |
| React frontend | **Not found anywhere** — no code in GitHub, nothing deployed |

**Important correction to the original brief:** the brief assumes an *existing* React UI to migrate. Based on this audit, there is no frontend implementation anywhere (GitHub or Vercel) — only the Supabase schema is real. This isn't a UI-framework migration from a working app; it's building the application layer from scratch against an already-solid database. Worth confirming this matches your understanding, since it changes the "preserve React until Laravel reaches parity" instruction in the original brief — there's nothing to keep in parallel.

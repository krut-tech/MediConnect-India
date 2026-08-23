# MediConnect India — DATABASE_MAPPING.md

Supabase `public` schema (project `cfuzzkodegaupdcvqqnr`) is authoritative. This maps each table group to its future Laravel model layer. No destructive migrations are implied by this document — Laravel models will be written to fit the existing schema, not the other way around.

Legend for **Laravel Access Strategy**:
- **Eloquent (auth'd)** — standard Eloquent model, relies on Postgres session context (`auth.uid()`) for RLS to scope rows correctly.
- **RPC/Edge only** — writes must go through a controlled function, not raw Eloquent inserts (e.g. `patients`, per Decision W4).
- **Read-only/system** — audit tables etc., Laravel should never write directly.

| Supabase Table(s) | Laravel Model | Relationships | Access Strategy | Notes |
|---|---|---|---|---|
| `roles`, `permissions`, `role_permissions` | `Role`, `Permission` | belongsToMany via `role_permissions` | Eloquent (auth'd), read-mostly | RBAC as data, not hardcoded — Laravel policies should query this, not hardcode role checks |
| `users` | `User` | 1:1 `auth.users` (Supabase Auth) | Eloquent (auth'd) | Do not create a second parallel Laravel auth system — see Section 12 of migration plan |
| `user_devices` | `UserDevice` | belongsTo `User` | Eloquent (auth'd) | |
| `admin_scope` | `AdminScope` | belongsTo `User` | Eloquent (auth'd) | Jurisdiction-based (national/state/district/city) — platform admin only |
| `staff_assignments` | `StaffAssignment` | belongsTo `User`, `Facility`, `FacilityGroup`, `Role`, `Department` | Eloquent (auth'd) | **This is the tenant-scope resolution table RLS reads from** — critical to get right |
| `facility_groups` | `FacilityGroup` | hasMany `Facility` | Eloquent (auth'd) | |
| `facilities` | `Facility` | belongsTo `FacilityGroup`; hasMany departments/wards/staff/etc. | Eloquent (auth'd) | **`facility_id` is the RLS isolation boundary across the whole schema** |
| `departments`, `wards`, `beds` | `Department`, `Ward`, `Bed` | facility → department → ward → bed | Eloquent (auth'd) | |
| `specialties`, `services_catalog`, `insurers`, `facility_specialties`, `facility_services`, `facility_insurers`, `doctor_specialties` | Catalog + pivot models | many-to-many pivots | Eloquent (auth'd), catalogs read-mostly | |
| `patients` | `Patient` | belongsTo `User`, `Facility` (registering) | **RPC/Edge only for write** | No general facility-staff UPDATE policy exists by design (Decision W4). Laravel must call the scoped function, never raw `Patient::update()`, for registration/demographic changes |
| `doctor_profiles` | `DoctorProfile` | belongsTo `User` | Eloquent (auth'd) | |
| `patient_provider_links` | `PatientProviderLink` | belongsTo Patient, Doctor(User), Facility | Eloquent (auth'd) | |
| `encounters`, `diagnoses`, `clinical_notes`, `vitals_log`, `procedures`, `patient_allergies`, `immunizations`, `patient_medical_history_notes`, `patient_medications` | Clinical model set | mostly belongsTo `Patient` + `Encounter` | Eloquent (auth'd) | Core EHR data — policy/authorization design here is the highest-stakes part of the whole app |
| `appt_availability`, `appt_bookings`, `appt_waitlist`, `telemed_sessions` | Appointment model set | belongsTo Patient, Doctor(User), Facility | Eloquent (auth'd) | |
| `drug_catalog`, `pharma_prescriptions`, `pharma_prescription_items`, `pharma_dispenses`, `pharma_inventory` | Pharmacy model set | prescription → items → dispenses; inventory per facility | Eloquent (auth'd) | |
| `lab_test_catalog`, `lab_orders`, `lab_results` | Lab model set | order → result (1:1) | Eloquent (auth'd) | `lab_results.is_critical` flag needs a notification hook |
| `admissions`, `bed_assignments` | `Admission`, `BedAssignment` | belongsTo Patient, Facility, Bed | Eloquent (auth'd) | |
| `bill_invoices`, `bill_line_items`, `bill_payments` | Billing model set | invoice → line items, payments | Eloquent (auth'd) | |
| `subscription_plans`, `facility_subscriptions`, `platform_invoices` | Platform billing (SaaS layer, not patient billing) | facility → subscription → plan | Eloquent (auth'd), admin-scoped | Separate concern from `bill_invoices` — platform's own revenue, not the hospital's |
| `inv_items`, `inv_vendors`, `inv_purchase_orders`, `inv_stock_movements` | Inventory model set | facility-scoped | Eloquent (auth'd) | Distinct from `pharma_inventory` — general supplies vs. drugs |
| `staff_shifts`, `staff_leave` | Staffing model set | belongsTo `StaffAssignment` | Eloquent (auth'd) | |
| `consent_purposes`, `consent_records` | `ConsentPurpose`, `ConsentRecord` | patient grants consent to facility/user for a purpose | Eloquent (auth'd) | Central to DPDP-style compliance — must be checked before any cross-facility patient data access |
| `erasure_requests` | `ErasureRequest` | belongsTo Patient | Eloquent (auth'd), admin workflow | Right-to-erasure request queue |
| `retention_policies` | `RetentionPolicy` | standalone reference | Read-only/system | Drives a future scheduled job, not user-facing CRUD |
| `emergency_access_log` | `EmergencyAccessLog` | actor, patient, reviewer | Eloquent (auth'd) insert; review flow | "Break glass" emergency access must be logged and later reviewed — Laravel needs a clear UI for the reviewed_by workflow |
| `document_access_log` | `DocumentAccessLog` | belongsTo Document, User | **Read-only/system** — should be written by a DB trigger or controlled function, not ad hoc Eloquent inserts, to keep it trustworthy as an audit trail |
| `audit_log` (+ monthly partitions, `audit_log_default`) | `AuditLog` (read model only) | belongsTo User (actor) | **Read-only/system** | Comment confirms only `audit_trigger_fn()` (SECURITY DEFINER) writes here. Laravel should never attempt direct writes — build an admin viewer, not a writer |
| `notification_events`, `notification_preferences`, `notification_templates`, `notification_delivery_log` | Notification model set | event → delivery log; template catalog | Eloquent (auth'd) for preferences; events likely produced by backend logic/queue jobs | |
| `documents`, `document_access_log` | `Document` | belongsTo Patient/Facility, uploadedBy User | Eloquent (auth'd) for metadata; actual file bytes in Supabase Storage | Every read should log to `document_access_log` |

## Open questions before modeling begins

1. **RLS policy bodies** haven't been individually pulled yet (only `rls_enabled: true` per table was confirmed). Before finalizing which Eloquent queries are safe to write as "auth'd", each policy's `USING`/`WITH CHECK` logic should be read so Laravel's session-context setup (passing `auth.uid()` correctly per request) is verified against it.
2. **The `patients` write path** (Decision W4) references an Edge Function/RPC that doesn't currently exist (`list_edge_functions` returned empty). Need to confirm: is it a plain Postgres RPC (`SECURITY DEFINER` function) instead? If it truly doesn't exist yet, that's a Phase 1 build item, not a migration item.
3. No migrations history was inspected yet (Supabase migrations table/CLI history) — recommended before writing any new Laravel migrations that might collide with existing ones.

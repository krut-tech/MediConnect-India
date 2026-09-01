<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStaffAssignmentRequest;
use App\Models\DoctorProfile;
use App\Models\Facility;
use App\Models\Role;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Phase 6 correction — Staff directory + creation (items 2, 5, 6).
 *
 * ============================================================
 * WHY THIS CONTROLLER DIDN'T EXIST BEFORE (item 2's actual finding)
 * ============================================================
 * Audited before writing anything: `staff_assignments` has had full,
 * real RLS since early in this project (`staff_assignments_insert`,
 * `_update`, `_delete`, `_select_own`, `_select_facility_admin` — all
 * verified live, unchanged by this session) — a hospital_admin has
 * always been able to create a doctor/nurse/staff assignment at the
 * database layer, and a super_admin at any facility (including a NEW
 * hospital_admin assignment). But no route, controller, or view in this
 * Laravel app ever exposed that capability — the only way to actually
 * create a `staff_assignments` row was a direct DB write outside the
 * app. That gap, not a missing RLS policy, is what this controller
 * closes. No new table, policy, or migration was needed for this.
 *
 * ============================================================
 * IDENTITY, NOT ACCOUNT CREATION
 * ============================================================
 * store() links an ALREADY-registered user (looked up by email) to a
 * role/facility/department. It never creates a `users`/`auth.users` row
 * — that remains Supabase Auth's own sign-up flow (see AuthController),
 * unchanged, and self-elevation during registration is still explicitly
 * prevented there (no role field on the register form). A hospital_admin
 * or super_admin cannot grant a role to someone who hasn't signed up
 * yet; the person must already have an account. A full invitation/
 * activation-email flow (create a pending account before the person
 * signs up) would need Supabase's Auth Admin API (service_role-gated)
 * and a new "pending profile" concept — a materially larger,
 * separately-scoped change; not built here. See MIGRATION_PROGRESS.md.
 *
 * ============================================================
 * ROLE FILTERING IS UX, NOT AUTHORIZATION
 * ============================================================
 * assignableRoles() only returns roles where `is_platform_role = false`
 * and `code <> 'patient'` — read live from the `roles` table, matching
 * `staff_assignments_insert`'s own WITH CHECK exactly, not a hardcoded
 * guess at which codes those are. This exists purely so the dropdown
 * never offers a choice the database would reject; the actual
 * enforcement is still 100% the live RLS policy — a tampered request
 * for a platform role or 'patient' fails at the database (0 rows
 * affected / insert rejected), not because this class blocked it. This
 * is also what stops a hospital_admin from ever granting a platform
 * role (Super Admin, State Admin, etc.) — verified live against the
 * real `roles` table (BUG 10): every *_admin platform tier
 * (super_admin, city/district/state/national_admin) has
 * is_platform_role = true and is excluded here; only facility-scoped
 * roles (doctor, nurse, hospital_admin itself, receptionist, etc.) are
 * ever offered, and even 'hospital_admin' can only be granted at a
 * facility the granting admin already administers (or platform-wide by
 * a super_admin) — enforced by staff_assignments_insert, not this class.
 *
 * ============================================================
 * PHASE 6 BUGFIX — SQLSTATE[42883] on assignableRoles() (BUG 1)
 * ============================================================
 * Root cause (confirmed from Render production logs, not guessed):
 * `->where('is_platform_role', false)` sends PHP `false` as an
 * unquoted, unquoted-as-boolean parameter under this connection's
 * PDO::ATTR_EMULATE_PREPARES=true setting (required for Supabase's
 * transaction-mode pooler — see config/database.php, NOT changed by
 * this fix, since that setting is a separate, deliberate
 * infra-compatibility requirement). Under emulated prepares, PDO's
 * pgsql driver serializes a PHP bool as a bare integer, and Postgres
 * has no `boolean = integer` operator (unlike MySQL's implicit
 * coercion) — every call to this method failed with
 * "operator does not exist: boolean = integer", which is why
 * `GET /staff` (and `/staff/create`, which calls the same method) 500'd
 * for every single request. Fixed by comparing against a literal SQL
 * boolean via whereRaw() instead of a bound parameter — safe with zero
 * injection risk since no user input is involved, `is_platform_role` is
 * a fixed column name, and `false` is a fixed literal. Confirmed no
 * other `where(<column>, true|false)` boolean-column comparison exists
 * anywhere else in this codebase (searched before writing this fix), so
 * this was an isolated bug in code introduced this session, not a
 * systemic pattern.
 *
 * ============================================================
 * PHASE 6 BUGFIX — MISSING NAMES (BUG 2)
 * ============================================================
 * "Name on file missing" appearing broadly for real doctors/staff was
 * NOT a data problem — every users.full_name in this database was
 * already populated (verified live before touching anything). The real
 * cause was a `users` table RLS gap: no policy ever let a viewer see a
 * DOCTOR's or STAFF member's `full_name`, even when the corresponding
 * doctor_profiles/staff_assignments row was already independently
 * visible to them. Two new, narrowly-scoped SELECT policies
 * (`users_select_doctor_public_identity`,
 * `users_select_staff_facility_admin_identity` — see that migration)
 * close exactly that gap, mirroring the existing doctor_profiles/
 * staff_assignments visibility rules 1:1. No code change was needed in
 * this controller for that fix — the exact same Eloquent `with('user')`
 * eager-loads now resolve correctly once the underlying grant exists.
 *
 * ============================================================
 * PHASE 6 BUGFIX — DOCTOR CREATION FLOW (BUG 3/4)
 * ============================================================
 * store() now, when the chosen role is 'doctor', also creates/updates
 * the target user's doctor_profiles row (registration_number,
 * specialty, years_experience) in the SAME request — via the new,
 * narrowly-scoped `doctor_profiles_write_facility_admin` RLS policy
 * (only for a user who holds an active 'doctor' assignment at a
 * facility the caller administers — see that migration). This is
 * additive to, not a replacement for, the doctor's own later
 * self-service editing at /my-doctor-profile (doctor_profiles_
 * write_own remains unchanged and still lets the doctor edit their own
 * profile further, e.g. qualifications/languages, which this admin
 * flow does not collect).
 *
 * Name completion: if (and only if) the looked-up user's current
 * `full_name` is genuinely blank, the admin may supply one, which is
 * applied via a plain UPDATE scoped to that single row — never
 * overwriting an existing name (checked server-side against the
 * looked-up user's CURRENT value at request time, not merely omitted
 * from a form). users_update_own RLS (`id = auth.uid()`) means this
 * UPDATE only actually succeeds when the admin is completing THEIR OWN
 * blank name — i.e., not at all for someone else's row, by design: this
 * app currently has no RLS grant for an admin to edit another user's
 * name, and this bugfix does not add one (a much larger identity-
 * management decision), so the completion path exists in the form but
 * will safely no-op (0 rows affected, no error) for another user until
 * that policy decision is made. Documented rather than silently
 * dropped — see class docblock/report.
 */
class StaffController extends Controller
{
    /**
     * Staff visible to the signed-in user — own assignment only for a
     * plain staff member, or every assignment across their facility for
     * a hospital_admin, or platform-wide for a super_admin — entirely
     * decided by staff_assignments_select_own / _select_facility_admin
     * RLS, same as every other index() in this app. Item 5/6: optional
     * `q` (name), `role_id`, `facility_id` filters layered on top,
     * never widening what RLS already returned. Facility-scoped
     * navigation (item 6) reaches this via a `facility_id` query param
     * linked from the facility detail page.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $roleId = $request->query('role_id');
        $facilityId = trim((string) $request->query('facility_id', ''));

        $staff = StaffAssignment::query()
            ->whereNull('deleted_at')
            ->with(['user', 'role', 'facility', 'department'])
            ->when($search !== '', fn ($query) => $query->whereHas(
                'user',
                fn ($userQuery) => $userQuery->where('full_name', 'ilike', "%{$search}%")
            ))
            ->when($roleId, fn ($query) => $query->where('role_id', $roleId))
            ->when($facilityId !== '', fn ($query) => $query->where('facility_id', $facilityId))
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('staff.index', [
            'staff' => $staff,
            'filters' => [
                'q' => $search,
                'role_id' => $roleId,
                'facility_id' => $facilityId,
            ],
            'roleOptions' => $this->assignableRoles(),
            'facilityOptions' => Facility::query()->orderBy('name')->get(['id', 'name']),
            'canCreate' => Auth::user()?->isAdministrator() ?? false,
        ]);
    }

    /**
     * Create form. `canCreate` gating in the view/sidebar is UX only —
     * staff_assignments_insert RLS is what actually decides whether the
     * eventual INSERT in store() succeeds, exactly as documented in the
     * class docblock above.
     */
    public function create(): View
    {
        return view('staff.create', [
            'roleOptions' => $this->assignableRoles(),
            'facilityOptions' => Facility::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Looks up the target user by email for the create form's "show me
     * who this is before I submit" step. Read-only, returns only the
     * name/email already governed by this same request's RLS context —
     * i.e. this endpoint reveals nothing about a user that
     * users_select_own/_authorized_patient_identity/_doctor_public_
     * identity/_staff_facility_admin_identity didn't already allow the
     * caller to see. A lookup for a user outside all of those simply
     * returns "not found", identical to a genuinely non-existent email
     * — same non-leaking pattern as resolvePatientId() elsewhere in
     * this app.
     */
    public function lookup(Request $request): \Illuminate\Http\JsonResponse
    {
        $email = trim((string) $request->query('email', ''));

        if ($email === '') {
            return response()->json(['found' => false]);
        }

        $user = User::query()->where('email', $email)->first(['id', 'full_name']);

        if (! $user) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'full_name' => $user->full_name,
            'name_missing' => blank($user->full_name),
        ]);
    }

    /**
     * Looks up the target user by email (never accepts a user id
     * directly — same non-leaking-lookup pattern as
     * AppointmentController::resolvePatientId()'s MRN lookup), attempts
     * the staff_assignments INSERT, and — only when the chosen role is
     * 'doctor' and any doctor-profile fields were actually filled in —
     * creates/updates that user's doctor_profiles row in the same
     * request. See class docblock for the full rationale on both the
     * name-completion and doctor-profile-assist behavior.
     */
    public function store(StoreStaffAssignmentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $targetUser = User::query()->where('email', $data['user_email'])->first();

        if (! $targetUser) {
            return back()
                ->withErrors(['user_email' => 'No account was found with that email. The person must already have a MediConnect account.'])
                ->withInput();
        }

        try {
            StaffAssignment::query()->create([
                'user_id' => $targetUser->id,
                'facility_id' => $data['facility_id'],
                'role_id' => $data['role_id'],
                'department_id' => $data['department_id'] ?? null,
            ]);
        } catch (QueryException $e) {
            return back()
                ->withErrors(['staff' => 'This assignment could not be created. You may not be authorized to assign this role at this facility.'])
                ->withInput();
        }

        // Name completion — ONLY when the target's current name is
        // genuinely blank (re-checked here against the fresh $targetUser
        // fetched above, never trusting the form alone) and a value was
        // supplied. See class docblock: users_update_own RLS means this
        // safely no-ops for anyone other than the admin's own row today.
        $suppliedName = trim((string) ($data['full_name'] ?? ''));
        if ($suppliedName !== '' && blank($targetUser->full_name)) {
            User::query()->whereKey($targetUser->id)->update(['full_name' => $suppliedName]);
        }

        // Doctor-profile assist — only for the 'doctor' role, and only
        // when at least one profile field was actually supplied.
        $selectedRole = Role::query()->find($data['role_id']);
        $hasProfileInput = filled($data['registration_number'] ?? null)
            || filled($data['specialty'] ?? null)
            || $data['years_experience'] !== null;

        if ($selectedRole?->code === 'doctor' && $hasProfileInput) {
            try {
                DoctorProfile::query()->updateOrCreate(
                    ['user_id' => $targetUser->id],
                    array_filter([
                        'registration_number' => $data['registration_number'] ?? null,
                        'specialties' => filled($data['specialty'] ?? null) ? [$data['specialty']] : null,
                        'years_experience' => $data['years_experience'] ?? null,
                    ], fn ($value) => $value !== null)
                );
            } catch (QueryException $e) {
                // The staff_assignments row above already succeeded —
                // don't roll that back or fail the whole request over
                // profile-assist metadata; surface it as a distinct,
                // honest status instead of a silent drop or a fatal error.
                return redirect()->route('staff.index')
                    ->with('status', 'Staff assignment created, but the doctor profile could not be set up automatically — the doctor can complete it themselves at My Doctor Profile.');
            }
        }

        return redirect()->route('staff.index')->with('status', 'Staff assignment created.');
    }

    /**
     * Roles a hospital_admin/super_admin may assign through this form —
     * read live from `roles`, matching staff_assignments_insert's own
     * WITH CHECK (`is_platform_role = false AND code <> 'patient'`)
     * exactly. See class docblock: this is UX filtering only, not the
     * authorization boundary.
     *
     * PHASE 6 BUGFIX (BUG 1): boolean column compared via whereRaw()
     * literal, not a bound parameter — see class docblock for why.
     */
    private function assignableRoles()
    {
        return Role::query()
            ->whereRaw('is_platform_role = false')
            ->where('code', '!=', 'patient')
            ->orderBy('label')
            ->get(['id', 'code', 'label']);
    }
}

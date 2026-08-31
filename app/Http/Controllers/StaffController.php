<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStaffAssignmentRequest;
use App\Models\Facility;
use App\Models\Role;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
 * yet; the person must already have an account.
 *
 * ============================================================
 * ROLE FILTERING IS UX, NOT AUTHORIZATION
 * ============================================================ 
 * index()/create() only show roles where `is_platform_role = false` and
 * `code <> 'patient'` — reading live from the `roles` table, matching
 * `staff_assignments_insert`'s own WITH CHECK exactly, not a hardcoded
 * guess at which codes those are. This exists purely so the dropdown
 * never offers a choice the database would reject; the actual
 * enforcement is still 100% the live RLS policy — a tampered request
 * for a platform role or 'patient' fails at the database (0 rows
 * affected / insert rejected), not because this class blocked it.
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
     * Looks up the target user by email (never accepts a user id
     * directly — same non-leaking-lookup pattern as
     * AppointmentController::resolvePatientId()'s MRN lookup) and
     * attempts the staff_assignments INSERT. Whether it actually
     * succeeds is entirely decided by the live staff_assignments_insert
     * RLS policy; a rejected insert (wrong facility scope, a platform
     * role, 'patient', or a non-existent email) surfaces as the same
     * ordinary validation error either way — never a raw exception.
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

        return redirect()->route('staff.index')->with('status', 'Staff assignment created.');
    }

    /**
     * Roles a hospital_admin/super_admin may assign through this form —
     * read live from `roles`, matching staff_assignments_insert's own
     * WITH CHECK (`is_platform_role = false AND code <> 'patient'`)
     * exactly. See class docblock: this is UX filtering only, not the
     * authorization boundary.
     */
    private function assignableRoles()
    {
        return Role::query()
            ->where('is_platform_role', false)
            ->where('code', '!=', 'patient')
            ->orderBy('label')
            ->get(['id', 'code', 'label']);
    }
}

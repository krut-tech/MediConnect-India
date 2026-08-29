<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStaffLeaveRequest;
use App\Models\StaffLeave;
use App\Models\StaffShift;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Staff self-service module (Phase 6): My Staff Profile, My Shifts
 * (read-only), My Leave (read + request-own).
 *
 * ============================================================
 * SCOPE — WHY THIS IS SELF-SERVICE ONLY
 * ============================================================
 * `staff_assignments` itself is admin-managed at the database layer:
 * live pg_policies show only `hospital_admin`/`super_admin` can
 * INSERT/UPDATE/DELETE a staff_assignments row
 * (staff_assignments_insert/_update/_delete) — a plain staff member
 * can only SELECT their own row (staff_assignments_select_own).
 * Building staff-assignment creation/management here would mean
 * either faking a capability RLS doesn't grant, or building an
 * Admin-side feature — both out of scope for this phase. Every method
 * below only ever reads or writes data scoped to the SIGNED-IN USER'S
 * OWN active assignment(s); none of them accept a staff/assignment id
 * from the request or route (no route parameter exists for any action
 * here — see routes/web.php), mirroring the same identity-from-
 * Auth::user()-only pattern already used by
 * PatientController::myProfile()/DoctorController::myProfile().
 *
 * ============================================================
 * RLS SCOPING (Phase 5 Step 3, unchanged by this phase)
 * ============================================================
 * Every method below runs inside the Postgres RLS context established
 * by 'supabase.rls' (see routes/web.php), same as every other
 * authenticated route. The `role` middleware in front of this
 * controller's routes additionally requires the signed-in user to
 * resolve to at least one active staff_assignments row (same tier as
 * /patients) — a plain patient account is correctly 403'd before ever
 * reaching these methods. Where a WHERE clause naming
 * staff_assignment_id appears below, it identifies intent ("my own
 * assignment(s)"), not a security boundary — the live
 * staff_shifts_select_own / staff_leave_select_own /
 * staff_leave_insert_own policies are what actually restrict which
 * rows are visible or writable for the real signed-in user, regardless
 * of what this controller's queries ask for.
 */
class StaffController extends Controller
{
    /**
     * The signed-in user's own active staff_assignments — same
     * "active assignment" definition (deleted_at null, valid_until
     * null or in the future) already used by
     * App\Http\Middleware\EnsureUserHasRole and DashboardController,
     * reused here rather than reinvented. Never accepts any id from
     * the request; Auth::user() is the sole identity source.
     */
    private function activeAssignments(): Builder
    {
        return Auth::user()
            ->staffAssignments()
            ->with(['role', 'facility', 'department'])
            ->whereNull('deleted_at')
            ->where(function ($query) {
                $query->whereNull('valid_until')->orWhere('valid_until', '>', now());
            })
            ->orderByDesc('is_primary');
    }

    /**
     * The signed-in user's own staff assignment(s). Normally at least
     * one (the 'role' middleware in front of this route already
     * requires it), but this still handles an empty result gracefully
     * rather than assuming — e.g. an assignment expiring between the
     * middleware's check and this query running.
     */
    public function myProfile(): View
    {
        return view('staff.my-profile', [
            'assignments' => $this->activeAssignments()->get(),
        ]);
    }

    /**
     * Read-only. See StaffShift's own docblock: this app never
     * writes to staff_shifts — only hospital_admin/super_admin can,
     * per live RLS.
     */
    public function myShifts(): View
    {
        $assignmentIds = $this->activeAssignments()->pluck('id');

        $shifts = StaffShift::query()
            ->whereIn('staff_assignment_id', $assignmentIds)
            ->orderByDesc('shift_start')
            ->paginate(15);

        return view('staff.my-shifts', [
            'shifts' => $shifts,
            'hasActiveAssignment' => $assignmentIds->isNotEmpty(),
        ]);
    }

    /**
     * Own leave requests, newest first, plus the create form. See
     * StaffLeave's own docblock: no update/cancel path exists here —
     * live RLS reserves that to hospital_admin/super_admin.
     */
    public function myLeave(): View
    {
        $assignmentIds = $this->activeAssignments()->pluck('id');

        $leaveRequests = StaffLeave::query()
            ->whereIn('staff_assignment_id', $assignmentIds)
            ->orderByDesc('leave_start')
            ->paginate(15);

        return view('staff.my-leave', [
            'leaveRequests' => $leaveRequests,
            'hasActiveAssignment' => $assignmentIds->isNotEmpty(),
        ]);
    }

    /**
     * Creates a leave request against the signed-in user's own
     * primary active assignment. staff_assignment_id is derived
     * solely from activeAssignments() above — never read from the
     * request. status is hardcoded to 'requested' — never read from
     * the request either, since the live staff_leave_insert_own RLS
     * policy's WITH CHECK does not itself restrict which status value
     * gets written (see StaffLeave's own docblock). A rejected INSERT
     * (e.g. no active assignment exists, or RLS denies it for a
     * reason this app isn't aware of) raises a real QueryException,
     * caught explicitly rather than inferred from a row count — same
     * pattern as DoctorController::updateMyProfile()'s create path.
     */
    public function storeLeave(StoreStaffLeaveRequest $request): RedirectResponse
    {
        $assignment = $this->activeAssignments()->first();

        if (! $assignment) {
            return back()
                ->withErrors(['leave' => 'You need an active staff assignment to request leave.'])
                ->withInput();
        }

        try {
            StaffLeave::query()->create([
                'staff_assignment_id' => $assignment->id,
                'leave_start' => $request->validated('leave_start'),
                'leave_end' => $request->validated('leave_end'),
                'status' => 'requested',
            ]);
        } catch (QueryException $e) {
            return back()
                ->withErrors(['leave' => 'Your leave request could not be submitted.'])
                ->withInput();
        }

        return redirect()->route('staff.my-leave')
            ->with('status', 'Leave request submitted.');
    }
}

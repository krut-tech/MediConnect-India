<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeaveRequest;
use App\Models\StaffLeave;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 6 finalization — leave / blocked-period management (items 2+3).
 *
 * See the commit message for why this single controller/table covers
 * both concerns rather than duplicating either. A row in
 * public.staff_leave means "this staff member is unavailable for this
 * date range" — whether requested by the staff member themselves
 * (personal leave) or, in practice, imposed directly by a
 * hospital_admin (a facility-driven block, e.g. a doctor pulled onto
 * an emergency roster) — both go through the same insert path; only
 * who is doing the inserting differs, and that is exactly what RLS
 * already governs (see StaffLeave's own docblock).
 *
 * This does NOT touch public.appt_availability or
 * public.appt_bookings — a staff_leave row does not itself block
 * appt_available_slots() computation or cancel existing bookings.
 * Cross-referencing leave against live slot computation (so a blocked
 * period actually prevents new bookings during it) is flagged as a
 * deferred item in the Phase 6 finalization report, same as this
 * app's established practice of stating gaps rather than silently
 * building around them.
 */
class LeaveController extends Controller
{
    /**
     * The signed-in user's own leave/blocked-period requests, plus —
     * if they hold an active hospital_admin assignment — every pending
     * request across their facility's staff, for approval. Which rows
     * come back is entirely decided by staff_leave_select_own /
     * staff_leave_facility_admin RLS; this method does not branch on
     * role to build two different queries.
     */
    public function index(): View
    {
        /** @var \App\Models\User&Authenticatable $user */
        $user = Auth::user();

        $leave = StaffLeave::query()
            ->with(['staffAssignment.user', 'staffAssignment.facility', 'staffAssignment.role'])
            ->orderByDesc('leave_start')
            ->get();

        return view('leave.index', [
            'leave' => $leave,
            'activeAssignment' => $user->activeStaffAssignment(),
            'isAdministrator' => $user->isAdministrator(),
        ]);
    }

    /**
     * Creates one leave/blocked-period request against the signed-in
     * user's own active staff assignment. staff_assignment_id is never
     * accepted from request input — set here only, from
     * Auth::user()->activeStaffAssignment() — so this form can never be
     * used to file a request against someone else's assignment no
     * matter what the request body contains. status is hardcoded to
     * 'requested', never accepted from input either — only
     * approve()/reject() below (facility_admin RLS-gated) may change
     * it.
     *
     * A caller with no active staff assignment (shouldn't happen — this
     * route sits behind 'role', same as schedule management) gets an
     * ordinary validation error rather than a null-property fatal.
     */
    public function store(StoreLeaveRequest $request): RedirectResponse
    {
        /** @var \App\Models\User&Authenticatable $user */
        $user = Auth::user();
        $assignment = $user->activeStaffAssignment();

        if (! $assignment) {
            return back()->withErrors(['leave' => 'No active staff assignment was found for your account.'])->withInput();
        }

        $data = $request->validated();

        try {
            StaffLeave::query()->create([
                'staff_assignment_id' => $assignment->id,
                'leave_start' => $data['leave_start'],
                'leave_end' => $data['leave_end'],
                'status' => 'requested',
            ]);
        } catch (QueryException $e) {
            return back()
                ->withErrors(['leave' => 'This request could not be saved. You may not be authorized to file leave against this assignment.'])
                ->withInput();
        }

        return redirect()->route('leave.index')->with('status', 'Leave/blocked-period request submitted.');
    }

    /**
     * Approves one pending request. Only reachable in practice for a
     * caller staff_leave_facility_admin RLS actually authorizes (an
     * in-scope hospital_admin, or a super_admin) — anyone else's UPDATE
     * matches 0 rows, reported as an ordinary error below, never a raw
     * 500. Uses the affected-row-count pattern, not Eloquent's boolean
     * update() return value, for the same reason as
     * AvailabilityController::update()/destroy().
     */
    public function approve(StaffLeave $leave): RedirectResponse
    {
        return $this->updateStatus($leave, 'approved');
    }

    /**
     * Rejects one pending request. Same authorization/affected-row-count
     * discipline as approve() above.
     */
    public function reject(StaffLeave $leave): RedirectResponse
    {
        return $this->updateStatus($leave, 'rejected');
    }

    private function updateStatus(StaffLeave $leave, string $status): RedirectResponse
    {
        $affected = StaffLeave::query()
            ->whereKey($leave->getKey())
            ->update(['status' => $status]);

        if ($affected === 0) {
            return back()->withErrors(['leave' => 'This request could not be updated. You may not be authorized to manage it.']);
        }

        return redirect()->route('leave.index')->with('status', "Request {$status}.");
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeaveRequest;
use App\Models\AppointmentBooking;
use App\Models\StaffLeave;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 6 finalization — leave / blocked-period management (items 2+3),
 * extended (Phase 6 correction) with affected-appointment conflict
 * detection on approval (items 5-6 of that spec).
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
 * This still does NOT touch public.appt_availability directly, and
 * approving leave still does not cancel, reschedule, or otherwise
 * write to any appt_bookings row — see approve()'s docblock below for
 * exactly what conflict detection does and does not do, and why.
 * Cross-referencing leave against live appt_available_slots() slot
 * computation (so a blocked period also prevents NEW bookings during
 * it) remains a separately-scoped, not-yet-built item.
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
     * Approves one pending request — but first checks whether doing so
     * would affect any already-booked appointment for that doctor.
     *
     * Behavior:
     *   1. Compute affectedAppointments() for this leave's doctor/date
     *      range (active bookings only — 'booked'/'confirmed'; a
     *      cancelled/completed/no_show booking is never "affected").
     *   2. If none exist, or the caller already passed ?confirm=1,
     *      approve exactly as before (updateStatus()) — nothing about
     *      the no-conflict path changed.
     *   3. If any exist and this is not a confirmed request, the
     *      approval is NOT applied. Instead the caller is sent back to
     *      /leave with a conflict summary (total count + per-date
     *      counts) flashed to the session, and the view offers a
     *      "confirm and approve anyway" action (the same route, with
     *      ?confirm=1) — i.e. "leave requested -> conflict detected ->
     *      review affected appointments -> approve", per the spec.
     *
     * Deliberately NOT done here, and why: automatically cancelling,
     * rescheduling, or relabeling the affected appt_bookings rows.
     * appt_bookings has no cancelled_by/cancellation_reason/resolution-
     * state column today (confirmed live before writing this), and
     * inventing one of those, or silently moving a patient's booking to
     * another doctor/time, is exactly the kind of unsafe unapproved
     * schema/behavior change the standing project rules call out.
     * Confirmed approval leaves every affected appointment exactly as
     * it was — visible, unmodified, still on the doctor's calendar —
     * so staff can resolve it manually (call the patient, cancel via
     * the existing cancel() action, etc.) until a real
     * reschedule/notify workflow is separately approved and built.
     */
    public function approve(StaffLeave $leave, Request $request): RedirectResponse
    {
        if (! $request->boolean('confirm')) {
            $affected = $this->affectedAppointments($leave);

            if ($affected->isNotEmpty()) {
                return redirect()->route('leave.index')->with('leave_conflict', [
                    'leave_id' => $leave->getKey(),
                    'doctor_name' => $leave->staffAssignment?->user?->full_name,
                    'leave_start' => $leave->leave_start?->format('d M Y'),
                    'leave_end' => $leave->leave_end?->format('d M Y'),
                    'total' => $affected->count(),
                    'by_date' => $affected
                        ->groupBy(fn (AppointmentBooking $booking) => $booking->scheduled_at->format('d M Y'))
                        ->map->count(),
                ]);
            }
        }

        return $this->updateStatus($leave, 'approved');
    }

    /**
     * Rejects one pending request. No conflict check needed — rejecting
     * leaves every appointment (and the doctor's normal availability)
     * completely unaffected. Same authorization/affected-row-count
     * discipline as approve() above.
     */
    public function reject(StaffLeave $leave): RedirectResponse
    {
        return $this->updateStatus($leave, 'rejected');
    }

    /**
     * Active (booked/confirmed) appointments for this leave's doctor
     * that fall inside the leave's date range — i.e. exactly the set
     * the spec calls "affected appointments." Read-only; runs under the
     * same RLS context (supabase.rls) as every other query in this
     * controller, so it can only ever see rows the signed-in caller
     * (an in-scope facility_admin or super_admin, per
     * staff_leave_facility_admin) is already authorized to see via
     * appt_bookings_select_own/_doctor/_facility_staff.
     *
     * @return Collection<int, AppointmentBooking>
     */
    private function affectedAppointments(StaffLeave $leave): Collection
    {
        $leave->loadMissing('staffAssignment');
        $doctorUserId = $leave->staffAssignment?->user_id;

        if (! $doctorUserId) {
            return new Collection();
        }

        return AppointmentBooking::query()
            ->where('doctor_user_id', $doctorUserId)
            ->whereIn('status', ['booked', 'confirmed'])
            ->whereDate('scheduled_at', '>=', $leave->leave_start)
            ->whereDate('scheduled_at', '<=', $leave->leave_end)
            ->orderBy('scheduled_at')
            ->get();
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

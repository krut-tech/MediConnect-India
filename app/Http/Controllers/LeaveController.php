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
 * detection on approval (items 5-6 of that spec), the audit trail +
 * preserved-not-hidden resolution state for those affected appointments
 * (items 7 + 9), and further extended (this correction, 2026-08-31
 * continuation) with self-service edit/withdraw and search/filter
 * (items 9-10).
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
 * approving leave still does not cancel or auto-reschedule any
 * appt_bookings row — see approve()'s docblock below for exactly what
 * conflict resolution does and does not do, and why.
 *
 * ============================================================
 * SELF-SERVICE EDIT/WITHDRAW (item 9, this correction)
 * ============================================================
 * update()/withdraw() are backed by a new, additive RLS policy
 * (staff_leave_update_own — verified live before this code was
 * written) that permits a signed-in user to UPDATE their OWN
 * staff_leave row only while status = 'requested', and only ever into
 * status 'requested' (unchanged) or 'cancelled' (withdraw) — never
 * 'approved'/'rejected', which stay exclusively
 * staff_leave_facility_admin's domain. This is the exact same
 * ownership-based pattern as every other _own policy already live on
 * this table; nothing here bypasses RLS or adds an application-side
 * substitute for it — the UPDATE will affect 0 rows (surfaced as the
 * existing "not authorized" error, same discipline as every other
 * write in this app) if RLS doesn't independently permit it.
 */
class LeaveController extends Controller
{
    /**
     * The signed-in user's own leave/blocked-period requests, plus —
     * if they hold an active hospital_admin assignment — every pending
     * request across their facility's staff, for approval. Which rows
     * come back is entirely decided by staff_leave_select_own /
     * staff_leave_facility_admin RLS; this method does not branch on
     * role to build two different queries. A plain patient has no
     * active staff assignment and no facility_admin grant, so the
     * (patients_) role sees nothing here — this route sits behind the
     * same 'role' (any active staff assignment) middleware group as
     * schedule management, so a patient never reaches it at all.
     *
     * Item 10 (search/filter, this correction): optional `q` (staff
     * member name), `status`, `date_from`, `date_to` query params are
     * applied to the RLS-scoped base query before the admin/own split
     * below — so both lists on this page reflect the active filter,
     * never widening which rows RLS already returned.
     */
    public function index(Request $request): View
    {
        /** @var \App\Models\User&Authenticatable $user */
        $user = Auth::user();

        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));
        $validStatuses = ['requested', 'approved', 'rejected', 'cancelled'];

        $leave = StaffLeave::query()
            ->with(['staffAssignment.user', 'staffAssignment.facility', 'staffAssignment.role', 'requestedByUser', 'reviewedByUser'])
            ->when($search !== '', fn ($query) => $query->whereHas(
                'staffAssignment.user',
                fn ($userQuery) => $userQuery->where('full_name', 'ilike', "%{$search}%")
            ))
            ->when(in_array($status, $validStatuses, true), fn ($query) => $query->where('status', $status))
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('leave_end', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('leave_start', '<=', $dateTo))
            ->orderByDesc('leave_start')
            ->get();

        return view('leave.index', [
            'leave' => $leave,
            'activeAssignment' => $user->activeStaffAssignment(),
            'isAdministrator' => $user->isAdministrator(),
            'filters' => [
                'q' => $search,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'statusOptions' => $validStatuses,
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
     * it. requested_by is likewise always the signed-in user, never
     * client input.
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
                'leave_type' => $data['leave_type'] ?? null,
                'reason' => $data['reason'] ?? null,
                'status' => 'requested',
                'requested_by' => $user->id,
            ]);
        } catch (QueryException $e) {
            return back()
                ->withErrors(['leave' => 'This request could not be saved. You may not be authorized to file leave against this assignment.'])
                ->withInput();
        }

        return redirect()->route('leave.index')->with('status', 'Leave/blocked-period request submitted.');
    }

    /**
     * Edit form for the signed-in user's own still-pending request.
     * Reachable regardless of current status — the form itself, and
     * update() below, are what actually enforce "only while requested"
     * (both the UI check here and the live RLS policy independently
     * enforce it; RLS is the real boundary, this is just UX so a
     * decided request doesn't show an editable form that would then
     * silently no-op).
     */
    public function edit(StaffLeave $leave): View|RedirectResponse
    {
        if ($leave->status !== 'requested') {
            return redirect()->route('leave.index')->with('status', 'This request has already been decided and can no longer be edited.');
        }

        return view('leave.edit', [
            'leave' => $leave,
        ]);
    }

    /**
     * Updates the signed-in user's own still-pending leave request.
     * Whether this actually succeeds is entirely decided by the live
     * staff_leave_update_own RLS policy (own assignment, status still
     * 'requested') — the affected-row-count check below is the same
     * discipline as every other write in this app, not a stand-in
     * authorization check.
     */
    public function update(StoreLeaveRequest $request, StaffLeave $leave): RedirectResponse
    {
        $data = $request->validated();

        $affected = StaffLeave::query()->whereKey($leave->getKey())->update([
            'leave_start' => $data['leave_start'],
            'leave_end' => $data['leave_end'],
            'leave_type' => $data['leave_type'] ?? null,
            'reason' => $data['reason'] ?? null,
        ]);

        if ($affected === 0) {
            return back()->withErrors(['leave' => 'This request could not be updated — it may have already been decided, or you may not be authorized to edit it.'])->withInput();
        }

        return redirect()->route('leave.index')->with('status', 'Request updated.');
    }

    /**
     * Withdraws (self-cancels) the signed-in user's own still-pending
     * request. Deliberately a separate action from reject() — this is
     * the requester withdrawing their own ask, not a facility admin's
     * decision, so reviewed_by/reviewed_at are NOT set here (those
     * remain "who decided this", which withdrawal isn't). Same RLS
     * boundary (staff_leave_update_own) and affected-row-count
     * discipline as update() above.
     */
    public function withdraw(StaffLeave $leave): RedirectResponse
    {
        $affected = StaffLeave::query()->whereKey($leave->getKey())->update([
            'status' => 'cancelled',
        ]);

        if ($affected === 0) {
            return back()->withErrors(['leave' => 'This request could not be withdrawn — it may have already been decided, or you may not be authorized to withdraw it.']);
        }

        return redirect()->route('leave.index')->with('status', 'Request withdrawn.');
    }

    /**
     * Approves one pending request — but first checks whether doing so
     * would affect any already-booked appointment for that doctor.
     *
     * Behavior:
     *   1. Compute affectedAppointments() for this leave's doctor/date
     *      range (active bookings only — 'booked'/'checked_in'; a
     *      cancelled/completed/no_show booking is never "affected").
     *   2. If none exist, or the caller already passed ?confirm=1,
     *      approve exactly as before (updateStatus()).
     *   3. If any exist and this is not a confirmed request, the
     *      approval is NOT applied. Instead the caller is sent back to
     *      /leave with a conflict summary (total count + per-date
     *      counts) flashed to the session, and the view offers a
     *      "confirm and approve anyway" action (the same route, with
     *      ?confirm=1) — i.e. "leave requested -> conflict detected ->
     *      review affected appointments -> approve", per the spec.
     *   4. On a confirmed approval that DOES have affected appointments,
     *      each affected booking is marked resolution_state =
     *      'pending_reschedule' (resolved_by/resolved_at/resolution_note
     *      set alongside it) — additive columns. The booking's own
     *      status/scheduled_at/doctor are NOT changed: this is metadata
     *      saying "this appointment needs facility follow-up," not an
     *      automatic reschedule or cancellation.
     */
    public function approve(StaffLeave $leave, Request $request): RedirectResponse
    {
        $affected = $this->affectedAppointments($leave);

        if (! $request->boolean('confirm')) {
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

        $result = $this->updateStatus($leave, 'approved', $request);

        if ($affected->isNotEmpty()) {
            AppointmentBooking::query()
                ->whereIn('id', $affected->pluck('id'))
                ->update([
                    'resolution_state' => 'pending_reschedule',
                    'resolution_note' => 'Doctor leave approved for this date — needs facility follow-up (reschedule or cancel).',
                    'resolved_by' => Auth::id(),
                    'resolved_at' => now(),
                ]);
        }

        return $result;
    }

    /**
     * Rejects one pending request. No conflict check needed — rejecting
     * leaves every appointment (and the doctor's normal availability)
     * completely unaffected. Same authorization/affected-row-count
     * discipline as approve() above.
     */
    public function reject(StaffLeave $leave, Request $request): RedirectResponse
    {
        return $this->updateStatus($leave, 'rejected', $request);
    }

    /**
     * Active (booked/checked_in) appointments for this leave's doctor
     * that fall inside the leave's date range — i.e. exactly the set
     * the spec calls "affected appointments." Read-only; runs under the
     * same RLS context (supabase.rls) as every other query in this
     * controller, so it can only ever see rows the signed-in caller
     * (an in-scope facility_admin or super_admin, per
     * staff_leave_facility_admin) is already authorized to see via
     * appt_bookings_select_own/_doctor/_facility_staff. Already-marked
     * (resolution_state IS NOT NULL) bookings are excluded so a second
     * approval pass over the same leave never double-flags a booking
     * that facility staff has already started resolving.
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
            ->whereIn('status', ['booked', 'checked_in'])
            ->whereNull('resolution_state')
            ->whereDate('scheduled_at', '>=', $leave->leave_start)
            ->whereDate('scheduled_at', '<=', $leave->leave_end)
            ->orderBy('scheduled_at')
            ->get();
    }

    /**
     * Records the reviewing actor/timestamp and an optional decision
     * reason (e.g. why a request was rejected) alongside the status
     * change — additive columns, see class docblock.
     */
    private function updateStatus(StaffLeave $leave, string $status, Request $request): RedirectResponse
    {
        $reason = trim((string) $request->input('decision_reason', ''));

        $affected = StaffLeave::query()
            ->whereKey($leave->getKey())
            ->update([
                'status' => $status,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'decision_reason' => $reason !== '' ? $reason : null,
            ]);

        if ($affected === 0) {
            return back()->withErrors(['leave' => 'This request could not be updated. You may not be authorized to manage it.']);
        }

        return redirect()->route('leave.index')->with('status', "Request {$status}.");
    }
}

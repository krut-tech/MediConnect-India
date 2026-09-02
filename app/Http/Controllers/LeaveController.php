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
 * (items 7 + 9), further extended (2026-08-31 continuation) with
 * self-service edit/withdraw and search/filter (items 9-10), extended
 * (production data-visibility audit) by fixing a view-layer bug where a
 * hospital_admin's facility-wide, non-pending leave records were
 * fetched correctly by index() but never rendered, and further extended
 * (this correction, revoke workflow) with approved-leave revocation —
 * see revoke()'s own docblock below for the full state-machine
 * rationale and why it deliberately does NOT touch appt_bookings.
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
 * STATE MACHINE (this correction — enforced explicitly below)
 * ============================================================
 *   requested -> approved   (approve(), only from 'requested')
 *   requested -> rejected   (reject(),  only from 'requested')
 *   requested -> cancelled  (withdraw(), only from 'requested' — RLS-enforced)
 *   approved  -> revoked    (revoke(),  only from 'approved')
 * Every other transition (rejected/cancelled/revoked -> anything;
 * approved -> approved; requested -> revoked; etc.) is rejected.
 *
 * Before this correction, staff_leave_facility_admin's RLS policy (an
 * unrestricted-by-status ALL policy — see StaffLeave's docblock) meant
 * approve()/reject() would silently let an admin transition ANY row
 * regardless of its current status — e.g. re-"approving" an already-
 * rejected request via a manipulated PATCH request, or double-approving
 * an already-approved one and clobbering reviewed_by/reviewed_at. RLS
 * was never going to catch this: it correctly scopes WHICH rows an
 * admin may touch (their facility only), not WHICH status transitions
 * are semantically valid — that is a business rule, not a row-
 * visibility rule, so it is enforced here at the application layer
 * (a guard clause in each transition method), matching the codebase's
 * own established "RLS decides visibility, the controller decides
 * business validity" split (see e.g. edit()'s pre-existing
 * status==='requested' guard, same discipline applied consistently
 * rather than only to the new revoke() method).
 *
 * ============================================================
 * SELF-SERVICE EDIT/WITHDRAW (item 9, earlier correction)
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
     *
     * staffAssignment.department eager-loaded (production audit) purely
     * for the view's facility-wide table — department_id/relation
     * already existed (StaffAssignment::department(), added for the
     * Staff directory), just never pulled into this query before.
     *
     * $validStatuses now includes 'cancelled' and 'revoked' (this
     * correction) — both real, reachable statuses (see the class
     * docblock's state machine), so both must be selectable in the
     * status filter dropdown and must not be excluded from "All".
     */
    public function index(Request $request): View
    {
        /** @var \App\Models\User&Authenticatable $user */
        $user = Auth::user();

        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));
        $validStatuses = ['requested', 'approved', 'rejected', 'cancelled', 'revoked'];

        $leave = StaffLeave::query()
            ->with(['staffAssignment.user', 'staffAssignment.facility', 'staffAssignment.role', 'staffAssignment.department', 'requestedByUser', 'reviewedByUser', 'revokedByUser'])
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
     * approve()/reject()/revoke() below (facility_admin RLS-gated) may
     * change it. requested_by is likewise always the signed-in user,
     * never client input.
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
     *
     * Deliberately NOT extended to approved leave (spec item 12,
     * this correction): the preferred workflow for changing an
     * approved period is revoke() below, then a fresh request — not an
     * in-place edit of the original approved row, which would destroy
     * the audit trail the revoke workflow exists to preserve.
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
     * State-machine guard (this correction): only a 'requested' row may
     * be approved. Before this guard, staff_leave_facility_admin's
     * unrestricted-by-status RLS meant this method would silently
     * "approve" a row regardless of its current status — see the class
     * docblock's state-machine section for the full rationale.
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
        if ($leave->status !== 'requested') {
            return back()->withErrors(['leave' => 'Only a pending request can be approved. This request has already been decided.']);
        }

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
     * discipline as approve() above, plus the same state-machine guard
     * (this correction) — only a 'requested' row may be rejected.
     */
    public function reject(StaffLeave $leave, Request $request): RedirectResponse
    {
        if ($leave->status !== 'requested') {
            return back()->withErrors(['leave' => 'Only a pending request can be rejected. This request has already been decided.']);
        }

        return $this->updateStatus($leave, 'rejected', $request);
    }

    /**
     * Confirmation screen for revoking one APPROVED leave/blocked-period
     * — spec item 4 ("Revoke confirmation must NOT immediately change
     * the record"). Reachable only from an 'approved' row; anything
     * else redirects back with an explanatory message, same UX-guard
     * pattern as edit() above (RLS + revoke()'s own guard are the real
     * boundary; this is reachability only).
     */
    public function confirmRevoke(StaffLeave $leave): View|RedirectResponse
    {
        if ($leave->status !== 'approved') {
            return redirect()->route('leave.index')->with('status', 'Only approved leave can be revoked.');
        }

        $leave->loadMissing(['staffAssignment.user', 'staffAssignment.facility']);

        return view('leave.revoke', [
            'leave' => $leave,
        ]);
    }

    /**
     * Revokes one previously-APPROVED leave/blocked-period request —
     * spec's "Approved Leave Revoke" workflow (this correction).
     *
     * ============================================================
     * WHY REVOKE, NOT DELETE OR EDIT (spec items 2, 5, 12)
     * ============================================================
     * The row is never deleted — a revoked leave remains a permanent,
     * queryable historical record (still returned by index() under the
     * 'revoked' status filter). It is also never edited in place: this
     * method only ever flips status -> 'revoked' and fills the three
     * new revoked_* columns (see StaffLeave's docblock for why those
     * are separate columns from reviewed_by/reviewed_at, not a reuse of
     * them) — the original leave_start/leave_end/requested_by/
     * reviewed_by/reviewed_at are untouched, preserving exactly what
     * was originally approved and by whom. If the underlying need is a
     * genuinely different date range, the spec's own preferred
     * workflow applies: revoke this row, then file a new request — two
     * auditable rows instead of one row silently rewritten.
     *
     * ============================================================
     * STATE-MACHINE GUARD (spec item 13/19: E, F, G)
     * ============================================================
     * Only a currently-'approved' row may be revoked — rejected/
     * cancelled/already-revoked -> revoked are all rejected here at the
     * application layer, same rationale as approve()/reject()'s new
     * guards above (RLS's staff_leave_facility_admin policy is
     * unrestricted by status, so this business rule cannot come from
     * RLS alone).
     *
     * ============================================================
     * AUTHORIZATION (spec items 6, 7)
     * ============================================================
     * No separate Laravel-side authorization check is added here — the
     * live staff_leave_facility_admin RLS policy already independently
     * enforces "only a hospital_admin at THIS row's own facility may
     * touch it" for every write on this table, including this one,
     * exactly as it already does for approve()/reject(). The
     * affected-row-count check below is that RLS boundary made visible
     * as an ordinary error, not a second, application-side
     * reimplementation of facility scoping — manipulating the {leave}
     * UUID in the URL to point at another facility's row still resolves
     * through the same RLS-scoped query and fails the same way (0 rows
     * affected) any other cross-facility write already does in this
     * app. A staff member (no hospital_admin grant) reaching this route
     * fails for the same reason approve()/reject() already do: they
     * have no facility_admin RLS grant, and staff_leave_update_own's
     * USING clause requires status='requested' — an approved row can
     * never match it — so no permissive policy anywhere would allow
     * their own UPDATE to succeed.
     *
     * ============================================================
     * REVOCATION REASON (spec item 4)
     * ============================================================
     * Required, and rejected if blank/whitespace-only — inline
     * validation here rather than a new FormRequest class, matching
     * this controller's existing withdraw()/reject() pattern of not
     * spinning up a FormRequest for a single required field.
     *
     * ============================================================
     * APPOINTMENT ENGINE INTEGRATION (spec item 8) — deliberately NOT
     * done by writing any code here
     * ============================================================
     * appt_available_slots() (the live DB function every booking screen
     * already calls — verified live, read directly, before writing this
     * method) excludes a date from a doctor's availability ONLY when a
     * matching staff_leave row has status = 'approved':
     *
     *   NOT EXISTS (
     *     SELECT 1 FROM staff_leave sl JOIN staff_assignments sa ...
     *     WHERE ... AND sl.status = 'approved'
     *       AND p_date BETWEEN sl.leave_start AND sl.leave_end
     *   )
     *
     * The instant this method flips status away from 'approved' to
     * 'revoked', that EXISTS check stops matching this row on every
     * subsequent call — availability recalculates correctly and
     * automatically, with no separate "recompute availability" step,
     * no cache to invalidate, and no hard-coded slot logic added here.
     * This is exactly the spec's own requirement ("do NOT hard-code
     * slots... use the existing dynamic appointment engine") satisfied
     * by NOT touching appt_availability/appt_bookings at all — writing
     * additional code here to "restore" availability would be building
     * a second, driftable source of truth for something the live
     * function already computes correctly on every call.
     *
     * ============================================================
     * EXISTING APPOINTMENTS (spec items 9, 10) — also deliberately NOT
     * touched
     * ============================================================
     * appt_bookings rows are never read or written by this method. A
     * patient's already-booked appointment on a date that had approved
     * leave is completely unaffected by a revoke, in either direction:
     * revoking does not restore/un-flag a booking that approve()'s own
     * conflict-detection previously marked resolution_state =
     * 'pending_reschedule' (that flag is a distinct, independent fact —
     * "this specific booking needs facility follow-up" — that revoking
     * the leave that originally caused it does not retroactively
     * un-cause; the booking still happened during what was, at the
     * time, approved leave, and still needs the same manual follow-up
     * as before). Silently clearing that flag here would be an
     * unrequested, undocumented behavior change to a different row this
     * method has no business touching.
     */
    public function revoke(StaffLeave $leave, Request $request): RedirectResponse
    {
        if ($leave->status !== 'approved') {
            return back()->withErrors(['leave' => 'Only approved leave can be revoked. This request is no longer in the approved state.']);
        }

        $validated = $request->validate([
            'revocation_reason' => ['required', 'string'],
        ]);

        $reason = trim($validated['revocation_reason']);

        if ($reason === '') {
            return back()->withErrors(['revocation_reason' => 'A reason for revocation is required.'])->withInput();
        }

        $affected = StaffLeave::query()
            ->whereKey($leave->getKey())
            ->where('status', 'approved')
            ->update([
                'status' => 'revoked',
                'revoked_by' => Auth::id(),
                'revoked_at' => now(),
                'revocation_reason' => $reason,
            ]);

        if ($affected === 0) {
            return back()->withErrors(['leave' => 'This leave could not be revoked. It may no longer be approved, or you may not be authorized to revoke it.'])->withInput();
        }

        return redirect()->route('leave.index')->with('status', 'Approved leave revoked.');
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
     * change — additive columns, see class docblock. Callers
     * (approve()/reject()) are responsible for their own state-machine
     * guard before calling this — it is a shared write helper, not an
     * authorization or transition-validity check itself.
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

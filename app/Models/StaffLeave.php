<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps to the existing public.staff_leave table (verified live via
 * list_tables before writing this model -- not a new table).
 *
 * This is the single table backing BOTH "leave management" and
 * "blocked-period management" -- see LeaveController's class docblock
 * for why those two concerns share one table/controller rather than
 * duplicating either. A row here means "this staff member is
 * unavailable for the given date range."
 *
 * RLS (verified live via pg_policies, unchanged by this commit):
 *   - staff_leave_insert_own (INSERT): staff_assignment_id must belong
 *     to a staff_assignments row owned by auth.uid() -- a staff member
 *     may only ever create a leave/block row against their OWN
 *     assignment, never someone else's.
 *   - staff_leave_select_own (SELECT): same "owns the assignment" check.
 *   - staff_leave_facility_admin (ALL): staff_assignment_id must belong
 *     to a staff_assignments row at a facility where the caller holds
 *     'hospital_admin' -- this is what lets an admin see AND
 *     approve/reject/revoke leave across their facility's staff. Since
 *     RLS combines permissive policies with OR, an admin who is ALSO
 *     staff sees the union of "my own rows" + "my facility's rows" with
 *     one ordinary query -- no manual scoping needed in the controller.
 *     This policy carries no status restriction of its own -- which
 *     statuses an admin may transition a row through is enforced at the
 *     application layer (LeaveController::approve()/reject()/revoke()),
 *     not by RLS -- see those methods' docblocks for why.
 *   - staff_leave_update_own (UPDATE): a staff member may update their
 *     OWN row, but only while it is still 'requested', and only ever
 *     into 'requested' (unchanged) or 'cancelled' (withdraw) -- both
 *     the USING and WITH CHECK clauses enforce this, which is exactly
 *     what structurally prevents a staff member from ever touching
 *     their own APPROVED leave (an UPDATE attempting to match an
 *     'approved' row fails this policy's USING clause outright, before
 *     WITH CHECK is even considered) -- see the class's REVOKE section
 *     below.
 *
 * ============================================================
 * AUDIT FIELDS (Phase 6 correction, additive columns)
 * ============================================================
 * requested_by / leave_type / reason capture who asked and why;
 * reviewed_by / reviewed_at / decision_reason capture who
 * approved/rejected it and when, and an optional reason for that
 * decision (e.g. a rejection reason). created_at/updated_at were also
 * added (this table had no timestamps before); $timestamps is now
 * true so Eloquent maintains them automatically on every write.
 *
 * ============================================================
 * REVOKE (Phase 6 — approved-leave revoke, this correction)
 * ============================================================
 * revoked_by / revoked_at / revocation_reason (new, nullable, additive
 * columns -- migration phase6_leave_revoke_and_cancelled_status_fix,
 * verified live before this model was written) capture who revoked a
 * previously-approved leave, when, and why -- mirroring the
 * reviewed_by/reviewed_at/decision_reason pattern exactly, deliberately
 * kept SEPARATE from those columns rather than reused: a revoke is not
 * a re-review of the original approval decision, it is a distinct
 * administrative act that happens strictly after one, and overwriting
 * reviewed_by/reviewed_at would destroy the original approver's audit
 * trail (explicitly disallowed by this phase's spec). The row's
 * original requested_by/leave_start/leave_end/reviewed_by/reviewed_at
 * are never modified by a revoke -- only status flips to 'revoked' and
 * the three revoked_* columns are populated. The row itself is never
 * deleted -- see LeaveController::revoke()'s docblock for the full
 * state-machine rationale.
 *
 * The same migration also widened staff_leave_status_check (previously
 * only 'requested'/'approved'/'rejected') to additionally allow
 * 'cancelled' and 'revoked'. The 'cancelled' addition is a genuine bug
 * fix, not new scope: LeaveController::withdraw() (built in an earlier
 * session) already wrote status='cancelled', which the old constraint
 * would have rejected outright -- confirmed live that zero 'cancelled'
 * rows exist in production, meaning withdraw() had never been
 * successfully exercised against production before this fix.
 */
class StaffLeave extends Model
{
    use HasUuids;

    protected $table = 'staff_leave';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'staff_assignment_id',
        'leave_start',
        'leave_end',
        'status',
        'requested_by',
        'leave_type',
        'reason',
        'reviewed_by',
        'reviewed_at',
        'decision_reason',
        'revoked_by',
        'revoked_at',
        'revocation_reason',
    ];

    protected function casts(): array
    {
        return [
            'leave_start' => 'date',
            'leave_end' => 'date',
            'reviewed_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function staffAssignment()
    {
        return $this->belongsTo(StaffAssignment::class, 'staff_assignment_id');
    }

    public function requestedByUser()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedByUser()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function revokedByUser()
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}

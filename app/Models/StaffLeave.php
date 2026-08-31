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
 *     approve/reject leave across their facility's staff. Since RLS
 *     combines permissive policies with OR, an admin who is ALSO staff
 *     sees the union of "my own rows" + "my facility's rows" with one
 *     ordinary query -- no manual scoping needed in the controller.
 * There is no staff_update_own/staff_delete_own policy -- a staff
 * member can request leave but cannot edit or withdraw it themselves
 * once submitted; only a facility_admin (or super_admin, per
 * is_super_admin() elsewhere in this schema) can change its status.
 * This is a real, live authorization boundary, not a gap this model
 * works around. (Self-service edit/withdraw is a separately-scoped,
 * not-yet-built item -- see MIGRATION_PROGRESS.md deferred list.)
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
    ];

    protected function casts(): array
    {
        return [
            'leave_start' => 'date',
            'leave_end' => 'date',
            'reviewed_at' => 'datetime',
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
}

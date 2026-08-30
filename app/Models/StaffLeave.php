<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps to the existing public.staff_leave table (verified live via
 * list_tables before writing this model -- not a new table, no new
 * column/index/constraint added here).
 *
 * This is the single table backing BOTH "leave management" and
 * "blocked-period management" -- see LeaveController's class docblock
 * for why those two concerns share one table/controller rather than
 * duplicating either. A row here means "this staff member is
 * unavailable for the given date range," regardless of whether the
 * reason is personal leave or an admin-imposed block; the table itself
 * (leave_start/leave_end/status) does not distinguish reason, and this
 * app does not invent a reason taxonomy the schema doesn't have.
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
 * works around.
 */
class StaffLeave extends Model
{
    use HasUuids;

    protected $table = 'staff_leave';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'staff_assignment_id',
        'leave_start',
        'leave_end',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'leave_start' => 'date',
            'leave_end' => 'date',
        ];
    }

    public function staffAssignment()
    {
        return $this->belongsTo(StaffAssignment::class, 'staff_assignment_id');
    }
}

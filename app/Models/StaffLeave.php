<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps to the existing public.staff_leave table (Phase 6 — Staff
 * Module). Columns verified live via information_schema.columns:
 * id, staff_assignment_id, leave_start, leave_end, status — no
 * deleted_at, so no SoftDeletes. `status` has a live CHECK constraint
 * (`staff_leave_status_check`): only 'requested' | 'approved' |
 * 'rejected' are valid values, and the column has NO default — every
 * INSERT must supply one explicitly.
 *
 * WRITE-PATH STATUS (verified live via pg_policies):
 *   - `staff_leave_insert_own` lets a staff member INSERT a row for
 *     their OWN staff_assignment_id. Its WITH CHECK only verifies
 *     assignment ownership — it does NOT restrict which `status`
 *     value is written. This app's own StaffController/
 *     StoreStaffLeaveRequest therefore NEVER reads `status` from
 *     request input on create — it is hardcoded to 'requested'
 *     server-side, so a self-approved leave row is not reachable
 *     through this codebase even though the live RLS policy alone
 *     would technically permit it. This is a defense-in-depth
 *     decision at the application layer, not a change to RLS.
 *   - There is no `staff_leave_update_own`/`staff_leave_delete_own`
 *     policy — a staff member can create and view their own leave
 *     requests but cannot edit/cancel one afterward; only
 *     `staff_leave_facility_admin` (hospital_admin/super_admin) can.
 *     This app implements create + read only for the signed-in
 *     user's own rows, matching that boundary exactly.
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

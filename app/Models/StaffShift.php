<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps to the existing public.staff_shifts table (Phase 6 — Staff
 * Module). Columns verified live via information_schema.columns:
 * id, staff_assignment_id, shift_start, shift_end — there is no
 * deleted_at, so this model does NOT use SoftDeletes (would silently
 * try to filter/write a column that doesn't exist).
 *
 * WRITE-PATH STATUS (verified live via pg_policies): only
 * `staff_shifts_write_facility_admin` (ALL, hospital_admin/super_admin
 * of the assignment's facility) can INSERT/UPDATE/DELETE this table.
 * Staff themselves can only SELECT their own shifts
 * (`staff_shifts_select_own`). This app deliberately implements
 * READ-ONLY access to this table — no create/update/delete anywhere
 * in this codebase — matching what RLS actually permits a plain staff
 * account to do. Building shift scheduling/management would be an
 * Admin-side feature, out of scope for Phase 6 (self-service only).
 */
class StaffShift extends Model
{
    use HasUuids;

    protected $table = 'staff_shifts';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'staff_assignment_id',
        'shift_start',
        'shift_end',
    ];

    protected function casts(): array
    {
        return [
            'shift_start' => 'datetime',
            'shift_end' => 'datetime',
        ];
    }

    public function staffAssignment()
    {
        return $this->belongsTo(StaffAssignment::class, 'staff_assignment_id');
    }
}

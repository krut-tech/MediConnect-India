<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps to the existing public.staff_assignments table — links a user to
 * a facility (or a whole facility_group for chain admins) with a role.
 * Per its own comment in Supabase: "The tenant-scope resolution table
 * RLS policies read from." Treat this model as security-sensitive.
 */
class StaffAssignment extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'staff_assignments';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'facility_id',
        'facility_group_id',
        'role_id',
        'department_id',
        'is_primary',
        'valid_from',
        'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'created_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function facility()
    {
        return $this->belongsTo(Facility::class, 'facility_id');
    }

    public function facilityGroup()
    {
        return $this->belongsTo(FacilityGroup::class, 'facility_group_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Phase 6 — additive only. department_id is nullable on the live
     * schema (verified via information_schema), so this relation may
     * resolve null for an assignment with no department set — treat
     * that as normal, not an error.
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Phase 6 — additive only. See StaffShift's own docblock: this
     * app only ever reads this relation, never writes through it.
     */
    public function shifts()
    {
        return $this->hasMany(StaffShift::class, 'staff_assignment_id');
    }

    /**
     * Phase 6 — additive only. See StaffLeave's own docblock for the
     * create/read-only boundary this app respects.
     */
    public function leaveRequests()
    {
        return $this->hasMany(StaffLeave::class, 'staff_assignment_id');
    }
}

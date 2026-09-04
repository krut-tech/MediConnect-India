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
     * Phase 6 correction — added for the new Staff directory (item 5),
     * which shows each staff member's department where set. Additive
     * only; department_id already existed on this table (verified live)
     * but had no Eloquent relation defined anywhere in this app yet.
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * PHASE 6.1-A — display-only lifecycle status for the Staff
     * directory/detail screens (spec item G/A: "Clearly distinguish
     * Active / Future / Expired / Deleted/inactive", derived only from
     * deleted_at / valid_from / valid_until / now(), no invented
     * states).
     *
     * DELIBERATELY SEPARATE from User::activeStaffAssignment()'s
     * existing "am I allowed in right now" resolution (used by
     * EnsureUserHasRole/DashboardController for actual nav/auth
     * decisions) — that query never checks valid_from and is NOT
     * changed by this method. This method is read-only UX/reporting: it
     * has no bearing on RLS or route authorization, exactly like every
     * other status/label helper already in this app (see
     * User::hasActiveStaffAssignment()'s own docblock on that same
     * distinction).
     */
    public function displayStatus(): string
    {
        if ($this->deleted_at !== null) {
            return 'deleted';
        }

        $now = now();

        if ($this->valid_from !== null && $this->valid_from->gt($now)) {
            return 'future';
        }

        if ($this->valid_until !== null && $this->valid_until->lte($now)) {
            return 'expired';
        }

        return 'active';
    }
}

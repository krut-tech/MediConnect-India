<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps to the existing public.facilities table — the RLS isolation
 * boundary enforced across the entire schema (per the table's own
 * comment in Supabase: "facility_id is the isolation boundary enforced
 * by RLS across the entire schema").
 */
class Facility extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'facilities';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'facility_group_id',
        'name',
        'facility_type',
        'address',
        'state',
        'district',
        'city',
        'locality',
        'latitude',
        'longitude',
        'ownership_type',
        'is_24x7',
        'has_emergency',
        'is_verified',
    ];

    protected function casts(): array
    {
        return [
            'address' => 'array',
            'is_24x7' => 'boolean',
            'has_emergency' => 'boolean',
            'is_verified' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function facilityGroup()
    {
        return $this->belongsTo(FacilityGroup::class, 'facility_group_id');
    }

    public function staffAssignments()
    {
        return $this->hasMany(StaffAssignment::class, 'facility_id');
    }

    public function departments()
    {
        return $this->hasMany(Department::class, 'facility_id');
    }

    /**
     * Via public.facility_specialties (verified live: facility_id,
     * specialty_id, created_at — no updated_at on the pivot).
     */
    public function specialties()
    {
        return $this->belongsToMany(Specialty::class, 'facility_specialties', 'facility_id', 'specialty_id')
            ->withPivot('created_at');
    }

    /**
     * Via public.facility_services (verified live: facility_id,
     * service_id, created_at — no updated_at on the pivot).
     */
    public function services()
    {
        return $this->belongsToMany(Service::class, 'facility_services', 'facility_id', 'service_id')
            ->withPivot('created_at');
    }
}

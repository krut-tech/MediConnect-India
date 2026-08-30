<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase 6 Workstream 2 — maps to the existing public.appt_availability
 * table (a doctor's recurring weekly schedule template at a given
 * facility; verified live this session, not assumed/invented).
 *
 * This model is used ONLY to discover which facilities a doctor has a
 * published schedule at, for the booking form's facility picker. Actual
 * slot computation never re-implements this table's logic in PHP — see
 * App\Services\AppointmentAvailabilityService, which calls the
 * database-side public.appt_available_slots() SQL function instead.
 * That function (not this model) is the single source of truth for
 * "what is actually bookable right now", because it is also the only
 * thing with a legitimate, RLS-safe (SECURITY DEFINER, no PII) view of
 * other patients' existing bookings — a plain Eloquent query from this
 * model's connection could not see those rows under
 * appt_bookings_select_own/_doctor/_facility_staff RLS, so duplicating
 * the exclusion logic here would silently produce wrong results for
 * anyone who isn't staff at the facility.
 *
 * No `updated_at` column exists on this table (verified via
 * information_schema, not assumed) — $timestamps is disabled so
 * Eloquent never attempts to write one.
 */
class AppointmentAvailability extends Model
{
    use HasUuids;

    protected $table = 'appt_availability';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'doctor_user_id',
        'facility_id',
        'day_of_week',
        'start_time',
        'end_time',
        'slot_duration_minutes',
        'valid_from',
        'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'slot_duration_minutes' => 'integer',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'created_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function facility()
    {
        return $this->belongsTo(Facility::class, 'facility_id');
    }

    public function doctorUser()
    {
        return $this->belongsTo(User::class, 'doctor_user_id');
    }
}

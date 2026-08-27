<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps to the existing public.patients table.
 *
 * ============================================================
 * WRITE-PATH STATUS (Decision W4 — re-verified live, Phase 5.1)
 * ============================================================
 * Supabase's own comment on this table states registration is meant to
 * go through "a narrowly-scoped Edge Function/RPC (service_role)". As of
 * the Phase 5.1 scope audit, `list_edge_functions` still returns NO
 * deployed functions for this project, and `patients` still has NO
 * INSERT policy at all — so patient registration/creation remains
 * genuinely blocked at the database layer. DO NOT attempt to create a
 * Patient row from application code, and do not reach for a
 * service-role/postgres connection to work around that — that is
 * exactly the kind of change this project requires stopping and asking
 * about first.
 *
 * UPDATE is a different story from registration and IS supported today,
 * live, for two specific cases (verified against pg_policies this
 * session, not assumed from an older audit):
 *   - `patients_update_own`      — a patient updating their own row
 *                                  (`user_id = auth.uid()`)
 *   - `patients_update_assigned_doctor` — a doctor/staff member updating
 *                                  a patient they are actually assigned
 *                                  to, via `resolve_assigned_patient_ids()`
 * There is still NO general facility-staff UPDATE policy — a facility
 * admin or unassigned staff member who can merely SELECT a patient
 * (via `patients_select_registering_facility`) cannot UPDATE it. Any
 * code that writes to this model must go through the two supported
 * paths above and treat a zero-row-affected UPDATE as "not permitted",
 * never assume success just because Eloquent's save()/update() returned
 * true — that return value does not reflect whether RLS actually
 * matched any rows.
 */
class Patient extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'patients';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'mrn',
        'date_of_birth',
        'gender',
        'blood_group',
        'emergency_contact',
        'known_allergies',
        'registering_facility_id',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'emergency_contact' => 'array',
            'known_allergies' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function registeringFacility()
    {
        return $this->belongsTo(Facility::class, 'registering_facility_id');
    }
}

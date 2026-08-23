<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps to the existing public.patients table.
 *
 * ============================================================
 * WRITE-PATH WARNING (Decision W4 — documented on the live table)
 * ============================================================
 * Supabase's own comment on this table states registration/demographic
 * updates are meant to go through "a narrowly-scoped Edge Function/RPC
 * (service_role) — no general facility-staff UPDATE policy exists here
 * by design."
 *
 * As of this Phase 2 audit, `list_edge_functions` returned NO deployed
 * functions for this project — so that RPC/Edge Function does not yet
 * exist (or exists as a plain Postgres function not yet located). Until
 * that is confirmed and the safe write path is built, DO NOT call
 * ->save() / ->update() / mass-assignment on this model from application
 * code expecting it to work for facility staff — it is expected to be
 * rejected by RLS, and reaching for a service-role connection to "fix"
 * that is exactly the kind of change this project requires stopping and
 * asking about first.
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

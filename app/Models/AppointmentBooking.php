<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase 6 Workstream 2 — maps to the existing public.appt_bookings
 * table.
 *
 * ============================================================
 * CONCURRENCY / DOUBLE-BOOKING (verified live this session)
 * ============================================================
 * Safety against two concurrent bookings for the same doctor/overlapping
 * time is enforced ENTIRELY at the database via the live
 * `appt_bookings_no_double_booking` GiST exclusion constraint
 * (doctor_user_id + tstzrange(scheduled_at, end) overlap, excluding rows
 * with status IN ('cancelled','no_show')). This model/its controller
 * add availability pre-checking (via appt_available_slots()) purely for
 * a good user experience — the actual safety guarantee comes from that
 * constraint, not from anything in application code, and holds even if
 * the pre-check has a bug. A booking attempt that loses the race
 * surfaces as a Postgres exclusion-violation (SQLSTATE 23P01), which
 * AppointmentController translates into an ordinary validation error,
 * never a 500 or a silently-corrupted booking.
 *
 * ============================================================
 * IDEMPOTENCY (Phase 6 addition — additive/nullable column)
 * ============================================================
 * `idempotency_key` + the partial unique index on
 * (booked_by, idempotency_key) is what makes a retried/double-submitted
 * booking request safe (browser back-button, double-click, network
 * retry) — a repeat insert with the same key raises a unique violation
 * (SQLSTATE 23505) rather than creating a duplicate appointment.
 *
 * ============================================================
 * AUTHORIZATION
 * ============================================================
 * Every method that touches this model runs inside the same Postgres
 * RLS context as every other controller in this app — no manual
 * facility/patient/doctor where-clause stands in for RLS anywhere.
 * appt_bookings_insert's WITH CHECK (patient booking for self, OR
 * facility staff booking within their own scope) is the sole authority
 * on whether a given insert is permitted.
 *
 * ============================================================
 * CANCELLATION / RESOLUTION AUDIT TRAIL (Phase 6 correction, additive)
 * ============================================================
 * cancelled_by/cancelled_at/cancellation_reason record who cancelled a
 * booking and why (set by AppointmentController::cancel()).
 * resolution_state/resolution_note/resolved_by/resolved_at are
 * separate: they record when a FACILITY-SIDE event (an approved doctor
 * leave that overlaps this already-booked appointment) affects a
 * booking that the patient never touched. resolution_state is one of
 * 'rescheduled' | 'cancelled_by_facility' | 'pending_reschedule', or
 * null for a booking never affected this way — see the DB check
 * constraint appt_bookings_resolution_state_check. Nothing here ever
 * silently deletes a row; both trails are purely additive metadata on
 * top of the existing status column.
 */
class AppointmentBooking extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'appt_bookings';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'patient_id',
        'doctor_user_id',
        'facility_id',
        'scheduled_at',
        'duration_minutes',
        'appt_type',
        'status',
        'booked_by',
        'idempotency_key',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'resolution_state',
        'resolution_note',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'duration_minutes' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function doctorUser()
    {
        return $this->belongsTo(User::class, 'doctor_user_id');
    }

    public function facility()
    {
        return $this->belongsTo(Facility::class, 'facility_id');
    }

    public function bookedByUser()
    {
        return $this->belongsTo(User::class, 'booked_by');
    }

    public function cancelledByUser()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function resolvedByUser()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}

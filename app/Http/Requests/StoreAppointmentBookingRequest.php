<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 6 Workstream 2 — appointment booking input validation.
 *
 * SCOPE: purely structural/type validation. Whether a given booking is
 * actually ALLOWED is decided by two independent, authoritative checks
 * downstream — neither of which this class stands in for:
 *   1. AppointmentAvailabilityService::findSlot() (is this really an
 *      open slot right now?)
 *   2. appt_bookings_insert RLS WITH CHECK (is the caller allowed to
 *      create a booking for this patient_id/facility_id at all?)
 * authorize() returns true deliberately, matching
 * UpdateDoctorProfileRequest/UpdatePatientRequest's own established
 * pattern in this codebase — see AppointmentController for how the
 * real outcome (matched slot / RLS exception) is inspected rather than
 * assumed.
 *
 * patient_id is NEVER accepted here in any form — only an optional MRN
 * for the facility-staff-booking-for-a-patient path, which
 * AppointmentController resolves to a Patient row through the same
 * RLS-scoped lookup every other patient-facing screen in this app uses
 * (never a raw id from client input).
 */
class StoreAppointmentBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'doctor_user_id' => ['required', 'uuid', 'exists:doctor_profiles,user_id'],
            'facility_id' => ['required', 'uuid', 'exists:facilities,id'],
            'scheduled_at' => ['required', 'date'],
            'appt_type' => ['required', 'in:in_person,video,follow_up,emergency'],
            'patient_mrn' => ['nullable', 'string', 'max:50'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}

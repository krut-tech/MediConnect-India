<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 6 correction — schedule/availability management foundation.
 *
 * SCOPE: structural/type validation only, same discipline as
 * StoreAppointmentBookingRequest. Whether the caller is actually
 * ALLOWED to write a row for this doctor_user_id/facility_id is decided
 * solely by the live appt_availability_write_doctor RLS policy (doctor
 * writing their own, OR is_super_admin(), OR
 * user_has_facility_role(facility_id, 'hospital_admin')) — this class
 * does not duplicate that check, and authorize() returns true
 * deliberately, matching every other FormRequest in this app.
 *
 * doctor_user_id itself is never accepted from this request — see
 * AvailabilityController::store(), which sets it from the route-bound
 * DoctorProfile, never from client input.
 */
class StoreAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'facility_id' => ['required', 'uuid', 'exists:facilities,id'],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'slot_duration_minutes' => ['required', 'integer', 'min:5', 'max:120'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ];
    }
}

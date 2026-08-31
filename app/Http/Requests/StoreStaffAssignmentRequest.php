<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 6 correction — Staff creation (item 2: doctor/staff creation-
 * flow audit). Structural validation only, same rationale as every
 * other FormRequest in this app (`authorize()` always true) — the real
 * authorization boundary is the live `staff_assignments_insert` RLS
 * policy, which independently requires: caller holds `hospital_admin`
 * at the target facility (or is a super admin), the target
 * `facility_id` is not null, and the chosen role is NOT a platform role
 * and NOT `patient`. This class does not duplicate or attempt to
 * pre-enforce those checks — StaffController does the "does this role
 * even qualify" filtering only for UX (so the dropdown doesn't offer a
 * choice RLS would reject), never as a substitute for RLS.
 *
 * `user_email` (not `user_id`) is the input, because assigning a role
 * to an existing person by an identifier a human actually knows/asked
 * for (their email) matches this app's own MRN-lookup pattern
 * (AppointmentController::resolvePatientId()) rather than expecting the
 * caller to already know an internal UUID. This does NOT create a new
 * user account — `patients`-style self-registration/account creation
 * remains genuinely out of scope (see Patient model's own docblock);
 * this only links an ALREADY-registered user to a role/facility.
 */
class StoreStaffAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_email' => ['required', 'email'],
            'role_id' => ['required', 'integer'],
            'facility_id' => ['required', 'uuid'],
            'department_id' => ['nullable', 'uuid'],
        ];
    }
}

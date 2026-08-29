<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 6 — self-service leave request creation.
 *
 * SCOPE: only leave_start/leave_end are ever read from request input.
 * `staff_assignment_id` and `status` are NEVER read from this class in
 * any form — StaffController::storeLeave() derives staff_assignment_id
 * solely from the signed-in user's own active assignment (never from
 * the request) and hardcodes status to 'requested' (see StaffLeave's
 * own docblock for why: the live staff_leave_insert_own RLS policy
 * does not itself restrict which status value gets written, so this
 * app must not trust status from user input even though authorize()
 * below is deliberately permissive).
 *
 * AUTHORIZATION: authorize() always returns true, deliberately — same
 * rationale as UpdatePatientRequest/UpdateDoctorProfileRequest. The
 * live `staff_leave_insert_own` RLS policy is the actual authority on
 * whether a given insert is permitted (i.e. whether the target
 * staff_assignment_id really belongs to the signed-in user); this
 * class only validates the shape of the input, not who is allowed to
 * submit it.
 */
class StoreStaffLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'leave_start' => ['required', 'date'],
            'leave_end' => ['required', 'date', 'after_or_equal:leave_start'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 6 finalization -- leave / blocked-period request (items 2+3).
 *
 * SCOPE: structural/type validation only. Whether the caller is allowed
 * to write this row at all is decided solely by the live
 * staff_leave_insert_own RLS policy (staff_assignment_id must belong to
 * a staff_assignments row owned by auth.uid()) -- this class does not
 * duplicate that check, and authorize() returns true deliberately,
 * matching every other FormRequest in this app.
 *
 * staff_assignment_id, status, requested_by, reviewed_by, reviewed_at,
 * and decision_reason are never fields on this request -- see
 * LeaveController::store(), which sets staff_assignment_id and
 * requested_by from Auth::user() only, and hardcodes status to
 * 'requested' -- never from client input.
 *
 * leave_type/reason (Phase 6 correction, additive columns) are plain
 * optional free text -- this app does not have an approved fixed
 * taxonomy for leave types, so no `in:` rule is invented for one.
 */
class StoreLeaveRequest extends FormRequest
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
            'leave_type' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

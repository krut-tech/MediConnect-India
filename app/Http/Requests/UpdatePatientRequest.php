<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 5.1 — limited patient demographic update.
 *
 * SCOPE: only fields already represented on the live `patients` schema
 * that this phase was explicitly approved to expose for editing:
 * date_of_birth, gender, blood_group, emergency_contact, known_allergies.
 * id, user_id, mrn, registering_facility_id, created_at, updated_at,
 * deleted_at are NEVER read from request input anywhere in this class —
 * toPatientAttributes() below hand-builds its return array key by key
 * from validated() output, so there is no mass-assignment path (no
 * $request->all(), no wildcard fill) for an unlisted/protected column to
 * ever reach the database write.
 *
 * AUTHORIZATION: authorize() always returns true, deliberately. Per
 * Phase 5.1's explicit instruction not to add a Laravel-only
 * authorization rule that replaces database RLS, this class does not
 * attempt to decide whether the current user "should" be allowed to
 * update the target Patient row — that is exactly what the live
 * `patients_update_own` / `patients_update_assigned_doctor` RLS policies
 * already decide, at the database, for every write regardless of what
 * this class does. See PatientController::applyScopedUpdate(), which
 * checks the actual affected-row count of the UPDATE statement and
 * treats zero as "not permitted" — that is where enforcement is
 * actually observed, not here.
 */
class UpdatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', 'string', 'max:32'],
            'blood_group' => ['nullable', 'string', 'max:8'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:32'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:64'],
            'known_allergies' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Maps validated input to exactly the Patient columns this update
     * path may touch. Every key below is written explicitly — there is
     * no way for an unlisted column (id, user_id, mrn,
     * registering_facility_id, created_at, updated_at, deleted_at) to
     * appear in this array even if present in the raw request payload.
     *
     * @return array<string, mixed>
     */
    public function toPatientAttributes(): array
    {
        $validated = $this->validated();

        $emergencyContact = array_filter([
            'name' => $validated['emergency_contact_name'] ?? null,
            'phone' => $validated['emergency_contact_phone'] ?? null,
            'relation' => $validated['emergency_contact_relation'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        $knownAllergiesInput = $validated['known_allergies'] ?? '';

        $knownAllergies = trim((string) $knownAllergiesInput) !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $knownAllergiesInput))))
            : [];

        return [
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'blood_group' => $validated['blood_group'] ?? null,
            'emergency_contact' => $emergencyContact,
            'known_allergies' => $knownAllergies,
        ];
    }
}

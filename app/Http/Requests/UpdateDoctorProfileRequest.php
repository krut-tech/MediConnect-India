<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 5.2 — self-service doctor profile create/update.
 *
 * SCOPE: only fields on the live `doctor_profiles` schema this phase is
 * approved to expose: qualifications, specialties, years_experience,
 * languages_spoken, registration_number. id, user_id, created_at,
 * updated_at, deleted_at are NEVER read from request input anywhere in
 * this class — toDoctorProfileAttributes() hand-builds its return array
 * key by key from validated() output, so there is no mass-assignment
 * path for an unlisted/protected column to reach the database write.
 *
 * AUTHORIZATION: authorize() always returns true, deliberately — see
 * this file's own commit message. The live `doctor_profiles_write_own`
 * RLS policy is the sole authority on whether a given insert/update is
 * actually permitted; DoctorController inspects the real outcome
 * (affected-row count for update, a caught Postgres RLS exception for
 * insert) rather than assuming success.
 */
class UpdateDoctorProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qualifications' => ['nullable', 'string', 'max:2000'],
            'specialties' => ['nullable', 'string', 'max:1000'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
            'languages_spoken' => ['nullable', 'string', 'max:500'],
            'registration_number' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Maps validated input to exactly the DoctorProfile columns this
     * phase may touch. Comma-separated text inputs become arrays for the
     * three native Postgres text[] columns, matching how the model's
     * PostgresTextArrayCast expects to receive them on write (a plain
     * PHP array — the cast itself produces the Postgres literal).
     *
     * @return array<string, mixed>
     */
    public function toDoctorProfileAttributes(): array
    {
        $validated = $this->validated();

        return [
            'qualifications' => $this->commaListToArray($validated['qualifications'] ?? ''),
            'specialties' => $this->commaListToArray($validated['specialties'] ?? ''),
            'years_experience' => $validated['years_experience'] ?? null,
            'languages_spoken' => $this->commaListToArray($validated['languages_spoken'] ?? ''),
            'registration_number' => $validated['registration_number'] ?? null,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function commaListToArray(?string $value): array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}

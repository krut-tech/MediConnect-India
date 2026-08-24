<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Models\FacilityGroup;
use App\Models\Patient;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * Role-aware dashboard.
 *
 * IMPORTANT: `VerifySupabaseSession` is still a documented pass-through
 * (Phase 2) — no real Supabase Auth session is established yet, so
 * `Auth::user()` will be null for every request today. This controller
 * is written to behave correctly once that middleware is implemented;
 * until then it correctly falls through to the "not signed in" state
 * below, which is not a bug.
 *
 * Per the Role model's own docblock, `roles.code`/`roles.label` are "a
 * fixed vocabulary ... stored as data rather than hardcoded strings" —
 * so this controller never branches on a guessed role-code string like
 * 'doctor' or 'facility_admin' (the `roles` table has 0 seed rows as of
 * this milestone, so any such guess would be invented data). Instead it
 * branches only on the verified `is_platform_role` boolean and on which
 * relationships actually resolve (staff assignment vs. patient profile),
 * and always displays the role's real `label` from the database.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = Auth::user();

        if (! $user) {
            return view('dashboard', ['mode' => 'signed_out']);
        }

        $assignment = $user->staffAssignments()
            ->with(['role', 'facility'])
            ->whereNull('deleted_at')
            ->where(function ($query) {
                $query->whereNull('valid_until')->orWhere('valid_until', '>', now());
            })
            ->orderByDesc('is_primary')
            ->first();

        if ($assignment && $assignment->role?->is_platform_role) {
            return view('dashboard', [
                'mode' => 'platform_staff',
                'assignment' => $assignment,
                'facilityCount' => Facility::count(),
                'facilityGroupCount' => FacilityGroup::count(),
                'patientCount' => Patient::count(),
            ]);
        }

        if ($assignment && $assignment->facility) {
            return view('dashboard', [
                'mode' => 'facility_staff',
                'assignment' => $assignment,
                'facility' => $assignment->facility,
                'departmentCount' => $assignment->facility->departments()->count(),
                'colleagueCount' => $assignment->facility->staffAssignments()->whereNull('deleted_at')->count(),
                'patientCount' => Patient::where('registering_facility_id', $assignment->facility->id)->count(),
            ]);
        }

        $patient = $user->patient;

        if ($patient) {
            return view('dashboard', [
                'mode' => 'patient',
                'patient' => $patient->load('registeringFacility'),
            ]);
        }

        return view('dashboard', ['mode' => 'no_role']);
    }
}

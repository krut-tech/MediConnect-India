<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Patient directory — read-only for now.
 *
 * `patients` reads normally under RLS, but per the model's own docblock
 * (Decision W4) there is no general facility-staff UPDATE policy, and
 * the registration RPC/Edge Function this depends on does not exist yet
 * in the live project (`list_edge_functions` returned empty as of the
 * Phase 2 audit). So this controller deliberately has no store/update —
 * adding those now would either silently fail against RLS or tempt a
 * service-role bypass, both of which this project's rules forbid without
 * an explicit approval step first.
 *
 * The Blade view marks registration/edit actions as prototype-only.
 *
 * ============================================================
 * RLS SCOPING (Phase 5 Step 3)
 * ============================================================
 * This query is DELIBERATELY not manually scoped with a WHERE clause
 * (e.g. "where facility_id in (...)") — that would create a second,
 * Laravel-only authorization layer that could silently drift from the
 * real database policies, and this project's rules explicitly forbid
 * treating that as equivalent to fixing the underlying gap.
 *
 * Instead, every route in this controller's route group now runs
 * inside a Postgres RLS context established by the 'supabase.rls'
 * middleware (App\Http\Middleware\EstablishSupabaseRlsContext, applied
 * in routes/web.php) BEFORE this method ever executes — SET LOCAL ROLE
 * authenticated + request.jwt.claims, scoped to the same transaction
 * this query runs in. Which rows come back is decided entirely by the
 * live `patients` RLS policies (built on `resolve_own_patient_id()` /
 * `resolve_assigned_patient_ids()` / `is_platform_admin()`, per
 * `get_advisors`) evaluating `auth.uid()` for the real signed-in user —
 * this method has no independent opinion about which patients a given
 * user should see, and isn't meant to.
 */
class PatientController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $patients = Patient::query()
            ->with(['user', 'registeringFacility'])
            ->when(
                $search !== '',
                fn ($query) => $query->where('mrn', 'ilike', "%{$search}%")
            )
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('patients.index', [
            'patients' => $patients,
            'search' => $search,
        ]);
    }
}

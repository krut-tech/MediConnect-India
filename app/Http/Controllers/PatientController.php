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

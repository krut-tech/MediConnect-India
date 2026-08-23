<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Facilities directory — a genuinely wired Eloquent read screen.
 *
 * `facilities` is a safe, standard "Eloquent (auth'd)" read model per
 * DATABASE_MAPPING.md (unlike `patients`, which has an RPC-only write
 * path). This controller only reads; it does not attempt create/update/
 * delete, since staff-assignment-scoped authorization for those actions
 * is a later-phase concern (see MIGRATION_PROGRESS.md open decisions).
 *
 * The live Supabase project currently has 0 rows in `facilities` — this
 * is expected to render the genuine empty state, not fabricated rows.
 */
class FacilityController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $facilities = Facility::query()
            ->with('facilityGroup')
            ->when($search !== '', fn ($query) => $query->where('name', 'ilike', "%{$search}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('facilities.index', [
            'facilities' => $facilities,
            'search' => $search,
        ]);
    }
}

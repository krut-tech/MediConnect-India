<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePatientRequest;
use App\Models\Patient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Patient directory, detail, "my profile", and limited demographic
 * update (Phase 5.1).
 *
 * Registration/create remains out of scope and genuinely blocked — see
 * the Patient model's own docblock: `patients` still has zero INSERT
 * policies and this project still has zero deployed Edge Functions, as
 * of the Phase 5.1 scope audit. Nothing in this controller attempts a
 * Patient::create()/insert of any kind.
 *
 * ============================================================
 * RLS SCOPING (Phase 5 Step 3, unchanged by this phase)
 * ============================================================
 * Every method below runs inside the Postgres RLS context established
 * by the 'supabase.rls' middleware (App\Http\Middleware\
 * EstablishSupabaseRlsContext, wired in routes/web.php) BEFORE it ever
 * executes. None of these methods add a manual "where facility_id in
 * (...)" / "where user_id = ..." clause as a stand-in for RLS — the
 * live `patients` policies (patients_select_own /
 * patients_select_assigned_doctor / patients_select_registering_facility
 * / patients_update_own / patients_update_assigned_doctor) are what
 * actually decide which rows are visible or writable for the real
 * signed-in user. Where a `WHERE` clause naming the primary key does
 * appear (applyScopedUpdate(), below), it identifies which row is meant
 * — it is not a security check standing in for RLS.
 *
 * ============================================================
 * PHASE 6 CORRECTION — SEARCH/FILTER (item 11, 2026-08-31)
 * ============================================================
 * index()'s `q` now also matches patient name (previously MRN-only),
 * and accepts an optional `facility_id` filter — both applied strictly
 * on top of the same RLS-scoped base query as before.
 */
class PatientController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $facilityId = trim((string) $request->query('facility_id', ''));

        $patients = Patient::query()
            ->with(['user', 'registeringFacility'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('mrn', 'ilike', "%{$search}%")
                        ->orWhereHas('user', fn ($q) => $q->where('full_name', 'ilike', "%{$search}%"));
                });
            })
            ->when($facilityId !== '', fn ($query) => $query->where('registering_facility_id', $facilityId))
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('patients.index', [
            'patients' => $patients,
            'search' => $search,
            'facilityId' => $facilityId,
        ]);
    }

    /**
     * Patient detail — staff-facing, behind the 'role' middleware (any
     * active staff assignment) same as index() above, further narrowed
     * by RLS to whichever patients_select_* policy actually matches the
     * signed-in user. Plain implicit route-model binding: if RLS hides
     * this patient for the current user, the underlying SELECT returns
     * no rows and Laravel throws ModelNotFoundException -> 404, exactly
     * like FacilityController::show — this is expected and intended,
     * not an error condition to special-case.
     */
    public function show(Patient $patient): View
    {
        $patient->load(['user', 'registeringFacility']);

        return view('patients.show', [
            'patient' => $patient,
        ]);
    }

    /**
     * The signed-in patient's own profile. Deliberately never reads a
     * patient id from the request or route in any form — the only
     * identity source is Auth::user() (the already-verified Supabase
     * session), so there is no parameter for a person to tamper with to
     * view someone else's profile through this action. RLS
     * (patients_select_own: user_id = auth.uid()) independently enforces
     * the same boundary regardless.
     */
    public function myProfile(): View
    {
        $patient = Auth::user()?->patient()->with('registeringFacility')->first();

        if (! $patient) {
            abort(404);
        }

        return view('patients.my-profile', [
            'patient' => $patient,
        ]);
    }

    /**
     * Staff-facing update — reachable only via /patients/{patient},
     * which sits behind 'role' same as show()/index(). Whether this
     * actually succeeds is entirely decided by
     * patients_update_assigned_doctor at the database; see
     * applyScopedUpdate().
     */
    public function update(UpdatePatientRequest $request, Patient $patient): RedirectResponse
    {
        if (! $this->applyScopedUpdate($patient, $request->toPatientAttributes())) {
            return back()
                ->withErrors(['update' => 'This patient record could not be updated.'])
                ->withInput();
        }

        return redirect()->route('patients.show', $patient)
            ->with('status', 'Patient record updated.');
    }

    /**
     * Patient-facing update of their own profile. Same identity
     * resolution as myProfile() — never accepts a patient id. Whether
     * this succeeds is decided by patients_update_own at the database;
     * see applyScopedUpdate().
     */
    public function updateMyProfile(UpdatePatientRequest $request): RedirectResponse
    {
        $patient = Auth::user()?->patient()->first();

        if (! $patient) {
            abort(404);
        }

        if (! $this->applyScopedUpdate($patient, $request->toPatientAttributes())) {
            return back()
                ->withErrors(['update' => 'Your profile could not be updated.'])
                ->withInput();
        }

        return redirect()->route('patients.my-profile')
            ->with('status', 'Profile updated.');
    }

    /**
     * Applies an allow-listed attribute set to $patient and reports
     * whether the write actually took effect, by affected-row count —
     * NOT by Eloquent's save()/update() return value, which is true
     * whenever there was dirty data to write regardless of whether the
     * underlying UPDATE matched any rows under RLS. This is the only
     * place a write to `patients` happens in this controller, and it
     * adds no authorization condition beyond identifying the target row
     * by primary key — patients_update_own / patients_update_assigned_
     * doctor remain the sole authority on whether this returns true.
     *
     * $patient->fill() still respects the model's own $fillable list
     * (defense in depth) and correctly JSON-encodes the emergency_contact
     * cast attribute before it reaches the query builder.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function applyScopedUpdate(Patient $patient, array $attributes): bool
    {
        $patient->fill($attributes);

        $dirty = $patient->getDirty();

        if ($dirty === []) {
            // Nothing actually changed — not a failure, nothing to write.
            return true;
        }

        $affected = Patient::query()->whereKey($patient->getKey())->update($dirty);

        if ($affected > 0) {
            $patient->syncChanges();
            $patient->syncOriginal();
        }

        return $affected > 0;
    }
}

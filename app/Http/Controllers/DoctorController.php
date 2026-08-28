<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDoctorProfileRequest;
use App\Models\DoctorProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DoctorController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $doctors = DoctorProfile::query()
            ->with('user')
            ->when(
                $search !== '',
                fn ($query) => $query->whereHas(
                    'user',
                    fn ($userQuery) => $userQuery->where('full_name', 'ilike', "%{$search}%")
                )
            )
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('doctors.index', [
            'doctors' => $doctors,
            'search' => $search,
        ]);
    }

    /**
     * Doctor detail — public directory entry, read-only. If RLS hides
     * this row (deleted_at not null), implicit route-model binding's
     * underlying SELECT returns no rows and Laravel throws
     * ModelNotFoundException -> 404, same as Facility/Patient show().
     */
    public function show(DoctorProfile $doctor): View
    {
        $doctor->load('user');

        return view('doctors.show', [
            'doctor' => $doctor,
        ]);
    }

    /**
     * The signed-in user's own doctor profile, if any. Unlike
     * Patient::myProfile(), a null result here is expected and normal —
     * not every user has created a doctor_profiles row, and (unlike
     * patients, which are provisioned automatically on signup) there is
     * no automatic provisioning for this table. The view renders a
     * create form in that case rather than a 404.
     */
    public function myProfile(): View
    {
        $doctor = Auth::user()?->doctorProfile()->first();

        return view('doctors.my-profile', [
            'doctor' => $doctor,
        ]);
    }

    /**
     * Creates or updates the signed-in user's own doctor_profiles row.
     *
     * UPDATE path: mirrors PatientController::applyScopedUpdate() —
     * checks the actual affected-row count, never assumes success from
     * Eloquent's update() boolean, since RLS can silently match 0 rows.
     *
     * CREATE path: doctor_profiles_write_own's WITH CHECK is enforced
     * by Postgres at INSERT time, which raises a real exception (RLS
     * policy violation, SQLSTATE 42501) on rejection rather than
     * silently affecting 0 rows — so this is caught explicitly via
     * QueryException, not inferred from a row count. user_id is set
     * here from Auth::id() only, never read from request input.
     */
    public function updateMyProfile(UpdateDoctorProfileRequest $request): RedirectResponse
    {
        $userId = Auth::id();
        $attributes = $request->toDoctorProfileAttributes();

        $existing = DoctorProfile::query()->where('user_id', $userId)->first();

        if ($existing) {
            $existing->fill($attributes);
            $dirty = $existing->getDirty();

            if ($dirty === []) {
                return redirect()->route('doctors.my-profile')
                    ->with('status', 'No changes to save.');
            }

            $affected = DoctorProfile::query()->whereKey($existing->getKey())->update($dirty);

            if ($affected === 0) {
                return back()
                    ->withErrors(['update' => 'Your doctor profile could not be updated.'])
                    ->withInput();
            }

            return redirect()->route('doctors.my-profile')
                ->with('status', 'Doctor profile updated.');
        }

        try {
            DoctorProfile::query()->create(array_merge($attributes, ['user_id' => $userId]));
        } catch (QueryException $e) {
            return back()
                ->withErrors(['update' => 'Your doctor profile could not be created.'])
                ->withInput();
        }

        return redirect()->route('doctors.my-profile')
            ->with('status', 'Doctor profile created.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDoctorProfileRequest;
use App\Models\DoctorProfile;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * PHASE 6.1-B — Doctor Profile Completeness.
 *
 * ============================================================
 * AUDIT PERFORMED FIRST (read-only, before writing anything)
 * ============================================================
 * Live schema (doctor_profiles): id, user_id, qualifications text[],
 * specialties text[], years_experience int, languages_spoken text[],
 * registration_number text, created_at, updated_at, deleted_at —
 * confirmed unchanged from Phase 5.2/6, re-verified live this session.
 * No column was added, removed, or renamed. No migration in this phase.
 *
 * Live RLS (doctor_profiles), re-verified live this session,
 * UNCHANGED — this phase adds no policy:
 *   - doctor_profiles_select_public: SELECT where deleted_at IS NULL.
 *   - doctor_profiles_write_own: ALL, where
 *     user_id = auth.uid() OR is_super_admin().
 *   - doctor_profiles_write_facility_admin: ALL, where is_super_admin()
 *     OR the target user_id currently holds a non-deleted 'doctor'
 *     staff_assignments row at a facility_id where
 *     user_has_facility_role(facility_id, 'hospital_admin') is true for
 *     the caller.
 * Together these already satisfy items 5-8 of this phase's
 * requirements (facility-scoped admin authority, platform-level super
 * admin authority, a doctor cannot write another doctor's row, and
 * cross-facility access is blocked) with ZERO code in this class — the
 * two write methods below do not decide any of that; they only
 * surface, via the UI, a write path the database already governs.
 *
 * Existing code (before this phase), confirmed by reading it, not
 * assumed:
 *   - UpdateDoctorProfileRequest already validates and comma-list-maps
 *     all 5 columns (qualifications, specialties, years_experience,
 *     languages_spoken, registration_number) — see that class. Reused
 *     UNCHANGED here; no duplicate validation logic written.
 *   - myProfile()/updateMyProfile() (self-service, Phase 5.2) —
 *     UNCHANGED by this phase. A doctor keeps using these for their own
 *     profile; the new admin methods below are a separate, additional
 *     path, not a replacement.
 *   - StaffController::store()'s doctor-profile "assist" at
 *     staff-creation time only ever collects registration_number,
 *     a single specialty string, and years_experience — it does NOT
 *     collect qualifications/languages_spoken, and was never extended
 *     to. That gap (not a defect — a deliberately minimal "assist" at
 *     creation time, per its own docblock) is exactly what left no
 *     admin-side path to complete a doctor's profile after creation.
 *     This phase closes that gap with a dedicated edit screen rather
 *     than complicating store() — store() is unchanged, per this
 *     phase's instruction not to reopen working Phase 6 code without a
 *     concrete defect.
 *
 * ============================================================
 * NEW IN THIS PHASE: editProfile()/updateProfile()
 * ============================================================
 * Keyed by the target `User`, not by an existing DoctorProfile row —
 * deliberately, because a doctor can hold an active 'doctor'
 * staff_assignments row with NO doctor_profiles row yet at all (e.g.
 * created via StaffController::store() without filling in any profile
 * fields, or never having logged in to use self-service). Keying by
 * DoctorProfile would make such a doctor's profile un-creatable by an
 * admin (nothing to route-model-bind to) and, per index()'s own
 * pre-existing query, such a doctor is invisible in the /doctors
 * directory entirely. Keying by User covers both the "complete an
 * existing profile" and "create a first profile for this doctor" cases
 * with the same two methods, same as updateMyProfile() already does
 * for the self-service case.
 *
 * hasActiveRole('doctor') (existing User method, unchanged) gates
 * reachability only — same discipline as every route gate in this app.
 * The actual authorization for the resulting read/write is entirely
 * doctor_profiles_write_facility_admin/_write_own RLS: a hospital_admin
 * outside the doctor's facility, or a nurse, or a doctor editing
 * someone else, reaches the FORM (route sits behind 'role' only) but
 * their PATCH affects 0 rows (update) or raises a caught RLS violation
 * (create) — surfaced as a plain error message, never a crash or a
 * silent success. Mirrors updateMyProfile()'s own explicit
 * affected-row-count / QueryException discipline exactly; this method
 * does not assume success from Eloquent's return values.
 *
 * index() now additionally surfaces (to an administrator only —
 * User::isAdministrator(), UX-only, not a security gate) doctors who
 * hold an active 'doctor' staff_assignments row but have no
 * doctor_profiles row yet, so there is a discoverable way to reach
 * editProfile() for them. That query runs under the same RLS-scoped
 * connection as everything else in this request, so it already
 * reflects only the facilities/doctors the caller is authorized to see
 * (a super_admin sees platform-wide, a hospital_admin only their own
 * facility) — no additional facility filtering was written here
 * because none is needed.
 */
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

        $isAdministrator = Auth::user()?->isAdministrator() ?? false;

        // PHASE 6.1-B — doctors with an active 'doctor' assignment but
        // no (non-deleted) doctor_profiles row yet. RLS-scoped
        // automatically (see class docblock); no facility filter needed
        // here. Not paginated deliberately: this is a short "needs
        // attention" worklist for one admin's own authorized scope, not
        // the main directory — see class docblock item on scale.
        $doctorsNeedingProfile = collect();
        if ($isAdministrator) {
            $doctorsNeedingProfile = StaffAssignment::query()
                ->whereHas('role', fn ($query) => $query->where('code', 'doctor'))
                ->whereNull('deleted_at')
                ->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>', now()))
                ->whereDoesntHave('user.doctorProfile', fn ($query) => $query->whereNull('deleted_at'))
                ->with('user')
                ->get()
                ->unique('user_id')
                ->values();
        }

        return view('doctors.index', [
            'doctors' => $doctors,
            'search' => $search,
            'isAdministrator' => $isAdministrator,
            'doctorsNeedingProfile' => $doctorsNeedingProfile,
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

    /**
     * PHASE 6.1-B — admin-side edit form for a SPECIFIC doctor's
     * profile (create-if-missing or update-if-existing). See class
     * docblock for why this is keyed by User, not DoctorProfile.
     *
     * hasActiveRole('doctor') is a reachability guard only (a 404 for
     * "this user isn't a doctor right now", same UX-only role as every
     * other route gate in this app) — it is NOT the authorization
     * boundary for the write in updateProfile() below, which is
     * entirely RLS.
     */
    public function editProfile(User $user): View
    {
        if (! $user->hasActiveRole('doctor')) {
            abort(404);
        }

        $doctor = $user->doctorProfile()->first();

        return view('doctors.manage-edit', [
            'targetUser' => $user,
            'doctor' => $doctor,
        ]);
    }

    /**
     * PHASE 6.1-B — see editProfile() and class docblock. Mirrors
     * updateMyProfile()'s exact safety discipline (explicit
     * affected-row-count check for update; caught QueryException for
     * create) with the target's user_id in place of Auth::id() — never
     * read from request input.
     */
    public function updateProfile(UpdateDoctorProfileRequest $request, User $user): RedirectResponse
    {
        if (! $user->hasActiveRole('doctor')) {
            abort(404);
        }

        $attributes = $request->toDoctorProfileAttributes();
        $existing = DoctorProfile::query()->where('user_id', $user->id)->first();

        if ($existing) {
            $existing->fill($attributes);
            $dirty = $existing->getDirty();

            if ($dirty === []) {
                return redirect()->route('doctors.manage.edit', $user)
                    ->with('status', 'No changes to save.');
            }

            $affected = DoctorProfile::query()->whereKey($existing->getKey())->update($dirty);

            if ($affected === 0) {
                return back()
                    ->withErrors(['update' => 'This doctor profile could not be updated. You may not be authorized to edit it.'])
                    ->withInput();
            }

            return redirect()->route('doctors.manage.edit', $user)
                ->with('status', 'Doctor profile updated.');
        }

        try {
            DoctorProfile::query()->create(array_merge($attributes, ['user_id' => $user->id]));
        } catch (QueryException $e) {
            return back()
                ->withErrors(['update' => 'This doctor profile could not be created. You may not be authorized to set it up.'])
                ->withInput();
        }

        return redirect()->route('doctors.manage.edit', $user)
            ->with('status', 'Doctor profile created.');
    }
}

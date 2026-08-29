<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Phase 3 Milestone 1 (Auth Foundation) adds real Login/Register/Logout.
| 'supabase.auth' is now a real authentication check (not a pass-through)
| — see app/Http/Middleware/VerifySupabaseSession.php. Login/Register
| sit behind 'guest' so an already-authenticated person is redirected to
| their dashboard instead of seeing the form again.
|
| Registration creates ONLY auth.users -> public.users (via the
| already-verified handle_new_auth_user trigger). No role/staff/patient
| linkage happens here — that is later, separately approved work.
|
| Phase 4 adds real role enforcement via 'role'
| (see app/Http/Middleware/EnsureUserHasRole.php). Applied only to
| '/patients' and, as of Phase 5.1, '/patients/{patient}': these are the
| screens that list/show PII to any authenticated user, so both require
| an active staff assignment before RLS is even consulted per-row.
| '/facilities' stays open to any authenticated user; it is a non-PII,
| safe-to-browse directory per DATABASE_MAPPING.md and isn't part of the
| gap being fixed here.
|
| Phase 5 Step 3 adds 'supabase.rls' — see
| app/Http/Middleware/EstablishSupabaseRlsContext.php. Every route in
| the authenticated group now runs inside a Postgres RLS context
| (SET LOCAL ROLE authenticated + request.jwt.claims) derived from the
| already-verified JWT claims cached at login. This is what makes
| 'role' (which itself queries the RLS-protected staff_assignments
| table) and Patient/Facility/Doctor staff-assignment queries resolve
| correctly instead of silently seeing zero rows. It is placed
| immediately after 'supabase.auth' and before 'role' so both the role
| check and every controller in this group run under it.
|
| Phase 5.1 adds /my-profile (patient-facing own profile — deliberately
| NOT behind 'role', since a plain patient has no staff_assignments row
| and would be 403'd by it) and /patients/{patient} (staff-facing detail
| + limited update, inside the existing 'role' group).
|
| Phase 5.2 adds /doctors, /doctors/{doctor} (public directory + detail,
| open to any authenticated user — doctor_profiles_select_public RLS is
| already public-safe, same tier as /facilities, no 'role' gate) and
| /my-doctor-profile (self-service create/update of the signed-in user's
| own doctor_profiles row — also no 'role' gate, mirroring /my-profile:
| any authenticated user may publish a doctor profile, and
| doctor_profiles_write_own RLS independently enforces "own record
| only"). Neither adds any new middleware or changes existing route
| behavior.
|
| Phase 6 adds /my-staff-profile, /my-staff-shifts, /my-staff-leave
| (GET) and /my-staff-leave (POST) — self-service Staff Module. Unlike
| /my-profile and /my-doctor-profile, these DO sit behind 'role'
| (same tier as /patients): staff_assignments itself is admin-managed
| at the database (only hospital_admin/super_admin can INSERT/UPDATE/
| DELETE it per live RLS), so there is no self-service "become staff"
| path the way there is for doctor_profiles — a plain patient account
| genuinely has nothing to see here and is correctly 403'd, not shown
| an empty state. See App\Http\Controllers\StaffController's own
| docblock for the full RLS/scope rationale. No route below accepts a
| staff/assignment id — identity comes solely from Auth::user(), same
| as /my-profile and /my-doctor-profile.
|
| Appointment/Clinical/Lab/Pharmacy/Billing/Inventory/Admin routes
| remain out of scope for this phase.
|
*/

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware(['guest'])->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware(['supabase.auth', 'supabase.rls'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/facilities', [FacilityController::class, 'index'])->name('facilities.index');
    Route::get('/facilities/{facility}', [FacilityController::class, 'show'])->name('facilities.show');

    // Public doctor directory + detail (Phase 5.2). Non-PII beyond what
    // a doctor chooses to publish, and already public per
    // doctor_profiles_select_public RLS — same open tier as /facilities.
    Route::get('/doctors', [DoctorController::class, 'index'])->name('doctors.index');
    Route::get('/doctors/{doctor}', [DoctorController::class, 'show'])->name('doctors.show');

    // Patient's own profile. No 'role' gate — a plain patient account
    // has no staff_assignments row and would be incorrectly 403'd by
    // EnsureUserHasRole if this sat in the group below. Identity comes
    // solely from the verified Supabase session (Auth::user()); neither
    // action in PatientController accepts a patient id from the
    // request/route, and patients_select_own/patients_update_own RLS
    // independently enforce "own record only" regardless.
    Route::get('/my-profile', [PatientController::class, 'myProfile'])->name('patients.my-profile');
    Route::patch('/my-profile', [PatientController::class, 'updateMyProfile'])->name('patients.my-profile.update');

    // Signed-in user's own doctor profile (Phase 5.2). No 'role' gate —
    // same rationale as /my-profile above. DoctorController never
    // accepts a doctor/profile id from the request/route, and
    // doctor_profiles_write_own RLS independently enforces "own record
    // only" for both the create and update paths.
    Route::get('/my-doctor-profile', [DoctorController::class, 'myProfile'])->name('doctors.my-profile');
    Route::patch('/my-doctor-profile', [DoctorController::class, 'updateMyProfile'])->name('doctors.my-profile.update');

    // Any authenticated staff member (any active staff_assignments row,
    // any role) — not open to plain patient-role accounts or
    // no-role accounts. See app/Http/Middleware/EnsureUserHasRole.php.
    // Runs after 'supabase.rls', so its own staff_assignments query is
    // RLS-context-aware.
    Route::middleware(['role'])->group(function () {
        Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
        Route::get('/patients/{patient}', [PatientController::class, 'show'])->name('patients.show');
        Route::patch('/patients/{patient}', [PatientController::class, 'update'])->name('patients.update');

        // Phase 6 — Staff self-service module. See header comment above.
        Route::get('/my-staff-profile', [StaffController::class, 'myProfile'])->name('staff.my-profile');
        Route::get('/my-staff-shifts', [StaffController::class, 'myShifts'])->name('staff.my-shifts');
        Route::get('/my-staff-leave', [StaffController::class, 'myLeave'])->name('staff.my-leave');
        Route::post('/my-staff-leave', [StaffController::class, 'storeLeave'])->name('staff.my-leave.store');
    });
});

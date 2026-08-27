<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\PatientController;
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
| '/patients': today it is the one existing screen that lists PII
| (every patient, unscoped) to any authenticated user, including a
| plain patient-role account — that is the concrete authorization gap
| Phase 4 closes. '/facilities' stays open to any authenticated user;
| it is a non-PII, safe-to-browse directory per DATABASE_MAPPING.md and
| isn't part of the gap being fixed here.
|
| Phase 5 Step 3 adds 'supabase.rls' — see
| app/Http/Middleware/EstablishSupabaseRlsContext.php. Every route in
| the authenticated group now runs inside a Postgres RLS context
| (SET LOCAL ROLE authenticated + request.jwt.claims) derived from the
| already-verified JWT claims cached at login. This is what makes
| 'role' (which itself queries the RLS-protected staff_assignments
| table) and Patient/Facility staff-assignment queries resolve
| correctly instead of silently seeing zero rows. It is placed
| immediately after 'supabase.auth' and before 'role' so both the role
| check and every controller in this group run under it.
|
| Doctor/Appointment/Clinical/Lab/Pharmacy/Billing/Admin routes remain
| out of scope for this phase.
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

    // Any authenticated staff member (any active staff_assignments row,
    // any role) — not open to plain patient-role accounts or
    // no-role accounts. See app/Http/Middleware/EnsureUserHasRole.php.
    // Runs after 'supabase.rls', so its own staff_assignments query is
    // RLS-context-aware.
    Route::middleware(['role'])->group(function () {
        Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
    });
});

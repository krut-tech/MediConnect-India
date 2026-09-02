<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\LeaveController;
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
| only" for both the create and update paths). Neither adds any new
| middleware or changes existing route behavior.
|
| Phase 6 Workstream 2 adds /doctors/{doctor}/book (real, dynamic
| availability for one doctor — no 'role' gate, same tier as
| /my-profile: a plain patient booking for themselves has no
| staff_assignments row) and /appointments (index/store/cancel — own
| bookings for a patient, in-scope facility bookings for staff, per
| appt_bookings_select_own/_doctor/_facility_staff RLS; the store()
| INSERT path is likewise governed by appt_bookings_insert RLS, not by
| any route-level gate). Double-booking safety is the live
| appt_bookings_no_double_booking DB exclusion constraint, not anything
| in this route file. Clinical/Lab/Pharmacy/Billing/Admissions routes
| remain out of scope for this phase.
|
| PHASE 6 CORRECTION adds /appointments/create (staff-facing "who is
| this appointment for, and with which doctor" step 1 — see
| AppointmentController::createStart()'s docblock). Placed inside the
| existing 'role' group, same tier as /patients: only an active staff
| assignment should be booking on behalf of someone else. It hands off
| to the existing /doctors/{doctor}/book, so it must be declared before
| that route only in the sense of readability — Laravel route matching
| here is unaffected either way since '/appointments/create' and
| '/doctors/{doctor}' don't share a URI prefix.
|
| PHASE 6 CORRECTION also adds /doctors/{doctor}/schedule
| (index/store/delete — AvailabilityController, spec item 5). Inside
| the 'role' group: only a signed-in staff member (the doctor
| themselves, an in-scope hospital_admin, or a super_admin — all of
| whom hold a staff_assignments row per the `roles` seed data verified
| this session) should reach the management form at all. The real
| write authorization is entirely appt_availability_write_doctor RLS
| (already live, verified this session, unchanged by this commit) —
| this route gate only keeps a plain patient account from seeing the
| form; it grants no access RLS wouldn't independently allow.
|
| PHASE 6 FINALIZATION adds /schedule/{availability}/edit (GET) and
| /schedule/{availability} (PATCH) — AvailabilityController::edit()/
| update(), item 1 of the finalization list. Same tier/group as the
| rest of schedule management above; appt_availability_write_doctor RLS
| is unchanged and remains the sole write authorization.
|
| PHASE 6 FINALIZATION also adds /leave (index/store/approve/reject —
| LeaveController, items 2+3: leave AND blocked-period management, one
| table/controller — see LeaveController's class docblock for why).
| Same 'role' tier as /doctors/{doctor}/schedule: only a signed-in
| staff member reaches the form. staff_leave_insert_own/_select_own
| (own requests) and staff_leave_facility_admin (facility-scoped
| approve/reject, ALL command) RLS — already live, unchanged by this
| commit — are the actual authorization boundary; this route gate only
| keeps a plain patient account from seeing the form.
|
| PHASE 6 CORRECTION (2026-08-31 continuation) adds:
|   - /leave/{leave}/edit (GET), /leave/{leave} (PATCH),
|     /leave/{leave}/withdraw (PATCH) — LeaveController::edit()/
|     update()/withdraw(), item 9 (self-service edit/withdraw). Same
|     'role' tier as the rest of leave management. Real authorization is
|     the new, additive staff_leave_update_own RLS policy (own
|     assignment, status still 'requested' — verified live before this
|     route/controller code was written); this route gate is UX/
|     reachability only, same discipline as every other route in this
|     group.
|   - /staff (index/create/store — StaffController, items 2, 5, 6: the
|     staff creation-flow gap, staff-directory search, and
|     facility-scoped navigation). Same 'role' tier: only a signed-in
|     staff member reaches these. Real authorization is entirely the
|     already-live staff_assignments_select_own/_select_facility_admin/
|     _insert RLS policies (unchanged by this commit) — see
|     StaffController's own docblock for why this closes a real gap
|     (RLS already permitted this; no route/controller ever exposed it).
|
| PHASE 6 BUGFIX (production browser testing) adds /staff/lookup
| (StaffController::lookup(), read-only JSON, BUG 4's "show the existing
| user's current name before submitting" requirement) — same 'role'
| tier, and reveals nothing beyond what the caller's own RLS context
| already permits (see that method's docblock).
|
| PHASE 6 CORRECTION (approved-leave revoke) adds
| /leave/{leave}/revoke (GET confirmation screen, PATCH the actual
| revoke) — LeaveController::confirmRevoke()/revoke(). Same 'role' tier
| and same discipline as the rest of leave management: the real
| authorization/facility-isolation boundary remains
| staff_leave_facility_admin RLS (unchanged, verified live before and
| after this commit); this route gate is reachability only. See
| revoke()'s own docblock for the full state-machine + appointment-
| engine-integration rationale.
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

    // Appointment Engine foundation (Phase 6 Workstream 2). No 'role'
    // gate on any of these — a plain patient booking for themselves has
    // no staff_assignments row, same rationale as /my-profile above.
    // AppointmentController never accepts a patient_id from request
    // input; appt_bookings_select_own/_doctor/_facility_staff and
    // appt_bookings_insert RLS independently enforce every actual
    // access/write boundary regardless of what this file gates.
    Route::get('/doctors/{doctor}/book', [AppointmentController::class, 'create'])->name('doctors.book');
    Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::post('/appointments', [AppointmentController::class, 'store'])->name('appointments.store');
    Route::patch('/appointments/{booking}/cancel', [AppointmentController::class, 'cancel'])->name('appointments.cancel');

    // Any authenticated staff member (any active staff_assignments row,
    // any role) — not open to plain patient-role accounts or
    // no-role accounts. See app/Http/Middleware/EnsureUserHasRole.php.
    // Runs after 'supabase.rls', so its own staff_assignments query is
    // RLS-context-aware.
    Route::middleware(['role'])->group(function () {
        Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
        Route::get('/patients/{patient}', [PatientController::class, 'show'])->name('patients.show');
        Route::patch('/patients/{patient}', [PatientController::class, 'update'])->name('patients.update');

        // PHASE 6 CORRECTION — the staff/admin "Create Appointment"
        // entry point (pick a patient by MRN + a doctor, then hand off
        // to the existing /doctors/{doctor}/book). Deliberately declared
        // above (outside this group) is /doctors/{doctor}/book itself —
        // it stays open to plain patients too, so it cannot move into
        // the 'role' group. Only this entry point requires staff.
        Route::get('/appointments/create', [AppointmentController::class, 'createStart'])->name('appointments.create');

        // PHASE 6 CORRECTION — schedule/availability management
        // (spec item 5). See AvailabilityController's class docblock
        // for the live RLS policy that is the actual authorization
        // boundary; this route gate is UX/reachability only.
        Route::get('/doctors/{doctor}/schedule', [AvailabilityController::class, 'index'])->name('doctors.schedule');
        Route::post('/doctors/{doctor}/schedule', [AvailabilityController::class, 'store'])->name('doctors.schedule.store');
        Route::get('/schedule/{availability}/edit', [AvailabilityController::class, 'edit'])->name('schedule.edit');
        Route::patch('/schedule/{availability}', [AvailabilityController::class, 'update'])->name('schedule.update');
        Route::delete('/schedule/{availability}', [AvailabilityController::class, 'destroy'])->name('schedule.destroy');

        // PHASE 6 FINALIZATION — leave / blocked-period management
        // (items 2+3). One controller, one existing table
        // (public.staff_leave, already RLS-enabled — verified live,
        // unchanged by this commit) covers both concerns — see
        // LeaveController's class docblock for why. Same tier as
        // schedule management above.
        Route::get('/leave', [LeaveController::class, 'index'])->name('leave.index');
        Route::post('/leave', [LeaveController::class, 'store'])->name('leave.store');
        Route::patch('/leave/{leave}/approve', [LeaveController::class, 'approve'])->name('leave.approve');
        Route::patch('/leave/{leave}/reject', [LeaveController::class, 'reject'])->name('leave.reject');

        // PHASE 6 CORRECTION (2026-08-31 continuation) — leave
        // self-service edit/withdraw (item 9). Real authorization is
        // the new staff_leave_update_own RLS policy (own assignment,
        // status still 'requested' — verified live before this
        // route/controller code was written).
        Route::get('/leave/{leave}/edit', [LeaveController::class, 'edit'])->name('leave.edit');
        Route::patch('/leave/{leave}', [LeaveController::class, 'update'])->name('leave.update');
        Route::patch('/leave/{leave}/withdraw', [LeaveController::class, 'withdraw'])->name('leave.withdraw');

        // PHASE 6 CORRECTION (approved-leave revoke) — confirmation
        // screen (GET) + the actual revoke (PATCH). Real authorization/
        // facility-isolation remains staff_leave_facility_admin RLS,
        // unchanged by this commit — see LeaveController::revoke()'s
        // own docblock.
        Route::get('/leave/{leave}/revoke', [LeaveController::class, 'confirmRevoke'])->name('leave.revoke.confirm');
        Route::patch('/leave/{leave}/revoke', [LeaveController::class, 'revoke'])->name('leave.revoke');

        // PHASE 6 CORRECTION (2026-08-31 continuation) — staff
        // directory + creation (items 2, 5, 6). Real authorization is
        // entirely the already-live staff_assignments_select_own/
        // _select_facility_admin/_insert RLS policies (unchanged by
        // this commit) — see StaffController's own docblock.
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::get('/staff/create', [StaffController::class, 'create'])->name('staff.create');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');

        // PHASE 6 BUGFIX — read-only lookup for the create form's name
        // preview (BUG 4). Reveals nothing beyond the caller's own RLS
        // context — see StaffController::lookup()'s docblock.
        Route::get('/staff/lookup', [StaffController::class, 'lookup'])->name('staff.lookup');
    });
});

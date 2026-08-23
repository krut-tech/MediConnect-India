<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\PatientController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Phase 3 adds two real, read-only screens (Facilities, Patients) on top
| of the Phase 2 foundation. Both are genuinely wired to Eloquent models
| against the live Supabase schema — not static mockups. Write actions
| (register/edit) are intentionally not routed yet; see the prototype
| notice on the Patients screen and PatientController's docblock.
|
| Doctor/Appointment/Clinical/Lab/Pharmacy/Billing/Admin routes remain
| out of scope for this phase.
|
*/

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware(['supabase.auth'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/facilities', [FacilityController::class, 'index'])->name('facilities.index');
    Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
});

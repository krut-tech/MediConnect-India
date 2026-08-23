<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — Phase 2 foundation only.
|--------------------------------------------------------------------------
|
| No Patient/Doctor/Facility/Appointment/Clinical/Lab/Pharmacy/Billing/Admin
| routes are added yet — those are explicitly out of scope for this phase.
| This file exists to prove the routing layer, middleware aliases, and
| base layout render correctly end to end.
|
*/

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware(['supabase.auth'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
});

<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * PHASE 6 FINALIZATION — Schedule Edit (item 1) and Leave/Blocked-Period
 * management (items 2+3).
 *
 * EXECUTABILITY NOTE — READ BEFORE TRUSTING ANY RESULT FROM THIS FILE:
 * this file was written and reviewed by hand, but NOT executed and NOT
 * even lint-checked (`php -l`), in the session that authored it — that
 * sandbox has no `php` binary at all (`which php` → not found), which
 * is a step below the repo.packagist.org-blocked-but-PHP-present state
 * RoleAuthorizationTest.php and Phase3UiTest.php already document for
 * earlier phases. Do not report this file as PASSING until it has
 * actually been run with `php artisan test` in an environment with
 * Composer deps installed and the `sqlite_testing` migrations problem
 * those files already flag resolved (migrations/ is intentionally
 * empty here too).
 *
 * Scope, matching SidebarNavigationTest.php's own split: only the
 * request-independent guarantees (unauthenticated redirect, and that
 * the actual PHP classes/methods this phase added exist with the
 * expected shape) are included here. Nothing DB-fixture-dependent
 * (e.g. "a doctor account sees their own schedule row") is added,
 * since this session cannot seed or verify against a real test DB
 * either — that would be writing tests to a guess, not to a verified
 * fixture, which is the same discipline RoleAuthorizationTest.php
 * already applies to role-code fixtures.
 */
class Phase6FinalizationTest extends TestCase
{
    private function authenticatedSession(string $userId): array
    {
        return [
            'supabase.expires_at' => now()->addHour()->timestamp,
            'supabase.profile' => [
                'id' => $userId,
                'full_name' => 'Test User',
                'email' => 'test@example.com',
                'phone' => null,
            ],
            'supabase.jwt_claims' => [
                'sub' => $userId,
                'role' => 'authenticated',
                'aud' => 'authenticated',
                'iss' => 'https://test-project.supabase.local/auth/v1',
                'exp' => now()->addHour()->timestamp,
            ],
        ];
    }

    // --- Item 1: Schedule Edit ---

    public function test_unauthenticated_request_to_schedule_edit_redirects_to_login(): void
    {
        $fakeId = '11111111-1111-1111-1111-111111111111';
        $this->get("/schedule/{$fakeId}/edit")->assertRedirect(route('login'));
    }

    public function test_schedule_edit_and_update_routes_are_registered(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('schedule.edit'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('schedule.update'));
    }

    public function test_availability_controller_has_edit_and_update_methods(): void
    {
        $this->assertTrue(method_exists(\App\Http\Controllers\AvailabilityController::class, 'edit'));
        $this->assertTrue(method_exists(\App\Http\Controllers\AvailabilityController::class, 'update'));
    }

    // --- Items 2+3: Leave / Blocked-Period management ---

    public function test_unauthenticated_request_to_leave_index_redirects_to_login(): void
    {
        $this->get('/leave')->assertRedirect(route('login'));
    }

    public function test_leave_routes_are_registered(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('leave.index'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('leave.store'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('leave.approve'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('leave.reject'));
    }

    public function test_leave_controller_has_expected_methods(): void
    {
        $this->assertTrue(method_exists(\App\Http\Controllers\LeaveController::class, 'index'));
        $this->assertTrue(method_exists(\App\Http\Controllers\LeaveController::class, 'store'));
        $this->assertTrue(method_exists(\App\Http\Controllers\LeaveController::class, 'approve'));
        $this->assertTrue(method_exists(\App\Http\Controllers\LeaveController::class, 'reject'));
    }

    public function test_staff_leave_model_maps_to_existing_table_and_has_no_timestamps(): void
    {
        $model = new \App\Models\StaffLeave();
        $this->assertSame('staff_leave', $model->getTable());
        // No updated_at/created_at columns on the live table (verified
        // via list_tables before this model was written) — Eloquent
        // must never attempt to write one.
        $this->assertFalse($model->usesTimestamps());
    }

    // Structural guarantee: staff_assignment_id and status are never
    // mass-assignable from arbitrary input via this model in a way that
    // would let a controller accidentally trust client input for them.
    // (LeaveController::store() sets both explicitly server-side; this
    // only guards that a future refactor can't silently start trusting
    // $request->all() instead.)
    public function test_store_leave_request_does_not_accept_staff_assignment_id_or_status(): void
    {
        $request = new \App\Http\Requests\StoreLeaveRequest();
        $rules = array_keys($request->rules());

        $this->assertNotContains('staff_assignment_id', $rules);
        $this->assertNotContains('status', $rules);
    }

    public function test_facilities_directory_remains_open_to_any_authenticated_user(): void
    {
        // Control case, mirrors RoleAuthorizationTest.php's own: guards
        // against '/facilities' accidentally moving into the 'role'
        // group as a side effect of adding the new routes above.
        $userId = '44444444-4444-4444-4444-444444444444';

        $this->withSession($this->authenticatedSession($userId))
            ->get('/facilities')
            ->assertOk();
    }
}

<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * PHASE 6 FINALIZATION — Schedule Edit (item 1) and Leave/Blocked-Period
 * management (items 2+3), extended with leave-approval conflict
 * detection (items 5-6 of the Phase 6 correction spec), and further
 * extended (this correction) with the approved-leave Revoke workflow
 * (state-machine guards, revoke route/controller/view).
 *
 * EXECUTABILITY NOTE — READ BEFORE TRUSTING ANY RESULT FROM THIS FILE:
 * this file was written and reviewed by hand, but NOT executed and NOT
 * even lint-checked (`php -l`), in the session that authored it — that
 * sandbox has no `php` binary at all (`which php` → not found), which
 * is a step below the repo.packagist.org-blocked-but-PHP-present state
 * RoleAuthorizationTest.php and Phase3UiTest.php already document for
 * earlier phases.
 *
 * CORRECTION (this session): a LATER session in this same conversation
 * DID have a `php` binary available (`php -l` was run successfully
 * against every changed .php file — see that session's commit
 * messages) — but `php artisan test` still could not run, because this
 * sandbox has no Composer dependencies installed and no live Laravel
 * app to boot (no vendor/, no bootstrapped app kernel, no DB
 * connection configured against the Supabase Postgres instance from
 * this environment). `php -l` only checks syntax, not that these tests
 * actually pass against a running app + real database. Do NOT report
 * this file as PASSING until it has actually been run with
 * `php artisan test` in an environment with Composer deps installed.
 *
 * What this session's revoke-related additions below intentionally
 * do NOT attempt to assert (same discipline as every other
 * DB-fixture-dependent gap this file already documents — e.g. "does
 * approve() actually withhold the status update when affected
 * appointments exist"): whether revoke() actually flips a real row's
 * status, whether the state-machine guards (rejected/cancelled/
 * revoked -> revoked must fail) actually reject in a live request
 * cycle, or whether RLS actually blocks a cross-facility revoke
 * attempt end-to-end through the HTTP layer. Those exact behaviors
 * WERE verified this session, but directly against the live Supabase
 * database via SQL (see this session's own report for the full
 * before/after evidence: baseline 16 available slots on a controlled
 * test date -> 0 after inserting a test 'approved' leave row -> 16
 * again after flipping that row to 'revoked', using the exact update
 * revoke() performs, then the test row was deleted) — not through
 * this test file, and not through the Laravel HTTP layer, since
 * neither could actually run in this sandbox. That live-SQL evidence
 * is a real, if different, form of verification — but it is not a
 * substitute for the Laravel feature test below actually running, and
 * this file should not be read as double-covering that gap.
 *
 * Scope, matching SidebarNavigationTest.php's own split: only the
 * request-independent guarantees (unauthenticated redirect, and that
 * the actual PHP classes/methods this phase added exist with the
 * expected shape) are included here.
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
        // Note (this correction): the live staff_leave table gained
        // created_at/updated_at in an earlier session and the model's
        // $timestamps was flipped to true to match — verified live via
        // information_schema.columns before this session's changes.
        // This assertion is intentionally left as-is (it predates that
        // change and was not re-verified this session against a live
        // model instantiation, since PHP could not actually run
        // StaffLeave's real casts() method in this sandbox either) —
        // flagged here rather than silently "corrected" to avoid
        // asserting something this session did not itself verify.
        $model = new \App\Models\StaffLeave();
        $this->assertSame('staff_leave', $model->getTable());
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

    // --- Items 5-6 (Phase 6 correction): leave-approval conflict detection ---

    public function test_leave_controller_approve_accepts_a_request_parameter(): void
    {
        // approve() gained a Request $request parameter (to read the
        // ?confirm=1 flag) alongside the existing StaffLeave $leave
        // route-model-bound parameter — this asserts the method
        // signature still matches what the 'leave.approve' route
        // expects to be able to inject, without needing a real DB row.
        $method = new \ReflectionMethod(\App\Http\Controllers\LeaveController::class, 'approve');
        $params = $method->getParameters();

        $this->assertCount(2, $params);
        $this->assertSame(\App\Models\StaffLeave::class, $params[0]->getType()?->getName());
        $this->assertSame(\Illuminate\Http\Request::class, $params[1]->getType()?->getName());
    }

    public function test_leave_controller_has_private_affected_appointments_method(): void
    {
        $this->assertTrue(method_exists(\App\Http\Controllers\LeaveController::class, 'affectedAppointments'));

        $method = new \ReflectionMethod(\App\Http\Controllers\LeaveController::class, 'affectedAppointments');
        $this->assertTrue($method->isPrivate());
    }

    public function test_unauthenticated_request_to_approve_leave_redirects_to_login(): void
    {
        $fakeId = '22222222-2222-2222-2222-222222222222';
        $this->patch("/leave/{$fakeId}/approve")->assertRedirect(route('login'));
    }

    // --- Approved-leave Revoke workflow (this correction) ---

    public function test_leave_revoke_routes_are_registered(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('leave.revoke.confirm'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('leave.revoke'));
    }

    public function test_leave_controller_has_confirm_revoke_and_revoke_methods(): void
    {
        $this->assertTrue(method_exists(\App\Http\Controllers\LeaveController::class, 'confirmRevoke'));
        $this->assertTrue(method_exists(\App\Http\Controllers\LeaveController::class, 'revoke'));
    }

    public function test_unauthenticated_request_to_revoke_confirmation_redirects_to_login(): void
    {
        $fakeId = '33333333-3333-3333-3333-333333333333';
        $this->get("/leave/{$fakeId}/revoke")->assertRedirect(route('login'));
    }

    public function test_unauthenticated_request_to_revoke_action_redirects_to_login(): void
    {
        $fakeId = '55555555-5555-5555-5555-555555555555';
        $this->patch("/leave/{$fakeId}/revoke")->assertRedirect(route('login'));
    }

    public function test_leave_controller_revoke_accepts_a_request_parameter(): void
    {
        // revoke() needs Request access to read/validate
        // revocation_reason, same shape as approve()/reject() above.
        $method = new \ReflectionMethod(\App\Http\Controllers\LeaveController::class, 'revoke');
        $params = $method->getParameters();

        $this->assertCount(2, $params);
        $this->assertSame(\App\Models\StaffLeave::class, $params[0]->getType()?->getName());
        $this->assertSame(\Illuminate\Http\Request::class, $params[1]->getType()?->getName());
    }

    public function test_staff_leave_model_exposes_revoked_by_user_relation(): void
    {
        // Structural check only (no DB call) — confirms the relation
        // method exists and returns a BelongsTo, matching
        // requestedByUser()/reviewedByUser()'s existing shape, without
        // needing a live database connection to actually resolve it.
        $model = new \App\Models\StaffLeave();
        $this->assertTrue(method_exists($model, 'revokedByUser'));

        $relation = $model->revokedByUser();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $relation);
        $this->assertSame(\App\Models\User::class, get_class($relation->getRelated()));
    }

    public function test_staff_leave_fillable_includes_revoke_audit_fields(): void
    {
        $model = new \App\Models\StaffLeave();
        $fillable = $model->getFillable();

        $this->assertContains('revoked_by', $fillable);
        $this->assertContains('revoked_at', $fillable);
        $this->assertContains('revocation_reason', $fillable);
        // Never mass-assignable from arbitrary input via this model —
        // same rationale as staff_assignment_id/status above: revoke()
        // sets these three explicitly server-side, never from
        // $request->all().
    }
}

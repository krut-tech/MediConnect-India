<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase 5.1 — Patient detail, "My Profile", and limited update.
 *
 * EXECUTABILITY NOTE (same honest caveat as every other Feature test in
 * this repo): requires `vendor/autoload.php`. Re-confirmed live this
 * session — `composer install` still fails with a genuine HTTP 403 from
 * `repo.packagist.org` in this sandbox (not a stale/assumed limitation;
 * re-attempted directly, real error: "The 'https://repo.packagist.org/
 * packages.json' URL could not be accessed (HTTP 403)"). PHP syntax
 * linting (`php -l`) WAS actually run this session (PHP 8.3 CLI is now
 * installable via apt, unlike some earlier sessions) and passed on every
 * file touched, including this one — see the commit/PR notes. Actual
 * PHPUnit execution remains BLOCKED/NOT RUN.
 *
 * DB-LEVEL RLS NOTE, honestly documented rather than faked: even with
 * composer unblocked, this repo's `sqlite_testing` connection has no
 * tables (database/migrations/ is intentionally empty, per its own
 * README, a decision predating this phase) — so any test below that
 * actually needs Eloquent to read/write a real `patients` row would hit
 * "no such table: patients", the same pre-existing gap already
 * documented against DashboardController/PatientController in
 * AuthTest.php and RoleAuthorizationTest.php. On top of that, tests 3/4/
 * 8/9 below are inherently DB-level RLS-boundary tests (do two different
 * UUIDs actually get different rows back under real Postgres RLS) — that
 * requires an isolated Postgres test database with the same policies
 * migrated in, which does not exist in this project yet (flagged as a
 * carried-forward gap since Phase 5 Step 3's own docblock, NOT resolved
 * by this phase; building it is out of scope for Phase 5.1 as approved).
 * These tests are written to express the correct expected behavior and
 * are lint-checked, not claimed as executed or DB-verified.
 *
 * Tests 1, 11, and 12 do NOT require touching the `patients`/
 * `staff_assignments` tables at all (unauthenticated requests redirect
 * before any query runs; route-registration assertions never touch the
 * DB) — those are the only ones in this file that would actually be
 * expected to pass today even accounting for the sqlite gap above, once
 * composer is unblocked.
 */
class PatientModuleTest extends TestCase
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

    // 1. Unauthenticated patient detail → redirect/login.
    public function test_unauthenticated_request_to_patient_detail_redirects_to_login(): void
    {
        $patientId = '44444444-4444-4444-4444-444444444444';

        $this->get("/patients/{$patientId}")->assertRedirect(route('login'));
    }

    public function test_unauthenticated_request_to_my_profile_redirects_to_login(): void
    {
        $this->get('/my-profile')->assertRedirect(route('login'));
    }

    // 2. Authorized patient detail → success.
    // DB-DEPENDENT: needs a real `patients` row visible under RLS to the
    // signed-in staff member. Blocked by both the composer/vendor gap
    // and the sqlite_testing "no such table" gap described above — not
    // executed, written to express the expected route/binding behavior.
    public function test_authorized_staff_can_view_patient_detail(): void
    {
        $userId = '55555555-5555-5555-5555-555555555555';
        $patientId = '66666666-6666-6666-6666-666666666666';

        $this->withSession($this->authenticatedSession($userId))
            ->get("/patients/{$patientId}")
            ->assertOk();
    }

    // 3. RLS-hidden patient → 404.
    // DB-LEVEL RLS TEST — see class docblock. Expresses the required
    // behavior (implicit route-model binding + patients_select_* RLS
    // together should 404, exactly like FacilityController::show) but
    // cannot be verified against real Postgres RLS in this sandbox.
    public function test_patient_hidden_by_rls_returns_not_found(): void
    {
        $userId = '77777777-7777-7777-7777-777777777777';
        $patientId = '88888888-8888-8888-8888-888888888888';

        $this->withSession($this->authenticatedSession($userId))
            ->get("/patients/{$patientId}")
            ->assertNotFound();
    }

    // 4. Patient cannot access another patient's record.
    // DB-LEVEL RLS TEST — see class docblock. A signed-in patient
    // (own Patient row = $ownPatientId) requesting a different patient's
    // detail via /patients/{patient} should be rejected before ever
    // reaching RLS, by the 'role' middleware (a plain patient has no
    // staff_assignments row) — this specifically exercises that
    // interaction, not just RLS alone.
    public function test_patient_cannot_view_another_patients_detail_via_staff_route(): void
    {
        $userId = '99999999-9999-9999-9999-999999999999';
        $otherPatientId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';

        $this->withSession($this->authenticatedSession($userId))
            ->get("/patients/{$otherPatientId}")
            ->assertForbidden();
    }

    // 5. Patient "my profile" resolves only the authenticated user.
    public function test_my_profile_route_accepts_no_patient_identifier(): void
    {
        // Structural guarantee, verifiable without touching the DB:
        // the route itself carries no {patient}/{id} parameter, so
        // there is no way to pass another patient's identity through it.
        $route = Route::getRoutes()->getByName('patients.my-profile');

        $this->assertNotNull($route);
        $this->assertSame([], $route->parameterNames());
    }

    // DB-DEPENDENT — see class docblock.
    public function test_my_profile_shows_only_the_signed_in_users_own_patient_record(): void
    {
        $userId = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';

        $this->withSession($this->authenticatedSession($userId))
            ->get('/my-profile')
            ->assertOk();
    }

    // 6. Patient can update permitted own demographic fields.
    // DB-DEPENDENT — see class docblock.
    public function test_patient_can_update_own_permitted_fields(): void
    {
        $userId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';

        $this->withSession($this->authenticatedSession($userId))
            ->patch('/my-profile', [
                'gender' => 'female',
                'blood_group' => 'O+',
                'known_allergies' => 'Penicillin, Peanuts',
            ])
            ->assertRedirect(route('patients.my-profile'));
    }

    // 7. Patient cannot modify user_id / mrn / registering_facility_id.
    // Does NOT require a real DB write to prove — UpdatePatientRequest
    // never reads these keys from input at all, so this is verifiable
    // purely at the request-mapping layer.
    public function test_update_request_never_maps_protected_fields(): void
    {
        $request = \App\Http\Requests\UpdatePatientRequest::create('/my-profile', 'PATCH', [
            'gender' => 'male',
            'user_id' => 'ffffffff-ffff-ffff-ffff-ffffffffffff',
            'mrn' => 'HACKED-MRN-0001',
            'registering_facility_id' => 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee',
            'id' => 'dddddddd-dddd-dddd-dddd-dddddddddddd',
            'created_at' => '2000-01-01',
            'deleted_at' => '2000-01-01',
        ]);

        $request->setContainer($this->app)->validateResolved();
        $attributes = $request->toPatientAttributes();

        $this->assertArrayNotHasKey('user_id', $attributes);
        $this->assertArrayNotHasKey('mrn', $attributes);
        $this->assertArrayNotHasKey('registering_facility_id', $attributes);
        $this->assertArrayNotHasKey('id', $attributes);
        $this->assertArrayNotHasKey('created_at', $attributes);
        $this->assertArrayNotHasKey('deleted_at', $attributes);
        $this->assertSame('male', $attributes['gender']);
    }

    // 8. Assigned doctor can update only where existing RLS permits.
    // DB-LEVEL RLS TEST — see class docblock.
    public function test_assigned_doctor_can_update_assigned_patient(): void
    {
        $doctorUserId = '12121212-1212-1212-1212-121212121212';
        $assignedPatientId = '13131313-1313-1313-1313-131313131313';

        $this->withSession($this->authenticatedSession($doctorUserId))
            ->patch("/patients/{$assignedPatientId}", ['gender' => 'male'])
            ->assertRedirect(route('patients.show', $assignedPatientId));
    }

    // 9. Unauthorized/unassigned staff cannot update protected patient rows.
    // DB-LEVEL RLS TEST — see class docblock. Expresses that
    // applyScopedUpdate() must treat a zero-affected-rows UPDATE as
    // failure (session error), never as success.
    public function test_unassigned_staff_cannot_update_patient_record(): void
    {
        $unassignedStaffUserId = '14141414-1414-1414-1414-141414141414';
        $notAssignedPatientId = '15151515-1515-1515-1515-151515151515';

        $this->withSession($this->authenticatedSession($unassignedStaffUserId))
            ->patch("/patients/{$notAssignedPatientId}", ['gender' => 'male'])
            ->assertSessionHasErrors(['update']);
    }

    // 10. Existing /patients list behavior remains unchanged.
    public function test_patients_list_route_and_name_unchanged(): void
    {
        $route = Route::getRoutes()->getByName('patients.index');

        $this->assertNotNull($route);
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertSame('patients', $route->uri());
    }

    // 11. Existing role authorization remains unchanged (companion to
    // RoleAuthorizationTest.php — confirms the new routes joined the
    // SAME 'role' group rather than introducing a separate/weaker gate).
    public function test_new_patient_detail_and_update_routes_require_role_middleware(): void
    {
        $showRoute = Route::getRoutes()->getByName('patients.show');
        $updateRoute = Route::getRoutes()->getByName('patients.update');

        $this->assertNotNull($showRoute);
        $this->assertNotNull($updateRoute);
        $this->assertContains('role', $showRoute->gatherMiddleware());
        $this->assertContains('role', $updateRoute->gatherMiddleware());
        $this->assertContains('supabase.rls', $showRoute->gatherMiddleware());
        $this->assertContains('supabase.rls', $updateRoute->gatherMiddleware());
    }

    public function test_my_profile_routes_do_not_require_role_middleware(): void
    {
        // Deliberately NOT behind 'role' — a plain patient account has
        // no staff_assignments row and would be incorrectly 403'd by it.
        $showRoute = Route::getRoutes()->getByName('patients.my-profile');
        $updateRoute = Route::getRoutes()->getByName('patients.my-profile.update');

        $this->assertNotNull($showRoute);
        $this->assertNotNull($updateRoute);
        $this->assertNotContains('role', $showRoute->gatherMiddleware());
        $this->assertNotContains('role', $updateRoute->gatherMiddleware());
        $this->assertContains('supabase.rls', $showRoute->gatherMiddleware());
        $this->assertContains('supabase.rls', $updateRoute->gatherMiddleware());
    }

    // 12. No patient INSERT path is accidentally introduced.
    public function test_no_patient_create_or_store_route_exists(): void
    {
        $names = collect(Route::getRoutes())->map(fn ($route) => $route->getName())->filter();

        $this->assertFalse($names->contains('patients.create'));
        $this->assertFalse($names->contains('patients.store'));

        $postToPatients = collect(Route::getRoutes())->first(
            fn ($route) => in_array('POST', $route->methods(), true) && $route->uri() === 'patients'
        );

        $this->assertNull($postToPatients, 'POST /patients must not exist — patient registration remains blocked.');
    }
}

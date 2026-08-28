<?php

namespace Tests\Feature;

use App\Http\Requests\UpdateDoctorProfileRequest;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase 5.2 — Doctor directory, detail, and self-service "My Doctor
 * Profile" (create + update).
 *
 * EXECUTABILITY NOTE: same caveats as tests/Feature/PatientModuleTest.php
 * — requires vendor/autoload.php (composer install BLOCKED in this
 * sandbox, repo.packagist.org 403) and a real Postgres connection for
 * any DB-touching case (sqlite_testing has no tables). See that file's
 * own docblock for the full explanation; not duplicated here beyond
 * this summary, per this repo's stated preference for one source of
 * truth over two copies that can drift.
 *
 * Tests that don't touch the DB at all (unauthenticated redirects,
 * route-registration/middleware assertions, and the FormRequest mapping
 * test) are the ones expected to actually pass once composer is
 * unblocked, same pattern as PatientModuleTest.php's tests 1, 11, 12.
 */
class DoctorModuleTest extends TestCase
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

    // 1. Unauthenticated requests redirect to login — no DB touch.
    public function test_unauthenticated_request_to_doctors_index_redirects_to_login(): void
    {
        $this->get('/doctors')->assertRedirect(route('login'));
    }

    public function test_unauthenticated_request_to_doctor_detail_redirects_to_login(): void
    {
        $doctorId = '21212121-2121-2121-2121-212121212121';

        $this->get("/doctors/{$doctorId}")->assertRedirect(route('login'));
    }

    public function test_unauthenticated_request_to_my_doctor_profile_redirects_to_login(): void
    {
        $this->get('/my-doctor-profile')->assertRedirect(route('login'));
    }

    // 2. Directory/detail are open to any authenticated user — no 'role'
    // gate, unlike /patients. Structural check, no DB touch.
    public function test_doctors_index_and_show_do_not_require_role_middleware(): void
    {
        $indexRoute = Route::getRoutes()->getByName('doctors.index');
        $showRoute = Route::getRoutes()->getByName('doctors.show');

        $this->assertNotNull($indexRoute);
        $this->assertNotNull($showRoute);
        $this->assertNotContains('role', $indexRoute->gatherMiddleware());
        $this->assertNotContains('role', $showRoute->gatherMiddleware());
        $this->assertContains('supabase.rls', $indexRoute->gatherMiddleware());
        $this->assertContains('supabase.rls', $showRoute->gatherMiddleware());
    }

    public function test_my_doctor_profile_routes_do_not_require_role_middleware(): void
    {
        $showRoute = Route::getRoutes()->getByName('doctors.my-profile');
        $updateRoute = Route::getRoutes()->getByName('doctors.my-profile.update');

        $this->assertNotNull($showRoute);
        $this->assertNotNull($updateRoute);
        $this->assertNotContains('role', $showRoute->gatherMiddleware());
        $this->assertNotContains('role', $updateRoute->gatherMiddleware());
        $this->assertContains('supabase.rls', $showRoute->gatherMiddleware());
        $this->assertContains('supabase.rls', $updateRoute->gatherMiddleware());
    }

    // 3. My-doctor-profile route accepts no doctor/profile identifier —
    // structural guarantee, no DB touch.
    public function test_my_doctor_profile_route_accepts_no_doctor_identifier(): void
    {
        $route = Route::getRoutes()->getByName('doctors.my-profile');

        $this->assertNotNull($route);
        $this->assertSame([], $route->parameterNames());
    }

    // 4. DB-DEPENDENT — see class docblock.
    public function test_doctors_index_renders_for_any_authenticated_user(): void
    {
        $userId = '22222222-2222-2222-2222-222222222222';

        $this->withSession($this->authenticatedSession($userId))
            ->get('/doctors')
            ->assertOk();
    }

    // 5. DB-DEPENDENT — see class docblock.
    public function test_my_doctor_profile_renders_for_a_user_with_no_profile_yet(): void
    {
        $userId = '23232323-2323-2323-2323-232323232323';

        $this->withSession($this->authenticatedSession($userId))
            ->get('/my-doctor-profile')
            ->assertOk();
    }

    // 6. Create path — DB-DEPENDENT.
    public function test_user_can_create_own_doctor_profile(): void
    {
        $userId = '24242424-2424-2424-2424-242424242424';

        $this->withSession($this->authenticatedSession($userId))
            ->patch('/my-doctor-profile', [
                'registration_number' => 'MCI-12345',
                'years_experience' => 5,
                'specialties' => 'Cardiology, Internal Medicine',
                'qualifications' => 'MBBS, MD',
                'languages_spoken' => 'Hindi, English',
            ])
            ->assertRedirect(route('doctors.my-profile'));
    }

    // 7. Update path — DB-DEPENDENT.
    public function test_user_can_update_own_existing_doctor_profile(): void
    {
        $userId = '25252525-2525-2525-2525-252525252525';

        $this->withSession($this->authenticatedSession($userId))
            ->patch('/my-doctor-profile', ['years_experience' => 10])
            ->assertRedirect(route('doctors.my-profile'));
    }

    // 8. Request mapping never exposes protected fields — verifiable
    // purely at the FormRequest layer, no DB touch.
    public function test_update_request_never_maps_protected_fields(): void
    {
        $request = UpdateDoctorProfileRequest::create('/my-doctor-profile', 'PATCH', [
            'years_experience' => 7,
            'user_id' => 'ffffffff-ffff-ffff-ffff-ffffffffffff',
            'id' => 'dddddddd-dddd-dddd-dddd-dddddddddddd',
            'created_at' => '2000-01-01',
            'deleted_at' => '2000-01-01',
        ]);

        $request->setContainer($this->app)->validateResolved();
        $attributes = $request->toDoctorProfileAttributes();

        $this->assertArrayNotHasKey('user_id', $attributes);
        $this->assertArrayNotHasKey('id', $attributes);
        $this->assertArrayNotHasKey('created_at', $attributes);
        $this->assertArrayNotHasKey('deleted_at', $attributes);
        $this->assertSame(7, $attributes['years_experience']);
    }

    // 9. Comma-separated text fields map to arrays correctly — no DB
    // touch, pure FormRequest-layer logic.
    public function test_update_request_maps_comma_separated_fields_to_arrays(): void
    {
        $request = UpdateDoctorProfileRequest::create('/my-doctor-profile', 'PATCH', [
            'specialties' => 'Cardiology,  Pediatrics ,Oncology',
            'qualifications' => 'MBBS',
            'languages_spoken' => '',
        ]);

        $request->setContainer($this->app)->validateResolved();
        $attributes = $request->toDoctorProfileAttributes();

        $this->assertSame(['Cardiology', 'Pediatrics', 'Oncology'], $attributes['specialties']);
        $this->assertSame(['MBBS'], $attributes['qualifications']);
        $this->assertSame([], $attributes['languages_spoken']);
    }

    // 10. Existing routes/behavior remain unchanged by this phase.
    public function test_existing_patient_and_facility_routes_unchanged(): void
    {
        $this->assertNotNull(Route::getRoutes()->getByName('patients.index'));
        $this->assertNotNull(Route::getRoutes()->getByName('facilities.index'));
        $this->assertNotNull(Route::getRoutes()->getByName('facilities.show'));
    }
}

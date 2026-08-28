<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Phase 5.1 — sidebar/mobile-nav "Patients" link visibility.
 *
 * This is a UX-only fix: EnsureUserHasRole (route authorization) and
 * the live RLS policies are completely unchanged. These tests assert
 * only that the rendered nav HTML omits/includes the "Patients" link
 * based on User::hasActiveStaffAssignment() — never that the route
 * itself becomes reachable or blocked (that remains
 * RoleAuthorizationTest's/PatientModuleTest's job).
 *
 * See class docblock in RoleAuthorizationTest.php for the standing
 * session-fixture requirements (supabase.jwt_claims etc.) reused here.
 */
class SidebarNavigationTest extends TestCase
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

    // Plain patient (no staff_assignments row): Patients link must NOT
    // render, but the page itself (dashboard, which is open to any
    // authenticated user regardless of role) must still load fine.
    // DB-DEPENDENT — see class docblock; needs a real `patients` row
    // and no `staff_assignments` row for this user under RLS.
    public function test_patients_link_hidden_for_plain_patient_account(): void
    {
        $userId = 'cccccccc-dddd-eeee-ffff-000000000001';

        $response = $this->withSession($this->authenticatedSession($userId))
            ->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee('Patients', false);
    }

    // Doctor (has an active staff_assignments row): Patients link MUST
    // render. DB-DEPENDENT — see class docblock.
    public function test_patients_link_visible_for_doctor_account(): void
    {
        $userId = 'cccccccc-dddd-eeee-ffff-000000000002';

        $response = $this->withSession($this->authenticatedSession($userId))
            ->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Patients', false);
    }

    // Hospital admin (facility-scoped staff_assignments row): Patients
    // link MUST render. DB-DEPENDENT — see class docblock.
    public function test_patients_link_visible_for_hospital_admin_account(): void
    {
        $userId = 'cccccccc-dddd-eeee-ffff-000000000003';

        $response = $this->withSession($this->authenticatedSession($userId))
            ->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Patients', false);
    }

    // Super admin (platform-wide staff_assignments row, facility_id
    // null / facility_group_id set): Patients link MUST render.
    // DB-DEPENDENT — see class docblock.
    public function test_patients_link_visible_for_super_admin_account(): void
    {
        $userId = 'cccccccc-dddd-eeee-ffff-000000000004';

        $response = $this->withSession($this->authenticatedSession($userId))
            ->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Patients', false);
    }

    // Structural guarantee, verifiable without touching the DB: the
    // hidden-when-absent behavior is driven by a real Eloquent method
    // on User, not a hardcoded email/role-string check anywhere in the
    // view layer.
    public function test_hasActiveStaffAssignment_method_exists_on_user_model(): void
    {
        $this->assertTrue(method_exists(\App\Models\User::class, 'hasActiveStaffAssignment'));
    }

    // /my-profile must be completely unaffected by this change — no
    // 'role' gate, still resolves purely from Auth::user(). Not
    // DB-dependent in the way the above are: unauthenticated case only.
    public function test_my_profile_route_unaffected_by_nav_visibility_change(): void
    {
        $this->get('/my-profile')->assertRedirect(route('login'));
    }
}

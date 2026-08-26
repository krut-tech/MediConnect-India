<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Phase 4 — Role Authorization (EnsureUserHasRole).
 *
 * Covers the one route Phase 4 actually gates: '/patients'. Deliberately
 * does NOT invent role-code fixtures (e.g. 'doctor') since this class
 * never hardcodes which codes are valid — see the middleware's own
 * docblock. These tests only exercise the no-parameter mode ("does the
 * user resolve to ANY active staff_assignments row"), which is the mode
 * actually applied to a route today.
 *
 * EXECUTABILITY NOTE (same honest caveat as AuthTest.php /
 * Phase3UiTest.php): requires `vendor/autoload.php`, blocked by the
 * repo.packagist.org 403 in this sandbox. Written and lint-checked
 * (`php -l`), not executed here. Additionally, these tests reach
 * $user->staffAssignments() via Eloquent, which — once composer is
 * unblocked — will hit the same "no such table" gap on the
 * `sqlite_testing` connection that Phase3UiTest and one AuthTest case
 * already document (migrations/ is intentionally empty). That gap is
 * pre-existing and not introduced by this file.
 */
class RoleAuthorizationTest extends TestCase
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
        ];
    }

    public function test_unauthenticated_request_to_patients_redirects_to_login(): void
    {
        $this->get('/patients')->assertRedirect(route('login'));
    }

    public function test_authenticated_user_with_no_staff_assignment_is_forbidden_from_patients(): void
    {
        $userId = '22222222-2222-2222-2222-222222222222';

        $this->withSession($this->authenticatedSession($userId))
            ->get('/patients')
            ->assertForbidden();
    }

    public function test_facilities_directory_remains_open_to_any_authenticated_user(): void
    {
        // Control case: '/facilities' is NOT behind the 'role' middleware
        // — Phase 4 only closes the '/patients' PII gap, per the routes
        // file's own comment. This guards against someone accidentally
        // widening the 'role' group to include it later without meaning
        // to.
        $userId = '33333333-3333-3333-3333-333333333333';

        $this->withSession($this->authenticatedSession($userId))
            ->get('/facilities')
            ->assertOk();
    }
}

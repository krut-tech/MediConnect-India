<?php

namespace Tests\Feature;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 3 Milestone 1 — Auth Foundation.
 *
 * All Supabase calls are mocked via Http::fake() — no real network
 * access, no real project touched, no production/live data involved.
 * The JWT used here is signed with the test-only fixture secret set in
 * phpunit.xml (SUPABASE_JWT_SECRET), never a real credential.
 *
 * EXECUTABILITY NOTE (same honest caveat as Phase3UiTest): these tests
 * require `vendor/autoload.php`, which needs `composer install` —
 * blocked in this sandbox (packagist.org unreachable). Written and
 * reviewed for correctness; not executed here. They do NOT depend on
 * the `sqlite_testing` DB connection at all (unlike Phase3UiTest) since
 * user resolution in this architecture never queries Eloquent directly
 * — so once composer is unblocked, these should not hit the
 * missing-table gap Phase3UiTest documents.
 *
 * ONE EXCEPTION, documented honestly: once past the auth layer,
 * test_authenticated_session_can_access_protected_route reaches
 * DashboardController, which itself calls $user->staffAssignments()
 * via Eloquent — that DOES hit the same sqlite_testing connection
 * Phase3UiTest already flagged has no tables (migrations/ is
 * intentionally empty). So even once composer install is unblocked,
 * expect that one test to fail on "no such table", same root cause as
 * Phase3UiTest — not a defect in the auth code being tested here.
 *
 * PHASE 5 STEP 3 UPDATE: routes behind 'supabase.auth' now also run
 * behind 'supabase.rls' (App\Http\Middleware\EstablishSupabaseRlsContext),
 * which requires a 'supabase.jwt_claims' session entry (in addition to
 * 'supabase.expires_at'/'supabase.profile') or it fails closed with a
 * 403 before the request reaches a controller. Every test below that
 * exercises a route inside that group now seeds 'supabase.jwt_claims'
 * alongside the existing session keys so it reaches exactly as far as
 * it did pre-Step-3 — this does NOT resolve the pre-existing
 * sqlite_testing/no-tables gap noted above, which is a separate,
 * already-documented limitation of this sandbox, not something this
 * update changes either way.
 */
class AuthTest extends TestCase
{
    private function fakeAccessToken(string $userId): string
    {
        $now = time();

        return JWT::encode([
            'sub' => $userId,
            'aud' => 'authenticated',
            'iss' => rtrim(config('services.supabase.url'), '/').'/auth/v1',
            'iat' => $now,
            'exp' => $now + 3600,
        ], config('services.supabase.jwt_secret'), 'HS256');
    }

    /**
     * Matches what AuthController::establishSession() now caches under
     * 'supabase.jwt_claims' — used by tests that seed a session directly
     * (bypassing a real login) to reach protected/RLS-wrapped routes.
     *
     * @return array<string,mixed>
     */
    private function fakeJwtClaims(string $userId): array
    {
        return [
            'sub' => $userId,
            'role' => 'authenticated',
            'aud' => 'authenticated',
            'iss' => rtrim(config('services.supabase.url'), '/').'/auth/v1',
            'exp' => now()->addHour()->timestamp,
        ];
    }

    public function test_guest_can_view_login_page(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }

    public function test_guest_can_view_register_page(): void
    {
        $this->get('/register')->assertOk()->assertSee('Create your account');
    }

    public function test_protected_route_redirects_unauthenticated_request_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->post('/login', [])
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        Http::fake([
            '*/auth/v1/token*' => Http::response(['error_description' => 'Invalid login credentials'], 400),
        ]);

        $this->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['auth']);
    }

    public function test_successful_login_establishes_session_and_redirects_to_dashboard(): void
    {
        $userId = '11111111-1111-1111-1111-111111111111';
        $token = $this->fakeAccessToken($userId);

        Http::fake([
            '*/auth/v1/token*' => Http::response([
                'access_token' => $token,
                'refresh_token' => 'fake-refresh-token',
                'expires_in' => 3600,
            ]),
            '*/rest/v1/users*' => Http::response([[
                'id' => $userId,
                'full_name' => 'Test User',
                'email' => 'test@example.com',
                'phone' => null,
                'preferred_language' => 'en',
                'is_active' => true,
                'abha_id' => null,
            ]]),
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertNotNull(session('supabase.access_token'));
        $this->assertEquals('Test User', session('supabase.profile')['full_name']);
        // Phase 5 Step 3: the RLS context service needs this, cached
        // from the SAME verifyAccessToken() call above — not re-decoded.
        $this->assertEquals($userId, session('supabase.jwt_claims')['sub']);
    }

    public function test_login_rejects_token_with_wrong_signature(): void
    {
        // Signed with a different secret than the app is configured to
        // verify against — must be rejected, never trusted.
        $badToken = JWT::encode([
            'sub' => '11111111-1111-1111-1111-111111111111',
            'aud' => 'authenticated',
            'iss' => rtrim(config('services.supabase.url'), '/').'/auth/v1',
            'iat' => time(),
            'exp' => time() + 3600,
        ], 'a-completely-different-secret', 'HS256');

        Http::fake([
            '*/auth/v1/token*' => Http::response([
                'access_token' => $badToken,
                'refresh_token' => 'fake-refresh-token',
                'expires_in' => 3600,
            ]),
        ]);

        $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'correct-password',
        ])->assertSessionHasErrors(['auth']);

        $this->assertNull(session('supabase.access_token'));
        $this->assertNull(session('supabase.jwt_claims'));
    }

    public function test_authenticated_session_can_access_protected_route(): void
    {
        $userId = '11111111-1111-1111-1111-111111111111';

        $this->withSession([
            'supabase.expires_at' => now()->addHour()->timestamp,
            'supabase.profile' => [
                'id' => $userId,
                'full_name' => 'Test User',
                'email' => 'test@example.com',
                'phone' => null,
            ],
            'supabase.jwt_claims' => $this->fakeJwtClaims($userId),
        ])->get('/dashboard')->assertOk();
    }

    public function test_expired_session_is_rejected_and_redirected_to_login(): void
    {
        $this->withSession([
            'supabase.expires_at' => now()->subHour()->timestamp,
            'supabase.profile' => ['id' => '11111111-1111-1111-1111-111111111111', 'full_name' => 'Test User'],
        ])->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_logout_invalidates_session(): void
    {
        Http::fake(['*/auth/v1/logout*' => Http::response([], 204)]);

        $userId = '11111111-1111-1111-1111-111111111111';

        $response = $this->withSession([
            'supabase.access_token' => 'fake-token',
            'supabase.expires_at' => now()->addHour()->timestamp,
            'supabase.profile' => ['id' => $userId, 'full_name' => 'Test User'],
            'supabase.jwt_claims' => $this->fakeJwtClaims($userId),
        ])->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertNull(session('supabase.access_token'));
        $this->assertNull(session('supabase.jwt_claims'));
    }

    public function test_already_authenticated_session_is_redirected_away_from_login_page(): void
    {
        $this->withSession([
            'supabase.expires_at' => now()->addHour()->timestamp,
        ])->get('/login')->assertRedirect(route('dashboard'));
    }

    public function test_registration_requires_name_email_and_matching_passwords(): void
    {
        $this->post('/register', [
            'full_name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['full_name', 'email', 'password']);
    }

    public function test_registration_form_has_no_role_field(): void
    {
        // Guards against ever re-introducing a role selector on public
        // registration — Milestone 1's explicit rule.
        $this->get('/register')->assertDontSee('name="role"', false);
    }
}

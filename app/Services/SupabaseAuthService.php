<?php

namespace App\Services;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use UnexpectedValueException;

/**
 * Thin wrapper around Supabase Auth (GoTrue) + PostgREST, used only with
 * the anon key and (after login) the end user's own access token — never
 * service_role. This is the Option B integration point approved for
 * MediConnect India: Laravel never talks to Postgres directly for
 * authenticated reads, it goes through Supabase so RLS evaluates
 * auth.uid() natively.
 */
class SupabaseAuthService
{
    private function baseUrl(): string
    {
        $url = config('services.supabase.url');

        if (! $url) {
            throw new RuntimeException('SUPABASE_URL is not configured.');
        }

        return rtrim($url, '/');
    }

    private function anonKey(): string
    {
        $key = config('services.supabase.anon_key');

        if (! $key) {
            throw new RuntimeException('SUPABASE_ANON_KEY is not configured.');
        }

        return $key;
    }

    private function jwtSecret(): string
    {
        $secret = config('services.supabase.jwt_secret');

        if (! $secret) {
            throw new RuntimeException('SUPABASE_JWT_SECRET is not configured.');
        }

        return $secret;
    }

    /**
     * POST /auth/v1/signup — creates the auth.users row. The already
     * verified `on_auth_user_created` trigger provisions the matching
     * public.users row server-side; nothing else is created here.
     *
     * `full_name` is passed as user metadata so the trigger's
     * `raw_user_meta_data ->> 'full_name'` branch picks it up directly,
     * instead of falling back to a derived name.
     *
     * Returns the raw decoded JSON. Depending on the project's Auth
     * settings (email confirmation on/off — not something this app can
     * see or assume), the response may or may not include a session.
     * Callers must handle both cases; this method does not guess.
     */
    public function signUp(string $email, ?string $phone, string $password, string $fullName): array
    {
        $payload = array_filter([
            'email' => $email ?: null,
            'phone' => $phone ?: null,
            'password' => $password,
            'data' => ['full_name' => $fullName],
        ], fn ($value) => $value !== null);

        $response = Http::withHeaders(['apikey' => $this->anonKey()])
            ->acceptJson()
            ->post($this->baseUrl().'/auth/v1/signup', $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                $response->json('error_description') ?? $response->json('msg') ?? 'Registration failed.'
            );
        }

        return $response->json();
    }

    /**
     * POST /auth/v1/token?grant_type=password
     */
    public function signInWithPassword(string $email, string $password): array
    {
        $response = Http::withHeaders(['apikey' => $this->anonKey()])
            ->acceptJson()
            ->post($this->baseUrl().'/auth/v1/token?grant_type=password', [
                'email' => $email,
                'password' => $password,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                $response->json('error_description') ?? $response->json('msg') ?? 'Invalid credentials.'
            );
        }

        return $response->json();
    }

    /**
     * POST /auth/v1/logout — best-effort server-side revocation of the
     * Supabase session. Failures here must never block local logout
     * (the Laravel session is the primary thing being cleared), so
     * callers should treat this as fire-and-forget.
     */
    public function signOut(string $accessToken): void
    {
        Http::withToken($accessToken)
            ->withHeaders(['apikey' => $this->anonKey()])
            ->post($this->baseUrl().'/auth/v1/logout');
    }

    /**
     * Verifies a Supabase-issued access token: signature (HS256, the
     * project's JWT secret), expiry, audience, and issuer. Throws on
     * any failure — callers must not treat an exception as "logged out
     * gracefully", it means the token must not be trusted.
     *
     * @return array<string,mixed> decoded claims
     */
    public function verifyAccessToken(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret(), 'HS256'));
        } catch (ExpiredException $e) {
            throw new RuntimeException('Session token has expired.', previous: $e);
        } catch (SignatureInvalidException $e) {
            throw new RuntimeException('Session token signature is invalid.', previous: $e);
        } catch (UnexpectedValueException $e) {
            throw new RuntimeException('Session token is malformed.', previous: $e);
        }

        $claims = (array) $decoded;

        if (($claims['aud'] ?? null) !== 'authenticated') {
            throw new RuntimeException('Session token has an unexpected audience.');
        }

        $expectedIssuer = $this->baseUrl().'/auth/v1';
        if (($claims['iss'] ?? null) !== $expectedIssuer) {
            throw new RuntimeException('Session token has an unexpected issuer.');
        }

        if (empty($claims['sub'])) {
            throw new RuntimeException('Session token is missing a subject claim.');
        }

        return $claims;
    }

    /**
     * GET /rest/v1/users?id=eq.<uid> using the USER's OWN access token
     * (never service_role) — RLS's `users_select_own` policy is what
     * makes this return exactly one row (their own) or none.
     *
     * @return array<string,mixed>|null
     */
    public function fetchOwnProfile(string $accessToken, string $userId): ?array
    {
        $response = Http::withToken($accessToken)
            ->withHeaders(['apikey' => $this->anonKey()])
            ->acceptJson()
            ->get($this->baseUrl().'/rest/v1/users', [
                'id' => 'eq.'.$userId,
                'select' => '*',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Could not load the account profile.');
        }

        $rows = $response->json();

        return $rows[0] ?? null;
    }
}

<?php

namespace App\Services;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Phase 5 Step 3 — Supabase RLS context.
 *
 * PROBLEM THIS SOLVES: Eloquent's `pgsql` connection reaches Postgres
 * through Supabase's Transaction Pooler as the dedicated `mediconnect_app`
 * role (NOINHERIT, BYPASSRLS=false, member of `authenticated` but never
 * `service_role`). On its own, that connection has no way to tell
 * Postgres RLS *which* authenticated user is making a given request —
 * `auth.uid()` (and the `resolve_own_patient_id()` /
 * `resolve_assigned_patient_ids()` / `resolve_user_facility_ids()` /
 * `user_has_facility_role()` / `user_has_role()` helper functions the
 * live RLS policies are built on, per `get_advisors`) all read from the
 * `request.jwt.claims` / `request.jwt.claim.sub` Postgres session GUCs
 * that PostgREST normally sets per-request. A plain Eloquent query
 * without this context either sees nothing (if a policy requires a
 * matching `auth.uid()`) or — worse — could see everything, depending on
 * how a given policy is written. Neither is acceptable; this service
 * makes sure every RLS-protected query runs with the right context or
 * doesn't run at all.
 *
 * WHAT THIS SERVICE DOES NOT DO: it does not decode, verify, or trust a
 * raw JWT. The ONLY verification of a Supabase access token in this
 * codebase happens once, in
 * `SupabaseAuthService::verifyAccessToken()`, at login/register time
 * (signature via the project JWKS, expiry, audience, issuer, subject).
 * This service only ever reads the *already-verified* claims that call
 * produced, cached into the Laravel session by
 * `AuthController::establishSession()` under `supabase.jwt_claims` —
 * the same cache `VerifySupabaseSession` already trusts to resolve "who
 * is logged in" from `supabase.profile`. If that cache is missing,
 * empty, or the session has expired, this service refuses to proceed —
 * it never falls back to guessing an identity.
 *
 * WHY `SET LOCAL` / transaction-scoped: Supabase's Transaction Pooler
 * (PgBouncer, transaction mode) hands the underlying Postgres connection
 * back to the pool as soon as a transaction commits. `SET LOCAL` (via
 * `set_config(..., is_local => true)`) ties these settings to the
 * current transaction only — they are automatically discarded at
 * COMMIT/ROLLBACK and can never leak into a different pooled request,
 * even under transaction-mode pooling. A bare `SET ROLE` / `SET
 * request.jwt.claims` (without LOCAL) would persist on the physical
 * connection past the end of this request and could leak into whichever
 * unrelated request the pooler hands that connection to next — this
 * service never does that.
 */
class SupabaseRlsContext
{
    /**
     * Read the already-verified JWT claims cached at login/register
     * time. Returns null (never throws) if no usable claims exist —
     * callers must treat null as "no identity available" and fail
     * closed, not as an error to work around.
     *
     * @return array<string,mixed>|null
     */
    public function claimsFromSession(Request $request): ?array
    {
        $expiresAt = $request->session()->get('supabase.expires_at');
        $claims = $request->session()->get('supabase.jwt_claims');

        if (! $expiresAt || ! is_array($claims)) {
            return null;
        }

        if (now()->timestamp >= $expiresAt) {
            return null;
        }

        if (empty($claims['sub'])) {
            return null;
        }

        return $claims;
    }

    /**
     * Run $callback inside a single database transaction with RLS
     * context (`SET LOCAL ROLE authenticated` + `request.jwt.claims` /
     * `request.jwt.claim.sub`) established for its duration. Every
     * Eloquent/DB call made inside $callback — directly or via anything
     * it calls — executes under this context, on the same connection,
     * inside the same transaction, so the settings are guaranteed to be
     * visible to it and guaranteed to never leak past it.
     *
     * Fails closed: throws immediately, before opening a transaction or
     * touching the database, if $claims has no verified subject.
     *
     * @param  array<string,mixed>  $claims  Already-verified claims from claimsFromSession().
     */
    public function run(array $claims, Closure $callback): mixed
    {
        $sub = $claims['sub'] ?? null;

        if (empty($sub)) {
            throw new RuntimeException(
                'Refusing to establish an RLS context without a verified subject claim.'
            );
        }

        // Only the claims Postgres/PostgREST-style RLS helpers actually
        // read are propagated — never the full raw token, and never
        // anything sourced from unverified request input.
        $jwtClaims = array_filter([
            'sub' => $sub,
            'role' => $claims['role'] ?? 'authenticated',
            'aud' => $claims['aud'] ?? null,
            'iss' => $claims['iss'] ?? null,
            'exp' => $claims['exp'] ?? null,
        ], fn ($value) => $value !== null);

        return DB::transaction(function () use ($jwtClaims, $sub, $callback) {
            // authenticated, never service_role/postgres — mediconnect_app
            // is NOINHERIT and was granted membership in `authenticated`
            // only (verified in Phase 5 Step 1); this SET LOCAL ROLE is
            // what makes that granted membership the session's active
            // privilege set for the rest of this transaction.
            DB::statement('SET LOCAL ROLE authenticated');

            // Full JSON form — this is what modern PostgREST/Supabase
            // helper functions (auth.jwt(), and the resolve_*/user_has_*
            // functions surfaced by get_advisors) read via
            // current_setting('request.jwt.claims', true)::jsonb.
            DB::statement(
                "SELECT set_config('request.jwt.claims', ?, true)",
                [json_encode($jwtClaims)]
            );

            // Flat per-claim key form — auth.uid()'s actual definition
            // checks this key FIRST, falling back to the JSON form only
            // if it's absent. Both are set so auth.uid()/auth.role()
            // resolve correctly regardless of which form a given
            // installed helper function happens to read.
            DB::statement("SELECT set_config('request.jwt.claim.sub', ?, true)", [$sub]);
            DB::statement(
                "SELECT set_config('request.jwt.claim.role', ?, true)",
                [$jwtClaims['role']]
            );

            return $callback();
        });
    }
}

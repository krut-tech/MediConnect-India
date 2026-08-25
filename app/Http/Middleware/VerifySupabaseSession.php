<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Real implementation — Phase 3 Milestone 1 (Auth Foundation).
 *
 * Resolution strategy, and why it does NOT use Eloquent's direct DB
 * connection: this app's approved architecture (Option B) requires that
 * only the end user's own Supabase access token, via PostgREST, ever
 * reads `public.users` — a direct Postgres connection has no way to
 * carry a specific request's `auth.uid()` context (Option A was
 * evaluated and not approved). So "who is logged in" is resolved purely
 * from the Laravel session:
 *
 *   1. AuthController::establishSession() verified the JWT once (via
 *      SupabaseAuthService::verifyAccessToken — signature/expiry/aud/iss)
 *      and fetched the profile once (via PostgREST, RLS-scoped) at
 *      login/register time, caching both the token metadata and the
 *      profile snapshot into the Laravel session.
 *   2. This middleware, on every request, checks the cached expiry
 *      locally and — if still valid — hydrates a User model instance
 *      from the cached profile and attaches it to the guard for this
 *      request. No network call and no direct DB query happen here.
 *
 * Trade-off (documented, not hidden): a profile change made through
 * another client won't be reflected until the person logs in again or
 * the token is refreshed. Re-verifying against Supabase on every single
 * request was considered and rejected for this milestone as unnecessary
 * latency for a first auth foundation — noted as a possible follow-up,
 * not implemented here.
 */
class VerifySupabaseSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->resolveUserFromSession($request)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->guest(route('login'));
        }

        return $next($request);
    }

    private function resolveUserFromSession(Request $request): bool
    {
        $expiresAt = $request->session()->get('supabase.expires_at');
        $profile = $request->session()->get('supabase.profile');

        if (! $expiresAt || ! $profile) {
            return false;
        }

        if (now()->timestamp >= $expiresAt) {
            return false;
        }

        $user = User::make()->forceFill($profile);
        $user->exists = true;
        $user->syncOriginal();

        Auth::guard('web')->setUser($user);

        return true;
    }
}

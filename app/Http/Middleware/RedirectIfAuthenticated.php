<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects an already-authenticated person away from guest-only pages
 * (login, register) to their dashboard, instead of showing them a form
 * to sign in again.
 *
 * Deliberately checks the same session-cached expiry that
 * VerifySupabaseSession uses, rather than Auth::guard('web')->check() —
 * the latter would fall through to Laravel's default Eloquent user
 * provider lookup on an unauthenticated request, which is exactly the
 * direct-DB-connection resolution path this app's approved architecture
 * (Option B) does not use for identifying who is logged in.
 */
class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $expiresAt = $request->session()->get('supabase.expires_at');

        if ($expiresAt && now()->timestamp < $expiresAt) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}

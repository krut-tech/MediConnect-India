<?php

namespace App\Http\Middleware;

use App\Services\SupabaseRlsContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 5 Step 3 — wires the already-verified Supabase JWT claims into
 * PostgreSQL RLS for this request.
 *
 * Must run AFTER `supabase.auth` (VerifySupabaseSession) — that
 * middleware is what actually decides "is someone logged in" and
 * populates the Laravel session this middleware reads from. This
 * middleware does not re-check authentication; it only decides whether
 * an RLS-safe database context can be established for the request that
 * VerifySupabaseSession already allowed through.
 *
 * Must run BEFORE any route/controller code — including other
 * middleware such as `role` (EnsureUserHasRole), which itself queries
 * the RLS-protected `staff_assignments` table via Eloquent — that
 * query needs this context to resolve correctly, not just controller
 * code further down the stack.
 *
 * Fails closed: if no usable verified claims exist (missing, expired,
 * no subject), this aborts with 403 before opening a database
 * transaction or letting the request reach a controller. There is no
 * fallback path that continues without RLS context.
 */
class EstablishSupabaseRlsContext
{
    public function __construct(private SupabaseRlsContext $rlsContext)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $claims = $this->rlsContext->claimsFromSession($request);

        if (! $claims) {
            abort(403, 'No verified Supabase identity is available for this request.');
        }

        return $this->rlsContext->run($claims, fn () => $next($request));
    }
}

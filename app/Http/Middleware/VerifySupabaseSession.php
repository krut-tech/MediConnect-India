<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PLACEHOLDER — not implemented in Phase 2.
 *
 * This middleware will eventually verify a Supabase Auth JWT (from a
 * cookie or Authorization header), resolve it to a `public.users` row,
 * and — critically — establish whatever session/connection context is
 * needed for Supabase RLS policies to evaluate `auth.uid()` correctly for
 * the rest of the request (see the architecture note in
 * config/database.php).
 *
 * Deliberately left as a pass-through for Phase 2 foundation work: no
 * fake authentication, no bypass, no invented user. Routes behind this
 * middleware are not meant to be considered "protected" yet.
 */
class VerifySupabaseSession
{
    public function handle(Request $request, Closure $next): Response
    {
        // Intentionally not implemented yet. See class docblock.
        return $next($request);
    }
}

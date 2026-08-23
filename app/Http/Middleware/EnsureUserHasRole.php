<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PLACEHOLDER — not implemented in Phase 2.
 *
 * Will eventually check the current user's `staff_assignments` /
 * `role_permissions` rows (the existing Supabase RBAC data — see
 * DATABASE_MAPPING.md) against a required role/permission passed as a
 * middleware parameter, e.g. `role:doctor` or `role:facility_admin`.
 *
 * This is meant to mirror — not replace — the authorization already
 * enforced by RLS at the database layer. It exists so route definitions
 * can express intent clearly once real modules are built.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Intentionally not implemented yet. See class docblock.
        return $next($request);
    }
}

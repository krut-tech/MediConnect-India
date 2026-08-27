<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Real implementation — Phase 4 (Authentication / Authorization).
 *
 * Data-driven, per the existing no-hardcoded-role-string convention
 * already established elsewhere in this codebase (DashboardController's
 * docblock; DATABASE_MAPPING.md: "RBAC as data, not hardcoded — Laravel
 * policies should query this, not hardcode role checks"). This class
 * introduces no new authorization architecture — it reuses the exact
 * `staff_assignments` resolution query (active, non-deleted, ordered by
 * `is_primary`) that DashboardController already relies on to tell
 * "platform_staff"/"facility_staff" apart from "patient"/"no_role".
 *
 * Two modes:
 *
 *   1. `role` (no parameters) — requires the authenticated user to
 *      resolve to at least one active `staff_assignments` row, i.e. any
 *      real role at all. Use this to gate a screen to "staff, whoever
 *      they are" without guessing which `roles.code` values exist —
 *      appropriate while `roles` may still have few/no seeded rows.
 *   2. `role:some_code,another_code` — additionally requires the
 *      resolved assignment's `role.code` to match one of the given
 *      codes. This class never invents or hardcodes which codes are
 *      valid; the codes are supplied by the route definition and
 *      checked against live `roles.code` data.
 *
 * This mirrors, and does not replace, the authorization Supabase RLS
 * already enforces at the database layer — it exists so route
 * definitions can express intent in application code too.
 *
 * Must run after `supabase.auth` (which populates the guard) AND after
 * `supabase.rls` (Phase 5 Step 3 —
 * App\Http\Middleware\EstablishSupabaseRlsContext), since the
 * `staff_assignments` query below is itself RLS-protected — see
 * routes/web.php for the required ordering. Before Phase 5 Step 3, this
 * query ran with no auth.uid() context at all and could only ever
 * resolve zero rows for a live RLS policy that checks auth.uid(); that
 * was the actual root cause of every real user being rejected here, not
 * a legitimate "no staff role" outcome.
 *
 * Fails closed (403) if no user is resolved, rather than assuming one.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::guard('web')->user();

        if (! $user) {
            abort(403);
        }

        $assignment = $user->staffAssignments()
            ->with('role')
            ->whereNull('deleted_at')
            ->where(function ($query) {
                $query->whereNull('valid_until')->orWhere('valid_until', '>', now());
            })
            ->orderByDesc('is_primary')
            ->first();

        if (! $assignment || ! $assignment->role) {
            abort(403);
        }

        if ($roles !== [] && ! in_array($assignment->role->code, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}

&lt;?php

namespace Tests\Unit;

use App\Services\SupabaseRlsContext;
use Illuminate\Http\Request;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 5 Step 3 — unit coverage for App\Services\SupabaseRlsContext.
 *
 * SCOPE OF THIS FILE (read before extending it):
 *
 * These tests cover claimsFromSession() and the fail-closed guard at
 * the top of run() — all of which execute BEFORE any database
 * connection is touched, so they need no real Postgres and are fully
 * runnable once composer/vendor is available (same sandbox blocker as
 * every other test in this suite — see MIGRATION_PROGRESS.md).
 *
 * WHAT THIS FILE DELIBERATELY DOES NOT DO — DB-LEVEL RLS CONTRACT TEST:
 *
 * The Step 3 instructions ask for a DB-level contract test using two
 * different UUIDs that proves cross-user patient access is actually
 * denied by Postgres RLS (not just "the code calls SET LOCAL ROLE" —
 * an assertion that RLS itself rejects a mismatched auth.uid()).
 * That test is NOT included here, and this is a documented NOT
 * POSSIBLE, not a silent omission:
 *
 *   1. `sqlite_testing` (this suite's only configured test connection)
 *      is SQLite. SQLite has no Postgres RLS, no `SET LOCAL ROLE`, no
 *      `set_config()` — a test against it cannot exercise Postgres RLS
 *      at all, only Laravel-side code paths, which is exactly the kind
 *      of "we added a WHERE clause and called it solved" test the
 *      original instructions explicitly rejected.
 *
 *   2. The only real Postgres reachable from this codebase is the
 *      live `mediconnect-india` Supabase project (see
 *      config/database.php) — there is no separate, isolated Postgres
 *      test database wired up. Running a contract test against it
 *      would mean inserting two throwaway patient rows (and the
 *      facility/staff rows RLS policies join against) under two fake
 *      UUIDs into the LIVE project to get a real cross-user denial —
 *      i.e. a write to production data. That violates this project's
 *      standing rule (MIGRATION_PROGRESS.md, every phase to date, this
 *      Step 3's own instructions) of zero writes to the live Supabase
 *      project without a separate, explicit approval step, and it is
 *      not worth breaking that rule for a test.
 *
 * WHAT WAS DONE INSTEAD, live and read-only, during this Step 3 pass
 * (see chat report, not duplicated into source comments to avoid
 * drift): confirmed live via `pg_roles` that `mediconnect_app` has
 * `rolbypassrls = false`, `rolinherit = false`, and is a member of
 * `authenticated` only (never `service_role`) — the actual DB-level
 * precondition SET LOCAL ROLE authenticated depends on to mean
 * anything. That is a real, live, DB-level check; it is just not an
 * automated PHPUnit test, and is not being mischaracterized as one.
 *
 * THE RIGHT FIX, for whoever picks this up next: provision a
 * dedicated Postgres test database (a local Postgres container, or a
 * throwaway Supabase branch via the `create_branch` tool this project
 * already has access to) with the same RLS policies migrated in, wire
 * it into phpunit.xml as a second connection, and write the two-UUID
 * contract test against THAT — never against the live project.
 */
class SupabaseRlsContextTest extends TestCase
{
    public function test_claims_from_session_returns_null_when_missing(): void
    {
        $request = Request::create('/');
        $request-&gt;setLaravelSession($this-&gt;app['session.store']);

        $this-&gt;assertNull((new SupabaseRlsContext())-&gt;claimsFromSession($request));
    }

    public function test_claims_from_session_returns_null_when_expired(): void
    {
        $request = Request::create('/');
        $session = $this-&gt;app['session.store'];
        $session-&gt;put('supabase.expires_at', now()-&gt;subMinute()-&gt;timestamp);
        $session-&gt;put('supabase.jwt_claims', ['sub' =&gt; 'user-1']);
        $request-&gt;setLaravelSession($session);

        $this-&gt;assertNull((new SupabaseRlsContext())-&gt;claimsFromSession($request));
    }

    public function test_claims_from_session_returns_null_when_sub_missing(): void
    {
        $request = Request::create('/');
        $session = $this-&gt;app['session.store'];
        $session-&gt;put('supabase.expires_at', now()-&gt;addHour()-&gt;timestamp);
        $session-&gt;put('supabase.jwt_claims', ['role' =&gt; 'authenticated']);
        $request-&gt;setLaravelSession($session);

        $this-&gt;assertNull((new SupabaseRlsContext())-&gt;claimsFromSession($request));
    }

    public function test_claims_from_session_returns_claims_when_valid(): void
    {
        $request = Request::create('/');
        $session = $this-&gt;app['session.store'];
        $claims = ['sub' =&gt; 'user-1', 'role' =&gt; 'authenticated'];
        $session-&gt;put('supabase.expires_at', now()-&gt;addHour()-&gt;timestamp);
        $session-&gt;put('supabase.jwt_claims', $claims);
        $request-&gt;setLaravelSession($session);

        $this-&gt;assertSame($claims, (new SupabaseRlsContext())-&gt;claimsFromSession($request));
    }

    public function test_run_fails_closed_without_touching_the_database_when_sub_is_missing(): void
    {
        $this-&gt;expectException(RuntimeException::class);

        // No DB connection is faked/mocked here on purpose: if run()
        // ever reached DB::transaction() before this check, this test
        // would error on "no connection configured" rather than assert
        // the RuntimeException — proving the guard really does run
        // first, before any database work.
        (new SupabaseRlsContext())-&gt;run(['role' =&gt; 'authenticated'], fn () =&gt; null);
    }
}

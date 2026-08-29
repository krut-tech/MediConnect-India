<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Named aliases for auth/role gating. 'supabase.auth' and 'guest'
        // are real implementations as of Phase 3 Milestone 1 (Auth
        // Foundation). 'role' is a real implementation as of Phase 4 —
        // see app/Http/Middleware/EnsureUserHasRole.php. 'supabase.rls'
        // is a real implementation as of Phase 5 Step 3 — see
        // app/Http/Middleware/EstablishSupabaseRlsContext.php. It must
        // always be placed AFTER 'supabase.auth' and BEFORE 'role' (or
        // any controller code) in route middleware stacks, since both
        // 'role' and most authenticated controllers run RLS-protected
        // Eloquent queries that need this context to resolve correctly.
        $middleware->trustProxies(at: '*');
        
        $middleware->alias([
            'supabase.auth' => \App\Http\Middleware\VerifySupabaseSession::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'supabase.rls' => \App\Http\Middleware\EstablishSupabaseRlsContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Phase 5.2 post-verification fix (2026-08-29) — production
        // evidence: a browser tab left open on an authenticated page for
        // an extended period (here: ~2 hours) submitted a form whose
        // embedded CSRF token no longer matched any current session,
        // because the backing container had since been replaced (a real
        // deploy, and separately a Render free-tier idle spin-down/
        // spin-up — confirmed via Render logs: instance 5nhmt, where the
        // page was rendered, was gone by the time of the request; the
        // request landed on a later instance, tnls7, whose session store
        // never had that session file). Root cause is session-storage
        // durability across container replacement — NOT a defect in
        // VerifyCsrfToken, the logout route, AuthController, or any
        // Blade form (all independently verified correct; every @csrf
        // token and route wiring is exactly as it should be). That
        // durability gap is explicitly out of scope for this change
        // (no SESSION_DRIVER change, no new infra, no schema).
        //
        // This handler changes ONLY what happens to a browser (non-JSON)
        // request AFTER VerifyCsrfToken has already correctly rejected a
        // stale/mismatched token — replacing Laravel's raw, unbranded
        // 419 "Page Expired" error page with a plain redirect back to
        // /login and a human-readable status message. CSRF verification
        // itself is completely untouched: a mismatched token is still
        // rejected exactly as before; this only makes the resulting,
        // already-rejected request land somewhere useful instead of a
        // dead-end error page. Scoped to TokenMismatchException only —
        // no other exception type's handling is changed.
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if (! $request->expectsJson()) {
                return redirect()->route('login')
                    ->with('status', 'Your session expired. Please sign in again.');
            }
        });
    })->create();

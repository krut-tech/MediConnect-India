<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        //
    })->create();

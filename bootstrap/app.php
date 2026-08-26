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
        // Trust all proxies so Vercel's terminating reverse proxy is
        // recognized and X-Forwarded-Proto is honored. Without this,
        // Laravel sees the internal request as plain HTTP and generates
        // http:// asset/URL links behind Vercel's HTTPS edge, causing
        // mixed-content blocking in the browser.
        $middleware->trustProxies(at: '*');

        // Named aliases for auth/role gating. 'supabase.auth' and 'guest'
        // are real implementations as of Phase 3 Milestone 1 (Auth
        // Foundation). 'role' remains a placeholder — real role/scope
        // enforcement is a later, separate milestone.
        $middleware->alias([
            'supabase.auth' => \App\Http\Middleware\VerifySupabaseSession::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

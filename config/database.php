<?php

use Illuminate\Support\Str;

return [

    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [

        /*
        |----------------------------------------------------------------
        | Supabase PostgreSQL (existing project, source of truth)
        |----------------------------------------------------------------
        |
        | IMPORTANT — architecture not yet finalized (flagged for approval,
        | see MIGRATION_PROGRESS.md "Open decisions"):
        |
        | Supabase enforces authorization via Row-Level Security policies
        | keyed off `auth.uid()`, which PostgREST populates from a request's
        | JWT (`request.jwt.claims`). A plain Laravel DB connection over TCP
        | does NOT get this for free — two credible approaches exist, and
        | picking one is a security-relevant decision this project's rules
        | require stopping for, not guessing at:
        |
        |   (A) Direct Postgres connection using a scoped, non-service-role
        |       Postgres role, with Laravel issuing
        |       `SET LOCAL request.jwt.claims = '...'` (or the equivalent
        |       session GUCs) at the start of each request/transaction so
        |       existing RLS policies evaluate `auth.uid()` correctly.
        |
        |   (B) Route authenticated reads/writes through Supabase's
        |       PostgREST/REST API (or targeted RPC/Edge Functions) using
        |       the end user's Supabase Auth JWT per-request, and reserve a
        |       direct Postgres connection (if any) for narrowly-scoped,
        |       explicitly-audited operations only.
        |
        | Neither is wired up yet. This connection block is a placeholder
        | pointing at env vars — no credentials are present in this
        | repository, and none should ever be committed or pasted into
        | chat. Do not point this at the service_role key "to make it
        | work" — that would bypass RLS platform-wide and is exactly the
        | kind of change this project requires explicit approval for.
        |
        | Phase 5 Step 2 note: this connection now goes through Supabase's
        | Transaction Pooler (PgBouncer, transaction mode, port 6543) via a
        | dedicated non-superuser role (`mediconnect_app`). Transaction-mode
        | pooling does not support server-side prepared statements shared
        | across pooled connections, so `options` below forces PDO to
        | emulate prepares client-side. This is an infra-compatibility
        | setting only — it does not change, weaken, or bypass RLS, and the
        | Option A/B decision above is still open.
        |
        */
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'postgres'),
            'username' => env('DB_USERNAME', ''),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'require'),
            'options' => [
                \PDO::ATTR_EMULATE_PREPARES => true,
            ],
        ],

        // Local-only fallback for running framework-level checks (routing,
        // Blade rendering, artisan commands) without ever touching the real
        // Supabase database. Never used in staging/production.
        'sqlite_testing' => [
            'driver' => 'sqlite',
            'database' => env('DB_TESTING_DATABASE', database_path('testing.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],

    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => Str::slug(env('APP_NAME', 'mediconnect'), '_').'_database_',
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

    ],

];

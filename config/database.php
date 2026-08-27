&lt;?php

use Illuminate\Support\Str;

return [

    'default' =&gt; env('DB_CONNECTION', 'pgsql'),

    'connections' =&gt; [

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
        'pgsql' =&gt; [
            'driver' =&gt; 'pgsql',
            'url' =&gt; env('DATABASE_URL'),
            'host' =&gt; env('DB_HOST', '127.0.0.1'),
            'port' =&gt; env('DB_PORT', '5432'),
            'database' =&gt; env('DB_DATABASE', 'postgres'),
            'username' =&gt; env('DB_USERNAME', ''),
            'password' =&gt; env('DB_PASSWORD', ''),
            'charset' =&gt; 'utf8',
            'prefix' =&gt; '',
            'prefix_indexes' =&gt; true,
            'search_path' =&gt; 'public',
            'sslmode' =&gt; env('DB_SSLMODE', 'require'),
            'options' =&gt; [
                \PDO::ATTR_EMULATE_PREPARES =&gt; true,
            ],
        ],

        // Local-only fallback for running framework-level checks (routing,
        // Blade rendering, artisan commands) without ever touching the real
        // Supabase database. Never used in staging/production.
        'sqlite_testing' =&gt; [
            'driver' =&gt; 'sqlite',
            'database' =&gt; env('DB_TESTING_DATABASE', database_path('testing.sqlite')),
            'prefix' =&gt; '',
            'foreign_key_constraints' =&gt; true,
        ],

    ],

    'migrations' =&gt; [
        'table' =&gt; 'migrations',
        'update_date_on_publish' =&gt; true,
    ],

    'redis' =&gt; [

        'client' =&gt; env('REDIS_CLIENT', 'phpredis'),

        'options' =&gt; [
            'cluster' =&gt; env('REDIS_CLUSTER', 'redis'),
            'prefix' =&gt; Str::slug(env('APP_NAME', 'mediconnect'), '_').'_database_',
        ],

        'default' =&gt; [
            'url' =&gt; env('REDIS_URL'),
            'host' =&gt; env('REDIS_HOST', '127.0.0.1'),
            'username' =&gt; env('REDIS_USERNAME'),
            'password' =&gt; env('REDIS_PASSWORD'),
            'port' =&gt; env('REDIS_PORT', '6379'),
            'database' =&gt; env('REDIS_DB', '0'),
        ],

    ],

];

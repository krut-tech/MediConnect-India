<?php

return [

    // Default guard is intentionally left generic. The real authentication
    // mechanism (validating Supabase Auth JWTs, mapping auth.users -> our
    // public.users/staff_assignments/patients) is a Phase 3+ decision, not
    // implemented in Phase 2. See App\Http\Middleware\VerifySupabaseSession
    // for the placeholder this will grow into.
    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,

];

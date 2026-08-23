<?php

return [

    // Supabase project config — read here, values sourced from .env only.
    // No secrets are ever hardcoded or committed.
    'supabase' => [
        'url' => env('SUPABASE_URL'),
        'anon_key' => env('SUPABASE_ANON_KEY'),
        // service_role key deliberately has no config entry here. If a
        // privileged operation is ever genuinely required, that is a
        // stop-and-ask situation per project rules, not a config default.
        'jwt_secret' => env('SUPABASE_JWT_SECRET'),
    ],

];

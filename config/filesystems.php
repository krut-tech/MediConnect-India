<?php

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        // Supabase Storage bucket for `documents` table content
        // (lab reports, prescriptions, ID proofs, consent forms).
        // Wired via the S3-compatible driver Supabase Storage exposes.
        // Not yet configured with real credentials — placeholder only.
        'supabase' => [
            'driver' => 's3',
            'key' => env('SUPABASE_STORAGE_KEY'),
            'secret' => env('SUPABASE_STORAGE_SECRET'),
            'region' => env('SUPABASE_STORAGE_REGION', 'ap-south-1'),
            'bucket' => env('SUPABASE_STORAGE_BUCKET', 'documents'),
            'endpoint' => env('SUPABASE_STORAGE_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'throw' => false,
        ],

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];

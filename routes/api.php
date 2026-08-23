<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — empty in Phase 2.
|--------------------------------------------------------------------------
|
| Reserved for future module APIs (e.g. appointment availability lookups,
| notification webhooks). Nothing to register yet.
|
*/

Route::get('/ping', function () {
    return response()->json(['status' => 'ok', 'app' => config('app.name')]);
});

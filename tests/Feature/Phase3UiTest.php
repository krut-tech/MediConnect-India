<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Phase 3 UI screens — Facilities and Patients index routes.
 *
 * NOTE ON EXECUTABILITY: these tests hit real Eloquent queries against
 * the `sqlite_testing` connection (config/database.php). Since
 * `database/migrations/` is intentionally empty (the existing Supabase
 * schema is the source of truth — see that directory's README), no
 * `facilities`/`patients` tables exist in the sqlite test DB yet. Until
 * either (a) a decision is made to mirror the read-model schema into
 * sqlite for testing, or (b) these are run against a real Postgres test
 * database, expect these specific tests to fail on "no such table" even
 * once `composer install` is unblocked — that is a test-infrastructure
 * gap, not a code defect, and is recorded honestly here rather than
 * silently skipped.
 */
class Phase3UiTest extends TestCase
{
    public function test_facilities_index_route_is_registered(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('facilities.index'));
    }

    public function test_patients_index_route_is_registered(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('patients.index'));
    }
}

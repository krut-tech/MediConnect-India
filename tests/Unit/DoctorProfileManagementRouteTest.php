<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * PHASE 6.1-B — structural guard for the new admin-side doctor-profile
 * routes. Pure file-content check (no DB/app boot, no HTTP kernel),
 * same pattern as StaffRouteOrderTest from Phase 6.1-A: confirms the
 * routes exist with the expected HTTP verbs and sit inside the 'role'
 * middleware group, so a future edit can't accidentally move them
 * outside staff-only reachability or drop one of the two actions.
 */
class DoctorProfileManagementRouteTest extends TestCase
{
    private function routesFileContents(): string
    {
        $path = __DIR__.'/../../routes/web.php';

        $this->assertFileExists($path, 'routes/web.php should exist at the expected path.');

        return file_get_contents($path);
    }

    public function test_edit_route_is_registered_as_get(): void
    {
        $contents = $this->routesFileContents();

        $this->assertStringContainsString(
            "Route::get('/doctors/manage/{user}/edit', [DoctorController::class, 'editProfile'])",
            $contents,
            "GET /doctors/manage/{user}/edit -> DoctorController::editProfile() must be registered."
        );
    }

    public function test_update_route_is_registered_as_patch(): void
    {
        $contents = $this->routesFileContents();

        $this->assertStringContainsString(
            "Route::patch('/doctors/manage/{user}', [DoctorController::class, 'updateProfile'])",
            $contents,
            "PATCH /doctors/manage/{user} -> DoctorController::updateProfile() must be registered."
        );
    }

    /**
     * Both new routes must sit inside the 'role' middleware group (only
     * a signed-in staff member should reach the form) — checked here by
     * confirming both declarations appear after the group's opening
     * line and before its closing (the last '});' in the file, which
     * closes the 'role' group followed by the outer group).
     */
    public function test_both_routes_sit_inside_the_role_middleware_group(): void
    {
        $contents = $this->routesFileContents();

        $roleGroupStart = strpos($contents, "Route::middleware(['role'])->group(function () {");
        $this->assertNotFalse($roleGroupStart, "The 'role' middleware group was not found.");

        $editPosition = strpos($contents, "'/doctors/manage/{user}/edit'");
        $updatePosition = strpos($contents, "'/doctors/manage/{user}'");

        $this->assertNotFalse($editPosition);
        $this->assertNotFalse($updatePosition);
        $this->assertGreaterThan(
            $roleGroupStart,
            $editPosition,
            "'/doctors/manage/{user}/edit' must be declared inside the 'role' middleware group."
        );
        $this->assertGreaterThan(
            $roleGroupStart,
            $updatePosition,
            "'/doctors/manage/{user}' must be declared inside the 'role' middleware group."
        );
    }
}

<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * PRE-MERGE AUDIT (Phase 6.1-A), item 5 — "route ordering so
 * /staff/{staff} cannot interfere with /staff/create or /staff/lookup".
 *
 * Laravel's router matches routes in registration order for the first
 * pattern that fits a request, so /staff/create and /staff/lookup only
 * resolve correctly for as long as they stay registered BEFORE the
 * catch-all /staff/{staff}. This is a plain string-position check
 * against the routes file itself — no app/container/DB boot, no HTTP
 * kernel — specifically so it keeps catching a future regression (e.g.
 * someone reordering routes/web.php) even in an environment where the
 * full Feature-test suite can't run (see PHASE_6_1_A_AUDIT.md).
 */
class StaffRouteOrderTest extends TestCase
{
    private function routesFileContents(): string
    {
        $path = __DIR__.'/../../routes/web.php';

        $this->assertFileExists($path, 'routes/web.php should exist at the expected path.');

        return file_get_contents($path);
    }

    public function test_staff_create_is_registered_before_staff_wildcard(): void
    {
        $contents = $this->routesFileContents();

        $createPosition = strpos($contents, "'/staff/create'");
        $wildcardPosition = strpos($contents, "'/staff/{staff}'");

        $this->assertNotFalse($createPosition, "Route '/staff/create' was not found in routes/web.php.");
        $this->assertNotFalse($wildcardPosition, "Route '/staff/{staff}' was not found in routes/web.php.");
        $this->assertLessThan(
            $wildcardPosition,
            $createPosition,
            "'/staff/create' must be registered before '/staff/{staff}', or the wildcard route will swallow it."
        );
    }

    public function test_staff_lookup_is_registered_before_staff_wildcard(): void
    {
        $contents = $this->routesFileContents();

        $lookupPosition = strpos($contents, "'/staff/lookup'");
        $wildcardPosition = strpos($contents, "'/staff/{staff}'");

        $this->assertNotFalse($lookupPosition, "Route '/staff/lookup' was not found in routes/web.php.");
        $this->assertNotFalse($wildcardPosition, "Route '/staff/{staff}' was not found in routes/web.php.");
        $this->assertLessThan(
            $wildcardPosition,
            $lookupPosition,
            "'/staff/lookup' must be registered before '/staff/{staff}', or the wildcard route will swallow it."
        );
    }

    public function test_staff_wildcard_route_is_declared_with_trashed(): void
    {
        $contents = $this->routesFileContents();

        $wildcardLineStart = strpos($contents, "Route::get('/staff/{staff}'");
        $this->assertNotFalse($wildcardLineStart, "Route '/staff/{staff}' declaration not found.");

        // Look only at this specific route's declaration line/statement,
        // not the whole file, so this can't accidentally pass because
        // ->withTrashed() appears elsewhere for a different route.
        $statementEnd = strpos($contents, ';', $wildcardLineStart);
        $statement = substr($contents, $wildcardLineStart, $statementEnd - $wildcardLineStart);

        $this->assertStringContainsString(
            '->withTrashed()',
            $statement,
            "'/staff/{staff}' must be declared ->withTrashed() so a soft-deleted assignment resolves to its row (for the 'Deleted' status) instead of always 404ing."
        );
    }
}

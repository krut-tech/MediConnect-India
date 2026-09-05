<?php

namespace Tests\Unit;

use App\Models\StaffAssignment;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * PHASE 6.1-A — pure-logic unit test for StaffAssignment::displayStatus().
 * No DB, no RLS, no HTTP — only checks the deleted_at/valid_from/
 * valid_until -> status derivation described in the model's own
 * docblock. Extends PHPUnit's TestCase directly (not Laravel's), since
 * this needs no app/container/DB boot at all.
 */
class StaffAssignmentDisplayStatusTest extends TestCase
{
    private function makeAssignment(?string $validFrom, ?string $validUntil, ?string $deletedAt): StaffAssignment
    {
        $assignment = new StaffAssignment();
        $assignment->valid_from = $validFrom ? Carbon::parse($validFrom) : null;
        $assignment->valid_until = $validUntil ? Carbon::parse($validUntil) : null;
        $assignment->deleted_at = $deletedAt ? Carbon::parse($deletedAt) : null;

        return $assignment;
    }

    public function test_active_when_within_validity_window_and_not_deleted(): void
    {
        $assignment = $this->makeAssignment('-1 day', '+1 day', null);

        $this->assertSame('active', $assignment->displayStatus());
    }

    public function test_active_when_no_valid_from_and_no_valid_until(): void
    {
        $assignment = $this->makeAssignment(null, null, null);

        $this->assertSame('active', $assignment->displayStatus());
    }

    public function test_future_when_valid_from_is_after_now(): void
    {
        $assignment = $this->makeAssignment('+1 day', null, null);

        $this->assertSame('future', $assignment->displayStatus());
    }

    public function test_expired_when_valid_until_has_passed(): void
    {
        $assignment = $this->makeAssignment('-2 days', '-1 day', null);

        $this->assertSame('expired', $assignment->displayStatus());
    }

    public function test_deleted_takes_priority_over_future_or_expired(): void
    {
        $assignment = $this->makeAssignment('-2 days', '-1 day', '-3 days');

        $this->assertSame('deleted', $assignment->displayStatus());

        $futureButDeleted = $this->makeAssignment('+1 day', null, '-1 hour');

        $this->assertSame('deleted', $futureButDeleted->displayStatus());
    }

    public function test_valid_until_exactly_now_counts_as_expired(): void
    {
        $now = Carbon::now();
        $assignment = new StaffAssignment();
        $assignment->valid_from = $now->copy()->subDay();
        $assignment->valid_until = $now;
        $assignment->deleted_at = null;

        $this->assertSame('expired', $assignment->displayStatus());
    }
}

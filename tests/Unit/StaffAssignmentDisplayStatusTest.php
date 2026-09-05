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

    /**
     * PRE-MERGE AUDIT (Phase 6.1-A) — boundary case: valid_from exactly
     * "now" must NOT be treated as future, since displayStatus() uses a
     * strict > comparison for the future check. By the time
     * displayStatus() runs, real now() is always fractionally later
     * than a $validFrom fixed a moment earlier, so this exercises the
     * same boundary as test_valid_until_exactly_now_counts_as_expired()
     * but for the future check instead.
     */
    public function test_valid_from_at_or_before_now_is_not_future(): void
    {
        $assignment = new StaffAssignment();
        $assignment->valid_from = Carbon::now();
        $assignment->valid_until = null;
        $assignment->deleted_at = null;

        $this->assertSame('active', $assignment->displayStatus());
    }

    /**
     * PRE-MERGE AUDIT (Phase 6.1-A) — a row can have valid_from in the
     * future AND a valid_until further in the future (e.g. a fixed-term
     * assignment scheduled ahead of time). The future check must win
     * over any valid_until comparison — a row that hasn't started yet
     * is never "expired".
     */
    public function test_future_wins_over_valid_until_when_both_are_ahead(): void
    {
        $assignment = $this->makeAssignment('+2 days', '+10 days', null);

        $this->assertSame('future', $assignment->displayStatus());
    }

    /**
     * PRE-MERGE AUDIT (Phase 6.1-A) — deleted_at alone (no valid_from/
     * valid_until at all) must still resolve to 'deleted', not 'active'.
     */
    public function test_deleted_with_no_validity_window_set(): void
    {
        $assignment = $this->makeAssignment(null, null, '-1 hour');

        $this->assertSame('deleted', $assignment->displayStatus());
    }
}

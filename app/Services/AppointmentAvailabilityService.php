<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase 6 Workstream 2 — computes REAL, dynamic appointment availability.
 *
 * Deliberately thin: all the actual business logic (theoretical slots
 * from the doctor's published schedule, minus approved leave, minus
 * existing non-cancelled/no-show bookings, minus already-past slots,
 * minus overlap) lives in the database-side
 * `public.appt_available_slots(doctor_user_id, facility_id, date)`
 * SQL function (SECURITY DEFINER, added this session — see the Phase 6
 * Workstream 2 report for its full definition and why it has to be a
 * DB-side function rather than an Eloquent query: a plain query from a
 * patient's own RLS-scoped connection cannot see other patients'
 * appt_bookings rows, so "which slots are already taken" cannot be
 * correctly computed in PHP for anyone except facility staff).
 *
 * This class exists only to call that function and shape the result
 * into Carbon instances for controllers/views — it must NEVER
 * reimplement the slot-generation/exclusion logic itself, which would
 * create a second, driftable source of truth for a patient-safety rule
 * (no double-booking, no expired-leave slots, no past slots).
 */
class AppointmentAvailabilityService
{
    private const FACILITY_TIMEZONE = 'Asia/Kolkata';

    /**
     * @return Collection<int, array{start: Carbon, end: Carbon, duration_minutes: int}>
     */
    public function availableSlots(string $doctorUserId, string $facilityId, CarbonInterface $date): Collection
    {
        $rows = DB::select(
            'select slot_start, slot_end from public.appt_available_slots(?, ?, ?)',
            [$doctorUserId, $facilityId, $date->format('Y-m-d')]
        );

        return collect($rows)->map(function ($row) {
            $start = Carbon::parse($row->slot_start)->setTimezone(self::FACILITY_TIMEZONE);
            $end = Carbon::parse($row->slot_end)->setTimezone(self::FACILITY_TIMEZONE);

            return [
                'start' => $start,
                'end' => $end,
                'duration_minutes' => (int) $start->diffInMinutes($end),
            ];
        });
    }

    /**
     * Re-verifies that $scheduledAt is one of the CURRENTLY real,
     * available slots for this doctor/facility — called at booking
     * time, never trusting a slot start/duration the client merely
     * echoed back from an earlier page load, since availability can
     * have changed (another booking, leave added, etc.) between when
     * the form was rendered and when it was submitted.
     *
     * @return array{start: Carbon, end: Carbon, duration_minutes: int}|null
     */
    public function findSlot(string $doctorUserId, string $facilityId, CarbonInterface $scheduledAt): ?array
    {
        $target = $scheduledAt instanceof Carbon ? $scheduledAt : Carbon::parse((string) $scheduledAt);
        $target = $target->clone()->setTimezone(self::FACILITY_TIMEZONE);

        return $this->availableSlots($doctorUserId, $facilityId, $target)
            ->first(fn (array $slot) => $slot['start']->equalTo($target));
    }
}

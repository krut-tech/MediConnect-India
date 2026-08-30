<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppointmentBookingRequest;
use App\Models\AppointmentAvailability;
use App\Models\AppointmentBooking;
use App\Models\DoctorProfile;
use App\Models\Patient;
use App\Services\AppointmentAvailabilityService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Phase 6 Workstream 2 — Appointment Engine foundation.
 *
 * ============================================================
 * WHY NO SLOT IS EVER HARD-CODED
 * ============================================================
 * Every available slot shown/accepted here is computed live from the
 * doctor's actual appt_availability schedule, approved staff_leave, and
 * existing appt_bookings via AppointmentAvailabilityService (backed by
 * the DB-side appt_available_slots() function). There is no fallback
 * list of "default" times anywhere in this class.
 *
 * ============================================================
 * TWO INDEPENDENT SAFETY LAYERS ON BOOKING
 * ============================================================
 * 1. Re-check: store() calls findSlot() again at submit time (not the
 *    slot the form happened to render minutes/hours earlier) — catches
 *    the ordinary case (slot taken since page load) with a normal
 *    validation error.
 * 2. DB exclusion constraint: even if (1) somehow raced with another
 *    request between the check and the INSERT, `appt_bookings_no_
 *    double_booking` makes the actual double-booking impossible at the
 *    database — the loser gets SQLSTATE 23P01, translated below into
 *    the same ordinary error, never a 500.
 * Idempotency (retry/double-submit/back-button) is handled by the
 * (booked_by, idempotency_key) unique index — SQLSTATE 23505.
 */
class AppointmentController extends Controller
{
    private const FACILITY_TIMEZONE = 'Asia/Kolkata';

    /**
     * Bookings visible to the signed-in user. No manual facility/
     * patient/doctor where-clause is added — appt_bookings_select_own /
     * _doctor / _facility_staff RLS is what actually decides which rows
     * come back, exactly like every other index() in this app.
     */
    public function index(): View
    {
        $bookings = AppointmentBooking::query()
            ->with(['patient.user', 'doctorUser', 'facility'])
            ->orderBy('scheduled_at', 'desc')
            ->paginate(15);

        return view('appointments.index', [
            'bookings' => $bookings,
        ]);
    }

    /**
     * Booking form for a specific doctor: pick a published facility,
     * pick a date, see the real currently-available slots for that
     * exact combination.
     */
    public function create(DoctorProfile $doctor, Request $request, AppointmentAvailabilityService $availability): View
    {
        $doctor->loadMissing('user');

        $facilities = AppointmentAvailability::query()
            ->where('doctor_user_id', $doctor->user_id)
            ->whereNull('deleted_at')
            ->with('facility')
            ->get()
            ->pluck('facility')
            ->filter()
            ->unique('id')
            ->values();

        $requestedFacilityId = $request->query('facility_id');
        $selectedFacilityId = $facilities->contains('id', $requestedFacilityId)
            ? $requestedFacilityId
            : $facilities->first()?->id;

        $date = $this->resolveRequestedDate($request->query('date'));

        $slots = $selectedFacilityId
            ? $availability->availableSlots($doctor->user_id, $selectedFacilityId, $date)
            : collect();

        return view('appointments.create', [
            'doctor' => $doctor,
            'facilities' => $facilities,
            'selectedFacilityId' => $selectedFacilityId,
            'date' => $date,
            'slots' => $slots,
            'canBookForOthers' => Auth::user()?->hasActiveStaffAssignment() ?? false,
        ]);
    }

    public function store(StoreAppointmentBookingRequest $request, AppointmentAvailabilityService $availability): RedirectResponse
    {
        $data = $request->validated();
        $actor = Auth::user();

        $patientId = $this->resolvePatientId($data['patient_mrn'] ?? null);

        if ($patientId === null) {
            return back()
                ->withErrors(['patient_mrn' => 'A valid patient MRN is required to book this appointment.'])
                ->withInput();
        }

        $requestedAt = Carbon::parse($data['scheduled_at'])->setTimezone(self::FACILITY_TIMEZONE);

        // Authoritative re-check — never trust a slot the client merely
        // echoed back from an earlier page render.
        $slot = $availability->findSlot($data['doctor_user_id'], $data['facility_id'], $requestedAt);

        if (! $slot) {
            return back()
                ->withErrors(['scheduled_at' => 'That slot is no longer available. Please choose another time.'])
                ->withInput();
        }

        try {
            AppointmentBooking::query()->create([
                'patient_id' => $patientId,
                'doctor_user_id' => $data['doctor_user_id'],
                'facility_id' => $data['facility_id'],
                'scheduled_at' => $slot['start'],
                'duration_minutes' => $slot['duration_minutes'],
                'appt_type' => $data['appt_type'],
                'status' => 'booked',
                'booked_by' => $actor->id,
                'idempotency_key' => $data['idempotency_key'],
            ]);
        } catch (QueryException $e) {
            return $this->handleBookingFailure($e, $actor->id, $data['idempotency_key']);
        }

        return redirect()->route('appointments.index')->with('status', 'Appointment booked.');
    }

    /**
     * Cancel — reachable only if RLS resolves this booking for the
     * signed-in user at all (own booking, the doctor on it, or facility
     * staff in scope); implicit route-model binding 404s otherwise,
     * same pattern as every other show()/update() in this app. Whether
     * the UPDATE actually takes effect is read from the affected-row
     * count, never assumed from Eloquent's return value — matching
     * PatientController::applyScopedUpdate()'s established pattern.
     */
    public function cancel(AppointmentBooking $booking): RedirectResponse
    {
        if (in_array($booking->status, ['cancelled', 'completed', 'no_show'], true)) {
            return redirect()->route('appointments.index')->with('status', 'This appointment is already closed out.');
        }

        $affected = AppointmentBooking::query()->whereKey($booking->getKey())->update(['status' => 'cancelled']);

        if ($affected === 0) {
            return back()->withErrors(['cancel' => 'This appointment could not be cancelled.']);
        }

        return redirect()->route('appointments.index')->with('status', 'Appointment cancelled.');
    }

    /**
     * Resolves the actual patient_id for this booking without EVER
     * accepting one directly from request input:
     *   - If the signed-in user has their own patient record, they are
     *     always booking for themselves — the MRN field (if any was
     *     submitted) is ignored for this path.
     *   - Otherwise, an MRN is required, and is looked up through the
     *     ordinary RLS-scoped Patient query — the exact same query any
     *     other staff-facing screen in this app would run, so it can
     *     only ever resolve to a patient this user is already permitted
     *     to see (patients_select_registering_facility /
     *     _assigned_doctor). A not-found result here is therefore
     *     indistinguishable from "not authorized", which is the correct,
     *     non-leaking behavior — not a bug.
     */
    private function resolvePatientId(?string $mrn): ?string
    {
        $ownPatient = Auth::user()?->patient()->first();

        if ($ownPatient) {
            return $ownPatient->id;
        }

        if (! $mrn) {
            return null;
        }

        return Patient::query()->where('mrn', $mrn)->value('id');
    }

    private function resolveRequestedDate(?string $raw): CarbonImmutable
    {
        $today = CarbonImmutable::now(self::FACILITY_TIMEZONE)->startOfDay();

        if (! $raw) {
            return $today;
        }

        try {
            $requested = CarbonImmutable::createFromFormat('!Y-m-d', $raw, self::FACILITY_TIMEZONE)->startOfDay();
        } catch (Throwable $e) {
            return $today;
        }

        // Never show/allow booking a past date — zero availability by
        // definition. Clamping (rather than erroring) keeps a stale
        // bookmarked or back-button URL usable instead of a dead end.
        return $requested->lessThan($today) ? $today : $requested;
    }

    /**
     * Translates the two specific, expected Postgres failure modes for
     * this INSERT into ordinary validation errors — never a raw
     * SQLSTATE/stack trace reaching the user (Step 24). Any other
     * QueryException (e.g. an RLS WITH CHECK rejection, SQLSTATE 42501,
     * for a facility_id the actor isn't actually scoped to) falls
     * through to a generic, still-human-readable failure.
     */
    private function handleBookingFailure(QueryException $e, string $actorId, string $idempotencyKey): RedirectResponse
    {
        $sqlState = $e->errorInfo[0] ?? null;

        if ($sqlState === '23505') {
            // Duplicate submit of the same rendered form (double-click,
            // browser back + resubmit, network retry) — not an error,
            // the original booking already exists.
            $existing = AppointmentBooking::query()
                ->where('booked_by', $actorId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            return redirect()->route('appointments.index')
                ->with('status', $existing ? 'Appointment already booked.' : 'This appointment could not be booked.');
        }

        if ($sqlState === '23P01') {
            // Lost the race — someone else booked the exact same
            // doctor/time between our findSlot() check and this INSERT.
            return back()
                ->withErrors(['scheduled_at' => 'That slot was just booked by someone else. Please choose another time.'])
                ->withInput();
        }

        return back()
            ->withErrors(['booking' => 'This appointment could not be booked.'])
            ->withInput();
    }
}

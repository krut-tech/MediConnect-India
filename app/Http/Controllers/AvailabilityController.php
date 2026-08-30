<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAvailabilityRequest;
use App\Models\AppointmentAvailability;
use App\Models\DoctorProfile;
use App\Models\Facility;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;

/**
 * Phase 6 correction — schedule/availability management foundation
 * (spec item 5). This is deliberately the SAME table Workstream 2's
 * booking engine already reads from (public.appt_availability, via
 * App\Models\AppointmentAvailability) — no new table, index, or
 * function. What was missing was any write path at all; the RLS
 * policy that authorizes writes to it (appt_availability_write_doctor)
 * was already live before this controller existed, verified this
 * session:
 *   doctor_user_id = auth.uid()
 *   OR is_super_admin()
 *   OR user_has_facility_role(facility_id, 'hospital_admin')
 * That is the actual, sole authority on whether a given write succeeds
 * — this controller adds no parallel authorization logic, only route
 * gating (the existing 'role' middleware group) to keep a plain patient
 * account from even reaching the form.
 *
 * Deliberately NOT covered by this foundation (see PHASE 6 correction
 * report — out of scope for this pass, not silently skipped):
 *   - a dedicated leave/blocked-period UI (staff_leave already exists
 *     and is already excluded by AppointmentAvailabilityService/
 *     appt_available_slots() when computing live slots — only a
 *     management screen for it is missing)
 *   - editing an existing row (only create + soft-delete/deactivate)
 */
class AvailabilityController extends Controller
{
    /**
     * A doctor's schedule rows plus the create form. Reachable by the
     * doctor themselves, an in-scope hospital_admin, or a super_admin —
     * enforced by RLS (see class docblock), not here. For anyone else,
     * the SELECT this runs (appt_availability_select_public: deleted_at
     * IS NULL) still only shows already-published rows, same as the
     * booking form's facility discovery query — it does not leak
     * inactive/soft-deleted schedule rows to someone outside the write
     * policy.
     */
    public function index(DoctorProfile $doctor): View
    {
        $doctor->loadMissing('user');

        $availability = AppointmentAvailability::query()
            ->where('doctor_user_id', $doctor->user_id)
            ->whereNull('deleted_at')
            ->with('facility')
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        $facilities = Facility::query()
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name']);

        return view('availability.index', [
            'doctor' => $doctor,
            'availability' => $availability,
            'facilities' => $facilities,
        ]);
    }

    /**
     * Creates one recurring weekly schedule row. doctor_user_id is set
     * from the route-bound DoctorProfile only — never from request
     * input, so this can't be used to write a schedule row for a
     * different doctor than the one in the URL no matter what the form
     * body contains. Whether that doctor_user_id is one this caller is
     * actually allowed to write for is decided entirely by
     * appt_availability_write_doctor RLS: a caller who fails it gets a
     * QueryException (SQLSTATE 42501), caught below and turned into an
     * ordinary validation error — never a raw 500 or stack trace,
     * matching AppointmentController::handleBookingFailure()'s
     * established pattern in this app.
     */
    public function store(DoctorProfile $doctor, StoreAvailabilityRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            AppointmentAvailability::query()->create([
                'doctor_user_id' => $doctor->user_id,
                'facility_id' => $data['facility_id'],
                'day_of_week' => $data['day_of_week'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'slot_duration_minutes' => $data['slot_duration_minutes'],
                'valid_from' => $data['valid_from'],
                'valid_until' => $data['valid_until'] ?? null,
            ]);
        } catch (QueryException $e) {
            return back()
                ->withErrors(['schedule' => 'This schedule could not be saved. You may not be authorized to manage this doctor\'s schedule at this facility.'])
                ->withInput();
        }

        return redirect()->route('doctors.schedule', $doctor)->with('status', 'Schedule published.');
    }

    /**
     * Deactivates (soft-deletes) one schedule row — the "disable
     * schedule" action from the spec. Uses the affected-row-count
     * pattern established by PatientController::applyScopedUpdate() /
     * AppointmentController::cancel(), not Eloquent's delete() return
     * value, since RLS can silently match 0 rows rather than raising.
     * Already-booked appointments against this schedule are untouched —
     * this only stops new slots from being generated against this row
     * going forward (appt_available_slots() only reads rows with
     * deleted_at IS NULL); it is not a cancellation of existing
     * appt_bookings, which remains AppointmentController::cancel()'s
     * job alone.
     */
    public function destroy(AppointmentAvailability $availability): RedirectResponse
    {
        $affected = AppointmentAvailability::query()
            ->whereKey($availability->getKey())
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);

        if ($affected === 0) {
            return back()->withErrors(['schedule' => 'This schedule entry could not be removed.']);
        }

        return redirect()->route('doctors.schedule', ['doctor' => $availability->doctor_user_id])
            ->with('status', 'Schedule entry removed.');
    }
}

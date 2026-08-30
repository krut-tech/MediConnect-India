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
 * PHASE 6 FINALIZATION adds edit() / update() (Schedule Edit, item 1).
 * Same RLS, same table, same request class as store() — the only new
 * behavior is a WHERE-key UPDATE instead of an INSERT. facility_id
 * changes are allowed (a doctor/admin correcting which facility a slot
 * belongs to) — the WITH CHECK clause of appt_availability_write_doctor
 * re-evaluates against the NEW facility_id on every UPDATE, so moving a
 * row to a facility the caller isn't authorized for is rejected by
 * Postgres itself (SQLSTATE 42501), not assumed safe by this
 * controller.
 *
 * PHASE 6 FINALIZATION production-issue fix: update()/destroy() were
 * redirecting to route('doctors.schedule', ['doctor' =>
 * $availability->doctor_user_id]) — but {doctor} binds to
 * DoctorProfile::id (its own gen_random_uuid() primary key, verified
 * live via information_schema), NOT users.id. doctor_profiles.user_id
 * is UNIQUE (verified live via pg_constraint), so exactly one
 * DoctorProfile exists per doctor_user_id; resolveDoctorProfileId()
 * below looks it up rather than passing the wrong id and 404ing after
 * every save. index()/store() were unaffected — they already receive
 * the correct DoctorProfile via route-model binding directly.
 *
 * Leave/blocked-period management is a separate concern — see
 * LeaveController (staff_leave), not this controller.
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
     * Edit form for one existing schedule row. Implicit route-model
     * binding: if RLS (appt_availability_select_public: deleted_at IS
     * NULL) hides this row, or the row was already deactivated, the
     * underlying SELECT returns no rows -> 404, same pattern as every
     * other show()/edit() in this app. This does NOT independently
     * check whether the caller is allowed to WRITE this row — that is
     * update()'s job (RLS is only consulted on the actual UPDATE) — so
     * a caller who can see a row (public, per appt_availability_
     * select_public) but not write it will simply have their save
     * rejected on submit, same as store() above.
     */
    public function edit(AppointmentAvailability $availability): View
    {
        $availability->loadMissing('doctorUser');

        $facilities = Facility::query()
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name']);

        return view('availability.edit', [
            'availability' => $availability,
            'facilities' => $facilities,
            'doctorProfileId' => $this->resolveDoctorProfileId($availability),
        ]);
    }

    /**
     * Updates one existing schedule row in place — the "update
     * schedule" action from the spec (distinct from destroy()'s
     * "disable schedule"). doctor_user_id is deliberately never part of
     * $data/the update payload — StoreAvailabilityRequest's rules don't
     * include it, so a row can never be reassigned to a different
     * doctor through this form. Uses the same affected-row-count
     * pattern as destroy()/PatientController::applyScopedUpdate(): a
     * caller outside appt_availability_write_doctor's WITH CHECK for
     * either the row's current facility_id or the newly-submitted one
     * gets 0 affected rows, reported as an ordinary error, never
     * silently treated as success.
     */
    public function update(AppointmentAvailability $availability, StoreAvailabilityRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            $affected = AppointmentAvailability::query()
                ->whereKey($availability->getKey())
                ->whereNull('deleted_at')
                ->update([
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
                ->withErrors(['schedule' => 'This schedule could not be updated. You may not be authorized to manage this doctor\'s schedule at this facility.'])
                ->withInput();
        }

        if ($affected === 0) {
            return back()
                ->withErrors(['schedule' => 'This schedule entry could not be updated.'])
                ->withInput();
        }

        return redirect()->route('doctors.schedule', ['doctor' => $this->resolveDoctorProfileId($availability)])
            ->with('status', 'Schedule updated.');
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
        $doctorProfileId = $this->resolveDoctorProfileId($availability);

        $affected = AppointmentAvailability::query()
            ->whereKey($availability->getKey())
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);

        if ($affected === 0) {
            return back()->withErrors(['schedule' => 'This schedule entry could not be removed.']);
        }

        return redirect()->route('doctors.schedule', ['doctor' => $doctorProfileId])
            ->with('status', 'Schedule entry removed.');
    }

    /**
     * {doctor} on the schedule routes binds to DoctorProfile::id (its
     * own primary key) — NOT users.id. $availability->doctor_user_id is
     * a users.id, so it can never be passed directly as the route
     * parameter (verified live: doctor_profiles.id defaults to
     * gen_random_uuid(), a separate column from user_id). Falls back to
     * the raw doctor_user_id only if no DoctorProfile row exists yet
     * (edge case: a doctor with schedule rows but who has since deleted
     * their public profile) so the redirect still resolves to
     * /doctors/{doctor}/schedule via SOME identifier rather than
     * throwing — that route's own DoctorProfile binding will then 404
     * naturally, which is the correct, honest outcome for that edge
     * case rather than this method masking it.
     */
    private function resolveDoctorProfileId(AppointmentAvailability $availability): string
    {
        return DoctorProfile::query()
            ->where('user_id', $availability->doctor_user_id)
            ->value('id') ?? $availability->doctor_user_id;
    }
}

<?php

namespace Tests\Feature;

use App\Http\Requests\StoreAppointmentBookingRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Phase 6 Workstream 2 — Appointment Engine foundation.
 *
 * EXECUTABILITY NOTE: same sandbox caveats documented in
 * DoctorModuleTest.php / PatientModuleTest.php — composer install is
 * BLOCKED in this environment (repo.packagist.org 403, no
 * vendor/autoload.php), and DB-touching cases additionally need a real
 * Postgres connection with the live schema (sqlite_testing has no
 * tables, and the availability/booking logic specifically depends on
 * the real appt_available_slots() function and the real
 * appt_bookings_no_double_booking exclusion constraint — nothing about
 * this feature is meaningfully testable against a fake/mocked DB).
 * NOT EXECUTED in this session, for that reason — reported as BLOCKED,
 * not PASS, per this project's own standing rule. Krut: these need to
 * run against the real Supabase Postgres connection, same as every
 * other DB-dependent test in this suite.
 *
 * Tests that touch neither Composer's autoloader oddities nor the DB at
 * all (route/middleware structure, FormRequest validation rules in
 * isolation) are the ones expected to actually pass once composer is
 * unblocked — same split DoctorModuleTest.php uses.
 */
class AppointmentModuleTest extends TestCase
{
    private function authenticatedSession(string $userId): array
    {
        return [
            'supabase.expires_at' => now()->addHour()->timestamp,
            'supabase.profile' => [
                'id' => $userId,
                'full_name' => 'Test User',
                'email' => 'test@example.com',
                'phone' => null,
            ],
            'supabase.jwt_claims' => [
                'sub' => $userId,
                'role' => 'authenticated',
                'aud' => 'authenticated',
                'iss' => 'https://test-project.supabase.local/auth/v1',
                'exp' => now()->addHour()->timestamp,
            ],
        ];
    }

    // 1. Unauthenticated requests redirect to login — no DB touch.
    public function test_unauthenticated_request_to_appointments_index_redirects_to_login(): void
    {
        $this->get('/appointments')->assertRedirect(route('login'));
    }

    public function test_unauthenticated_request_to_booking_form_redirects_to_login(): void
    {
        $doctorId = '21212121-2121-2121-2121-212121212121';

        $this->get("/doctors/{$doctorId}/book")->assertRedirect(route('login'));
    }

    public function test_unauthenticated_post_to_appointments_redirects_to_login(): void
    {
        $this->post('/appointments', [])->assertRedirect(route('login'));
    }

    // 2. None of the appointment routes carry the 'role' gate — a plain
    // patient booking for themselves has no staff_assignments row.
    // Structural check, no DB touch.
    public function test_appointment_routes_do_not_require_role_middleware(): void
    {
        foreach (['doctors.book', 'appointments.index', 'appointments.store', 'appointments.cancel'] as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Route [{$name}] is not registered.");
            $this->assertNotContains('role', $route->gatherMiddleware(), "Route [{$name}] unexpectedly requires 'role'.");
            $this->assertContains('supabase.rls', $route->gatherMiddleware(), "Route [{$name}] is missing 'supabase.rls'.");
        }
    }

    // 3. Cancel route binds a single booking, no other identifier —
    // structural guarantee, no DB touch.
    public function test_cancel_route_accepts_only_a_booking_identifier(): void
    {
        $route = Route::getRoutes()->getByName('appointments.cancel');

        $this->assertNotNull($route);
        $this->assertSame(['booking'], $route->parameterNames());
    }

    // 4. StoreAppointmentBookingRequest never authorizes itself — RLS +
    // the controller's own findSlot() re-check are the real authority,
    // matching UpdateDoctorProfileRequest/UpdatePatientRequest's
    // established pattern in this codebase. No DB touch.
    public function test_store_request_delegates_authorization_to_rls_and_controller(): void
    {
        $request = new StoreAppointmentBookingRequest();

        $this->assertTrue($request->authorize());
    }

    // 5. Required fields are actually required — pure validation-rule
    // check, no DB touch, no controller involved.
    public function test_store_request_rejects_missing_required_fields(): void
    {
        $validator = Validator::make([], (new StoreAppointmentBookingRequest())->rules());

        $this->assertTrue($validator->fails());
        foreach (['doctor_user_id', 'facility_id', 'scheduled_at', 'appt_type', 'idempotency_key'] as $field) {
            $this->assertTrue($validator->errors()->has($field), "Expected a validation error for [{$field}].");
        }
    }

    // 6. appt_type is restricted to the live appt_bookings_appt_type_check
    // CHECK constraint's exact allowed values (verified live this
    // session) — pure validation-rule check, no DB touch.
    public function test_store_request_rejects_an_appt_type_outside_the_database_check_constraint(): void
    {
        $validator = Validator::make([
            'doctor_user_id' => '11111111-1111-1111-1111-111111111111',
            'facility_id' => '22222222-2222-2222-2222-222222222222',
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'appt_type' => 'routine_checkup', // not one of the four DB-allowed values
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ], (new StoreAppointmentBookingRequest())->rules());

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('appt_type'));
    }

    // 7. A well-formed payload passes structural validation — no DB
    // touch (the exists: rules here only run in the real HTTP
    // request/DB-dependent tests below, not in this isolated check).
    public function test_store_request_accepts_a_well_formed_payload_shape(): void
    {
        $rules = (new StoreAppointmentBookingRequest())->rules();
        unset($rules['doctor_user_id'][2], $rules['facility_id'][2]); // drop the exists: rules for this DB-free shape check

        $validator = Validator::make([
            'doctor_user_id' => '11111111-1111-1111-1111-111111111111',
            'facility_id' => '22222222-2222-2222-2222-222222222222',
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'appt_type' => 'video',
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ], $rules);

        $this->assertFalse($validator->fails());
    }

    // 8. DB-DEPENDENT (BLOCKED — see class docblock). Booking form
    // renders for any authenticated user with no published schedule.
    public function test_booking_form_renders_empty_state_when_doctor_has_no_schedule(): void
    {
        $userId = '26262626-2626-2626-2626-262626262626';
        $doctorId = '27272727-2727-2727-2727-272727272727';

        $this->withSession($this->authenticatedSession($userId))
            ->get("/doctors/{$doctorId}/book")
            ->assertOk()
            ->assertSee('No schedule published yet');
    }

    // 9. DB-DEPENDENT (BLOCKED). A patient with no self patient record
    // and no MRN supplied cannot book — resolvePatientId() must return
    // null and store() must reject with a validation error, never a 500
    // and never a silently-wrong patient_id.
    public function test_booking_without_a_resolvable_patient_is_rejected(): void
    {
        $userId = '28282828-2828-2828-2828-282828282828';

        $this->withSession($this->authenticatedSession($userId))
            ->post('/appointments', [
                'doctor_user_id' => '11111111-1111-1111-1111-111111111111',
                'facility_id' => '22222222-2222-2222-2222-222222222222',
                'scheduled_at' => now()->addDay()->toIso8601String(),
                'appt_type' => 'in_person',
                'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            ])
            ->assertSessionHasErrors('patient_mrn');
    }

    // 10. DB-DEPENDENT (BLOCKED). A slot that doesn't actually exist in
    // appt_available_slots() (e.g. outside any published schedule) must
    // be rejected by the authoritative re-check in store(), never
    // inserted on the strength of client-supplied data alone.
    public function test_booking_a_nonexistent_slot_is_rejected(): void
    {
        $userId = '29292929-2929-2929-2929-292929292929';

        $this->withSession($this->authenticatedSession($userId))
            ->post('/appointments', [
                'doctor_user_id' => '11111111-1111-1111-1111-111111111111',
                'facility_id' => '22222222-2222-2222-2222-222222222222',
                'scheduled_at' => now()->addYears(5)->toIso8601String(),
                'appt_type' => 'in_person',
                'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            ])
            ->assertSessionHasErrors('scheduled_at');
    }

    // 11. DB-DEPENDENT (BLOCKED). Two bookings submitted with the SAME
    // idempotency_key must never create two rows — the second attempt
    // must resolve to the already-booked outcome, never a duplicate and
    // never a raw SQLSTATE reaching the user.
    public function test_duplicate_idempotency_key_does_not_create_a_second_booking(): void
    {
        $this->markTestSkipped(
            'Requires a real doctor with a published appt_availability row, a '
            .'bookable patient, and two sequential HTTP requests against the '
            .'real Supabase Postgres connection — not exercisable against '
            .'sqlite_testing. See class docblock.'
        );
    }

    // 12. DB-DEPENDENT (BLOCKED). Two concurrent bookings for the exact
    // same doctor_user_id + overlapping time range must never both
    // succeed — this is what appt_bookings_no_double_booking actually
    // guarantees, and the only way to genuinely exercise it is two real
    // concurrent transactions against the real Postgres connection.
    public function test_concurrent_bookings_for_the_same_slot_cannot_both_succeed(): void
    {
        $this->markTestSkipped(
            'Requires two genuinely concurrent database transactions against '
            .'the real Supabase Postgres connection to exercise '
            .'appt_bookings_no_double_booking — not exercisable sequentially, '
            .'and not exercisable against sqlite_testing at all. See class '
            .'docblock.'
        );
    }

    // 13. DB-DEPENDENT (BLOCKED). Cancelling an already-cancelled/
    // completed/no-show booking must be a safe no-op, never an error and
    // never a second state transition.
    public function test_cancelling_an_already_closed_booking_is_a_safe_no_op(): void
    {
        $this->markTestSkipped(
            'Requires an existing, RLS-visible appt_bookings row in a closed '
            .'status — not constructible against sqlite_testing. See class '
            .'docblock.'
        );
    }

    // 14. Existing routes/behavior remain unchanged by this workstream.
    public function test_existing_doctor_and_patient_routes_unchanged(): void
    {
        $this->assertNotNull(Route::getRoutes()->getByName('doctors.index'));
        $this->assertNotNull(Route::getRoutes()->getByName('doctors.show'));
        $this->assertNotNull(Route::getRoutes()->getByName('patients.my-profile'));
    }
}

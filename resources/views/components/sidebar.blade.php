@props(['class' => ''])

{{--
    Static placeholder nav items for Phase 2. Real, role-aware navigation
    (driven by roles/role_permissions data) is a later-phase concern —
    this only proves the layout shell renders correctly.

    Phase 5.1 exception: the "Patients" link below is conditionally
    shown based on Auth::user()->hasActiveStaffAssignment() (same
    active-assignment definition EnsureUserHasRole uses for /patients
    itself), because a plain patient account otherwise saw a nav item
    that always 403'd — misleading UX, not a change to authorization.
    Dashboard/Facilities remain unconditional, matching their routes
    (open to any authenticated user, no 'role' middleware).

    Phase 5.2: "Doctors" is unconditional, same tier as Facilities — a
    public, non-PII directory (doctor_profiles_select_public RLS), not
    gated by role.

    Phase 6 WS2: "Appointments" is unconditional, same tier as
    Dashboard/Facilities/Doctors — both a plain patient (booking for
    themselves) and staff (booking/managing within their facility scope)
    have a real, non-empty use for /appointments; /appointments itself
    carries no 'role' gate either. This is UX-only — appt_bookings_
    select_own/_doctor/_facility_staff RLS decides what actually shows.

    PHASE 6 CORRECTION: "My Doctor Profile" previously reused the same
    hasActiveStaffAssignment() condition as "Patients" ("only staff
    members are plausible doctors") — but that is true of every staff
    role, not just doctors, and was confirmed live to show this link
    for a Hospital Admin account. It now uses
    Auth::user()->hasActiveRole('doctor') (User::hasActiveRole(),
    checking the verified `roles.code` value on the user's active
    staff_assignments row) so only an actual doctor sees it.
    /my-doctor-profile itself still carries no 'role' gate (see route
    comment) — this remains UX-only, not a new authorization boundary.

    PHASE 6 FINALIZATION: "My Schedule" (doctor + has a doctor profile
    published — see route/controller: /doctors/{doctor}/schedule binds
    a DoctorProfile) and "Leave & Blocked Periods" (any active staff
    assignment, mirrors "Patients") added — see this commit's message
    for the full rationale. Both are UX-only; the real authorization for
    everything either page can do remains appt_availability_write_doctor
    / staff_leave_insert_own / staff_leave_select_own /
    staff_leave_facility_admin RLS, none of which this file touches.

    PHASE 6 CORRECTION (2026-08-31 continuation): "Staff" added, same
    hasActiveStaffAssignment() condition as "Patients"/"Leave & Blocked
    Periods" — any signed-in staff member can at least see their own row
    (staff_assignments_select_own); a hospital_admin/super_admin sees
    their full authorized scope via staff_assignments_select_facility_
    admin. This is UX-only — see StaffController's own docblock for the
    real, already-live RLS boundary.
--}}
<aside {{ $attributes->merge(['class' => 'w-64 shrink-0 flex-col border-r border-surface-muted bg-white ' . $class]) }}>
    <div class="flex items-center gap-2 px-5 py-4 border-b border-surface-muted">
        <div class="h-8 w-8 rounded-lg bg-primary-600 flex items-center justify-center text-white text-sm font-semibold">
            M
        </div>
        <span class="text-sm font-semibold text-ink">MediConnect India</span>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
        <x-sidebar-link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')">
            Dashboard
        </x-sidebar-link>
        <x-sidebar-link href="{{ route('facilities.index') }}" :active="request()->routeIs('facilities.*')">
            Facilities
        </x-sidebar-link>
        <x-sidebar-link href="{{ route('doctors.index') }}" :active="request()->routeIs('doctors.index') || request()->routeIs('doctors.show')">
            Doctors
        </x-sidebar-link>
        <x-sidebar-link href="{{ route('appointments.index') }}" :active="request()->routeIs('appointments.*') || request()->routeIs('doctors.book')">
            Appointments
        </x-sidebar-link>
        @if (auth()->user()?->hasActiveStaffAssignment())
            <x-sidebar-link href="{{ route('patients.index') }}" :active="request()->routeIs('patients.*')">
                Patients
            </x-sidebar-link>
        @endif
        @if (auth()->user()?->hasActiveStaffAssignment())
            <x-sidebar-link href="{{ route('staff.index') }}" :active="request()->routeIs('staff.*')">
                Staff
            </x-sidebar-link>
        @endif
        @if (auth()->user()?->hasActiveRole('doctor'))
            <x-sidebar-link href="{{ route('doctors.my-profile') }}" :active="request()->routeIs('doctors.my-profile')">
                My Doctor Profile
            </x-sidebar-link>
        @endif
        @if (auth()->user()?->hasActiveRole('doctor') && auth()->user()?->doctorProfile)
            <x-sidebar-link href="{{ route('doctors.schedule', ['doctor' => auth()->user()->doctorProfile]) }}" :active="request()->routeIs('doctors.schedule') || request()->routeIs('schedule.*')">
                My Schedule
            </x-sidebar-link>
        @endif
        @if (auth()->user()?->hasActiveStaffAssignment())
            <x-sidebar-link href="{{ route('leave.index') }}" :active="request()->routeIs('leave.*')">
                Leave &amp; Blocked Periods
            </x-sidebar-link>
        @endif
    </nav>
</aside>

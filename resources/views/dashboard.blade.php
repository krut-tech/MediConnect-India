<x-layouts.authenticated title="Dashboard">
    <x-slot name="header">
        <x-page-header
            title="Dashboard"
            subtitle="{{ match($mode) {
                'platform_staff' => 'Platform-wide overview.',
                'facility_staff' => $facility->name . ' — your facility overview.',
                'patient' => 'Your profile.',
                'no_role' => 'No active role assignment found.',
                default => 'Sign-in is not wired up yet — see the note below.',
            } }}"
        />
    </x-slot>

    @switch($mode)
        @case('platform_staff')
            <div class="mb-4">
                <x-badge variant="success">{{ $assignment->role->label }}</x-badge>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-stat-card label="Facilities" :value="$facilityCount" hint="Registered on the platform" />
                <x-stat-card label="Facility groups" :value="$facilityGroupCount" hint="Hospital chains / networks" />
                <x-stat-card label="Patients" :value="$patientCount" hint="Visible under your access scope" />
            </div>

            <div class="mt-4 flex flex-wrap gap-3">
                <x-button variant="secondary" href="{{ route('facilities.index') }}">View facilities</x-button>
                <x-button variant="secondary" href="{{ route('patients.index') }}">View patients</x-button>
                <x-button variant="secondary" href="{{ route('staff.my-profile') }}">My staff profile</x-button>
            </div>
            @break

        @case('facility_staff')
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <x-badge variant="success">{{ $assignment->role->label }}</x-badge>
                <x-badge variant="neutral">{{ $facility->name }}</x-badge>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-stat-card label="Departments" :value="$departmentCount" />
                <x-stat-card label="Staff at this facility" :value="$colleagueCount" />
                <x-stat-card label="Registered patients" :value="$patientCount" hint="Registered via this facility" />
            </div>

            <div class="mt-4 flex flex-wrap gap-3">
                <x-button variant="secondary" href="{{ route('facilities.show', $facility) }}">View facility details</x-button>
                <x-button variant="secondary" href="{{ route('staff.my-profile') }}">My staff profile</x-button>
                <x-button variant="secondary" href="{{ route('staff.my-shifts') }}">My shifts</x-button>
                <x-button variant="secondary" href="{{ route('staff.my-leave') }}">My leave</x-button>
            </div>
            @break

        @case('patient')
            <x-card title="Your profile">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm text-ink-subtle">MRN</dt>
                        <dd class="mt-0.5 font-medium text-ink">{{ $patient->mrn ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-ink-subtle">Gender</dt>
                        <dd class="mt-0.5 font-medium text-ink">{{ $patient->gender ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-ink-subtle">Registering facility</dt>
                        <dd class="mt-0.5 font-medium text-ink">{{ $patient->registeringFacility?->name ?? '—' }}</dd>
                    </div>
                </dl>
            </x-card>

            <x-alert variant="info" class="mt-4">
                Appointments, records, and prescriptions aren't built yet — this phase only covers your profile summary.
            </x-alert>
            @break

        @case('no_role')
            <x-card>
                <x-empty-state
                    title="No active role assignment"
                    description="Your account isn't linked to a staff assignment or patient profile yet. Contact your facility admin if this seems wrong."
                />
            </x-card>
            @break

        @default {{-- signed_out --}}
            <x-alert variant="info">
                Real sign-in isn't wired up yet (<code class="text-xs">VerifySupabaseSession</code> is still a documented pass-through from Phase 2) — so this dashboard can't show role-specific content today. Once Supabase Auth is connected, this same screen will render your facility/platform/patient view automatically based on your actual role.
            </x-alert>

            <div class="mt-4 flex flex-wrap gap-3">
                <x-button variant="secondary" href="{{ route('facilities.index') }}">Browse facilities</x-button>
                <x-button variant="secondary" href="{{ route('patients.index') }}">Browse patients</x-button>
            </div>
    @endswitch
</x-layouts.authenticated>

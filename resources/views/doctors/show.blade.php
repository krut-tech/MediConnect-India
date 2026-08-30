<x-layouts.authenticated title="Doctor detail">
    <x-slot name="header">
        <x-breadcrumb :items="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Doctors', 'href' => route('doctors.index')],
            ['label' => $doctor->user?->full_name ?? 'Doctor'],
        ]" class="mb-3" />

        <x-page-header
            :title="$doctor->user?->full_name ?? 'Unnamed doctor'"
            :subtitle="$doctor->registration_number ? 'Reg. no. '.$doctor->registration_number : null"
        >
            {{-- Phase 6 WS2: "Book appointment" always shown for a
                 patient-tier user — the booking form itself handles the
                 "no published schedule yet" case with its own empty
                 state, so this link never needs to guess in advance
                 whether a schedule exists.

                 PHASE 6 CORRECTION: an administrator (hospital_admin or
                 any platform-tier role — see User::isAdministrator())
                 must not be steered into the patient self-booking form
                 from here — links to Appointments instead. Any staff
                 member (any active staff assignment — doctor included)
                 additionally gets "Manage schedule", matching the spec's
                 "Doctor -> View doctor/profile/schedule" for Hospital
                 Admin; the real write authorization for that screen is
                 appt_availability_write_doctor RLS (AvailabilityController
                 class docblock), so this link is safe to show broadly —
                 a staff member outside that policy simply can't publish
                 anything from the page it leads to. --}}
            <x-slot name="actions">
                <div class="flex flex-wrap gap-2">
                    @if (auth()->user()?->hasActiveStaffAssignment())
                        <x-button :href="route('doctors.schedule', $doctor)" variant="secondary">
                            Manage schedule
                        </x-button>
                    @endif

                    @if (auth()->user()?->isAdministrator())
                        <x-button :href="route('appointments.index')" variant="secondary">
                            Manage appointments
                        </x-button>
                    @else
                        <x-button :href="route('doctors.book', $doctor)" variant="primary">Book appointment</x-button>
                    @endif
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-card title="Profile">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm text-ink-subtle">Registration number</dt>
                        <dd class="mt-0.5 font-medium text-ink">{{ $doctor->registration_number ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-ink-subtle">Years of experience</dt>
                        <dd class="mt-0.5 font-medium text-ink">
                            {{ $doctor->years_experience !== null ? $doctor->years_experience.' years' : '—' }}
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-sm text-ink-subtle">Specialties</dt>
                        <dd class="mt-0.5">
                            @if(!empty($doctor->specialties))
                                <div class="flex flex-wrap gap-2">
                                    @foreach($doctor->specialties as $specialty)
                                        <x-badge variant="neutral">{{ $specialty }}</x-badge>
                                    @endforeach
                                </div>
                            @else
                                <span class="font-medium text-ink">—</span>
                            @endif
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-sm text-ink-subtle">Qualifications</dt>
                        <dd class="mt-0.5">
                            @if(!empty($doctor->qualifications))
                                <div class="flex flex-wrap gap-2">
                                    @foreach($doctor->qualifications as $qualification)
                                        <x-badge variant="neutral">{{ $qualification }}</x-badge>
                                    @endforeach
                                </div>
                            @else
                                <span class="font-medium text-ink">—</span>
                            @endif
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-sm text-ink-subtle">Languages spoken</dt>
                        <dd class="mt-0.5 font-medium text-ink">
                            {{ !empty($doctor->languages_spoken) ? implode(', ', $doctor->languages_spoken) : '—' }}
                        </dd>
                    </div>
                </dl>
            </x-card>
        </div>
    </div>
</x-layouts.authenticated>

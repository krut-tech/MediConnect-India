<x-layouts.authenticated title="Doctors">
    <x-slot name="header">
        <x-breadcrumb :items="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Doctors'],
        ]" class="mb-3" />

        <x-page-header
            title="Doctors"
            subtitle="Doctor profiles published on the platform."
        />
    </x-slot>

    @if(session('status'))
        <x-alert variant="success" class="mb-4">{{ session('status') }}</x-alert>
    @endif

    {{-- PHASE 6.1-B — administrators only (UX gate; the real
         authorization is doctor_profiles_write_facility_admin/_own RLS
         at write time — see DoctorController's class docblock).
         Doctors who hold an active 'doctor' staff assignment but have
         no doctor_profiles row yet — otherwise invisible in this
         directory, since it's built from doctor_profiles. --}}
    @if($isAdministrator && $doctorsNeedingProfile->isNotEmpty())
        <x-card class="mb-4" title="Doctors needing a profile">
            <div class="space-y-2">
                @foreach($doctorsNeedingProfile as $assignment)
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-surface-muted px-3 py-2">
                        <div class="flex items-center gap-3 min-w-0">
                            <x-avatar :name="$assignment->user?->full_name ?? 'Doctor'" size="sm" />
                            <span class="font-medium text-ink truncate">
                                {{ $assignment->user?->full_name ?? 'Name on file missing' }}
                            </span>
                        </div>
                        @if($assignment->user)
                            <x-button href="{{ route('doctors.manage.edit', $assignment->user) }}" variant="secondary">
                                Complete profile
                            </x-button>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

    <div class="mb-4 max-w-sm">
        <form method="GET" role="search">
            <x-search-input name="q" placeholder="Search by name…" :value="$search" />
        </form>
    </div>

    @if($doctors->isEmpty())
        <x-card>
            <x-empty-state
                title="No doctor profiles yet"
                description="Doctors will appear here once they publish their own profile from My Doctor Profile."
            />
        </x-card>
    @else
        {{-- Desktop / tablet: table --}}
        @php
            $doctorTableHeadings = ['Doctor', 'Registration no.', 'Experience', 'Specialties'];
            if ($isAdministrator) {
                $doctorTableHeadings[] = '';
            }
        @endphp
        <div class="hidden sm:block">
            <x-card class="!p-0">
                <x-table :headings="$doctorTableHeadings">
                    @foreach($doctors as $doctor)
                        <tr>
                            <td class="flex items-center gap-3 font-medium text-ink">
                                <x-avatar :name="$doctor->user?->full_name ?? 'Doctor'" size="sm" />
                                <a href="{{ route('doctors.show', $doctor) }}" class="hover:text-primary-600 hover:underline">
                                    {{ $doctor->user?->full_name ?? 'Name on file missing' }}
                                </a>
                            </td>
                            <td class="text-ink-muted">{{ $doctor->registration_number ?? '—' }}</td>
                            <td class="text-ink-muted">
                                {{ $doctor->years_experience !== null ? $doctor->years_experience.' yrs' : '—' }}
                            </td>
                            <td class="text-ink-muted">
                                {{ !empty($doctor->specialties) ? implode(', ', $doctor->specialties) : '—' }}
                            </td>
                            @if($isAdministrator)
                                <td>
                                    @if($doctor->user)
                                        <a href="{{ route('doctors.manage.edit', $doctor->user) }}" class="text-brand hover:underline">Edit</a>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
        </div>

        {{-- Mobile: stacked cards --}}
        <div class="space-y-3 sm:hidden">
            @foreach($doctors as $doctor)
                <x-card>
                    <div class="flex items-center gap-3">
                        <x-avatar :name="$doctor->user?->full_name ?? 'Doctor'" />
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-ink truncate">
                                <a href="{{ route('doctors.show', $doctor) }}" class="hover:text-primary-600 hover:underline">
                                    {{ $doctor->user?->full_name ?? 'Name on file missing' }}
                                </a>
                            </p>
                            <p class="mt-0.5 text-sm text-ink-subtle">
                                {{ $doctor->registration_number ?? '—' }}
                                @if($doctor->years_experience !== null)
                                    · {{ $doctor->years_experience }} yrs
                                @endif
                            </p>
                        </div>
                        @if($isAdministrator && $doctor->user)
                            <a href="{{ route('doctors.manage.edit', $doctor->user) }}" class="shrink-0 text-sm text-brand hover:underline">Edit</a>
                        @endif
                    </div>
                </x-card>
            @endforeach
        </div>

        <div class="mt-4">
            <x-pagination :paginator="$doctors" />
        </div>
    @endif
</x-layouts.authenticated>

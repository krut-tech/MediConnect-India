
<x-layouts.authenticated title="Patients">
    <x-slot name="header">
        <x-breadcrumb :items="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Patients'],
        ]" class="mb-3" />

        <x-page-header
            title="Patients"
            subtitle="Registered patients visible under your current access scope."
        >
            <x-slot name="actions">
                <x-button variant="secondary" disabled title="Registration not wired yet — see notice below">
                    Register patient
                </x-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-prototype-notice
        message="Patient registration (creating a new patients row) is not connected yet — patients has no INSERT policy, and this project has no deployed Edge Function for it, as of this phase. Viewing and editing existing patients (this list, patient detail, My Profile) IS live and RLS-scoped — that part is no longer a prototype."
        class="mb-4"
    />

    <div class="mb-4 max-w-sm">
        <form method="GET" role="search">
            <x-search-input name="q" placeholder="Search by MRN…" :value="$search" />
        </form>
    </div>

    @if($patients->isEmpty())
        <x-card>
            <x-empty-state
                title="No patients registered yet"
                description="Patients will appear here once registration is implemented and RLS scopes them to your facility or care relationship."
            />
        </x-card>
    @else
        {{-- Desktop / tablet: table --}}
        <div class="hidden sm:block">
            <x-card class="!p-0">
                <x-table :headings="['Patient', 'MRN', 'Gender', 'Registering facility']">
                    @foreach($patients as $patient)
                        <tr>
                            <td class="flex items-center gap-3 font-medium text-ink">
                                <x-avatar :name="$patient->user?->full_name ?? 'Patient'" size="sm" />
                                <a href="{{ route('patients.show', $patient) }}" class="hover:text-primary-600 hover:underline">
                                    {{ $patient->user?->full_name ?? 'Name on file missing' }}
                                </a>
                            </td>
                            <td class="text-ink-muted">{{ $patient->mrn ?? '—' }}</td>
                            <td class="text-ink-muted">{{ $patient->gender ?? '—' }}</td>
                            <td class="text-ink-muted">{{ $patient->registeringFacility?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
        </div>

        {{-- Mobile: stacked cards --}}
        <div class="space-y-3 sm:hidden">
            @foreach($patients as $patient)
                <x-card>
                    <div class="flex items-center gap-3">
                        <x-avatar :name="$patient->user?->full_name ?? 'Patient'" />
                        <div class="min-w-0">
                            <p class="font-medium text-ink truncate">
                                <a href="{{ route('patients.show', $patient) }}" class="hover:text-primary-600 hover:underline">
                                    {{ $patient->user?->full_name ?? 'Name on file missing' }}
                                </a>
                            </p>
                            <p class="mt-0.5 text-sm text-ink-subtle">
                                MRN {{ $patient->mrn ?? '—' }} · {{ $patient->gender ?? '—' }}
                            </p>
                        </div>
                    </div>
                </x-card>
            @endforeach
        </div>

        <div class="mt-4">
            <x-pagination :paginator="$patients" />
        </div>
    @endif
</x-layouts.authenticated>

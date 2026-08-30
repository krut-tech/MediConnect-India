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
        <div class="hidden sm:block">
            <x-card class="!p-0">
                <x-table :headings="['Doctor', 'Registration no.', 'Experience', 'Specialties']">
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
                        <div class="min-w-0">
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
                    </div>
                </x-card>
            @endforeach
        </div>

        <div class="mt-4">
            <x-pagination :paginator="$doctors" />
        </div>
    @endif
</x-layouts.authenticated>

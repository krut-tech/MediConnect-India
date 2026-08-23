<x-layouts.authenticated title="Facilities">
    <x-slot name="header">
        <x-breadcrumb :items="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Facilities'],
        ]" class="mb-3" />

        <x-page-header
            title="Facilities"
            subtitle="Hospitals, clinics, and other registered facilities on the platform."
        >
            <x-slot name="actions">
                <x-button variant="secondary" href="{{ route('dashboard') }}">Back to dashboard</x-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="mb-4 max-w-sm">
        <form method="GET" role="search">
            <x-search-input name="q" placeholder="Search facilities by name…" :value="$search" />
        </form>
    </div>

    @if($facilities->isEmpty())
        <x-card>
            <x-empty-state
                title="No facilities registered yet"
                description="Once facilities are onboarded, they'll appear here with their type, location, and chain grouping."
            />
        </x-card>
    @else
        {{-- Desktop / tablet: table --}}
        <div class="hidden sm:block">
            <x-card class="!p-0">
                <x-table :headings="['Name', 'Type', 'City', 'Group', 'Status']">
                    @foreach($facilities as $facility)
                        <tr>
                            <td class="font-medium text-ink">{{ $facility->name }}</td>
                            <td class="text-ink-muted">{{ $facility->facility_type ?? '—' }}</td>
                            <td class="text-ink-muted">{{ $facility->city ?? '—' }}</td>
                            <td class="text-ink-muted">{{ $facility->facilityGroup?->name ?? 'Standalone' }}</td>
                            <td>
                                @if($facility->is_verified)
                                    <x-badge variant="success">Verified</x-badge>
                                @else
                                    <x-badge variant="neutral">Unverified</x-badge>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
        </div>

        {{-- Mobile: stacked cards, not a shrunk table --}}
        <div class="space-y-3 sm:hidden">
            @foreach($facilities as $facility)
                <x-card>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-ink truncate">{{ $facility->name }}</p>
                            <p class="mt-0.5 text-sm text-ink-subtle">
                                {{ $facility->facility_type ?? '—' }} · {{ $facility->city ?? '—' }}
                            </p>
                            <p class="mt-1 text-xs text-ink-subtle">
                                {{ $facility->facilityGroup?->name ?? 'Standalone' }}
                            </p>
                        </div>

                        @if($facility->is_verified)
                            <x-badge variant="success">Verified</x-badge>
                        @else
                            <x-badge variant="neutral">Unverified</x-badge>
                        @endif
                    </div>
                </x-card>
            @endforeach
        </div>

        <div class="mt-4">
            <x-pagination :paginator="$facilities" />
        </div>
    @endif
</x-layouts.authenticated>

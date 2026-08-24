<x-layouts.authenticated :title="$facility->name">
    <x-slot name="header">
        <x-breadcrumb :items="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Facilities', 'href' => route('facilities.index')],
            ['label' => $facility->name],
        ]" class="mb-3" />

        <x-page-header
            :title="$facility->name"
            :subtitle="collect([$facility->facility_type, $facility->city, $facility->state])->filter()->join(' · ')"
        >
            <x-slot name="actions">
                @if($facility->is_verified)
                    <x-badge variant="success">Verified</x-badge>
                @else
                    <x-badge variant="neutral">Unverified</x-badge>
                @endif
                @if($facility->has_emergency)
                    <x-badge variant="danger">Emergency</x-badge>
                @endif
                @if($facility->is_24x7)
                    <x-badge variant="neutral">24×7</x-badge>
                @endif
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Main column --}}
        <div class="lg:col-span-2 space-y-6">
            <x-card title="Facility information">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm text-ink-subtle">Type</dt>
                        <dd class="mt-0.5 font-medium text-ink">{{ $facility->facility_type ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-ink-subtle">Ownership</dt>
                        <dd class="mt-0.5 font-medium text-ink">{{ $facility->ownership_type ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-ink-subtle">City / District</dt>
                        <dd class="mt-0.5 font-medium text-ink">
                            {{ collect([$facility->city, $facility->district])->filter()->join(', ') ?: '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-ink-subtle">State</dt>
                        <dd class="mt-0.5 font-medium text-ink">{{ $facility->state ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-ink-subtle">Facility group</dt>
                        <dd class="mt-0.5 font-medium text-ink">{{ $facility->facilityGroup?->name ?? 'Standalone' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-ink-subtle">Locality</dt>
                        <dd class="mt-0.5 font-medium text-ink">{{ $facility->locality ?? '—' }}</dd>
                    </div>
                </dl>
            </x-card>

            <x-card title="Departments">
                @if($facility->departments->isEmpty())
                    <x-empty-state
                        title="No departments listed"
                        description="Departments will appear here once they're added for this facility."
                    />
                @else
                    <ul class="grid gap-2 sm:grid-cols-2">
                        @foreach($facility->departments as $department)
                            <li class="rounded-lg border border-line px-3 py-2 text-sm text-ink">
                                {{ $department->name }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card title="Specialties & services">
                @if($facility->specialties->isEmpty() && $facility->services->isEmpty())
                    <x-empty-state
                        title="No specialties or services listed"
                        description="Specialties and services offered by this facility will appear here once configured."
                    />
                @else
                    <div class="space-y-4">
                        @if($facility->specialties->isNotEmpty())
                            <div>
                                <p class="text-sm text-ink-subtle mb-2">Specialties</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($facility->specialties as $specialty)
                                        <x-badge variant="neutral">{{ $specialty->name }}</x-badge>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if($facility->services->isNotEmpty())
                            <div>
                                <p class="text-sm text-ink-subtle mb-2">Services</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($facility->services as $service)
                                        <x-badge variant="neutral">{{ $service->name }}</x-badge>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </x-card>
        </div>

        {{-- Side column: staff & providers --}}
        <div>
            <x-card title="Staff & providers">
                @if($facility->staffAssignments->isEmpty())
                    <x-empty-state
                        title="No staff assigned"
                        description="Staff and providers linked to this facility will appear here, shown under their real role from the roles table."
                    />
                @else
                    <ul class="space-y-3">
                        @foreach($facility->staffAssignments as $assignment)
                            <li class="flex items-center gap-3">
                                <x-avatar :name="$assignment->user?->full_name ?? 'Staff'" size="sm" />
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-ink truncate">
                                        {{ $assignment->user?->full_name ?? 'Unnamed' }}
                                    </p>
                                    <p class="text-xs text-ink-subtle">{{ $assignment->role?->label ?? '—' }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    </div>
</x-layouts.authenticated>

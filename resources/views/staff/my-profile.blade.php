<x-layouts.authenticated title="My Staff Profile">
    <x-slot name="header">
        <x-breadcrumb :items="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'My Staff Profile'],
        ]" class="mb-3" />

        <x-page-header
            title="My Staff Profile"
            subtitle="Your staff assignment(s) — facility, department, and role."
        />
    </x-slot>

    @if($assignments->isEmpty())
        <x-card>
            <x-empty-state
                title="No active staff assignment found"
                description="Your account isn't linked to an active staff assignment right now. Contact your facility admin if this seems wrong."
            />
        </x-card>
    @else
        <div class="space-y-4">
            @foreach($assignments as $assignment)
                <x-card>
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <x-badge variant="success">{{ $assignment->role?->label ?? 'Unknown role' }}</x-badge>
                        @if($assignment->is_primary)
                            <x-badge variant="neutral">Primary</x-badge>
                        @endif
                        @if($assignment->valid_until)
                            <x-badge variant="warning">Valid until {{ $assignment->valid_until->toFormattedDateString() }}</x-badge>
                        @endif
                    </div>

                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm text-ink-subtle">Facility</dt>
                            <dd class="mt-0.5 font-medium text-ink">
                                @if($assignment->facility)
                                    <a href="{{ route('facilities.show', $assignment->facility) }}" class="hover:text-primary-600 hover:underline">
                                        {{ $assignment->facility->name }}
                                    </a>
                                @elseif($assignment->facility_group_id)
                                    Platform-wide (facility group)
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm text-ink-subtle">Department</dt>
                            <dd class="mt-0.5 font-medium text-ink">{{ $assignment->department?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-ink-subtle">Assigned since</dt>
                            <dd class="mt-0.5 font-medium text-ink">{{ $assignment->valid_from?->toFormattedDateString() ?? '—' }}</dd>
                        </div>
                    </dl>
                </x-card>
            @endforeach
        </div>
    @endif
</x-layouts.authenticated>

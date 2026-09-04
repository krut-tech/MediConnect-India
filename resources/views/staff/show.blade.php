<x-layouts.authenticated title="Staff detail">
    @php
        $status = $assignment->displayStatus();
        $statusVariant = match ($status) {
            'active' => 'success',
            'future' => 'neutral',
            'expired' => 'warning',
            'deleted' => 'danger',
            default => 'neutral',
        };
        $statusLabel = match ($status) {
            'active' => 'Active',
            'future' => 'Future',
            'expired' => 'Expired',
            'deleted' => 'Deleted',
            default => ucfirst($status),
        };
    @endphp

    <x-slot name="header">
        <x-breadcrumb :items="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Staff', 'href' => route('staff.index')],
            ['label' => $assignment->user?->full_name ?? 'Staff'],
        ]" class="mb-3" />

        <x-page-header
            :title="$assignment->user?->full_name ?? 'Name on file missing'"
            :subtitle="$assignment->role?->label ?? '—'"
        >
            <x-slot name="actions">
                <x-badge :variant="$statusVariant">{{ $statusLabel }}</x-badge>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-card title="Assignment">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm text-ink-subtle">Full name</dt>
                        <dd class="mt-0.5 font-medium text-ink">
                            {{ $assignment->user?->full_name ?? 'Name on file missing' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-ink-subtle">User ID</dt>
                        <dd class="mt-0.5 font-mono text-xs text-ink-muted">{{ $assignment->user?->id ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-ink-subtle">Role</dt>
                        <dd class="mt-0.5 font-medium text-ink">{{ $assignment->role?->label ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-ink-subtle">Facility</dt>
                        <dd class="mt-0.5 font-medium text-ink">
                            @if($assignment->facility)
                                <a href="{{ route('facilities.show', $assignment->facility) }}" class="text-brand hover:underline">
                                    {{ $assignment->facility->name }}
                                </a>
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
                        <dt class="text-sm text-ink-subtle">Assignment ID</dt>
                        <dd class="mt-0.5 font-mono text-xs text-ink-muted">{{ $assignment->id }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-ink-subtle">Valid from</dt>
                        <dd class="mt-0.5 font-medium text-ink">
                            {{ $assignment->valid_from?->format('d M Y, H:i') ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-ink-subtle">Valid until</dt>
                        <dd class="mt-0.5 font-medium text-ink">
                            {{ $assignment->valid_until?->format('d M Y, H:i') ?? 'No end date' }}
                        </dd>
                    </div>
                    @if($assignment->deleted_at)
                        <div>
                            <dt class="text-sm text-ink-subtle">Deleted at</dt>
                            <dd class="mt-0.5 font-medium text-ink">
                                {{ $assignment->deleted_at->format('d M Y, H:i') }}
                            </dd>
                        </div>
                    @endif
                </dl>
            </x-card>

            @if($assignment->role?->code === 'doctor')
                <x-card title="Doctor profile">
                    @if($doctorProfile)
                        <dl class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm text-ink-subtle">Registration number</dt>
                                <dd class="mt-0.5 font-medium text-ink">{{ $doctorProfile->registration_number ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm text-ink-subtle">Years of experience</dt>
                                <dd class="mt-0.5 font-medium text-ink">
                                    {{ $doctorProfile->years_experience !== null ? $doctorProfile->years_experience.' years' : '—' }}
                                </dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-sm text-ink-subtle">Specialties</dt>
                                <dd class="mt-0.5">
                                    @if(!empty($doctorProfile->specialties))
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($doctorProfile->specialties as $specialty)
                                                <x-badge variant="neutral">{{ $specialty }}</x-badge>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="font-medium text-ink">—</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    @else
                        <x-empty-state
                            title="No doctor profile yet"
                            description="This doctor hasn't set up (or been set up with) a doctor_profiles record."
                        />
                    @endif
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.authenticated>

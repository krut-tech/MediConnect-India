<x-layouts.authenticated title="Staff">
    <x-slot name="header">
        <x-breadcrumb :items="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Staff'],
        ]" class="mb-3" />

        <x-page-header
            title="Staff"
            subtitle="Staff visible to you — your own assignment, or (for facility/platform admins) everyone in your authorized scope."
        />
    </x-slot>

    @if(session('status'))
        <x-alert variant="success" class="mb-4">{{ session('status') }}</x-alert>
    @endif

    <x-card class="mb-4">
        <form method="GET" action="{{ route('staff.index') }}" class="grid gap-3 sm:grid-cols-4">
            <x-input label="Search" name="q" type="text" placeholder="Name" value="{{ $filters['q'] }}" />
            <div>
                <label class="mb-1 block text-sm font-medium text-ink">Role</label>
                <select name="role_id" class="w-full rounded-lg border border-surface-muted px-3 py-2 text-sm">
                    <option value="">All roles</option>
                    @foreach($roleOptions as $role)
                        <option value="{{ $role->id }}" @selected((string) $filters['role_id'] === (string) $role->id)>{{ $role->label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-ink">Facility</label>
                <select name="facility_id" class="w-full rounded-lg border border-surface-muted px-3 py-2 text-sm">
                    <option value="">All facilities</option>
                    @foreach($facilityOptions as $facility)
                        <option value="{{ $facility->id }}" @selected($filters['facility_id'] === $facility->id)>{{ $facility->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <x-button type="submit" variant="primary">Filter</x-button>
                @if($filters['q'] || $filters['role_id'] || $filters['facility_id'])
                    <x-button href="{{ route('staff.index') }}" variant="secondary">Clear</x-button>
                @endif
            </div>
        </form>
    </x-card>

    @if($canCreate)
        <div class="mb-4">
            <x-button href="{{ route('staff.create') }}" variant="primary">+ Assign staff</x-button>
        </div>
    @endif

    @if($staff->isEmpty())
        <x-card>
            <x-empty-state
                title="No staff match"
                description="Nothing found for the current search/filter within your authorized scope."
            />
        </x-card>
    @else
        <div class="hidden sm:block">
            <x-card class="!p-0">
                <x-table :headings="['Name', 'Role', 'Facility', 'Department']">
                    @foreach($staff as $assignment)
                        <tr>
                            <td class="flex items-center gap-3 font-medium text-ink">
                                <x-avatar :name="$assignment->user?->full_name ?? 'Staff'" size="sm" />
                                {{ $assignment->user?->full_name ?? 'Name on file missing' }}
                            </td>
                            <td class="text-ink-muted">{{ $assignment->role?->label ?? '—' }}</td>
                            <td class="text-ink-muted">{{ $assignment->facility?->name ?? '—' }}</td>
                            <td class="text-ink-muted">{{ $assignment->department?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
        </div>

        <div class="space-y-3 sm:hidden">
            @foreach($staff as $assignment)
                <x-card>
                    <div class="flex items-center gap-3">
                        <x-avatar :name="$assignment->user?->full_name ?? 'Staff'" />
                        <div class="min-w-0">
                            <p class="font-medium text-ink truncate">{{ $assignment->user?->full_name ?? 'Name on file missing' }}</p>
                            <p class="mt-0.5 text-sm text-ink-subtle">
                                {{ $assignment->role?->label ?? '—' }} · {{ $assignment->facility?->name ?? '—' }}
                            </p>
                        </div>
                    </div>
                </x-card>
            @endforeach
        </div>

        <div class="mt-4">
            <x-pagination :paginator="$staff" />
        </div>
    @endif
</x-layouts.authenticated>

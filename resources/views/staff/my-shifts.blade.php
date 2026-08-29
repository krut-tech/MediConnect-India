<x-layouts.authenticated title="My Shifts">
    <x-slot name="header">
        <x-breadcrumb :items="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'My Shifts'],
        ]" class="mb-3" />

        <x-page-header
            title="My Shifts"
            subtitle="Read-only — shift scheduling is managed by your facility admin."
        />
    </x-slot>

    @if(!$hasActiveAssignment)
        <x-card>
            <x-empty-state
                title="No active staff assignment found"
                description="Your account isn't linked to an active staff assignment right now. Contact your facility admin if this seems wrong."
            />
        </x-card>
    @elseif($shifts->isEmpty())
        <x-card>
            <x-empty-state
                title="No shifts scheduled"
                description="Shifts assigned to you by your facility admin will appear here."
            />
        </x-card>
    @else
        {{-- Desktop / tablet: table --}}
        <div class="hidden sm:block">
            <x-card class="!p-0">
                <x-table :headings="['Start', 'End']">
                    @foreach($shifts as $shift)
                        <tr>
                            <td class="font-medium text-ink">{{ $shift->shift_start->format('D, d M Y H:i') }}</td>
                            <td class="text-ink-muted">{{ $shift->shift_end->format('D, d M Y H:i') }}</td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
        </div>

        {{-- Mobile: stacked cards --}}
        <div class="space-y-3 sm:hidden">
            @foreach($shifts as $shift)
                <x-card>
                    <p class="font-medium text-ink">{{ $shift->shift_start->format('D, d M Y H:i') }}</p>
                    <p class="mt-0.5 text-sm text-ink-subtle">to {{ $shift->shift_end->format('D, d M Y H:i') }}</p>
                </x-card>
            @endforeach
        </div>

        <div class="mt-4">
            <x-pagination :paginator="$shifts" />
        </div>
    @endif
</x-layouts.authenticated>

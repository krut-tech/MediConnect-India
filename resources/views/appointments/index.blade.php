<x-layouts.authenticated title="Appointments">
    <x-slot name="header">
        <x-breadcrumb :items="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Appointments'],
        ]" class="mb-3" />

        <x-page-header
            title="Appointments"
            subtitle="Bookings visible to you — scoped by your role, exactly like every other list in this app."
        />
    </x-slot>

    @if(session('status'))
        <x-alert variant="success" class="mb-4">{{ session('status') }}</x-alert>
    @endif

    @error('cancel')
        <x-alert variant="danger" class="mb-4">{{ $message }}</x-alert>
    @enderror

    <x-card class="mb-4">
        <form method="GET" action="{{ route('appointments.index') }}" class="grid gap-3 sm:grid-cols-4">
            <x-input label="Search" name="q" type="text" placeholder="Doctor, patient, or MRN" value="{{ $filters['q'] }}" />
            <div>
                <label class="mb-1 block text-sm font-medium text-ink">Status</label>
                <select name="status" class="w-full rounded-lg border border-surface-muted px-3 py-2 text-sm">
                    <option value="">All statuses</option>
                    @foreach($statusOptions as $option)
                        <option value="{{ $option }}" @selected($filters['status'] === $option)>{{ str_replace('_', ' ', ucfirst($option)) }}</option>
                    @endforeach
                </select>
            </div>
            <x-input label="From" name="date_from" type="date" value="{{ $filters['date_from'] }}" />
            <x-input label="To" name="date_to" type="date" value="{{ $filters['date_to'] }}" />
            <div class="sm:col-span-4 flex gap-2">
                <x-button type="submit" variant="primary">Filter</x-button>
                @if($filters['q'] || $filters['status'] || $filters['date_from'] || $filters['date_to'])
                    <x-button href="{{ route('appointments.index') }}" variant="secondary">Clear</x-button>
                @endif
            </div>
        </form>
    </x-card>

    @if($bookings->isEmpty())
        <x-card>
            <x-empty-state
                title="No appointments match"
                description="Nothing found for the current search/filter. Try clearing it, or check back once a booking exists."
            />
        </x-card>
    @else
        {{-- Desktop / tablet: table --}}
        <div class="hidden sm:block">
            <x-card class="!p-0">
                <x-table :headings="['When', 'Doctor', 'Patient', 'Facility', 'Type', 'Status', '']">
                    @foreach($bookings as $booking)
                        <tr>
                            <td class="text-ink-muted">
                                {{ $booking->scheduled_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}
                            </td>
                            <td class="font-medium text-ink">{{ $booking->doctorUser?->full_name ?? '—' }}</td>
                            <td class="text-ink-muted">
                                {{ $booking->patient?->user?->full_name ?? '—' }}
                                @if($booking->patient?->mrn)
                                    <span class="block text-xs text-ink-subtle">MRN {{ $booking->patient->mrn }}</span>
                                @endif
                            </td>
                            <td class="text-ink-muted">{{ $booking->facility?->name ?? '—' }}</td>
                            <td class="text-ink-muted capitalize">{{ str_replace('_', ' ', $booking->appt_type) }}</td>
                            <td>
                                <x-badge :variant="match($booking->status) {
                                    'completed' => 'success',
                                    'no_show' => 'warning',
                                    'cancelled' => 'danger',
                                    default => 'neutral',
                                }">
                                    {{ str_replace('_', ' ', $booking->status) }}
                                </x-badge>
                                @if($booking->resolution_state === 'pending_reschedule')
                                    <x-badge variant="warning" class="mt-1 block w-fit">Needs follow-up (doctor leave)</x-badge>
                                @elseif($booking->resolution_state === 'rescheduled')
                                    <x-badge variant="neutral" class="mt-1 block w-fit">Rescheduled</x-badge>
                                @elseif($booking->resolution_state === 'cancelled_by_facility')
                                    <x-badge variant="danger" class="mt-1 block w-fit">Cancelled by facility</x-badge>
                                @endif
                                @if($booking->status === 'cancelled' && $booking->cancellation_reason)
                                    <p class="mt-1 text-xs text-ink-subtle" title="{{ $booking->cancellation_reason }}">"{{ \Illuminate\Support\Str::limit($booking->cancellation_reason, 40) }}"</p>
                                @endif
                            </td>
                            <td class="text-right">
                                @if(!in_array($booking->status, ['cancelled', 'completed', 'no_show'], true))
                                    <form method="POST" action="{{ route('appointments.cancel', $booking) }}" onsubmit="return confirm('Cancel this appointment?');">
                                        @csrf
                                        @method('PATCH')
                                        <x-button type="submit" variant="danger">Cancel</x-button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
        </div>

        {{-- Mobile: stacked cards --}}
        <div class="space-y-3 sm:hidden">
            @foreach($bookings as $booking)
                <x-card>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-ink">
                                {{ $booking->scheduled_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}
                            </p>
                            <p class="mt-0.5 text-sm text-ink-subtle">
                                Dr. {{ $booking->doctorUser?->full_name ?? '—' }} · {{ $booking->facility?->name ?? '—' }}
                            </p>
                            @if($booking->patient?->user?->full_name)
                                <p class="mt-0.5 text-sm text-ink-subtle">
                                    Patient: {{ $booking->patient->user->full_name }}
                                    @if($booking->patient->mrn) (MRN {{ $booking->patient->mrn }}) @endif
                                </p>
                            @endif
                        </div>
                        <x-badge :variant="match($booking->status) {
                            'completed' => 'success',
                            'no_show' => 'warning',
                            'cancelled' => 'danger',
                            default => 'neutral',
                        }">
                            {{ str_replace('_', ' ', $booking->status) }}
                        </x-badge>
                    </div>
                    @if($booking->resolution_state === 'pending_reschedule')
                        <x-badge variant="warning" class="mt-2 block w-fit">Needs follow-up (doctor leave)</x-badge>
                    @endif
                    @if(!in_array($booking->status, ['cancelled', 'completed', 'no_show'], true))
                        <form method="POST" action="{{ route('appointments.cancel', $booking) }}" class="mt-3" onsubmit="return confirm('Cancel this appointment?');">
                            @csrf
                            @method('PATCH')
                            <x-button type="submit" variant="danger" class="w-full">Cancel</x-button>
                        </form>
                    @endif
                </x-card>
            @endforeach
        </div>

        <div class="mt-4">
            <x-pagination :paginator="$bookings" />
        </div>
    @endif
</x-layouts.authenticated>

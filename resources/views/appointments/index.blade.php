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

    @if($bookings->isEmpty())
        <x-card>
            <x-empty-state
                title="No appointments yet"
                description="Bookings you make, or bookings made for you, will appear here."
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
                            <td class="text-ink-muted">{{ $booking->patient?->user?->full_name ?? '—' }}</td>
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
                                <p class="mt-0.5 text-sm text-ink-subtle">Patient: {{ $booking->patient->user->full_name }}</p>
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

<x-layouts.authenticated title="Manage schedule">
    <x-slot name="header">
        <x-breadcrumb :items="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Doctors', 'href' => route('doctors.index')],
            ['label' => $doctor->user?->full_name ?? 'Doctor', 'href' => route('doctors.show', $doctor)],
            ['label' => 'Schedule'],
        ]" class="mb-3" />

        <x-page-header
            :title="'Schedule — '.($doctor->user?->full_name ?? 'this doctor')"
            subtitle="Recurring weekly availability. Available slots are always computed live from this — nothing here is a fixed list of times."
        />
    </x-slot>

    @if(session('status'))
        <x-alert variant="success" class="mb-4">{{ session('status') }}</x-alert>
    @endif

    @error('schedule')
        <x-alert variant="danger" class="mb-4">{{ $message }}</x-alert>
    @enderror

    <x-card title="Published schedule" class="mb-6">
        @if($availability->isEmpty())
            <x-empty-state
                title="No schedule published yet"
                description="This doctor hasn't published availability at any facility yet. Use the form below to publish the first one."
            />
        @else
            <div class="hidden sm:block">
                <x-table :headings="['Facility', 'Day', 'Time', 'Slot length', 'Valid from', 'Valid until', '']">
                    @foreach($availability as $row)
                        <tr>
                            <td class="font-medium text-ink">{{ $row->facility?->name ?? '—' }}</td>
                            <td class="text-ink-muted">{{ ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][$row->day_of_week] ?? '—' }}</td>
                            <td class="text-ink-muted">{{ \Illuminate\Support\Carbon::parse($row->start_time)->format('h:i A') }} – {{ \Illuminate\Support\Carbon::parse($row->end_time)->format('h:i A') }}</td>
                            <td class="text-ink-muted">{{ $row->slot_duration_minutes }} min</td>
                            <td class="text-ink-muted">{{ $row->valid_from?->format('d M Y') ?? '—' }}</td>
                            <td class="text-ink-muted">{{ $row->valid_until?->format('d M Y') ?? 'Ongoing' }}</td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <x-button href="{{ route('schedule.edit', $row) }}" variant="secondary">Edit</x-button>
                                    <form method="POST" action="{{ route('schedule.destroy', $row) }}" onsubmit="return confirm('Remove this schedule entry? Existing bookings against it are not affected.');">
                                        @csrf
                                        @method('DELETE')
                                        <x-button type="submit" variant="danger">Remove</x-button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            </div>

            <div class="space-y-3 sm:hidden">
                @foreach($availability as $row)
                    <div class="rounded-lg border border-surface-muted p-3">
                        <p class="font-medium text-ink">{{ $row->facility?->name ?? '—' }} · {{ ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][$row->day_of_week] ?? '—' }}</p>
                        <p class="mt-0.5 text-sm text-ink-subtle">
                            {{ \Illuminate\Support\Carbon::parse($row->start_time)->format('h:i A') }} – {{ \Illuminate\Support\Carbon::parse($row->end_time)->format('h:i A') }}
                            · {{ $row->slot_duration_minutes }} min slots
                        </p>
                        <p class="mt-0.5 text-sm text-ink-subtle">
                            {{ $row->valid_from?->format('d M Y') ?? '—' }} – {{ $row->valid_until?->format('d M Y') ?? 'Ongoing' }}
                        </p>
                        <div class="mt-2 flex gap-2">
                            <x-button href="{{ route('schedule.edit', $row) }}" variant="secondary" class="w-full">Edit</x-button>
                            <form method="POST" action="{{ route('schedule.destroy', $row) }}" class="w-full" onsubmit="return confirm('Remove this schedule entry? Existing bookings against it are not affected.');">
                                @csrf
                                @method('DELETE')
                                <x-button type="submit" variant="danger" class="w-full">Remove</x-button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>

    <x-card title="Publish new schedule">
        <form method="POST" action="{{ route('doctors.schedule.store', $doctor) }}" class="space-y-4">
            @csrf

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="facility_id" class="form-label">Facility</label>
                    <select name="facility_id" id="facility_id" class="form-input">
                        @foreach($facilities as $facility)
                            <option value="{{ $facility->id }}" @selected(old('facility_id') === $facility->id)>{{ $facility->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="day_of_week" class="form-label">Day of week</label>
                    <select name="day_of_week" id="day_of_week" class="form-input">
                        @foreach(['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $i => $label)
                            <option value="{{ $i }}" @selected((int) old('day_of_week', -1) === $i)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <x-input label="Start time" name="start_time" type="time" value="{{ old('start_time', '09:00') }}" />
                <x-input label="End time" name="end_time" type="time" value="{{ old('end_time', '13:00') }}" />
                <x-input label="Slot length (minutes)" name="slot_duration_minutes" type="number" min="5" max="120" value="{{ old('slot_duration_minutes', 15) }}" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-input label="Valid from" name="valid_from" type="date" value="{{ old('valid_from', \Carbon\CarbonImmutable::now('Asia/Kolkata')->format('Y-m-d')) }}" />
                <x-input label="Valid until (optional)" name="valid_until" type="date" value="{{ old('valid_until') }}" help="Leave blank for an ongoing/ open-ended schedule." />
            </div>

            <x-button type="submit" variant="primary">Publish schedule</x-button>
        </form>
    </x-card>
</x-layouts.authenticated>

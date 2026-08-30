<x-layouts.authenticated title="Edit schedule entry">
    <x-slot name="header">
        <x-breadcrumb :items="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Doctors', 'href' => route('doctors.index')],
            ['label' => $availability->doctorUser?->full_name ?? 'Doctor', 'href' => route('doctors.show', ['doctor' => $doctorProfileId])],
            ['label' => 'Schedule', 'href' => route('doctors.schedule', ['doctor' => $doctorProfileId])],
            ['label' => 'Edit'],
        ]" class="mb-3" />

        <x-page-header
            title="Edit schedule entry"
            subtitle="Updates this recurring weekly slot in place. Existing bookings against it are not affected."
        />
    </x-slot>

    @error('schedule')
        <x-alert variant="danger" class="mb-4">{{ $message }}</x-alert>
    @enderror

    <x-card>
        <form method="POST" action="{{ route('schedule.update', $availability) }}" class="space-y-4">
            @csrf
            @method('PATCH')

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="facility_id" class="form-label">Facility</label>
                    <select name="facility_id" id="facility_id" class="form-input">
                        @foreach($facilities as $facility)
                            <option value="{{ $facility->id }}" @selected(old('facility_id', $availability->facility_id) === $facility->id)>{{ $facility->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="day_of_week" class="form-label">Day of week</label>
                    <select name="day_of_week" id="day_of_week" class="form-input">
                        @foreach(['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $i => $label)
                            <option value="{{ $i }}" @selected((int) old('day_of_week', $availability->day_of_week) === $i)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <x-input label="Start time" name="start_time" type="time" value="{{ old('start_time', \Illuminate\Support\Carbon::parse($availability->start_time)->format('H:i')) }}" />
                <x-input label="End time" name="end_time" type="time" value="{{ old('end_time', \Illuminate\Support\Carbon::parse($availability->end_time)->format('H:i')) }}" />
                <x-input label="Slot length (minutes)" name="slot_duration_minutes" type="number" min="5" max="120" value="{{ old('slot_duration_minutes', $availability->slot_duration_minutes) }}" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-input label="Valid from" name="valid_from" type="date" value="{{ old('valid_from', $availability->valid_from?->format('Y-m-d')) }}" />
                <x-input label="Valid until (optional)" name="valid_until" type="date" value="{{ old('valid_until', $availability->valid_until?->format('Y-m-d')) }}" help="Leave blank for an ongoing/ open-ended schedule." />
            </div>

            <div class="flex gap-3">
                <x-button type="submit" variant="primary">Save changes</x-button>
                <x-button href="{{ route('doctors.schedule', ['doctor' => $doctorProfileId]) }}" variant="secondary">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.authenticated>

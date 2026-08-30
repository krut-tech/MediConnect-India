<x-layouts.authenticated title="Book an appointment">
    <x-slot name="header">
        <x-breadcrumb :items="$prefillMrn !== ''
            ? [
                ['label' => 'Dashboard', 'href' => route('dashboard')],
                ['label' => 'Appointments', 'href' => route('appointments.index')],
                ['label' => 'Create appointment', 'href' => route('appointments.create')],
                ['label' => $doctor->user?->full_name ?? 'Doctor'],
            ]
            : [
                ['label' => 'Dashboard', 'href' => route('dashboard')],
                ['label' => 'Doctors', 'href' => route('doctors.index')],
                ['label' => $doctor->user?->full_name ?? 'Doctor', 'href' => route('doctors.show', $doctor)],
                ['label' => 'Book appointment'],
            ]" class="mb-3" />

        <x-page-header
            :title="'Book with '.($doctor->user?->full_name ?? 'this doctor')"
            :subtitle="$prefillMrn !== ''
                ? 'Step 2 of 2 — every slot below is computed live from this doctor\'s real schedule, leave, and existing bookings.'
                : 'Every slot below is computed live from this doctor\'s real schedule, leave, and existing bookings — nothing here is hard-coded.'"
        />
    </x-slot>

    @error('scheduled_at')
        <x-alert variant="danger" class="mb-4">{{ $message }}</x-alert>
    @enderror
    @error('patient_mrn')
        <x-alert variant="danger" class="mb-4">{{ $message }}</x-alert>
    @enderror
    @error('booking')
        <x-alert variant="danger" class="mb-4">{{ $message }}</x-alert>
    @enderror

    @if($canBookForOthers && $prefillMrn !== '')
        <x-alert variant="info" class="mb-4">
            Booking for patient MRN {{ $prefillMrn }}.
            <a href="{{ route('appointments.create') }}" class="underline">Change patient or doctor</a>
        </x-alert>
    @endif

    @if($facilities->isEmpty())
        <x-card>
            <x-empty-state
                title="No schedule published yet"
                description="This doctor hasn't published availability at any facility yet, so there is nothing to book."
            />
        </x-card>
    @else
        <x-card title="Choose facility and date" class="mb-6">
            <form method="GET" action="{{ route('doctors.book', $doctor) }}" class="grid gap-4 sm:grid-cols-3 sm:items-end">
                @if($prefillMrn !== '')
                    <input type="hidden" name="patient_mrn" value="{{ $prefillMrn }}">
                @endif
                <div>
                    <label for="facility_id" class="form-label">Facility</label>
                    <select name="facility_id" id="facility_id" class="form-input">
                        @foreach($facilities as $facility)
                            <option value="{{ $facility->id }}" @selected($selectedFacilityId === $facility->id)>
                                {{ $facility->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <x-input
                    label="Date"
                    name="date"
                    type="date"
                    value="{{ $date->format('Y-m-d') }}"
                    min="{{ \Carbon\CarbonImmutable::now('Asia/Kolkata')->format('Y-m-d') }}"
                />
                <x-button type="submit" variant="secondary">Check availability</x-button>
            </form>
        </x-card>

        <x-card title="Available slots — {{ $date->format('d M Y') }}">
            @if($slots->isEmpty())
                <x-empty-state
                    title="No slots available"
                    description="Nothing open for this facility/date — try a different date, or a different facility above."
                />
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($slots as $slot)
                        <form method="POST" action="{{ route('appointments.store') }}" class="rounded-lg border border-surface-muted p-3 space-y-2">
                            @csrf
                            <input type="hidden" name="doctor_user_id" value="{{ $doctor->user_id }}">
                            <input type="hidden" name="facility_id" value="{{ $selectedFacilityId }}">
                            <input type="hidden" name="scheduled_at" value="{{ $slot['start']->toIso8601String() }}">
                            <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">

                            <p class="font-medium text-ink">
                                {{ $slot['start']->format('h:i A') }} – {{ $slot['end']->format('h:i A') }}
                            </p>

                            <select name="appt_type" class="form-input text-sm">
                                <option value="in_person">In person</option>
                                <option value="video">Video</option>
                                <option value="follow_up">Follow-up</option>
                                <option value="emergency">Emergency</option>
                            </select>

                            @if($canBookForOthers)
                                @if($prefillMrn !== '')
                                    <input type="hidden" name="patient_mrn" value="{{ $prefillMrn }}">
                                    <p class="text-xs text-ink-subtle">Patient MRN: {{ $prefillMrn }}</p>
                                @else
                                    <input type="text" name="patient_mrn" placeholder="Patient MRN" class="form-input text-sm">
                                @endif
                            @endif

                            <x-button type="submit" variant="primary" class="w-full">
                                {{ $prefillMrn !== '' ? 'Confirm appointment' : 'Book this slot' }}
                            </x-button>
                        </form>
                    @endforeach
                </div>
            @endif
        </x-card>
    @endif
</x-layouts.authenticated>

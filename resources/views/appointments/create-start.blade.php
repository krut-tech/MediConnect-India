<x-layouts.authenticated title="Create appointment">
    <x-slot name="header">
        <x-breadcrumb :items="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Appointments', 'href' => route('appointments.index')],
            ['label' => 'Create appointment'],
        ]" class="mb-3" />

        <x-page-header
            title="Create appointment"
            subtitle="Book on behalf of a patient — choose who it's for and which doctor, then pick a live slot on the next step."
        />
    </x-slot>

    @error('patient_mrn')
        <x-alert variant="danger" class="mb-4">{{ $message }}</x-alert>
    @enderror

    <x-card title="Step 1 of 2 — Patient and doctor" class="mb-6">
        <form method="GET" action="{{ route('appointments.create') }}" class="space-y-4">
            <x-input
                label="Patient MRN"
                name="patient_mrn"
                value="{{ old('patient_mrn', $patientMrn) }}"
                placeholder="e.g. MC00000001"
                help="The medical record number of the patient this appointment is for. Looked up the same way every other staff-facing screen in this app looks up a patient — never by name alone."
            />

            <div>
                <label for="q" class="form-label">Find doctor</label>
                <div class="flex gap-2">
                    <input type="text" name="q" id="q" value="{{ $search }}" placeholder="Search by doctor name" class="form-input flex-1">
                    <x-button type="submit" variant="secondary" formaction="{{ route('appointments.create') }}">Search</x-button>
                </div>
            </div>
        </form>

        @if($doctors->isEmpty())
            <div class="mt-4">
                <x-empty-state
                    title="No doctors found"
                    description="Try a different search, or browse the full Doctors directory."
                />
            </div>
        @else
            <div class="mt-4 divide-y divide-surface-muted rounded-lg border border-surface-muted">
                @foreach($doctors as $doctor)
                    <form method="GET" action="{{ route('appointments.create') }}" class="flex items-center justify-between gap-3 px-4 py-3">
                        <input type="hidden" name="patient_mrn" value="{{ $patientMrn }}">
                        <input type="hidden" name="doctor_id" value="{{ $doctor->id }}">
                        <div class="min-w-0">
                            <p class="font-medium text-ink truncate">{{ $doctor->user?->full_name ?? 'Name on file missing' }}</p>
                            <p class="text-sm text-ink-subtle truncate">{{ $doctor->registration_number ? 'Reg. no. '.$doctor->registration_number : '—' }}</p>
                        </div>
                        <x-button
                            type="submit"
                            variant="primary"
                            :disabled="$patientMrn === ''"
                        >
                            Choose
                        </x-button>
                    </form>
                @endforeach
            </div>
            @if($patientMrn === '')
                <p class="mt-3 text-sm text-ink-subtle">Enter a patient MRN above before choosing a doctor.</p>
            @endif
        @endif
    </x-card>
</x-layouts.authenticated>

<x-layouts.authenticated title="Doctor profile">
    <x-slot name="header">
        <x-breadcrumb :items="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Doctors', 'href' => route('doctors.index')],
            ['label' => $targetUser->full_name ?? 'Doctor profile'],
        ]" class="mb-3" />

        <x-page-header
            :title="($doctor ? 'Edit' : 'Complete').' doctor profile — '.($targetUser->full_name ?? 'Name on file missing')"
            subtitle="Managed on behalf of this doctor. They can also edit this themselves from My Doctor Profile."
        />
    </x-slot>

    @if(session('status'))
        <x-alert variant="success" class="mb-4">{{ session('status') }}</x-alert>
    @endif

    @error('update')
        <x-alert variant="danger" class="mb-4">{{ $message }}</x-alert>
    @enderror

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-card :title="$doctor ? 'Update profile' : 'Create profile'">
                <form method="POST" action="{{ route('doctors.manage.update', $targetUser) }}" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-input label="Registration number" name="registration_number"
                            value="{{ old('registration_number', $doctor?->registration_number) }}" />
                        <x-input label="Years of experience" name="years_experience" type="number" min="0" max="80"
                            value="{{ old('years_experience', $doctor?->years_experience) }}" />
                    </div>

                    <x-input label="Specialties" name="specialties"
                        help="Comma-separated, e.g. Cardiology, Pediatrics"
                        value="{{ old('specialties', !empty($doctor?->specialties) ? implode(', ', $doctor->specialties) : '') }}" />

                    <x-input label="Qualifications" name="qualifications"
                        help="Comma-separated, e.g. MBBS, MD (Internal Medicine)"
                        value="{{ old('qualifications', !empty($doctor?->qualifications) ? implode(', ', $doctor->qualifications) : '') }}" />

                    <x-input label="Languages spoken" name="languages_spoken"
                        help="Comma-separated, e.g. Hindi, English, Gujarati"
                        value="{{ old('languages_spoken', !empty($doctor?->languages_spoken) ? implode(', ', $doctor->languages_spoken) : '') }}" />

                    <div class="flex flex-wrap gap-2">
                        <x-button type="submit" variant="primary">
                            {{ $doctor ? 'Save changes' : 'Create profile' }}
                        </x-button>
                        <x-button href="{{ route('doctors.index') }}" variant="secondary">Back to Doctors</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
</x-layouts.authenticated>

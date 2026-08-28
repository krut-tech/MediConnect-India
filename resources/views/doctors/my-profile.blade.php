<x-layouts.authenticated title="My Doctor Profile">
    <x-slot name="header">
        <x-breadcrumb :items="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'My Doctor Profile'],
        ]" class="mb-3" />

        <x-page-header
            title="My Doctor Profile"
            subtitle="Published to the public Doctors directory."
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
            @if($doctor)
                <x-card title="Your profile">
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm text-ink-subtle">Registration number</dt>
                            <dd class="mt-0.5 font-medium text-ink">{{ $doctor->registration_number ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-ink-subtle">Years of experience</dt>
                            <dd class="mt-0.5 font-medium text-ink">
                                {{ $doctor->years_experience !== null ? $doctor->years_experience.' years' : '—' }}
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-sm text-ink-subtle">Specialties</dt>
                            <dd class="mt-0.5">
                                @if(!empty($doctor->specialties))
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($doctor->specialties as $specialty)
                                            <x-badge variant="neutral">{{ $specialty }}</x-badge>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="font-medium text-ink">—</span>
                                @endif
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-sm text-ink-subtle">Qualifications</dt>
                            <dd class="mt-0.5">
                                @if(!empty($doctor->qualifications))
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($doctor->qualifications as $qualification)
                                            <x-badge variant="neutral">{{ $qualification }}</x-badge>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="font-medium text-ink">—</span>
                                @endif
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-sm text-ink-subtle">Languages spoken</dt>
                            <dd class="mt-0.5 font-medium text-ink">
                                {{ !empty($doctor->languages_spoken) ? implode(', ', $doctor->languages_spoken) : '—' }}
                            </dd>
                        </div>
                    </dl>
                </x-card>
            @else
                <x-prototype-notice
                    message="You haven't published a doctor profile yet. Fill in the form below to create one — it will appear in the public Doctors directory."
                    class="mb-4"
                />
            @endif

            <x-card :title="$doctor ? 'Update your profile' : 'Create your profile'">
                <form method="POST" action="{{ route('doctors.my-profile.update') }}" class="space-y-4">
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

                    <x-button type="submit" variant="primary">
                        {{ $doctor ? 'Save changes' : 'Create profile' }}
                    </x-button>
                </form>
            </x-card>
        </div>
    </div>
</x-layouts.authenticated>

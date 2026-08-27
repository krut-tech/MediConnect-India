<x-layouts.authenticated title="My Profile">
    <x-slot name="header">
        <x-breadcrumb :items="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'My Profile'],
        ]" class="mb-3" />

        <x-page-header
            title="My Profile"
            subtitle="Your own patient record."
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
            <x-card title="Your information">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm text-ink-subtle">Date of birth</dt>
                        <dd class="mt-0.5 font-medium text-ink">{{ $patient->date_of_birth?->format('d M Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-ink-subtle">Gender</dt>
                        <dd class="mt-0.5 font-medium text-ink">{{ $patient->gender ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-ink-subtle">Blood group</dt>
                        <dd class="mt-0.5 font-medium text-ink">{{ $patient->blood_group ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-ink-subtle">Registering facility</dt>
                        <dd class="mt-0.5 font-medium text-ink">{{ $patient->registeringFacility?->name ?? '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-sm text-ink-subtle">Emergency contact</dt>
                        <dd class="mt-0.5 font-medium text-ink">
                            @if(!empty($patient->emergency_contact['name']) || !empty($patient->emergency_contact['phone']))
                                {{ collect([$patient->emergency_contact['name'] ?? null, $patient->emergency_contact['phone'] ?? null, $patient->emergency_contact['relation'] ?? null])->filter()->join(' · ') }}
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-sm text-ink-subtle">Known allergies</dt>
                        <dd class="mt-0.5">
                            @if(!empty($patient->known_allergies))
                                <div class="flex flex-wrap gap-2">
                                    @foreach($patient->known_allergies as $allergy)
                                        <x-badge variant="warning">{{ $allergy }}</x-badge>
                                    @endforeach
                                </div>
                            @else
                                <span class="font-medium text-ink">—</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </x-card>

            <x-card title="Update your profile">
                <form method="POST" action="{{ route('patients.my-profile.update') }}" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-input label="Date of birth" name="date_of_birth" type="date"
                            value="{{ old('date_of_birth', $patient->date_of_birth?->format('Y-m-d')) }}" />
                        <x-input label="Gender" name="gender" value="{{ old('gender', $patient->gender) }}" />
                        <x-input label="Blood group" name="blood_group" value="{{ old('blood_group', $patient->blood_group) }}" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-input label="Emergency contact name" name="emergency_contact_name"
                            value="{{ old('emergency_contact_name', $patient->emergency_contact['name'] ?? '') }}" />
                        <x-input label="Emergency contact phone" name="emergency_contact_phone"
                            value="{{ old('emergency_contact_phone', $patient->emergency_contact['phone'] ?? '') }}" />
                        <x-input label="Relation" name="emergency_contact_relation"
                            value="{{ old('emergency_contact_relation', $patient->emergency_contact['relation'] ?? '') }}" />
                    </div>

                    <x-input label="Known allergies" name="known_allergies"
                        help="Comma-separated, e.g. Penicillin, Peanuts"
                        value="{{ old('known_allergies', is_array($patient->known_allergies) ? implode(', ', $patient->known_allergies) : '') }}" />

                    <x-button type="submit" variant="primary">Save changes</x-button>
                </form>
            </x-card>
        </div>
    </div>
</x-layouts.authenticated>

<x-layouts.authenticated title="Doctor detail">
    <x-slot name="header">
        <x-breadcrumb :items="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Doctors', 'href' => route('doctors.index')],
            ['label' => $doctor->user?->full_name ?? 'Doctor'],
        ]" class="mb-3" />

        <x-page-header
            :title="$doctor->user?->full_name ?? 'Unnamed doctor'"
            :subtitle="$doctor->registration_number ? 'Reg. no. '.$doctor->registration_number : null"
        />
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-card title="Profile">
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
        </div>
    </div>
</x-layouts.authenticated>

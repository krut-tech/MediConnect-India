<x-layouts.authenticated title="Assign staff">
    <x-slot name="header">
        <x-breadcrumb :items="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Staff', 'href' => route('staff.index')],
            ['label' => 'Assign staff'],
        ]" class="mb-3" />

        <x-page-header
            title="Assign staff"
            subtitle="Links an already-registered MediConnect account to a role and facility. This does not create a new account — the person must already have signed up."
        />
    </x-slot>

    @error('user_email')
        <x-alert variant="danger" class="mb-4">{{ $message }}</x-alert>
    @enderror
    @error('staff')
        <x-alert variant="danger" class="mb-4">{{ $message }}</x-alert>
    @enderror

    <x-card>
        <form method="POST" action="{{ route('staff.store') }}" class="space-y-4">
            @csrf

            <x-input label="Person's email" name="user_email" type="email" placeholder="name@example.com" value="{{ old('user_email') }}" help="They must already have a MediConnect account." />

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="role_id" class="form-label">Role</label>
                    <select name="role_id" id="role_id" class="form-input">
                        @foreach($roleOptions as $role)
                            <option value="{{ $role->id }}" @selected((string) old('role_id') === (string) $role->id)>{{ $role->label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="facility_id" class="form-label">Facility</label>
                    <select name="facility_id" id="facility_id" class="form-input">
                        @foreach($facilityOptions as $facility)
                            <option value="{{ $facility->id }}" @selected(old('facility_id') === $facility->id)>{{ $facility->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <p class="text-sm text-ink-subtle">
                You can only successfully assign roles at a facility you administer (or any facility, if you're a
                super admin) — this is enforced by the database regardless of what's shown here.
            </p>

            <div class="flex gap-3">
                <x-button type="submit" variant="primary">Assign</x-button>
                <x-button href="{{ route('staff.index') }}" variant="secondary">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.authenticated>

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

            <div>
                <x-input label="Person's email" name="user_email" id="user_email" type="email" placeholder="name@example.com" value="{{ old('user_email') }}" help="They must already have a MediConnect account." />
                <p id="lookup-status" class="mt-1 text-sm text-ink-subtle"></p>
            </div>

            {{-- BUG 4: shows the existing user's current name (never
                 editable when one already exists) so the admin always
                 sees exactly who they're assigning before submitting.
                 Only becomes an editable field when the account's name
                 is genuinely blank — and even then, per
                 StaffController::store()'s docblock, this currently
                 only takes effect if the admin is completing their OWN
                 account's blank name (users_update_own RLS); a known,
                 documented limitation, not silently hidden. --}}
            <div id="name-section" class="hidden">
                <div id="name-known" class="hidden rounded-lg border border-surface-muted bg-surface-subtle px-3 py-2 text-sm text-ink">
                    Current name on file: <span id="name-known-value" class="font-medium"></span>
                </div>
                <div id="name-missing" class="hidden">
                    <x-input label="Full name (currently missing on their account)" name="full_name" id="full_name" type="text" value="{{ old('full_name') }}" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="role_id" class="form-label">Role</label>
                    <select name="role_id" id="role_id" class="form-input">
                        @foreach($roleOptions as $role)
                            <option value="{{ $role->id }}" data-code="{{ $role->code }}" @selected((string) old('role_id') === (string) $role->id)>{{ $role->label }}</option>
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

            {{-- BUG 3: admin-assisted doctor profile setup, shown only
                 when the selected role is Doctor. Wired to the new
                 doctor_profiles_write_facility_admin RLS policy. --}}
            <div id="doctor-fields" class="hidden space-y-4 rounded-lg border border-surface-muted p-4">
                <p class="text-sm font-medium text-ink">Doctor profile (optional — the doctor can also complete this themselves later)</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input label="Registration / license number" name="registration_number" type="text" value="{{ old('registration_number') }}" />
                    <x-input label="Specialty" name="specialty" type="text" value="{{ old('specialty') }}" />
                </div>
                <x-input label="Years of experience" name="years_experience" type="number" min="0" max="80" value="{{ old('years_experience') }}" />
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

    {{-- Plain vanilla JS only, matching this app's existing modular-JS
         approach (no framework) — calls the read-only /staff/lookup
         endpoint (debounced) and toggles the doctor-fields section based
         on the selected role's data-code, both purely presentational;
         neither changes what the server actually authorizes. --}}
    <script>
        (function () {
            const emailInput = document.getElementById('user_email');
            const status = document.getElementById('lookup-status');
            const nameSection = document.getElementById('name-section');
            const nameKnown = document.getElementById('name-known');
            const nameKnownValue = document.getElementById('name-known-value');
            const nameMissing = document.getElementById('name-missing');
            const roleSelect = document.getElementById('role_id');
            const doctorFields = document.getElementById('doctor-fields');

            let timer = null;
            emailInput.addEventListener('input', function () {
                clearTimeout(timer);
                const email = emailInput.value.trim();
                nameSection.classList.add('hidden');
                nameKnown.classList.add('hidden');
                nameMissing.classList.add('hidden');
                status.textContent = '';
                if (!email || !email.includes('@')) return;

                timer = setTimeout(function () {
                    status.textContent = 'Looking up account…';
                    fetch('{{ route('staff.lookup') }}?email=' + encodeURIComponent(email))
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (!data.found) {
                                status.textContent = 'No account found with this email — they must sign up first.';
                                return;
                            }
                            status.textContent = '';
                            nameSection.classList.remove('hidden');
                            if (data.name_missing) {
                                nameMissing.classList.remove('hidden');
                            } else {
                                nameKnown.classList.remove('hidden');
                                nameKnownValue.textContent = data.full_name;
                            }
                        })
                        .catch(function () {
                            status.textContent = '';
                        });
                }, 400);
            });

            function toggleDoctorFields() {
                const selected = roleSelect.options[roleSelect.selectedIndex];
                if (selected && selected.dataset.code === 'doctor') {
                    doctorFields.classList.remove('hidden');
                } else {
                    doctorFields.classList.add('hidden');
                }
            }
            roleSelect.addEventListener('change', toggleDoctorFields);
            toggleDoctorFields();
        })();
    </script>
</x-layouts.authenticated>

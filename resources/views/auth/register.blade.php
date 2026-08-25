<x-layouts.guest title="Create account">
    <x-card>
        <h1 class="text-xl font-semibold text-ink mb-1">Create your account</h1>
        <p class="text-sm text-ink-subtle mb-6">Register for MediConnect India.</p>

        @error('auth')
            <x-alert variant="danger" class="mb-4">{{ $message }}</x-alert>
        @enderror

        {{--
            Deliberately no role selector on this form — Milestone 1
            scope: public registration creates only auth.users ->
            public.users. Staff/patient linkage is separate, later,
            admin-driven work.
        --}}
        <form method="POST" action="{{ route('register') }}" class="space-y-4" novalidate>
            @csrf

            <x-input
                label="Full name"
                name="full_name"
                type="text"
                autocomplete="name"
                value="{{ old('full_name') }}"
                required
                autofocus
            />

            <x-input
                label="Email"
                name="email"
                type="email"
                autocomplete="email"
                inputmode="email"
                value="{{ old('email') }}"
                required
            />

            <x-input
                label="Phone (optional)"
                name="phone"
                type="tel"
                autocomplete="tel"
                value="{{ old('phone') }}"
            />

            <x-input
                label="Password"
                name="password"
                type="password"
                autocomplete="new-password"
                help="At least 8 characters."
                required
            />

            <x-input
                label="Confirm password"
                name="password_confirmation"
                type="password"
                autocomplete="new-password"
                required
            />

            <x-button type="submit" class="w-full">Create account</x-button>
        </form>

        <p class="mt-6 text-center text-sm text-ink-subtle">
            Already have an account?
            <a href="{{ route('login') }}" class="text-primary-600 font-medium hover:underline">Sign in</a>
        </p>
    </x-card>
</x-layouts.guest>

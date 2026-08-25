<x-layouts.guest title="Sign in">
    <x-card>
        <h1 class="text-xl font-semibold text-ink mb-1">Sign in</h1>
        <p class="text-sm text-ink-subtle mb-6">Access your MediConnect India account.</p>

        @if(session('status'))
            <x-alert variant="success" class="mb-4">{{ session('status') }}</x-alert>
        @endif

        @error('auth')
            <x-alert variant="danger" class="mb-4">{{ $message }}</x-alert>
        @enderror

        <form method="POST" action="{{ route('login') }}" class="space-y-4" novalidate>
            @csrf

            <x-input
                label="Email"
                name="email"
                type="email"
                autocomplete="email"
                inputmode="email"
                value="{{ old('email') }}"
                required
                autofocus
            />

            <x-input
                label="Password"
                name="password"
                type="password"
                autocomplete="current-password"
                required
            />

            <x-button type="submit" class="w-full">Sign in</x-button>
        </form>

        <p class="mt-6 text-center text-sm text-ink-subtle">
            Don't have an account?
            <a href="{{ route('register') }}" class="text-primary-600 font-medium hover:underline">Create one</a>
        </p>
    </x-card>
</x-layouts.guest>

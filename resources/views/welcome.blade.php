<x-layouts.guest title="MediConnect India">
    <x-card title="MediConnect India">
        <p class="text-sm text-ink-muted">
            Sign in to your account, or register as a new user. Staff and
            patient role assignment is handled separately by an
            administrator after registration.
        </p>

        <div class="mt-6 flex flex-wrap gap-3">
            <x-button href="{{ route('login') }}">Sign in</x-button>
            <x-button variant="secondary" href="{{ route('register') }}">Create account</x-button>
        </div>
    </x-card>
</x-layouts.guest>

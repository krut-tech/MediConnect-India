<x-layouts.guest title="Page not found">
    <x-card class="text-center">
        <x-empty-state
            title="Page not found"
            description="The page you're looking for doesn't exist, or the record has been removed."
        />
        <x-button variant="primary" href="{{ route('dashboard') }}" class="mt-4">
            Back to dashboard
        </x-button>
    </x-card>
</x-layouts.guest>

<x-layouts.authenticated title="Dashboard">
    <x-slot name="header">
        <x-page-header
            title="Dashboard"
            subtitle="Foundation placeholder — role-specific dashboards come in a later phase."
        />
    </x-slot>

    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <x-card title="Foundation status">
            <div class="space-y-3">
                <x-alert variant="info">
                    This is a placeholder dashboard proving the authenticated
                    layout, sidebar, navbar, and mobile navigation render
                    correctly. No patient/doctor/facility data is wired up yet.
                </x-alert>

                <div class="flex flex-wrap gap-2">
                    <x-badge variant="success">Layout: OK</x-badge>
                    <x-badge variant="neutral">Modules: Not started</x-badge>
                </div>
            </div>
        </x-card>

        <x-card title="Component preview">
            <div class="space-y-3">
                <x-button variant="primary">Primary</x-button>
                <x-button variant="secondary">Secondary</x-button>
                <x-button variant="danger">Danger</x-button>
            </div>
        </x-card>

        <x-card title="Empty state preview">
            <x-empty-state
                title="No appointments yet"
                description="This is a preview of the empty-state component future modules will reuse."
            />
        </x-card>
    </div>
</x-layouts.authenticated>

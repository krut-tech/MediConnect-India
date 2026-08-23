<x-layouts.app :title="$title ?? config('app.name')">
    <div class="flex min-h-screen">
        <x-sidebar class="hidden lg:flex" />

        <div class="flex-1 flex flex-col min-w-0">
            <x-navbar />

            <main class="flex-1 px-4 py-6 lg:px-8">
                @isset($header)
                    <div class="mb-6">
                        {{ $header }}
                    </div>
                @endisset

                {{ $slot }}
            </main>
        </div>
    </div>

    <x-mobile-nav />
</x-layouts.app>

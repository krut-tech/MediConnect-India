<x-layouts.app :title="$title ?? config('app.name')">
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-12">
        <div class="mb-8 flex items-center gap-2">
            <div class="h-9 w-9 rounded-lg bg-primary-600 flex items-center justify-center text-white font-semibold">
                M
            </div>
            <span class="text-lg font-semibold text-ink">MediConnect India</span>
        </div>

        <div class="w-full max-w-md">
            {{ $slot }}
        </div>
    </div>
</x-layouts.app>

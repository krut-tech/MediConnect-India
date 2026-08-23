@props(['class' => ''])

{{--
    Static placeholder nav items for Phase 2. Real, role-aware navigation
    (driven by roles/role_permissions data) is a later-phase concern —
    this only proves the layout shell renders correctly.
--}}
<aside {{ $attributes->merge(['class' => 'w-64 shrink-0 flex-col border-r border-surface-muted bg-white ' . $class]) }}>
    <div class="flex items-center gap-2 px-5 py-4 border-b border-surface-muted">
        <div class="h-8 w-8 rounded-lg bg-primary-600 flex items-center justify-center text-white text-sm font-semibold">
            M
        </div>
        <span class="text-sm font-semibold text-ink">MediConnect India</span>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
        <x-sidebar-link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')">
            Dashboard
        </x-sidebar-link>
    </nav>
</aside>

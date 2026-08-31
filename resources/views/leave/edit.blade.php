<x-layouts.authenticated title="Edit leave request">
    <x-slot name="header">
        <x-breadcrumb :items="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Leave & blocked periods', 'href' => route('leave.index')],
            ['label' => 'Edit'],
        ]" class="mb-3" />

        <x-page-header
            title="Edit leave request"
            subtitle="You can change this while it's still pending review. Once it's approved or rejected, it can no longer be edited here."
        />
    </x-slot>

    @error('leave')
        <x-alert variant="danger" class="mb-4">{{ $message }}</x-alert>
    @enderror

    <x-card>
        <form method="POST" action="{{ route('leave.update', $leave) }}" class="space-y-4">
            @csrf
            @method('PATCH')

            <div class="grid gap-4 sm:grid-cols-2">
                <x-input label="From" name="leave_start" type="date" value="{{ old('leave_start', $leave->leave_start?->format('Y-m-d')) }}" />
                <x-input label="To" name="leave_end" type="date" value="{{ old('leave_end', $leave->leave_end?->format('Y-m-d')) }}" />
            </div>
            <x-input label="Type (optional)" name="leave_type" type="text" placeholder="e.g. Personal, Sick, Conference" value="{{ old('leave_type', $leave->leave_type) }}" />
            <x-input label="Reason (optional)" name="reason" type="text" value="{{ old('reason', $leave->reason) }}" />

            <div class="flex gap-3">
                <x-button type="submit" variant="primary">Save changes</x-button>
                <x-button href="{{ route('leave.index') }}" variant="secondary">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.authenticated>

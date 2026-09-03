<x-layouts.authenticated title="Revoke approved leave">
    <x-slot name="header">
        <x-breadcrumb :items="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Leave & blocked periods', 'href' => route('leave.index')],
            ['label' => 'Revoke'],
        ]" class="mb-3" />

        <x-page-header
            title="Revoke approved leave?"
            subtitle="This does not delete the record — it will remain visible in history with status Revoked, and will no longer block appointment availability going forward."
        />
    </x-slot>

    @error('leave')
        <x-alert variant="danger" class="mb-4">{{ $message }}</x-alert>
    @enderror

    <x-card class="mb-4">
        <dl class="grid gap-3 sm:grid-cols-2">
            <div>
                <dt class="text-sm text-ink-subtle">Staff</dt>
                <dd class="font-medium text-ink">{{ $leave->staffAssignment?->user?->full_name ?? 'Name on file missing' }}</dd>
            </div>
            <div>
                <dt class="text-sm text-ink-subtle">Facility</dt>
                <dd class="font-medium text-ink">{{ $leave->staffAssignment?->facility?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm text-ink-subtle">Period</dt>
                <dd class="font-medium text-ink">{{ $leave->leave_start?->format('d M Y') }} – {{ $leave->leave_end?->format('d M Y') }}</dd>
            </div>
            <div>
                <dt class="text-sm text-ink-subtle">Status</dt>
                <dd><x-badge variant="success">Approved</x-badge></dd>
            </div>
        </dl>
    </x-card>

    <x-card>
        <form method="POST" action="{{ route('leave.revoke', $leave) }}" class="space-y-4">
            @csrf
            @method('PATCH')

            <div>
                <label for="revocation_reason" class="form-label">Reason for revocation</label>
                <textarea
                    name="revocation_reason"
                    id="revocation_reason"
                    rows="3"
                    required
                    class="form-input"
                >{{ old('revocation_reason') }}</textarea>
                @error('revocation_reason')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex gap-3">
                <x-button type="submit" variant="danger">Revoke leave</x-button>
                <x-button href="{{ route('leave.index') }}" variant="secondary">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.authenticated>

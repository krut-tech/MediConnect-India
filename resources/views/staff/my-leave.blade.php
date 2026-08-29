<x-layouts.authenticated title="My Leave">
    <x-slot name="header">
        <x-breadcrumb :items="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'My Leave'],
        ]" class="mb-3" />

        <x-page-header
            title="My Leave"
            subtitle="Request leave and track the status of your requests."
        />
    </x-slot>

    @if(session('status'))
        <x-alert variant="success" class="mb-4">{{ session('status') }}</x-alert>
    @endif

    @error('leave')
        <x-alert variant="danger" class="mb-4">{{ $message }}</x-alert>
    @enderror

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">
            @if(!$hasActiveAssignment)
                <x-card>
                    <x-empty-state
                        title="No active staff assignment found"
                        description="Your account isn't linked to an active staff assignment right now. Contact your facility admin if this seems wrong."
                    />
                </x-card>
            @elseif($leaveRequests->isEmpty())
                <x-card>
                    <x-empty-state
                        title="No leave requests yet"
                        description="Requests you submit will appear here along with their approval status."
                    />
                </x-card>
            @else
                <x-card class="!p-0">
                    <x-table :headings="['From', 'To', 'Status']">
                        @foreach($leaveRequests as $leave)
                            <tr>
                                <td class="font-medium text-ink">{{ $leave->leave_start->toFormattedDateString() }}</td>
                                <td class="text-ink-muted">{{ $leave->leave_end->toFormattedDateString() }}</td>
                                <td>
                                    <x-badge :variant="match($leave->status) {
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        default => 'warning',
                                    }">
                                        {{ ucfirst($leave->status) }}
                                    </x-badge>
                                </td>
                            </tr>
                        @endforeach
                    </x-table>
                </x-card>

                <div class="mt-4">
                    <x-pagination :paginator="$leaveRequests" />
                </div>
            @endif
        </div>

        <div>
            @if($hasActiveAssignment)
                <x-card title="Request leave">
                    <form method="POST" action="{{ route('staff.my-leave.store') }}" class="space-y-4">
                        @csrf
                        <x-input label="From" name="leave_start" type="date" value="{{ old('leave_start') }}" />
                        <x-input label="To" name="leave_end" type="date" value="{{ old('leave_end') }}" />
                        <x-button type="submit" variant="primary">Submit request</x-button>
                    </form>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.authenticated>

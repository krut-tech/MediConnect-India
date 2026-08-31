<x-layouts.authenticated title="Leave & blocked periods">
    <x-slot name="header">
        <x-breadcrumb :items="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Leave & blocked periods'],
        ]" class="mb-3" />

        <x-page-header
            title="Leave & blocked periods"
            subtitle="Request time off, or (for facility admins) review and decide staff requests. A row here marks a staff member unavailable for a date range — it does not by itself cancel any existing bookings."
        />
    </x-slot>

    @if(session('status'))
        <x-alert variant="success" class="mb-4">{{ session('status') }}</x-alert>
    @endif

    @error('leave')
        <x-alert variant="danger" class="mb-4">{{ $message }}</x-alert>
    @enderror

    @if(session('leave_conflict'))
        @php($conflict = session('leave_conflict'))
        <x-alert variant="warning" class="mb-4">
            <p class="font-medium">
                Approving this leave would affect {{ $conflict['total'] }} already-booked appointment{{ $conflict['total'] === 1 ? '' : 's' }}
                for {{ $conflict['doctor_name'] ?? 'this doctor' }} ({{ $conflict['leave_start'] }} – {{ $conflict['leave_end'] }}).
            </p>
            <ul class="mt-2 list-disc pl-5 text-sm">
                @foreach($conflict['by_date'] as $date => $count)
                    <li>{{ $date }} — {{ $count }} patient{{ $count === 1 ? '' : 's' }}</li>
                @endforeach
            </ul>
            <p class="mt-2 text-sm">
                Approving will mark these appointments "Needs follow-up" (visible on the Appointments page) so
                nothing is silently lost — it will not cancel or reschedule them automatically. Resolve them
                manually (contact the patient, or cancel individually from Appointments) once approved.
            </p>
            <form method="POST" action="{{ route('leave.approve', $conflict['leave_id']) }}" class="mt-3">
                @csrf
                @method('PATCH')
                <input type="hidden" name="confirm" value="1">
                <x-button type="submit" variant="primary">Confirm and approve anyway</x-button>
            </form>
        </x-alert>
    @endif

    @if($isAdministrator)
        <x-card title="Requests to review" class="mb-6">
            @php($pending = $leave->where('status', 'requested'))
            @if($pending->isEmpty())
                <x-empty-state
                    title="No pending requests"
                    description="Nothing from your facility's staff is currently awaiting a decision."
                />
            @else
                <div class="hidden sm:block">
                    <x-table :headings="['Staff member', 'Facility', 'Type', 'From', 'To', 'Reason', '']">
                        @foreach($pending as $row)
                            <tr>
                                <td class="font-medium text-ink">{{ $row->staffAssignment?->user?->full_name ?? 'Name on file missing' }} <span class="text-ink-subtle">({{ $row->staffAssignment?->role?->label ?? '—' }})</span></td>
                                <td class="text-ink-muted">{{ $row->staffAssignment?->facility?->name ?? '—' }}</td>
                                <td class="text-ink-muted">{{ $row->leave_type ?? '—' }}</td>
                                <td class="text-ink-muted">{{ $row->leave_start?->format('d M Y') }}</td>
                                <td class="text-ink-muted">{{ $row->leave_end?->format('d M Y') }}</td>
                                <td class="max-w-[16rem] truncate text-ink-muted" title="{{ $row->reason }}">{{ $row->reason ?? '—' }}</td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <form method="POST" action="{{ route('leave.approve', $row) }}">
                                            @csrf
                                            @method('PATCH')
                                            <x-button type="submit" variant="primary">Approve</x-button>
                                        </form>
                                        <form method="POST" action="{{ route('leave.reject', $row) }}">
                                            @csrf
                                            @method('PATCH')
                                            <x-button type="submit" variant="danger">Reject</x-button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-table>
                </div>

                <div class="space-y-3 sm:hidden">
                    @foreach($pending as $row)
                        <div class="rounded-lg border border-surface-muted p-3">
                            <p class="font-medium text-ink">{{ $row->staffAssignment?->user?->full_name ?? 'Name on file missing' }}</p>
                            <p class="mt-0.5 text-sm text-ink-subtle">{{ $row->staffAssignment?->role?->label ?? '—' }} · {{ $row->staffAssignment?->facility?->name ?? '—' }}</p>
                            <p class="mt-0.5 text-sm text-ink-subtle">{{ $row->leave_type ?? 'Type not specified' }} · {{ $row->leave_start?->format('d M Y') }} – {{ $row->leave_end?->format('d M Y') }}</p>
                            @if($row->reason)
                                <p class="mt-0.5 text-sm text-ink-subtle">"{{ $row->reason }}"</p>
                            @endif
                            <div class="mt-2 flex gap-2">
                                <form method="POST" action="{{ route('leave.approve', $row) }}" class="w-full">
                                    @csrf
                                    @method('PATCH')
                                    <x-button type="submit" variant="primary" class="w-full">Approve</x-button>
                                </form>
                                <form method="POST" action="{{ route('leave.reject', $row) }}" class="w-full">
                                    @csrf
                                    @method('PATCH')
                                    <x-button type="submit" variant="danger" class="w-full">Reject</x-button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>
    @endif

    <x-card title="My requests" class="mb-6">
        @php($mine = $leave->where('staff_assignment_id', $activeAssignment?->id))
        @if($mine->isEmpty())
            <x-empty-state
                title="No leave or blocked periods on file"
                description="Use the form below to request time off, or to block a period from new appointments."
            />
        @else
            <div class="hidden sm:block">
                <x-table :headings="['Type', 'From', 'To', 'Status', 'Decided by', 'Decision note']">
                    @foreach($mine as $row)
                        <tr>
                            <td class="text-ink-muted">{{ $row->leave_type ?? '—' }}</td>
                            <td class="text-ink-muted">{{ $row->leave_start?->format('d M Y') }}</td>
                            <td class="text-ink-muted">{{ $row->leave_end?->format('d M Y') }}</td>
                            <td>
                                <x-badge :variant="match($row->status) {
                                    'approved' => 'success',
                                    'rejected' => 'danger',
                                    default => 'warning',
                                }">{{ ucfirst($row->status) }}</x-badge>
                            </td>
                            <td class="text-ink-muted">
                                @if($row->reviewedByUser)
                                    {{ $row->reviewedByUser->full_name }} · {{ $row->reviewed_at?->format('d M Y') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="max-w-[14rem] truncate text-ink-muted" title="{{ $row->decision_reason }}">{{ $row->decision_reason ?? '—' }}</td>
                        </tr>
                    @endforeach
                </x-table>
            </div>

            <div class="space-y-3 sm:hidden">
                @foreach($mine as $row)
                    <div class="rounded-lg border border-surface-muted p-3">
                        <p class="font-medium text-ink">{{ $row->leave_start?->format('d M Y') }} – {{ $row->leave_end?->format('d M Y') }}</p>
                        <p class="mt-0.5 text-sm text-ink-subtle">{{ $row->leave_type ?? 'Type not specified' }}</p>
                        <x-badge class="mt-1" :variant="match($row->status) {
                            'approved' => 'success',
                            'rejected' => 'danger',
                            default => 'warning',
                        }">{{ ucfirst($row->status) }}</x-badge>
                        @if($row->reviewedByUser)
                            <p class="mt-1 text-sm text-ink-subtle">Decided by {{ $row->reviewedByUser->full_name }} · {{ $row->reviewed_at?->format('d M Y') }}</p>
                        @endif
                        @if($row->decision_reason)
                            <p class="mt-0.5 text-sm text-ink-subtle">"{{ $row->decision_reason }}"</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>

    @if($activeAssignment)
        <x-card title="Request leave / block a period">
            <form method="POST" action="{{ route('leave.store') }}" class="space-y-4">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input label="From" name="leave_start" type="date" value="{{ old('leave_start') }}" />
                    <x-input label="To" name="leave_end" type="date" value="{{ old('leave_end') }}" />
                </div>
                <x-input label="Type (optional)" name="leave_type" type="text" placeholder="e.g. Personal, Sick, Conference" value="{{ old('leave_type') }}" />
                <x-input label="Reason (optional)" name="reason" type="text" value="{{ old('reason') }}" />
                <x-button type="submit" variant="primary">Submit request</x-button>
            </form>
        </x-card>
    @else
        <x-alert variant="warning">No active staff assignment was found for your account — leave/blocked-period requests need one.</x-alert>
    @endif
</x-layouts.authenticated>

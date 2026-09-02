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
        <x-card class="mb-4">
            <form method="GET" action="{{ route('leave.index') }}" class="grid gap-3 sm:grid-cols-4">
                <x-input label="Search" name="q" type="text" placeholder="Staff member name" value="{{ $filters['q'] }}" />
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">Status</label>
                    <select name="status" class="w-full rounded-lg border border-surface-muted px-3 py-2 text-sm">
                        <option value="">All statuses</option>
                        @foreach($statusOptions as $option)
                            <option value="{{ $option }}" @selected($filters['status'] === $option)>{{ ucfirst($option) }}</option>
                        @endforeach
                    </select>
                </div>
                <x-input label="From" name="date_from" type="date" value="{{ $filters['date_from'] }}" />
                <x-input label="To" name="date_to" type="date" value="{{ $filters['date_to'] }}" />
                <div class="sm:col-span-4 flex gap-2">
                    <x-button type="submit" variant="primary">Filter</x-button>
                    @if($filters['q'] || $filters['status'] || $filters['date_from'] || $filters['date_to'])
                        <x-button href="{{ route('leave.index') }}" variant="secondary">Clear</x-button>
                    @endif
                </div>
            </form>
        </x-card>

        <x-card title="Requests to review" class="mb-6">
            @php($pending = $leave->where('status', 'requested'))
            @if($pending->isEmpty())
                <x-empty-state
                    title="No pending requests"
                    description="Nothing from your facility's staff is currently awaiting a decision (or none match your filter)."
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

        {{--
            PHASE 6 AUDIT CORRECTION: this card is the fix for the
            original production-visibility bug. Everything above only
            ever showed status='requested' rows. Everything below ("My
            requests") only ever shows the signed-in admin's OWN
            staff_assignment_id. Neither subset can ever contain, say,
            another doctor's APPROVED leave — even though RLS
            (staff_leave_facility_admin) and the controller's filters
            both already return it correctly in $leave. This card shows
            the full filtered $leave collection (every status, every
            staff member RLS permits) so that data is no longer fetched
            and then thrown away.

            PHASE 6 CORRECTION (approved-leave revoke): the Actions
            column's Revoke button is scoped to status==='approved'
            only — every other status (requested/rejected/cancelled/
            revoked) intentionally shows no destructive action here,
            per spec item 14 ("no destructive actions unless explicitly
            supported"). A still-'requested' row belongs to the
            "Requests to review" card above, not here, so Approve/Reject
            never appear in this table. There is deliberately no Revoke
            button anywhere in "My requests" below — an admin's own
            approved leave can still be revoked (the real boundary is
            facility-scoped RLS, not "whose row is this"), but only by
            reaching it through this facility-wide table, not through
            the self-service section, per spec item 6's "no self-service
            revoke of one's own approved leave" intent.
        --}}
        <x-card title="Leave & blocked periods" class="mb-6">
            @if($leave->isEmpty())
                <x-empty-state
                    title="No leave records match these filters."
                    description="Try widening the status, date range, or staff name filter above."
                />
            @else
                <div class="hidden lg:block overflow-x-auto">
                    <x-table :headings="['Staff', 'Role', 'Facility', 'Department', 'Period', 'Type', 'Reason', 'Status', 'Requested by', 'Requested at', 'Decided by', 'Decided at', 'Revoked', '']">
                        @foreach($leave as $row)
                            <tr>
                                <td class="font-medium text-ink">{{ $row->staffAssignment?->user?->full_name ?? 'Name on file missing' }}</td>
                                <td class="text-ink-muted">{{ $row->staffAssignment?->role?->label ?? '—' }}</td>
                                <td class="text-ink-muted">{{ $row->staffAssignment?->facility?->name ?? '—' }}</td>
                                <td class="text-ink-muted">{{ $row->staffAssignment?->department?->name ?? '—' }}</td>
                                <td class="text-ink-muted whitespace-nowrap">{{ $row->leave_start?->format('d M Y') }} – {{ $row->leave_end?->format('d M Y') }}</td>
                                <td class="text-ink-muted">{{ $row->leave_type ?? '—' }}</td>
                                <td class="max-w-[12rem] truncate text-ink-muted" title="{{ $row->reason }}">{{ $row->reason ?? '—' }}</td>
                                <td>
                                    <x-badge :variant="match($row->status) {
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        'cancelled', 'revoked' => 'neutral',
                                        default => 'warning',
                                    }">{{ ucfirst($row->status) }}</x-badge>
                                </td>
                                <td class="text-ink-muted">
                                    {{ $row->requestedByUser?->full_name ?? '—' }}
                                </td>
                                <td class="text-ink-muted">{{ $row->created_at?->format('d M Y') ?? '—' }}</td>
                                <td class="text-ink-muted">
                                    @if($row->status === 'approved' || $row->status === 'rejected')
                                        {{ $row->reviewedByUser?->full_name ?? '—' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-ink-muted">{{ $row->reviewed_at?->format('d M Y') ?? '—' }}</td>
                                <td class="max-w-[10rem] text-ink-muted" title="{{ $row->revocation_reason }}">
                                    @if($row->status === 'revoked')
                                        {{ $row->revokedByUser?->full_name ?? '—' }}<br>
                                        <span class="text-xs">{{ $row->revoked_at?->format('d M Y') ?? '—' }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if($row->status === 'approved')
                                        <x-button href="{{ route('leave.revoke.confirm', $row) }}" variant="danger">Revoke</x-button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </x-table>
                </div>

                <div class="space-y-3 lg:hidden">
                    @foreach($leave as $row)
                        <div class="rounded-lg border border-surface-muted p-3">
                            <p class="font-medium text-ink">{{ $row->staffAssignment?->user?->full_name ?? 'Name on file missing' }}</p>
                            <p class="mt-0.5 text-sm text-ink-subtle">{{ $row->staffAssignment?->role?->label ?? '—' }} · {{ $row->staffAssignment?->facility?->name ?? '—' }}@if($row->staffAssignment?->department) · {{ $row->staffAssignment->department->name }}@endif</p>
                            <p class="mt-0.5 text-sm text-ink-subtle">{{ $row->leave_type ?? 'Type not specified' }} · {{ $row->leave_start?->format('d M Y') }} – {{ $row->leave_end?->format('d M Y') }}</p>
                            @if($row->reason)
                                <p class="mt-0.5 text-sm text-ink-subtle">"{{ $row->reason }}"</p>
                            @endif
                            <x-badge class="mt-1" :variant="match($row->status) {
                                'approved' => 'success',
                                'rejected' => 'danger',
                                'cancelled', 'revoked' => 'neutral',
                                default => 'warning',
                            }">{{ ucfirst($row->status) }}</x-badge>
                            <p class="mt-1 text-sm text-ink-subtle">Requested by {{ $row->requestedByUser?->full_name ?? '—' }} · {{ $row->created_at?->format('d M Y') ?? '—' }}</p>
                            @if($row->status === 'approved' || $row->status === 'rejected')
                                <p class="mt-0.5 text-sm text-ink-subtle">Decided by {{ $row->reviewedByUser?->full_name ?? '—' }}{{ $row->reviewed_at ? ' · '.$row->reviewed_at->format('d M Y') : '' }}</p>
                            @endif
                            @if($row->status === 'revoked')
                                <p class="mt-0.5 text-sm text-ink-subtle">Revoked by {{ $row->revokedByUser?->full_name ?? '—' }}{{ $row->revoked_at ? ' · '.$row->revoked_at->format('d M Y') : '' }}</p>
                                @if($row->revocation_reason)
                                    <p class="mt-0.5 text-sm text-ink-subtle">"{{ $row->revocation_reason }}"</p>
                                @endif
                            @endif
                            @if($row->status === 'approved')
                                <div class="mt-2">
                                    <x-button href="{{ route('leave.revoke.confirm', $row) }}" variant="danger" class="w-full">Revoke</x-button>
                                </div>
                            @endif
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
                <x-table :headings="['Type', 'From', 'To', 'Status', 'Decided by', 'Decision note', '']">
                    @foreach($mine as $row)
                        <tr>
                            <td class="text-ink-muted">{{ $row->leave_type ?? '—' }}</td>
                            <td class="text-ink-muted">{{ $row->leave_start?->format('d M Y') }}</td>
                            <td class="text-ink-muted">{{ $row->leave_end?->format('d M Y') }}</td>
                            <td>
                                <x-badge :variant="match($row->status) {
                                    'approved' => 'success',
                                    'rejected' => 'danger',
                                    'cancelled', 'revoked' => 'neutral',
                                    default => 'warning',
                                }">{{ ucfirst($row->status) }}</x-badge>
                            </td>
                            <td class="text-ink-muted">
                                @if($row->status === 'revoked')
                                    {{ $row->revokedByUser?->full_name ?? '—' }} · {{ $row->revoked_at?->format('d M Y') }}
                                @elseif($row->reviewedByUser)
                                    {{ $row->reviewedByUser->full_name }} · {{ $row->reviewed_at?->format('d M Y') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="max-w-[14rem] truncate text-ink-muted" title="{{ $row->status === 'revoked' ? $row->revocation_reason : $row->decision_reason }}">{{ ($row->status === 'revoked' ? $row->revocation_reason : $row->decision_reason) ?? '—' }}</td>
                            <td class="text-right">
                                @if($row->status === 'requested')
                                    <div class="flex items-center justify-end gap-2">
                                        <x-button href="{{ route('leave.edit', $row) }}" variant="secondary">Edit</x-button>
                                        <form method="POST" action="{{ route('leave.withdraw', $row) }}" onsubmit="return confirm('Withdraw this request?');">
                                            @csrf
                                            @method('PATCH')
                                            <x-button type="submit" variant="danger">Withdraw</x-button>
                                        </form>
                                    </div>
                                @endif
                            </td>
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
                            'cancelled', 'revoked' => 'neutral',
                            default => 'warning',
                        }">{{ ucfirst($row->status) }}</x-badge>
                        @if($row->status === 'revoked')
                            <p class="mt-1 text-sm text-ink-subtle">Revoked by {{ $row->revokedByUser?->full_name ?? '—' }} · {{ $row->revoked_at?->format('d M Y') }}</p>
                            @if($row->revocation_reason)
                                <p class="mt-0.5 text-sm text-ink-subtle">"{{ $row->revocation_reason }}"</p>
                            @endif
                        @elseif($row->reviewedByUser)
                            <p class="mt-1 text-sm text-ink-subtle">Decided by {{ $row->reviewedByUser->full_name }} · {{ $row->reviewed_at?->format('d M Y') }}</p>
                            @if($row->decision_reason)
                                <p class="mt-0.5 text-sm text-ink-subtle">"{{ $row->decision_reason }}"</p>
                            @endif
                        @endif
                        @if($row->status === 'requested')
                            <div class="mt-2 flex gap-2">
                                <x-button href="{{ route('leave.edit', $row) }}" variant="secondary" class="w-full">Edit</x-button>
                                <form method="POST" action="{{ route('leave.withdraw', $row) }}" class="w-full" onsubmit="return confirm('Withdraw this request?');">
                                    @csrf
                                    @method('PATCH')
                                    <x-button type="submit" variant="danger" class="w-full">Withdraw</x-button>
                                </form>
                            </div>
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

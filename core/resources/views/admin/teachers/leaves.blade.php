@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <div class="col-lg-12 mb-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">@lang('Leave Notification Recipients')</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.teachers.leaves.recipients.store') }}" method="POST" class="row g-3 align-items-end mb-4">
                        @csrf
                        <div class="col-md-8">
                            <label class="form-label" for="leave-recipient-email">@lang('Add Recipient Email')</label>
                            <input
                                type="email"
                                id="leave-recipient-email"
                                name="email"
                                class="form-control"
                                value="{{ old('email') }}"
                                placeholder="ops@aiclub.com"
                                required
                            >
                            <small class="text-muted">@lang('Every active recipient will receive an email when a teacher applies for leave.')</small>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn--primary w-100">@lang('Add Recipient')</button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table--light style--two">
                            <thead>
                                <tr>
                                    <th>@lang('Email')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Actions')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($mailRecipients as $recipient)
                                    <tr>
                                        <td>
                                            <form action="{{ route('admin.teachers.leaves.recipients.update', $recipient->id) }}" method="POST" class="d-flex gap-2 align-items-center flex-wrap">
                                                @csrf
                                                <input type="email" name="email" class="form-control form-control-sm" style="min-width:280px;" value="{{ $recipient->email }}" required>
                                        </td>
                                        <td>
                                                <select name="is_active" class="form-select form-select-sm" style="min-width: 120px;">
                                                    <option value="1" @selected($recipient->is_active)>@lang('Active')</option>
                                                    <option value="0" @selected(!$recipient->is_active)>@lang('Inactive')</option>
                                                </select>
                                        </td>
                                        <td>
                                                <button type="submit" class="btn btn-sm btn--primary">@lang('Edit')</button>
                                            </form>
                                            <form action="{{ route('admin.teachers.leaves.recipients.delete', $recipient->id) }}" method="POST" class="d-inline-block ms-2" onsubmit="return confirm('Delete this recipient?');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn--danger">@lang('Delete')</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">@lang('No recipient emails added yet')</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">@lang('Teacher Leave Requests')</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table--light style--two mb-0">
                            <thead>
                                <tr>
                                    <th>@lang('Teacher')</th>
                                    <th>@lang('Date Range')</th>
                                    <th>@lang('Reason')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Admin Note')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($leaves as $leave)
                                    <tr>
                                        <td>
                                            <span class="fw-semibold">{{ $leave->teacher?->firstname }} {{ $leave->teacher?->lastname }}</span><br>
                                            <small>@{{ $leave->teacher?->username }}</small>
                                        </td>
                                        <td>{{ $leave->from_date }} - {{ $leave->to_date }}</td>
                                        <td style="max-width: 280px; white-space: normal;">{{ $leave->reason }}</td>
                                        <td>
                                            @if($leave->status === 'approved')
                                                <span class="badge badge--success">{{ strtoupper($leave->status) }}</span>
                                            @elseif($leave->status === 'rejected')
                                                <span class="badge badge--danger">{{ strtoupper($leave->status) }}</span>
                                            @else
                                                <span class="badge badge--warning">{{ strtoupper($leave->status) }}</span>
                                            @endif
                                        </td>
                                        <td style="max-width: 260px; white-space: normal;">{{ $leave->admin_note ?: '-' }}</td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline--primary" data-bs-toggle="modal" data-bs-target="#leaveAction{{ $leave->id }}">
                                                @lang('Update')
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">@lang('No leave requests found')</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($leaves->hasPages())
                    <div class="card-footer py-3">
                        {{ paginateLinks($leaves) }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    @foreach($leaves as $leave)
        <div class="modal fade" id="leaveAction{{ $leave->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">@lang('Update Leave Request')</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="{{ route('admin.teachers.leaves.status', $leave->id) }}">
                        @csrf
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">@lang('Status')</label>
                                <select name="status" class="form-control" required>
                                    <option value="approved" @selected($leave->status === 'approved')>@lang('Approve')</option>
                                    <option value="rejected" @selected($leave->status === 'rejected')>@lang('Reject')</option>
                                </select>
                            </div>
                            <div class="mb-0">
                                <label class="form-label">@lang('Admin Note')</label>
                                <textarea name="admin_note" class="form-control" rows="3" placeholder="@lang('Optional note for teacher')">{{ $leave->admin_note }}</textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline--dark" data-bs-dismiss="modal">@lang('Close')</button>
                            <button type="submit" class="btn btn--primary">@lang('Save')</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection

@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <div class="col-lg-12 mb-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">@lang('Mail Setting')</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.consultation.requests.mail.recipients.store') }}" method="POST" class="row g-3 align-items-end mb-4">
                        @csrf
                        <div class="col-md-8">
                            <label class="form-label" for="email">@lang('Add Recipient Email')</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="form-control"
                                value="{{ old('email') }}"
                                placeholder="naveen.c@ravviolabs.com"
                                required
                            >
                            <small class="text-muted">@lang('Every active recipient will receive a notification whenever a consultation call is booked.')</small>
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
                                            <form action="{{ route('admin.consultation.requests.mail.recipients.update', $recipient->id) }}" method="POST" class="d-flex gap-2 align-items-center flex-wrap">
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
                                            <form action="{{ route('admin.consultation.requests.mail.recipients.delete', $recipient->id) }}" method="POST" class="d-inline-block ms-2" onsubmit="return confirm('Delete this recipient?');">
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
                <div class="card-body p-0">
                    <div class="table-responsive--sm table-responsive">
                        <table class="table table--light style--two">
                            <thead>
                                <tr>
                                    <th>@lang('Name')</th>
                                    <th>@lang('Child Grade')</th>
                                    <th>@lang('Phone')</th>
                                    <th>@lang('Email')</th>
                                    <th>@lang('Preferred Time')</th>
                                    <th>@lang('Schedule')</th>
                                    <th>@lang('Course')</th>
                                    <th>@lang('Assigned Teacher')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Source')</th>
                                    <th>@lang('Submitted At')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($requests as $item)
                                    @php
                                        $status = strtolower((string) $item->status);
                                        $statusClass = match ($status) {
                                            'scheduled' => 'badge--success',
                                            'assigned_to_teacher' => 'badge--info',
                                            'completed' => 'badge--success',
                                            'cancelled', 'rejected' => 'badge--danger',
                                            default => 'badge--warning',
                                        };
                                    @endphp
                                    <tr>
                                        <td>{{ $item->parent_student_name }}</td>
                                        <td>{{ $item->child_grade }}</td>
                                        <td>{{ $item->phone_number }}</td>
                                        <td>{{ $item->email_address }}</td>
                                        <td>{{ $item->preferred_at ? showDateTime($item->preferred_at, 'd M Y h:i A') : '-' }}</td>
                                        <td>
                                            <form method="POST" action="{{ route('admin.consultation.requests.assign.teacher', $item->id) }}" class="d-flex gap-2 align-items-center flex-wrap">
                                                @csrf
                                                <div class="d-flex flex-column gap-2" style="min-width: 300px;">
                                                    <label class="form-label mb-0">@lang('Assigned Teacher')</label>
                                                    <select name="assigned_teacher_id" class="form-select form-select-sm" required>
                                                        <option value="">@lang('Select Teacher')</option>
                                                        @foreach($teachers as $teacher)
                                                            <option value="{{ $teacher->id }}" @selected($item->assigned_teacher_id == $teacher->id)>
                                                                {{ $teacher->fullname }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <select name="course_id" class="form-select form-select-sm" required>
                                                        <option value="">@lang('Select Course')</option>
                                                        @foreach($courses as $course)
                                                            <option value="{{ $course->id }}" @selected($item->course_id == $course->id)>
                                                                {{ $course->title }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <input
                                                        type="datetime-local"
                                                        step="1800"
                                                        name="scheduled_at"
                                                        class="form-control form-control-sm"
                                                        value="{{ $item->scheduled_at ? \Carbon\Carbon::parse($item->scheduled_at)->format('Y-m-d\\TH:i') : ($item->preferred_at ? \Carbon\Carbon::parse($item->preferred_at)->format('Y-m-d\\TH:i') : '') }}"
                                                        required
                                                    >
                                                    <input
                                                        type="number"
                                                        name="meeting_duration"
                                                        class="form-control form-control-sm"
                                                        min="15"
                                                        max="240"
                                                        value="{{ $item->meeting_duration ?: 30 }}"
                                                        placeholder="@lang('Duration in minutes')"
                                                    >
                                                    <textarea
                                                        name="notes"
                                                        class="form-control form-control-sm"
                                                        rows="2"
                                                        placeholder="@lang('Notes')"
                                                    >{{ $item->notes }}</textarea>
                                                    <button type="submit" class="btn btn-sm btn--primary">@lang('Assign Teacher')</button>
                                                </div>
                                            </form>
                                        </td>
                                        <td>{{ $item->course?->title ?: '-' }}</td>
                                        <td>{{ $item->assignedTeacher?->fullname ?: '-' }}</td>
                                        <td><span class="badge {{ $statusClass }}">{{ strtoupper($item->status ?? 'pending_admin_assignment') }}</span></td>
                                        <td>{{ $item->source }}</td>
                                        <td>{{ showDateTime($item->created_at) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">{{ __($emptyMessage) }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($requests->hasPages())
                    <div class="card-footer py-4">
                        {{ paginateLinks($requests) }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('breadcrumb-plugins')
    <x-search-form placeholder="Search by name, grade, phone or email" />
@endpush

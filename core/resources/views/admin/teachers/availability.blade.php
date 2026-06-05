@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">@lang('Check Teacher Availability')</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.teachers.availability') }}" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">@lang('Date')</label>
                            <input type="date" name="date" class="form-control" value="{{ old('date', $filters['date']) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">@lang('Start Time')</label>
                            <input type="time" name="start_time" min="09:00" max="21:00" step="1800" class="form-control" value="{{ old('start_time', $filters['start_time']) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">@lang('Duration (Minutes)')</label>
                            <input type="number" name="duration" min="1" max="480" class="form-control" value="{{ old('duration', $filters['duration']) }}" required>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="unavailable_only" value="1" id="unavailableOnlyFilter" @checked((int)($filters['unavailable_only'] ?? 0) === 1)>
                                <label class="form-check-label" for="unavailableOnlyFilter">
                                    @lang('Only Unavailable Slot Conflicts')
                                </label>
                            </div>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn--primary w-100">@lang('Check')</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @if ($checked)
        <div class="row mt-3">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            @lang('Availability Result'):
                            {{ $filters['date'] }} {{ $filters['start_time'] }} ({{ $filters['duration'] }} @lang('minutes'))
                            @if((int)($filters['unavailable_only'] ?? 0) === 1)
                                - @lang('Filtered: Unavailable Slot Conflicts Only')
                            @endif
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table--light style--two mb-0">
                                <thead>
                                    <tr>
                                        <th>@lang('Teacher')</th>
                                        <th>@lang('Email')</th>
                                            <th>@lang('Live Session Conflicts')</th>
                                            <th>@lang('Booking Conflict')</th>
                                            <th>@lang('Unavailable Slot Conflict')</th>
                                            <th>@lang('Status')</th>
                                            <th>@lang('Action')</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($teachers as $teacher)
                                        <tr>
                                            <td>
                                                <span class="fw-bold">{{ $teacher->firstname }} {{ $teacher->lastname }}</span><br>
                                                <small>@{{ $teacher->username }}</small>
                                            </td>
                                            <td>{{ $teacher->email }}</td>
                                            <td>{{ $teacher->session_conflict_count }}</td>
                                            <td>
                                                @if($teacher->booking_conflict)
                                                    <span class="badge badge--danger">@lang('Yes')</span>
                                                @else
                                                    <span class="badge badge--success">@lang('No')</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($teacher->unavailable_slot_conflict)
                                                    <span class="badge badge--danger">@lang('Yes')</span>
                                                @else
                                                    <span class="badge badge--success">@lang('No')</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($teacher->is_available)
                                                    <span class="badge badge--success">@lang('Available')</span>
                                                @else
                                                    <span class="badge badge--danger">@lang('Unavailable')</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if(!$teacher->is_available)
                                                    <button type="button" class="btn btn-sm btn-outline--primary" data-bs-toggle="modal" data-bs-target="#teacherAvailabilityDetail{{ $teacher->id }}">
                                                        <i class="las la-eye"></i> @lang('Details')
                                                    </button>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">@lang('No teachers found')</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @foreach($teachers as $teacher)
            @if(!$teacher->is_available)
                <div class="modal fade" id="teacherAvailabilityDetail{{ $teacher->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    @lang('Teacher Schedule Details'):
                                    {{ $teacher->firstname }} {{ $teacher->lastname }} (@{{ $teacher->username }})
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-4">
                                    <h6 class="mb-2">@lang('Conflicting Meetings In Selected Window')</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>@lang('Course')</th>
                                                    <th>@lang('Lecture')</th>
                                                    <th>@lang('Batch')</th>
                                                    <th>@lang('Date Time')</th>
                                                    <th>@lang('Duration')</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($teacher->conflicting_sessions as $session)
                                                    <tr>
                                                        <td>{{ $session->course?->title ?? '-' }}</td>
                                                        <td>{{ $session->lecture?->title ?? '-' }}</td>
                                                        <td>{{ $session->batch?->title ?? '-' }}</td>
                                                        <td>{{ showDateTime($session->scheduled_at, 'Y-m-d h:i A') }}</td>
                                                        <td>{{ $session->duration_minutes }} @lang('mins')</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="5" class="text-muted text-center">@lang('No live session conflicts')</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <h6 class="mb-2">@lang('One-To-One Booking Conflicts')</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>@lang('Course')</th>
                                                    <th>@lang('Student')</th>
                                                    <th>@lang('Date')</th>
                                                    <th>@lang('Time')</th>
                                                    <th>@lang('Status')</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($teacher->booking_conflicts as $booking)
                                                    <tr>
                                                        <td>{{ $booking->course?->title ?? '-' }}</td>
                                                        <td>{{ trim(($booking->user?->firstname ?? '') . ' ' . ($booking->user?->lastname ?? '')) ?: ($booking->user?->username ?? '-') }}</td>
                                                        <td>{{ $booking->start_date }}</td>
                                                        <td>{{ $booking->start_time }} - {{ $booking->end_time }}</td>
                                                        <td><span class="badge badge--warning">{{ strtoupper($booking->status) }}</span></td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="5" class="text-muted text-center">@lang('No one-to-one booking conflicts')</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <h6 class="mb-2">@lang('Teacher Marked Unavailable Slots')</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>@lang('Start')</th>
                                                    <th>@lang('End')</th>
                                                    <th>@lang('Reason')</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($teacher->unavailable_slot_conflicts as $slot)
                                                    <tr>
                                                        <td>{{ showDateTime($slot->start_at, 'Y-m-d h:i A') }}</td>
                                                        <td>{{ showDateTime($slot->end_at, 'Y-m-d h:i A') }}</td>
                                                        <td>{{ $slot->reason ?: '-' }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="3" class="text-muted text-center">@lang('No unavailable slot conflicts')</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div>
                                    <h6 class="mb-2">@lang('Full Scheduled Meetings')</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>@lang('Course')</th>
                                                    <th>@lang('Lecture')</th>
                                                    <th>@lang('Batch')</th>
                                                    <th>@lang('Date Time')</th>
                                                    <th>@lang('Duration')</th>
                                                    <th>@lang('Status')</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($teacher->full_schedule as $session)
                                                    <tr>
                                                        <td>{{ $session->course?->title ?? '-' }}</td>
                                                        <td>{{ $session->lecture?->title ?? '-' }}</td>
                                                        <td>{{ $session->batch?->title ?? '-' }}</td>
                                                        <td>{{ showDateTime($session->scheduled_at, 'Y-m-d h:i A') }}</td>
                                                        <td>{{ $session->duration_minutes }} @lang('mins')</td>
                                                        <td><span class="badge badge--info">{{ strtoupper($session->status) }}</span></td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="6" class="text-muted text-center">@lang('No scheduled meetings found')</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline--dark" data-bs-dismiss="modal">@lang('Close')</button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    @endif
@endsection

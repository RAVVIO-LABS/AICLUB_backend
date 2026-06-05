@extends('admin.layouts.app')

@section('panel')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.teachers.students', $teacher->id) }}">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-9">
                        <label class="form-label">@lang('Search')</label>
                        <input type="text" class="form-control" name="search"
                            placeholder="@lang('Student / Course / Batch / Email')" value="{{ $search }}">
                    </div>
                    <div class="col-lg-3 d-flex gap-2">
                        <button type="submit" class="btn btn--primary w-100">
                            <i class="las la-search"></i> @lang('Filter')
                        </button>
                        <a href="{{ route('admin.teachers.students', $teacher->id) }}" class="btn btn-outline--dark w-100">
                            <i class="las la-redo-alt"></i> @lang('Reset')
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <div>
                <h5 class="card-title mb-0">@lang('Assigned Students')</h5>
                <small class="text-muted">
                    {{ $teacher->fullname }} (<span>@</span>{{ $teacher->username }})
                </small>
            </div>
            <a href="{{ route('admin.teachers.detail', $teacher->id) }}" class="btn btn-sm btn-outline--primary">
                <i class="las la-arrow-left"></i> @lang('Back to Teacher')
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table--light style--two mb-0">
                    <thead>
                        <tr>
                            <th>@lang('Student')</th>
                            <th>@lang('Course')</th>
                            <th>@lang('Batch')</th>
                            <th>@lang('Class Type')</th>
                            <th>@lang('Booking Status')</th>
                            <th>@lang('Sessions')</th>
                            <th>@lang('Last Session')</th>
                            <th>@lang('Trial Completed')</th>
                            <th>@lang('Zoom Links')</th>
                            <th>@lang('Amount')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($studentRows as $row)
                            <tr>
                                <td>
                                    <span class="fw-bold">{{ $row['student_name'] ?: __('N/A') }}</span><br>
                                    @if($row['user_id'])
                                        <a href="{{ route('admin.users.detail', $row['user_id']) }}">
                                            {{ $row['student_email'] ?: '@'.$row['student_username'] }}
                                        </a>
                                    @else
                                        <small class="text-muted">{{ $row['student_email'] ?: '@'.$row['student_username'] }}</small>
                                    @endif
                                </td>
                                <td>{{ $row['course_title'] ?: __('N/A') }}</td>
                                <td>{{ $row['batch_title'] ?: __('N/A') }}</td>
                                <td>
                                    <span class="badge badge--info">{{ strtoupper(str_replace('_', ' ', $row['class_type'] ?: 'N/A')) }}</span>
                                </td>
                                <td>
                                    <span class="badge badge--primary">{{ strtoupper(str_replace('_', ' ', $row['booking_status'] ?: 'N/A')) }}</span>
                                </td>
                                <td>
                                    {{ $row['sessions_covered'] }} / {{ $row['sessions_covered'] + $row['sessions_left'] }}<br>
                                    <small class="text-muted">@lang('Left'): {{ $row['sessions_left'] }}</small>
                                </td>
                                <td>
                                    @if($row['last_session_at'])
                                        {{ showDateTime($row['last_session_at']) }}<br>
                                        <small class="text-muted">{{ diffForHumans($row['last_session_at']) }}</small>
                                    @else
                                        <span class="text-muted">@lang('N/A')</span>
                                    @endif
                                </td>
                                <td>
                                    @if($row['trial_completed_at'])
                                        {{ showDateTime($row['trial_completed_at']) }}
                                    @else
                                        <span class="text-muted">@lang('N/A')</span>
                                    @endif
                                </td>
                                <td>
                                    @if(collect($row['zoom_links'])->isNotEmpty())
                                        @foreach($row['zoom_links']->take(2) as $link)
                                            <a href="{{ $link }}" target="_blank" rel="noopener noreferrer" class="d-block">@lang('Link')</a>
                                        @endforeach
                                        @if($row['zoom_links']->count() > 2)
                                            <small class="text-muted">+{{ $row['zoom_links']->count() - 2 }}</small>
                                        @endif
                                    @else
                                        <span class="text-muted">@lang('N/A')</span>
                                    @endif
                                </td>
                                <td>{{ showAmount($row['amount']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-center text-muted py-4" colspan="100%">@lang('No assigned students found')</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($bookings->hasPages())
            <div class="card-footer py-3">
                {{ paginateLinks($bookings) }}
            </div>
        @endif
    </div>
@endsection

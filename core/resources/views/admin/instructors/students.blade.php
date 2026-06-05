@extends('admin.layouts.app')

@section('panel')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.instructors.students') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-4">
                        <label class="form-label">@lang('Search')</label>
                        <input type="text" class="form-control" name="search"
                            placeholder="@lang('Kid / Course / Academy / Email')" value="{{ $search }}">
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label">@lang('Academy')</label>
                        <select name="instructor_id" class="form-control select2">
                            <option value="0">@lang('All Academy')</option>
                            @foreach($instructors as $instructor)
                                <option value="{{ $instructor->id }}" @selected($instructorId === (int) $instructor->id)>
                                    {{ trim($instructor->firstname . ' ' . $instructor->lastname) ?: $instructor->username }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-4 d-flex gap-2">
                        <button type="submit" class="btn btn--primary w-100">
                            <i class="las la-search"></i> @lang('Filter')
                        </button>
                        <a href="{{ route('admin.instructors.students') }}" class="btn btn-outline--dark w-100">
                            <i class="las la-redo-alt"></i> @lang('Reset')
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">@lang('Student Details')</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table--light style--two mb-0">
                    <thead>
                        <tr>
                            <th>@lang('Kid')</th>
                            <th>@lang('Course')</th>
                            <th>@lang('Class Type')</th>
                            <th>@lang('Sessions Covered')</th>
                            <th>@lang('Sessions Left')</th>
                            <th>@lang('Last Session')</th>
                            <th>@lang('Academy')</th>
                            <th>@lang('Trial Completed')</th>
                            <th>@lang('Courses Bought')</th>
                            <th>@lang('Zoom Links (Trial / 1:1 / Group)')</th>
                            <th>@lang('1:1 Content Release')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($studentRows as $row)
                            <tr>
                                <td>
                                    <span class="fw-bold">{{ $row['kid_name'] ?: __('N/A') }}</span><br>
                                    <small class="text-muted">{{ $row['kid_email'] ?: '@'.$row['kid_username'] }}</small>
                                </td>
                                <td>{{ $row['course_title'] ?: __('N/A') }}</td>
                                <td><span class="badge badge--info">{{ strtoupper(str_replace('_', ' ', (string) $row['class_type'])) }}</span></td>
                                <td>{{ $row['sessions_covered'] }}</td>
                                <td>{{ $row['sessions_left'] }}</td>
                                <td>
                                    @if($row['last_session_at'])
                                        {{ showDateTime($row['last_session_at']) }}<br>
                                        <small class="text-muted">{{ diffForHumans($row['last_session_at']) }}</small>
                                    @else
                                        <span class="text-muted">@lang('N/A')</span>
                                    @endif
                                </td>
                                <td>{{ $row['course_instructor'] }}</td>
                                <td>
                                    @if($row['trial_completed_at'])
                                        {{ showDateTime($row['trial_completed_at']) }}
                                    @else
                                        <span class="text-muted">@lang('N/A')</span>
                                    @endif
                                </td>
                                <td>{{ $row['courses_bought'] }}</td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        <div>
                                            <small class="text-muted">@lang('Trial'):</small>
                                            @if(collect($row['trial_zoom_links'])->isNotEmpty())
                                                @foreach($row['trial_zoom_links']->take(2) as $link)
                                                    <a href="{{ $link }}" target="_blank" rel="noopener noreferrer" class="ms-1">@lang('Link')</a>
                                                @endforeach
                                                @if($row['trial_zoom_links']->count() > 2)
                                                    <small class="text-muted ms-1">+{{ $row['trial_zoom_links']->count() - 2 }}</small>
                                                @endif
                                            @else
                                                <span class="text-muted ms-1">@lang('N/A')</span>
                                            @endif
                                        </div>
                                        <div>
                                            <small class="text-muted">@lang('1:1'):</small>
                                            @if(collect($row['one_to_one_zoom_links'])->isNotEmpty())
                                                @foreach($row['one_to_one_zoom_links']->take(2) as $link)
                                                    <a href="{{ $link }}" target="_blank" rel="noopener noreferrer" class="ms-1">@lang('Link')</a>
                                                @endforeach
                                                @if($row['one_to_one_zoom_links']->count() > 2)
                                                    <small class="text-muted ms-1">+{{ $row['one_to_one_zoom_links']->count() - 2 }}</small>
                                                @endif
                                            @else
                                                <span class="text-muted ms-1">@lang('N/A')</span>
                                            @endif
                                        </div>
                                        <div>
                                            <small class="text-muted">@lang('Group'):</small>
                                            @if(collect($row['group_zoom_links'])->isNotEmpty())
                                                @foreach($row['group_zoom_links']->take(2) as $link)
                                                    <a href="{{ $link }}" target="_blank" rel="noopener noreferrer" class="ms-1">@lang('Link')</a>
                                                @endforeach
                                                @if($row['group_zoom_links']->count() > 2)
                                                    <small class="text-muted ms-1">+{{ $row['group_zoom_links']->count() - 2 }}</small>
                                                @endif
                                            @else
                                                <span class="text-muted ms-1">@lang('N/A')</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td style="min-width: 280px;">
                                    @if(($row['class_type'] ?? '') === 'one_to_one' && !empty($row['booking_id']))
                                        <form method="POST" action="{{ route('admin.instructors.students.release.one.to.one.lecture', $row['booking_id']) }}">
                                            @csrf
                                            <div class="d-flex flex-column gap-2">
                                                <select name="lecture_id" class="form-control form-control-sm" required>
                                                    <option value="">@lang('Select Lecture')</option>
                                                    @foreach(($row['lecture_options'] ?? collect()) as $lectureOption)
                                                        @php
                                                            $releaseKey = ((int) $row['booking_id']) . ':' . ((int) $lectureOption['id']);
                                                            $isReleased = (bool) ($oneToOneReleaseMap[$releaseKey] ?? false);
                                                        @endphp
                                                        <option value="{{ $lectureOption['id'] }}">
                                                            {{ $lectureOption['section_title'] }} - {{ $lectureOption['title'] }} {{ $isReleased ? __('(Released)') : __('(Hidden)') }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <div class="form-check form-switch">
                                                    <input type="hidden" name="is_released" value="0">
                                                    <input class="form-check-input" type="checkbox" role="switch" name="is_released" value="1" checked>
                                                    <label class="form-check-label">@lang('Release selected lecture')</label>
                                                </div>
                                                <button type="submit" class="btn btn-sm btn-outline--primary">@lang('Update')</button>
                                            </div>
                                        </form>
                                    @else
                                        <span class="text-muted">@lang('Only for one-to-one')</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-muted text-center py-4" colspan="100%">@lang('No student records found')</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($purchases->hasPages())
            <div class="card-footer py-3">
                {{ paginateLinks($purchases) }}
            </div>
        @endif
    </div>
@endsection

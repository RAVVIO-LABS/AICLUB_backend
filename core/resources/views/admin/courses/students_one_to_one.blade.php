@extends('admin.layouts.app')

@section('panel')
    <div class="row gy-3">
        @forelse($courses as $course)
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">{{ __($course->title) }}</h6>
                            <small class="text-muted">
                                @lang('Academy'): {{ @$course->instructor->firstname }} {{ @$course->instructor->lastname }}
                                ({{ @$course->instructor->username }})
                            </small>
                        </div>
                        <a class="btn btn-sm btn-outline--primary" href="{{ route('admin.courses.details', $course->slug) }}">
                            <i class="las la-desktop"></i> @lang('Course Details')
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive table-responsive--sm">
                            <table class="table table--light style--two custom-data-table mb-0">
                                <thead>
                                    <tr>
                                        <th>@lang('Student')</th>
                                        <th>@lang('Schedule')</th>
                                        <th>@lang('Meeting Details')</th>
                                        <th>@lang('Duration')</th>
                                        <th>@lang('Teacher')</th>
                                        <th>@lang('Status')</th>
                                        <th>@lang('Amount')</th>
                                        <th>@lang('Content Release')</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($course->liveBookings as $booking)
                                        <tr>
                                            <td>
                                                <strong>{{ @$booking->user->firstname }} {{ @$booking->user->lastname }}</strong><br>
                                                <a href="{{ route('admin.users.detail', $booking->user_id) }}">
                                                    <span>@</span>{{ @$booking->user->username }}
                                                </a>
                                            </td>
                                            <td>
                                                @if($booking->start_date && $booking->start_time)
                                                    {{ showDateTime($booking->start_date, 'Y-m-d') }}
                                                    {{ $booking->start_time }} - {{ $booking->end_time ?: '--:--' }}
                                                @else
                                                    <span class="text-muted">@lang('Not scheduled')</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($booking->sessions->count() > 0)
                                                    <div class="d-flex flex-column gap-2">
                                                        @foreach($booking->sessions as $session)
                                                            <div class="border rounded p-2">
                                                                <div><strong>@lang('Lecture'):</strong> {{ optional($session->lecture)->title ?? __('N/A') }}</div>
                                                                <div><strong>@lang('Time'):</strong> {{ showDateTime($session->scheduled_at, 'd M Y h:i A') }}</div>
                                                                @if($session->zoom_join_url)
                                                                    <a href="{{ $session->zoom_join_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline--primary mt-1">
                                                                        @lang('Join')
                                                                    </a>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <span class="text-muted">@lang('No meetings generated')</span>
                                                @endif
                                            </td>
                                            <td>{{ $booking->class_duration ?: '--' }} @lang('mins')</td>
                                            <td>
                                                @if($booking->assignedTeacher)
                                                    {{ $booking->assignedTeacher->firstname }} {{ $booking->assignedTeacher->lastname }}
                                                @else
                                                    <span class="text-muted">@lang('Not assigned')</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge badge--info">{{ strtoupper($booking->status) }}</span>
                                            </td>
                                            <td>{{ showAmount($booking->purchase?->amount ?? 0) }}</td>
                                            <td style="min-width: 280px;">
                                                <form method="POST" action="{{ route('admin.courses.release.one.to.one.lecture', $booking->id) }}">
                                                    @csrf
                                                    <div class="d-flex flex-column gap-2">
                                                        <select name="lecture_id" class="form-control form-control-sm" required>
                                                            <option value="">@lang('Select Lecture')</option>
                                                            @foreach($course->sections as $section)
                                                                @foreach($section->curriculums as $curriculum)
                                                                    @foreach($curriculum->lectures as $lecture)
                                                                        @php
                                                                            $releaseKey = $booking->id . ':' . $lecture->id;
                                                                            $isReleased = (bool) ($oneToOneReleaseMap[$releaseKey] ?? false);
                                                                        @endphp
                                                                        <option value="{{ $lecture->id }}">
                                                                            {{ $section->title }} - {{ $lecture->title }} {{ $isReleased ? __('(Released)') : __('(Hidden)') }}
                                                                        </option>
                                                                    @endforeach
                                                                @endforeach
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
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="100%" class="text-center text-muted">{{ __($emptyMessage) }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <p class="text-center text-muted mb-0">{{ __($emptyMessage) }}</p>
                    </div>
                </div>
            </div>
        @endforelse
    </div>

    @if ($courses->hasPages())
        <div class="card mt-3">
            <div class="card-body py-3">
                {{ paginateLinks($courses) }}
            </div>
        </div>
    @endif
@endsection

@push('breadcrumb-plugins')
    <a href="{{ route('admin.courses.one.to.one.schedule') }}" class="btn btn-outline--primary btn-sm">
        <i class="las la-calendar-check"></i> @lang('One-To-One Scheduler')
    </a>
    <x-search-form />
@endpush

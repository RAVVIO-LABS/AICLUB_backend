@extends('admin.layouts.app')

@section('panel')
    <div class="row gy-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0">@lang('Course Batch Enrollments')</h5>
                        <small class="text-muted">
                            {{ $course->title }}
                            @if($course->instructor)
                                | @lang('Academy'): {{ $course->instructor->fullname ?: $course->instructor->username }}
                            @endif
                        </small>
                    </div>
                    <a href="{{ route('admin.courses.details', $course->slug) }}" class="btn btn-sm btn-outline--primary">
                        <i class="las la-arrow-left"></i> @lang('Back to Course')
                    </a>
                </div>
            </div>
        </div>

        @forelse($batches as $batch)
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">{{ $batch['title'] }}</h6>
                            <small class="text-muted">
                                @if($batch['start_date'])
                                    {{ showDateTime($batch['start_date'], 'Y-m-d') }}
                                @else
                                    @lang('Date not set')
                                @endif
                                @if($batch['meeting_start_time'] || $batch['meeting_end_time'])
                                    | {{ $batch['meeting_start_time'] ?: '--' }} - {{ $batch['meeting_end_time'] ?: '--' }}
                                @endif
                            </small>
                        </div>
                        <div class="text-end">
                            <span class="badge badge--primary">@lang('Enrolled'): {{ $batch['students_count'] }}</span>
                            @if($batch['capacity'] > 0)
                                <span class="badge badge--info">@lang('Capacity'): {{ $batch['capacity'] }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>@lang('Teacher Assigned'):</strong>
                            @if($batch['teachers']->isNotEmpty())
                                {{ $batch['teachers']->implode(', ') }}
                            @else
                                <span class="text-muted">@lang('Not assigned')</span>
                            @endif
                        </div>

                        <div class="table-responsive">
                            <table class="table table--light style--two mb-0">
                                <thead>
                                    <tr>
                                        <th>@lang('Student')</th>
                                        <th>@lang('Email / Username')</th>
                                        <th>@lang('Booking Status')</th>
                                        <th>@lang('Assigned Teacher')</th>
                                        <th>@lang('Amount')</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($batch['students'] as $student)
                                        <tr>
                                            <td>
                                                @if($student['user_id'])
                                                    <a href="{{ route('admin.users.detail', $student['user_id']) }}" class="fw-bold">
                                                        {{ $student['name'] ?: __('N/A') }}
                                                    </a>
                                                @else
                                                    <span class="fw-bold">{{ $student['name'] ?: __('N/A') }}</span>
                                                @endif
                                            </td>
                                            <td>{{ $student['email'] ?: '@'.$student['username'] }}</td>
                                            <td>
                                                <span class="badge badge--info">{{ strtoupper(str_replace('_', ' ', $student['status'])) }}</span>
                                            </td>
                                            <td>{{ $student['assigned_teacher'] ?: __('N/A') }}</td>
                                            <td>{{ showAmount($student['amount']) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="100%" class="text-center text-muted">@lang('No enrolled kids found for this batch')</td>
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
                    <div class="card-body text-center text-muted py-4">
                        @lang('No zoom batches found for this course')
                    </div>
                </div>
            </div>
        @endforelse
    </div>
@endsection

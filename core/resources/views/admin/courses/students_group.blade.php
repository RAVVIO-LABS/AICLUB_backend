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
                    <div class="card-body">
                        @forelse($course->liveBatches as $batch)
                            <div class="border rounded p-3 mb-3">
                                <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-2">
                                    <div>
                                        <h6 class="mb-0">{{ __($batch->title ?: 'Untitled Batch') }}</h6>
                                        <small class="text-muted">
                                            {{ showDateTime($batch->start_date, 'Y-m-d') }} -
                                            {{ $batch->meeting_start_time }} to {{ $batch->meeting_end_time }}
                                        </small>
                                    </div>
                                    <span class="badge badge--primary">@lang('Batch')</span>
                                </div>

                                <div class="table-responsive table-responsive--sm">
                                    <table class="table table--light style--two custom-data-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>@lang('Student')</th>
                                                <th>@lang('Booking Status')</th>
                                                <th>@lang('Teacher')</th>
                                                <th>@lang('Amount')</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($batch->bookings as $booking)
                                                <tr>
                                                    <td>
                                                        <strong>{{ @$booking->user->firstname }} {{ @$booking->user->lastname }}</strong><br>
                                                        <a href="{{ route('admin.users.detail', $booking->user_id) }}">
                                                            <span>@</span>{{ @$booking->user->username }}
                                                        </a>
                                                    </td>
                                                    <td><span class="badge badge--info">{{ strtoupper($booking->status) }}</span></td>
                                                    <td>
                                                        @if($booking->assignedTeacher)
                                                            {{ $booking->assignedTeacher->firstname }} {{ $booking->assignedTeacher->lastname }}
                                                        @else
                                                            <span class="text-muted">@lang('Not assigned')</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ showAmount($booking->purchase?->amount ?? 0) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="100%" class="text-center text-muted">@lang('No students found for this batch')</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted mb-0">{{ __($emptyMessage) }}</p>
                        @endforelse
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
    <x-search-form />
@endpush


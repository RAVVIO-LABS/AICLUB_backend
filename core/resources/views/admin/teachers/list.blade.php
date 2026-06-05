@extends('admin.layouts.app')
@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive--md  table-responsive">
                        <table class="table table--light style--two">
                            <thead>
                                <tr>
                                    <th>@lang('Academy')</th>
                                    <th>@lang('Email-Mobile')</th>
                                    <th>@lang('Country')</th>
                                    <th>@lang('Total Courses')</th>
                                    <th>@lang('Assigned Students')</th>
                                    <th>@lang('Trial Classes')</th>
                                    <th>@lang('Class Completion')</th>
                                    <th>@lang('Class Cancelled')</th>
                                    <th>@lang('Total Earnings')</th>
                                    <th>@lang('Joined At')</th>
                                    <th>@lang('Balance')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($teachers as $teacher)
                                    <tr>
                                        <td>
                                            <span class="fw-bold">{{ $teacher->fullname }}</span>
                                            <br>
                                            <span class="small">
                                                <a
                                                    href="{{ route('admin.teachers.detail', $teacher->id) }}"><span>@</span>{{ $teacher->username }}</a>
                                            </span>
                                        </td>


                                        <td>
                                            {{ $teacher->email }}<br>{{ $teacher->mobileNumber }}
                                        </td>
                                        <td>
                                            <span class="fw-bold"
                                                title="{{ @$teacher->country_name }}">{{ $teacher->country_code }}</span>
                                        </td>

                                        <td>
                                            <span class="fw-bold">
                                                {{ $teacher->courses_count }} @lang('Courses')
                                            </span>
                                        </td>

                                        <td>
                                            <span class="fw-bold">
                                                {{ $teacher->performance_stats['assigned_students'] ?? 0 }}
                                            </span>
                                        </td>

                                        <td>
                                            <span class="fw-bold">
                                                {{ $teacher->performance_stats['trial_classes'] ?? 0 }}
                                            </span>
                                        </td>

                                        <td>
                                            <span class="d-block small">
                                                1:1: <span class="fw-bold">{{ $teacher->performance_stats['one_to_one_completed'] ?? 0 }}</span>
                                            </span>
                                            <span class="d-block small">
                                                Group: <span class="fw-bold">{{ $teacher->performance_stats['group_completed'] ?? 0 }}</span>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="d-block small">
                                                1:1: <span class="fw-bold">{{ $teacher->performance_stats['one_to_one_cancelled'] ?? 0 }}</span>
                                            </span>
                                            <span class="d-block small">
                                                Group: <span class="fw-bold">{{ $teacher->performance_stats['group_cancelled'] ?? 0 }}</span>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="fw-bold">
                                                {{ showAmount($teacher->total_earnings_amount ?? 0) }}
                                            </span>
                                        </td>

                                        <td>
                                            {{ showDateTime($teacher->created_at) }} <br>
                                            {{ diffForHumans($teacher->created_at) }}
                                        </td>


                                        <td>
                                            <span class="fw-bold">

                                                {{ showAmount($teacher->balance) }}
                                            </span>
                                        </td>

                                        <td>
                                            <div class="button--group">
                                                <a href="{{ route('admin.teachers.detail', $teacher->id) }}"
                                                    class="btn btn-sm btn-outline--primary">
                                                    <i class="las la-desktop"></i> @lang('Details')
                                                </a>
                                                @if (request()->routeIs('admin.teachers.pending.approval'))
                                                    <form method="POST" action="{{ route('admin.teachers.approve.request', $teacher->id) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline--success">
                                                            <i class="las la-check"></i> @lang('Approve')
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="{{ route('admin.teachers.reject.request', $teacher->id) }}" class="d-inline">
                                                        @csrf
                                                        <input type="hidden" name="reason" value="Registration request rejected by admin">
                                                        <button type="submit" class="btn btn-sm btn-outline--danger">
                                                            <i class="las la-times"></i> @lang('Reject')
                                                        </button>
                                                    </form>
                                                @endif
                                                @if (request()->routeIs('admin.teachers.kyc.pending'))
                                                    <a href="{{ route('admin.teachers.kyc.details', $teacher->id) }}"
                                                        target="_blank" class="btn btn-sm btn-outline--dark">
                                                        <i class="las la-user-check"></i>@lang('KYC Data')
                                                    </a>
                                                @endif
                                            </div>
                                        </td>

                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">{{ __($emptyMessage) }}</td>
                                    </tr>
                                @endforelse

                            </tbody>
                        </table><!-- table end -->
                    </div>
                </div>
                @if ($teachers->hasPages())
                    <div class="card-footer py-4">
                        {{ paginateLinks($teachers) }}
                    </div>
                @endif
            </div>
        </div>


    </div>
@endsection



@push('breadcrumb-plugins')
    <a href="{{ route('admin.teachers.leaves') }}" class="btn btn-outline--warning btn-sm">
        <i class="las la-calendar-times"></i> @lang('Leaves')
        @if(!empty($pendingLeaveCount))
            <span class="badge badge--danger ms-1">{{ $pendingLeaveCount }}</span>
        @endif
    </a>
    <a href="{{ route('admin.teachers.availability') }}" class="btn btn-outline--primary btn-sm">
        <i class="las la-calendar-check"></i> @lang('Availability')
    </a>
    <x-search-form placeholder="Username / Email" />
@endpush

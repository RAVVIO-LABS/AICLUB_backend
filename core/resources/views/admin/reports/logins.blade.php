@extends('admin.layouts.app')

@section('panel')
    <div class="row">

        <div class="col-lg-12">
            <div class="card">
                <div class="card-body p-0">

                    <div class="table-responsive--sm table-responsive">
                        <table class="table table--light style--two">
                            <thead>
                                <tr>
                                    <th>@lang('User')</th>
                                    <th>@lang('User Type')</th>
                                    <th>@lang('Login at')</th>
                                    <th>@lang('IP')</th>
                                    <th>@lang('Location')</th>
                                    <th>@lang('Browser | OS')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($loginLogs as $log)
                                    <tr>

                                        <td>
                                            @if ($log->user)
                                                <span class="fw-bold">{{ @$log->user->fullname }}</span>
                                                <br>
                                                <span class="small"> <a
                                                        href="{{ route('admin.users.detail', $log->user_id) }}"><span>@</span>{{ @$log->user->username }}</a>
                                                </span>
                                            @else
                                                <span class="fw-bold">{{ @$log->instructor->fullname }}</span>
                                                <br>
                                                @if (@$log->instructor->role_type === 'teacher')
                                                    <span class="small"> <a
                                                            href="{{ route('admin.teachers.detail', $log->instructor_id) }}"><span>@</span>{{ @$log->instructor->username }}</a>
                                                    </span>
                                                @else
                                                    <span class="small"> <a
                                                            href="{{ route('admin.instructors.detail', $log->instructor_id) }}"><span>@</span>{{ @$log->instructor->username }}</a>
                                                    </span>
                                                @endif
                                            @endif

                                        </td>
                                        <td>
                                            @if ($log->user)
                                                <span class="badge badge--warning">@lang('User')</span>
                                            @else
                                                @if (@$log->instructor->role_type === 'teacher')
                                                    <span class="badge badge--info">@lang('Teacher')</span>
                                                @else
                                                    <span class="badge badge--success">@lang('Academy')</span>
                                                @endif
                                            @endif

                                            
                                        </td>


                                        <td>
                                            {{ showDateTime($log->created_at) }} <br> {{ diffForHumans($log->created_at) }}
                                        </td>



                                        <td>
                                            <span class="fw-bold">
                                                <a
                                                    href="{{ route('admin.report.login.ipHistory', [$log->user_ip]) }}">{{ $log->user_ip }}</a>
                                            </span>
                                        </td>

                                        <td>{{ __($log->city) }} <br> {{ __($log->country) }}</td>
                                        <td>
                                            {{ __($log->browser) }} <br> {{ __($log->os) }}
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
                @if ($loginLogs->hasPages())
                    <div class="card-footer py-4">
                        {{ paginateLinks($loginLogs) }}
                    </div>
                @endif
            </div><!-- card end -->
        </div>


    </div>
@endsection



@push('breadcrumb-plugins')
    @if (request()->routeIs('admin.report.login.history'))
        <x-search-form placeholder="Search Username" dateSearch='yes' />
    @endif
@endpush
@if (request()->routeIs('admin.report.login.ipHistory') )
    @push('breadcrumb-plugins')
        <a href="https://www.ip2location.com/{{ $ip }}" target="_blank"
            class="btn btn-outline--primary">@lang('Lookup IP') {{ $ip }}</a>
    @endpush
@endif

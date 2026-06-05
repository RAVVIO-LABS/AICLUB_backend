@extends('admin.layouts.app')
@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">@lang('Pending Academy Approvals')</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive--md table-responsive">
                        <table class="table table--light style--two">
                            <thead>
                                <tr>
                                    <th>@lang('Academy')</th>
                                    <th>@lang('Email-Mobile')</th>
                                    <th>@lang('Country')</th>
                                    <th>@lang('Total Courses')</th>
                                    <th>@lang('Total Earnings')</th>
                                    <th>@lang('Joined At')</th>
                                    <th>@lang('Balance')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($instructors as $instructor)
                                    <tr>
                                        <td>
                                            <span class="fw-bold">{{ $instructor->fullname }}</span>
                                            <br>
                                            <span class="small">
                                                <a href="{{ route('admin.instructors.detail', $instructor->id) }}"><span>@</span>{{ $instructor->username }}</a>
                                            </span>
                                        </td>
                                        <td>
                                            {{ $instructor->email }}<br>{{ $instructor->mobileNumber }}
                                        </td>
                                        <td>
                                            <span class="fw-bold" title="{{ @$instructor->country_name }}">{{ $instructor->country_code }}</span>
                                        </td>
                                        <td>
                                            <span class="fw-bold">{{ $instructor->courses->count() }} @lang('Courses')</span>
                                        </td>
                                        <td>
                                            <span class="fw-bold">{{ showAmount($instructor->transactions->where('remark', 'payment_received')->sum('amount')) }}</span>
                                        </td>
                                        <td>
                                            {{ showDateTime($instructor->created_at) }} <br>
                                            {{ diffForHumans($instructor->created_at) }}
                                        </td>
                                        <td>
                                            <span class="fw-bold">{{ showAmount($instructor->balance) }}</span>
                                        </td>
                                        <td>
                                            <div class="button--group">
                                                <a href="{{ route('admin.instructors.detail', $instructor->id) }}" class="btn btn-sm btn-outline--primary">
                                                    <i class="las la-desktop"></i> @lang('Details')
                                                </a>
                                                <form method="POST" action="{{ route('admin.instructors.approve.request', $instructor->id) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline--success">
                                                        <i class="las la-check"></i> @lang('Approve')
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.instructors.reject.request', $instructor->id) }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="reason" value="Registration request rejected by admin">
                                                    <button type="submit" class="btn btn-sm btn-outline--danger">
                                                        <i class="las la-times"></i> @lang('Reject')
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
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
                @if ($instructors->hasPages())
                    <div class="card-footer py-4">
                        {{ paginateLinks($instructors) }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('breadcrumb-plugins')
    <x-search-form placeholder="Username / Email" />
@endpush

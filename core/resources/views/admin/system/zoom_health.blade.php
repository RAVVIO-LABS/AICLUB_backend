@extends('admin.layouts.app')

@section('panel')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">@lang('Zoom Health Check')</h5>
                <form method="POST" action="{{ route('admin.system.zoom.health.run') }}">
                    @csrf
                    <button type="submit" class="btn btn--primary btn-sm">@lang('Run Check')</button>
                </form>
            </div>
            <div class="card-body">
                @if (!$report)
                    <p class="mb-0 text-muted">@lang('Run the health check to verify Zoom OAuth and host-pool accessibility.')</p>
                @else
                    <div class="mb-3">
                        <h6>@lang('OAuth Status')</h6>
                        @if($report['oauth']['ok'])
                            <span class="badge badge--success">@lang('OK')</span>
                        @else
                            <span class="badge badge--danger">@lang('FAILED')</span>
                        @endif
                        <p class="mt-2 mb-0">{{ $report['oauth']['message'] }}</p>
                    </div>

                    <h6>@lang('Host Pool Status')</h6>
                    <div class="table-responsive">
                        <table class="table table--light">
                            <thead>
                                <tr>
                                    <th>@lang('Label')</th>
                                    <th>@lang('Email')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Upcoming Meetings')</th>
                                    <th>@lang('Message')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($report['hosts'] as $host)
                                    <tr>
                                        <td>{{ $host['label'] }}</td>
                                        <td>{{ $host['email'] }}</td>
                                        <td>
                                            @if($host['ok'])
                                                <span class="badge badge--success">@lang('OK')</span>
                                            @else
                                                <span class="badge badge--danger">@lang('FAILED')</span>
                                            @endif
                                        </td>
                                        <td>{{ is_null($host['upcoming_count']) ? '-' : $host['upcoming_count'] }}</td>
                                        <td>{{ $host['message'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">@lang('No hosts configured')</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

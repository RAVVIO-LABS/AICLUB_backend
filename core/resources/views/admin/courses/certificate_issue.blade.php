@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive--sm table-responsive">
                        <table class="table table--light style--two custom-data-table">
                            <thead>
                                <tr>
                                    <th>@lang('Certificate Id')</th>
                                    <th>@lang('User')</th>
                                    <th>@lang('Course Title')</th>
                                    <th>@lang('Issue Date')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($getCertificateUsers as $getCertificateUser)
                                    <tr>
                                        <td>
                                            #{{$getCertificateUser->secret}}
                                        </td>
                                        <td>
                                            {{ __($getCertificateUser->user->firstname . ' ' . $getCertificateUser->user->lastname) }} <br>
                                            <a href="{{route('admin.users.detail', $getCertificateUser->id)}}"><span>@</span>{{ $getCertificateUser->user->username }}</a>
                                        </td>
                                        <td>
                                           <a href="{{route('admin.courses.details', $getCertificateUser->course->slug)}}">{{ $getCertificateUser->course->title }}</a> 
                                        </td>
                                        <td>
                                            {{ showDateTime($getCertificateUser->created_at, 'd M Y') }}
                                        </td>
                                        <td>
                                            <button  class="btn btn-sm btn-outline--primary previewBtn"  data-action="{{ route('admin.certificate.preview', $getCertificateUser->id) }}" class="dropdown-item">
                                                <i class="la la-desktop"></i>@lang('Preview ')
                                            </button>
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
                @if ($getCertificateUsers->hasPages())
                    <div class="card-footer py-4">
                        {{ paginateLinks($getCertificateUsers) }}
                    </div>
                @endif
            </div>
        </div>
    </div>

@endsection

@push('breadcrumb-plugins')
   <x-search-form/>
@endpush

@push('script')
<script>
    $('.previewBtn').on('click', function(){
        let url = $(this).data('action');
        window.open(url, '_blank', 'noopener,noreferrer,width=900,height=600');
    })
</script>
@endpush
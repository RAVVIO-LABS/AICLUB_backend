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
                                    <th>@lang('Thumb')</th>
                                    <th>@lang('Title')</th>
                                    <th>@lang('Academy')</th>
                                    <th>@lang('Category/Sub Category')</th>
                                    <th>@lang('Price')</th>
                                    <th>@lang('Popular')</th>
                                    <th>@lang('Status')</th>
                                    @if (request()->routeIs('admin.courses.trash'))
                                        <th>@lang('Deleted At')</th>
                                        <th>@lang('Deleted IP')</th>
                                    @endif
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($courses as $course)
                                    <tr>
                                        <td>
                                            <div class="user">
                                                <div class="thumb"><img
                                                        src="{{ getImage(getFilePath('courseImage') . '/' . $course->image, getFileSize('courseImage')) }}"
                                                        alt="{{ __($course->image) }}" class="plugin_bg"></div>
                                                <span class="name">{{ __($course->name) }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            {{ __($course->title) }}
                                        </td>

                                        <td>

                                        {{ __($course->instructor->firstname .' '.$course->instructor->lastname)}} <br>
                                       <a href=""><span>@</span>{{$course->instructor->username}}</a>
                                        </td>

                                        <td>
                                           <span class="text--primary">
                                               {{ __(@$course->category->name) }}
                                            </span> <br>
                                             {{__(@$course->subcategory->name)}}
                                        </td>
                                        <td>
                                            @if ($course->discount_price > 0)
                                                <del>{{ showAmount(@$course->price) }}</del> <br>

                                                {{ showAmount(@$course->price - @$course->discount) }}
                                            @else
                                                {{ showAmount(@$course->price) }}
                                            @endif
                                        </td>
                                        <td>
                                             @if (@$course->is_populer == Status::YES)
                                                 <span class="badge badge--success">@lang('Yes')</span>
                                             @else
                                                 <span class="badge badge--danger">@lang('No')</span>
                                             @endif
                                        </td>
                                        <td>
                                            @php
                                                echo $course->statusBadge;
                                            @endphp
                                        </td>

                                        @if (request()->routeIs('admin.courses.trash'))
                                            <td>{{ showDateTime($course->deleted_at) }}</td>
                                            <td>{{ $course->deleted_ip ?? '-' }}</td>
                                        @endif

                                        <td>

                                            <div class="button--group">

                                                @if($course->deleted_at)
                                                <button class="btn btn-sm btn-outline--success confirmationBtn" data-question="@lang('Are you sure you want to restore this course')." data-action="{{ route('admin.courses.restore', $course->slug) }}"><i class="las la-undo"></i>@lang('Restore')</button>
                                                @endif

                                                <a  class="btn btn-sm btn-outline--primary" href="{{route('admin.courses.details',$course->slug )}}" class="dropdown-item">
                                                    <i class="la la-desktop"></i>@lang('Details')
                                                </a>
                                             
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
                @if ($courses->hasPages())
                <div class="card-footer py-4">
                    {{ paginateLinks($courses) }}
                </div>
                @endif
            </div>
        </div>
    </div>


    <x-confirmation-modal />
@endsection


@push('breadcrumb-plugins')
   <x-search-form/>
@endpush

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
                                    <th>@lang('Users')</th>
                                    <th>@lang('Category/Sub Category')</th>
                                    <th>@lang('Price')</th>
                                    <th>@lang('Popular')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($enrolledCourses as $enrolledCourse)
                                    @php
                                        $course = $enrolledCourse->course;
                                        $user = $enrolledCourse->user;
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="user">
                                                <div class="thumb"><img
                                                        src="{{ getImage(getFilePath('courseImage') . '/' . ($course->image ?? 'default.png'), getFileSize('courseImage')) }}"
                                                        alt="{{ __($course->image ?? 'course') }}" class="plugin_bg"></div>
                                              
                                            </div>
                                        </td>
                                        <td>
                                            {{ __($course->title ?? 'Course unavailable') }}
                                        </td>

                                        <td>

                                        {{ __((($user->firstname ?? '') .' '. ($user->lastname ?? '')) ?: 'User unavailable')}} <br>
                                       @if($user)
                                           <a href="{{route('admin.users.detail',$enrolledCourse->user_id )}}"><span>@</span>{{$user->username}}</a>
                                       @else
                                           <span class="text-muted">-</span>
                                       @endif
                                        </td>

                                        <td>
                                           <span class="text--primary">
                                               {{ __(@$course->category->name) }}
                                            </span> <br>
                                             {{__(@$course->subcategory->name)}}
                                        </td>
                                        <td>
                                            @if (($course->discount_price ?? 0) > 0)
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
                                                echo @$course->statusBadge ?: '<span class="badge badge--warning">Unavailable</span>';
                                            @endphp
                                        </td>
                                        <td>

                                            <div class="button--group">
                                                @if($course)
                                                    <a class="btn btn-sm btn-outline--primary" href="{{ route('admin.courses.details', $course->slug) }}" class="dropdown-item">
                                                        <i class="la la-desktop"></i>@lang('Details')
                                                    </a>
                                                @else
                                                    <span class="text-muted">-</span>
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
                        </table>
                    </div>
                </div>
                @if ($enrolledCourses->hasPages())
                <div class="card-footer py-4">
                    {{ paginateLinks($enrolledCourses) }}
                </div>
                @endif
            </div>
        </div>
    </div>

@endsection


@push('breadcrumb-plugins')
   <x-search-form/>
@endpush

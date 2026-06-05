@extends('admin.layouts.app')

@section('panel')
    <div class="row gy-3">
        <div class="col-xl-6">
            <div class="course-video">
                <video id="my-video" class="video-js" controls preload="auto"
                    poster={{ getImage(getFilePath('courseImage') . '/' . $course->image) }}>
                    <source src="{{ $course->intro_url ?: getImage(getFilePath('courseIntro') . '/' . $course->intro) }}" type="video/mp4" />
                </video>
            </div>
        </div>

        @include('admin.courses.right_sidebar', ['course' => $course])
    </div>

    <div class="div mt-4">
        @include('admin.courses.more_info', ['course' => $course, 'enrolledUsers' => $enrolledUsers, 'availableInstructors' => $availableInstructors, 'courseTeachers' => $courseTeachers, 'pendingBookings' => $pendingBookings, 'zoomAuditLogs' => $zoomAuditLogs, 'releaseBatches' => $releaseBatches])
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h4 class="section-title mb-0">{{ __('Course Description') }}</h2>
        </div>
        <div class="card-body">
            @php
                echo $course->description;
            @endphp
        </div>
    </div>

    <div class="modal fade" id="articleModal" tabindex="-1" aria-labelledby="articleModalLabel" aria-hidden="true">
        <div class="modal-dialog  modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="articleModalLabel"></h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">

                </div>
            </div>
        </div>
    </div>

    <div id="rejectModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        @lang('Reject Course')
                    </h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <form action="" method="POST">
                    @csrf
                    <div class="modal-body">

                        <h6 class="mb-2">@lang('Are you sure you want to reject this course.')</h6>
                        <div class="form-group">
                            <label>@lang('Reason')</label>
                            <textarea class="form-control" name="reason" rows="4" required></textarea>
                        </div>

                    </div>
                    <div class="modal-footer">

                        <button type="submit" class="btn btn--primary h-45 w-100">@lang('Submit')</button>

                    </div>
                </form>
            </div>
        </div>
    </div>
    <x-confirmation-modal />
@endsection

@push('breadcrumb-plugins')
    @if (!$course->is_populer)
        <button class="btn btn-sm btn-outline--success confirmationBtn" data-question="@lang('Are you sure you want to be listed as popular')?"
            data-action="{{ route('admin.courses.popular', $course->slug) }}"><i
                class="las la-check"></i>@lang('Mark As Popular')</button>
    @else
        <button class="btn btn-sm btn-outline--danger confirmationBtn" data-question="@lang('Are you sure you want to be unlisted as popular')?"
            data-action="{{ route('admin.courses.remove.popular', $course->slug) }}"><i
                class="las la-times"></i>@lang('Remove From Popular')</button>
    @endif

    @if ($course->status == Status::PENDING)
        <button class="btn btn-sm btn-outline--success confirmationBtn" data-question="@lang('Are you sure you want to approve this course')?"
            data-action="{{ route('admin.courses.approve', $course->id) }}"><i
                class="las la-check"></i>@lang('Approved')</button>
    @endif
    @if ($course->status != Status::REJECT)
        <button class="btn btn-sm btn-outline--danger rejectBtn"
            data-action="{{ route('admin.courses.reject', $course->id) }}"><i
                class="las la-times"></i>@lang('Reject')</button>
    @endif
    <a href="{{ route('admin.courses.batch.enrollments', $course->slug) }}" class="btn btn-sm btn-outline--primary"><i class="las la-users"></i> Batch Enrollments</a>

    <x-back route="{{route('admin.courses.index')}}"/>

@endpush


@push('style')
    <style>
        .video-js {
            width: 100% !important;
            height: 100% !important;
        }

        .course-video{
            border-radius: 6px;
            overflow: hidden;
        }
    </style>
@endpush


@push('style-lib')
    <link href="{{ asset('assets/admin/css/video-js.min.css') }}" rel="stylesheet" />
@endpush

@push('script-lib')
    <script src="{{ asset('assets/admin/js/video.min.js') }}"></script>
@endpush

@push('script')
    <script>
        $(document).ready(function() {
            var player = videojs('my-video', {
                controls: true,
                autoplay: true,
                preload: 'auto',
                fluid: true,
                playbackRates: [0.5, 1, 1.5, 2, 2.5],
                controlBar: {
                    volumePanel: {
                        inline: false
                    },
                    pictureInPictureToggle: true,
                    fullscreenToggle: true,
                    playbackRateMenuButton: true
                }
            });



            $(".videoBtn").on("click", function() {
                var videoSrc = $(this).attr("data-src");

                if (videoSrc) {
                    player.src({
                        type: "video/mp4",
                        src: videoSrc
                    });
                    player.play();
                }

                var element = $(this).closest("li");
                if (element.hasClass("active")) {
                    element.removeClass("active");
                } else {
                    element.addClass("active").siblings("li").removeClass("active");
                }
            });

            $('.articleBtn').on('click', function() {
                var title = $(this).data('title');
                var content = $(this).data('article');
                const modal = $('#articleModal');
                modal.find('#articleModalLabel').text(title);
                modal.find('.modal-body').html(content);
                modal.modal('show');

            })


        });


        $('.rejectBtn').on('click', function() {
            const modal = $('#rejectModal');
            modal.find('form').attr('action', $(this).data('action'));
            modal.modal('show');
        })
    </script>
@endpush

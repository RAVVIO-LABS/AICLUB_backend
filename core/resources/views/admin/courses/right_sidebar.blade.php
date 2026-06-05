<div class="col-xl-6">
    <div class="card">
        <div class="card-body">
            <div class="accordion custom--accordion-two playlist-wrapper" id="courseAccordion">
                @foreach ($course->sections as $key => $section)
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading-{{ $key }}">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#collapse-{{ $key }}" aria-expanded="false"
                                aria-controls="collapse-{{ $key }}">
                                {{ $section->title }}
                            </button>
                        </h2>
                        <div id="collapse-{{ $key }}" class="accordion-collapse collapse"
                            aria-labelledby="heading-{{ $key }}" data-bs-parent="#courseAccordion"
                            style="">
                            <div class="accordion-body">
                                <ul id="play-list">
                                    @foreach ($section->curriculums as $curriculum)
                                        @if ($curriculum->type == Status::LECTURE)
                                            @foreach ($curriculum->lectures as $lecture)
                                                @if ($lecture->content_type == Status::VIDEO)
                                                    <li>
                                                        <button type="button" class="list-btn videoBtn"
                                                            data-src="{{ getImage(getFilePath('video') . '/' . $lecture->file) }}">
                                                            <div class="content pl-0">
                                                                <p>
                                                                    <i class="las la-film"></i>
                                                                    {{ __($lecture->title) }}
                                                                </p>
                                                            </div>
                                                        </button>
                                                        <div
                                                            class="d-flex flex-wrap align-items-center justify-content-between">
                                                            <span
                                                                class="text-muted fs--14px d-flex align-items-center me-2">
                                                                <i
                                                                    class="las la-play-circle fs--18px"></i>{{ $lecture->video_duration }}
                                                            </span>
                                                            @if (count($lecture->resources) > 0)
                                                                <button class="btn btn-sm btn-outline--primary"
                                                                    data-bs-toggle="dropdown" type="button"
                                                                    aria-expanded="false">
                                                                    <i class="la la-ellipsis-v"></i>@lang('Resources')
                                                                </button>
                                                                <div class="dropdown-menu resource-dropdown">
                                                                    @foreach ($lecture->resources as $resource)
                                                                        <a href="{{ route('admin.courses.resourse.download', $resource->id) }}"
                                                                            class="dropdown-item">
                                                                            <i
                                                                                class="la la-download"></i>{{ $resource->file }}
                                                                        </a>
                                                                    @endforeach
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </li>
                                                @else
                                                    <li>
                                                        <button type="button" class="list-btn articleBtn"
                                                            data-title="{{ $lecture->title }}"
                                                            data-article="{{ $lecture->article }}">
                                                            <div class="content pl-0">
                                                                <p>
                                                                    <i class="lab la-wpforms"></i>
                                                                    {{ __($lecture->title) }}
                                                                </p>
                                                            </div>
                                                        </button>
                                                        <div
                                                            class="d-flex flex-wrap align-items-center justify-content-between">
                                                            <span
                                                                class="text-muted fs--14px d-flex align-items-center me-2">
                                                                <i
                                                                    class="las la-book-open fs--18px"></i>@lang('Article')
                                                            </span>
                                                            @if (count($lecture->resources) > 0)
                                                                <button class="btn btn-sm btn-outline--info"
                                                                    data-bs-toggle="dropdown" type="button"
                                                                    aria-expanded="false">
                                                                    <i class="la la-ellipsis-v"></i>@lang('Resources')
                                                                </button>
                                                                <div class="dropdown-menu">
                                                                    @foreach ($lecture->resources as $resource)
                                                                        <a href="{{ route('admin.courses.resourse.download', $resource->id) }}"
                                                                            class="dropdown-item">
                                                                            <i
                                                                                class="la la-download"></i>{{ $resource->file }}
                                                                        </a>
                                                                    @endforeach
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </li>
                                                @endif
                                            @endforeach
                                        @endif
                                        @if ($curriculum->type == Status::QUIZ)
                                            @foreach ($curriculum->quizzes as $quiz)
                                                <li>
                                                    <button data-quiz="{{ $quiz }}" type="button"
                                                        class="list-btn quizBtn"
                                                        data-src="{{ $course->intro_url ?: getImage(getFilePath('courseIntro') . '/' . $course->intro) }}">
                                                        <div class="content pl-0">
                                                            <p>
                                                                <i class="lab la-wpforms"></i>
                                                                {{ __($quiz->title) }}
                                                            </p>
                                                        </div>
                                                    </button>
                                                    <div
                                                        class="d-flex flex-wrap align-items-center justify-content-between">
                                                        <span
                                                            class="text-muted fs--14px d-flex align-items-center me-2">
                                                            <i class="las la-pencil-alt fs--18px"></i>@lang('Quiz')
                                                        </span>
                                                        @if (count($lecture->resources) > 0)
                                                            <button class="btn btn-sm btn-outline--info"
                                                                data-bs-toggle="dropdown" type="button"
                                                                aria-expanded="false">
                                                                <i class="la la-ellipsis-v"></i>@lang('Resources')
                                                            </button>
                                                            <div class="dropdown-menu">
                                                                @foreach ($lecture->resources as $resource)
                                                                    <a href="{{ route('admin.courses.resourse.download', $resource->id) }}"
                                                                        class="dropdown-item">
                                                                        <i
                                                                            class="la la-download"></i>{{ $resource->file }}
                                                                    </a>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>
                                                </li>
                                            @endforeach
                                        @endif
                                    @endforeach

                                </ul><!-- play-list end -->
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="quizModal" tabindex="-1" aria-labelledby="quizModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title fs-4 fw-bold" id="quizModalLabel"></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="slider-container">
                    <div id="qa-slider" class="mb-3"></div>
                    <div class="d-flex justify-content-between">
                        <button class="btn btn--secondary px-4" id="prev" disabled>@lang('Previous')</button>
                        <button class="btn btn--primary px-4" id="next">@lang('Next')</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('style')
    <style>
        .slider-container {
            width: 100%;
            border-radius: 10px;
        }

        .qa-item {
            display: none;
        }

        .qa-item.active {
            display: block;
        }


        .btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }

        .accordion-button {
            box-shadow: none !important;
        }

        .resource-dropdown {
            width: 320px;
            padding: 12px;
            border-radius: 6px;
            border: 0;
            box-shadow: 0px 5px 30px rgb(0 0 0 / 10%);
        }

        .resource-dropdown .dropdown-item {
            white-space: wrap !important;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            border: 1px solid rgb(0 0 0 / 8%);
            border-radius: 6px;
            margin-bottom: 12px;
            font-size: 0.825rem;
        }

        .resource-dropdown .dropdown-item i {
            font-size: 20px;
        }
    </style>
@endpush

@push('script')
    <script>
        $('.quizBtn').on('click', function() {
            const quiz = $(this).data('quiz');
            $('#next').prop('disabled', false);
            if (!quiz || !quiz.questions) {
                console.error('Quiz data is missing or incorrect');
                return;
            }
            $('#quizModalLabel').text(quiz?.title);

            const questions = quiz.questions;
            const slider = $('#qa-slider');
            slider.html('');

            questions.forEach((question, index) => {
                let optionHtml = '';

                if (question.options) {
                    question.options.forEach((option) => {
                        optionHtml += `
            <div class="form-check text-start">
                <input type="radio" class="form-check-input" name="question${index}" value="${option.id}" 
                    ${option?.answer == 1 ? 'checked' : ''}>
                <label class="form-check-label">${option.option}</label>
            </div>
        `;
                    });
                }

                slider.append(`
                <div class="qa-item ${index === 0 ? 'active' : ''}">
                    <div class="d-flex justify-content-between">
                        <div class="form-group">
                            <h3 class="fw-bold mb-0">Question :</h3>
                            <p class="fs-5 fw-bold">${question.question}</p>
                        </div>
                      
                        
                    </div>


                    <h4 class="fs-6 fw-semibold py-3">Options:</h4>
                    ${optionHtml}
                </div>
            `);
            });

            $('#quizModal').modal('show');
            updateSlider();
        });

        let currentIndex = 0;

        function updateSlider() {
            let items = $('.qa-item');
            items.removeClass('active').eq(currentIndex).addClass('active');

            $('#prev').prop('disabled', currentIndex === 0);
            $('#next').prop('disabled', currentIndex === items.length - 1);
        }

        $('#next').click(function() {
            let items = $('.qa-item');
            if (currentIndex < items.length - 1) {
                currentIndex++;
                updateSlider();
            }
        });

        $('#prev').click(function() {
            if (currentIndex > 0) {
                currentIndex--;
                updateSlider();
            }
        });

        $('#quizModal').on('hidden.bs.modal', function() {
            currentIndex = 0;
            updateSlider();
        });
    </script>
@endpush

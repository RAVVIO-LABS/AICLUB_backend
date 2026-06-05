<div class="card">
    <div class="card-body">
        <div class="accordion custom--accordion-two" id="courseDetailsAccordion">
            <div class="accordion-item">
                <h2 class="accordion-header" id="detailsHeading">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse"
                        data-bs-target="#detailsCollapse">
                        {{ __('Course Details') }}
                    </button>
                </h2>
                <div id="detailsCollapse" class="accordion-collapse collapse show">
                    <div class="accordion-body">
                        <ul class="caption-list">
                            <li><span class="caption">@lang('Academy')</span>
                                <span class="value">
                                    {{ $course->instructor->fistname . ' ' . $course->instructor->lastname }}
                                </span>
                            </li>
    
                            @if($course->duration)
                            <li><span class="caption">@lang('Discount Duration')
                                </span>
                                <span class="value">
    
                                    @if ($course->duration >= now())
                                       <span class="text--success">{{showDateTime($course->duration)}}</span>
                                    @else
                                    <span class="text--danger">{{showDateTime($course->duration)}}</span>
                                    @endif
                                </span>
                            </li>
    
                            <li><span class="caption">@lang('Course Discount')
                                </span>
                                <span class="value">
    
                                    @if ($course->discount_type == Status::PERCENT)
                                        {{ getAmount($course->discount) . '%' }}
                                    @else
                                        {{ showAmount($course->discount) }}
                                    @endif
                                </span>
    
    
                            </li>
                            @endif
                        
                            <li><span class="caption">@lang('Price')
                                </span>
                                <span class="value">
                                    @if ($course->discount_price > 0)
                                        <del>
                                            {{ showAmount($course->price) }}
                                        </del> <br>
    
                                        {{ showAmount($course->price - $course->discount_price) }}
                                    @else
                                        {{ showAmount($course->price) }}
                                    @endif
    
                                </span>
    
                            </li>
                            <li><span class="caption">@lang('Status')</span>
                                <span class="value">
                                    @php
    
                                        echo $course->statusBadge;
                                    @endphp
    
                                </span>
                            </li>
                            <li><span class="caption">@lang('Total Sections')</span> <span
                                    class="value">{{ count($course->sections) }} @lang('Sections')</span></li>
                            <li><span class="caption">@lang('Total Lecture')</span> <span
                                    class="value">{{ count($course->lectures) }} @lang('lectures')</span></li>
                            <li><span class="caption">@lang('Total Quiz')</span> <span
                                    class="value">{{ count($course->quizzes) }} @lang('Quizzes')</span></li>
                            <li><span class="caption">@lang('Level')</span>
                                <span class="value">
                                    @php
                                        echo $course->levelBadge;
                                    @endphp
                                </span>
                            </li>
                            <li><span class="caption">@lang('Total Enrolled')</span>
                                <span class="value">
                                    {{ $enrolledUsers }} @lang('Students')
                                </span>
                            </li>
                            <li><span class="caption">@lang('Complate Course')
                                </span>
                                <span class="value">
                                    {{ $course->total_complete }} @lang('Students')
                                </span>

                            </li>

                            <li>
                                <span class="caption">@lang('Max Batches')</span>
                                <span class="value">{{ (int) ($course->max_batches_per_course ?? 10) }}</span>
                            </li>

                        </ul>

                        <form method="POST" action="{{ route('admin.courses.update.batch.limit', $course->slug) }}" class="mt-3">
                            @csrf
                            <div class="row g-2 align-items-end">
                                <div class="col-md-8">
                                    <label class="form-label">@lang('Max Batches Per Course')</label>
                                    <input type="number" min="1" max="100" name="max_batches_per_course" class="form-control" value="{{ (int) ($course->max_batches_per_course ?? 10) }}" required>
                                    <small class="text-muted">@lang('Instructors cannot create more batches than this limit for this course.')</small>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn--primary w-100">@lang('Update Limit')</button>
                                </div>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('admin.courses.release.lecture', $course->slug) }}" class="mt-4">
                            @csrf
                            <div class="border rounded p-3 bg--light">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                    <h6 class="mb-0">@lang('Lecture Release Control')</h6>
                                    <small class="text-muted">@lang('Admin can release/hide any lecture for any batch.')</small>
                                </div>
                                <div class="row g-3 align-items-end">
                                    <div class="col-lg-4">
                                        <label class="form-label fw-semibold">@lang('Batch')</label>
                                        <select name="batch_id" class="form-control" required>
                                            <option value="">@lang('Select Batch')</option>
                                            @foreach(($releaseBatches ?? collect()) as $batch)
                                                <option value="{{ $batch->id }}">{{ $batch->title ?: 'Batch #'.$batch->id }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-lg-5">
                                        <label class="form-label fw-semibold">@lang('Lecture')</label>
                                        <select name="lecture_id" class="form-control" required>
                                            <option value="">@lang('Select Lecture')</option>
                                            @foreach($course->sections as $section)
                                                @foreach($section->curriculums as $curriculum)
                                                    @foreach($curriculum->lectures as $lecture)
                                                        <option value="{{ $lecture->id }}">{{ $section->title }} - {{ $lecture->title }}</option>
                                                    @endforeach
                                                @endforeach
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-lg-2">
                                        <label class="form-label fw-semibold d-block mb-2">@lang('Action')</label>
                                        <input type="hidden" name="is_released" value="0">
                                        <div class="form-check form-switch d-flex align-items-center gap-2">
                                            <input class="form-check-input" type="checkbox" role="switch" id="lectureReleaseToggle" name="is_released" value="1" checked>
                                            <label class="form-check-label fw-semibold text--success" for="lectureReleaseToggle" id="lectureReleaseToggleLabel">@lang('Release')</label>
                                        </div>
                                    </div>
                                    <div class="col-lg-1">
                                        <button type="submit" class="btn btn--primary w-100">@lang('Save')</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header" id="batchCapacityHeading">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#batchCapacityCollapse">
                        {{ __('Batch Enrollment Limit') }}
                    </button>
                </h2>
                <div id="batchCapacityCollapse" class="accordion-collapse collapse">
                    <div class="accordion-body">
                        @if($course->liveBatches->count() > 0)
                            <div class="row g-3">
                                @foreach($course->liveBatches as $batch)
                                    <div class="col-12">
                                        <div class="border rounded p-3">
                                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                                <div>
                                                    <h6 class="mb-0">{{ __($batch->title ?: 'Batch #'.$batch->id) }}</h6>
                                                    <small class="text-muted">
                                                        {{ showDateTime($batch->start_date, 'Y-m-d') }} -
                                                        {{ $batch->meeting_start_time }} to {{ $batch->meeting_end_time }}
                                                    </small>
                                                </div>
                                            </div>
                                            <form method="POST" action="{{ route('admin.courses.update.batch.capacity', [$course->slug, $batch->id]) }}">
                                                @csrf
                                                <div class="row g-2 align-items-end">
                                                    <div class="col-md-4">
                                                        <label class="form-label">@lang('Max Students')</label>
                                                        <input type="number" min="1" max="500" name="capacity" class="form-control" value="{{ (int) ($batch->capacity ?? 20) }}" required>
                                                        <small class="text-muted">@lang('Set the maximum students allowed for this batch.')</small>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <button type="submit" class="btn btn--primary w-100">@lang('Update Limit')</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-muted mb-0">@lang('No live batches available for this course.')</p>
                        @endif
                    </div>
                </div>
            </div>
    
            @if (count($course->requirements) > 0)
                <div class="accordion-item">
                    <h2 class="accordion-header" id="requirementsHeading">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#requirementsCollapse">
                            {{ __('Course Requirements') }}
                        </button>
                    </h2>
                    <div id="requirementsCollapse" class="accordion-collapse collapse">
                        <div class="accordion-body">
                            <ul class="caption-list">
                                @foreach ($course->requirements as $req)
                                    <li><i class="las la-check"></i> <span
                                            class="value">{{ $req->requirement }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif
    
            @if (count($course->contents) > 0)
                <div class="accordion-item">
                    <h2 class="accordion-header" id="contentHeading">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#contentCollapse">
                            {{ __('Who this course is for:') }}
                        </button>
                    </h2>
                    <div id="contentCollapse" class="accordion-collapse collapse">
                        <div class="accordion-body">
                            <ul class="caption-list">
                                @foreach ($course->contents as $item)
                                    <li><i class="las la-check"></i> <span class="value">{{ $item->content }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif
    
            @if (count($course->objects) > 0)
                <div class="accordion-item">
                    <h2 class="accordion-header" id="learningHeading">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#learningCollapse">
                            {{ __('What you\'ll learn') }}
                        </button>
                    </h2>
                    <div id="learningCollapse" class="accordion-collapse collapse">
                        <div class="accordion-body">
                            <ul class="caption-list">
                                @foreach ($course->objects as $item)
                                    <li><i class="las la-check"></i> <span class="value">{{ $item->object }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <div class="accordion-item">
                <h2 class="accordion-header" id="teacherAssignHeading">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#teacherAssignCollapse">
                        {{ __('Assign Teachers To Course') }}
                    </button>
                </h2>
                <div id="teacherAssignCollapse" class="accordion-collapse collapse">
                    <div class="accordion-body">
                        <form method="POST" action="{{ route('admin.courses.assign.course.teacher', $course->slug) }}" class="mb-3">
                            @csrf
                            <div class="row g-2 align-items-end">
                                <div class="col-md-8">
                                    <label class="form-label">@lang('Teacher')</label>
                                    <select name="teacher_instructor_id" class="form-control" required>
                                        <option value="">@lang('Select Teacher')</option>
                                        @foreach ($availableInstructors as $teacher)
                                            <option value="{{ $teacher->id }}">{{ $teacher->firstname }} {{ $teacher->lastname }} ({{ $teacher->username }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn--primary w-100">@lang('Assign Teacher')</button>
                                </div>
                            </div>
                        </form>

                        <ul class="caption-list">
                            @forelse($courseTeachers as $assignment)
                                <li>
                                    <span class="caption">@lang('Assigned')</span>
                                    <span class="value d-flex justify-content-between align-items-center gap-2 w-100">
                                        <span>{{ @$assignment->teacher->firstname }} {{ @$assignment->teacher->lastname }} ({{ @$assignment->teacher->username }})</span>
                                        <form method="POST" action="{{ route('admin.courses.remove.course.teacher', $course->slug) }}" class="m-0">
                                            @csrf
                                            <input type="hidden" name="teacher_instructor_id" value="{{ $assignment->teacher_instructor_id }}">
                                            <button type="submit" class="btn btn-sm btn-outline--danger">@lang('Remove')</button>
                                        </form>
                                    </span>
                                </li>
                            @empty
                                <li>
                                    <span class="value text-muted">@lang('No teachers assigned to this course yet')</span>
                                </li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>

            @if($pendingBookings->count() > 0)
                <div class="accordion-item">
                    <h2 class="accordion-header" id="bookingAssignHeading">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#bookingAssignCollapse">
                            {{ __('Assign Teacher To One-To-One') }}
                        </button>
                    </h2>
                    <div id="bookingAssignCollapse" class="accordion-collapse collapse">
                        <div class="accordion-body">
                            @foreach($pendingBookings as $booking)
                                <div class="border rounded p-3 mb-3">
                                    <p class="mb-1"><strong>@lang('Student'):</strong> {{ @$booking->user->fullname }}</p>
                                    <p class="mb-1"><strong>@lang('Type'):</strong> {{ strtoupper($booking->class_type) }}</p>
                                    <p class="mb-2"><strong>@lang('Status'):</strong> {{ strtoupper($booking->status) }}</p>

                                    <form method="POST" action="{{ route('admin.courses.assign.booking.teacher', $booking->id) }}">
                                        @csrf
                                        <div class="row g-2 align-items-end">
                                            @if($booking->class_type === 'one_to_one')
                                                <div class="col-md-3">
                                                    <label class="form-label">@lang('Date')</label>
                                                    <input type="date" name="start_date" value="{{ $booking->start_date }}" class="form-control" required>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">@lang('Time')</label>
                                                    <input type="time" name="start_time" min="09:00" max="21:00" step="1800" value="{{ $booking->start_time }}" class="form-control" required>
                                                </div>
                                            @endif
                                            <div class="col-md-3">
                                                <label class="form-label">@lang('Teacher')</label>
                                                <select name="teacher_instructor_id" class="form-control" required>
                                                    <option value="">@lang('Select Teacher')</option>
                                                    @foreach(($availableTeachersByBooking[$booking->id] ?? collect()) as $assignment)
                                                        <option value="{{ $assignment->teacher_instructor_id }}" @selected($booking->assigned_teacher_id == $assignment->teacher_instructor_id)>
                                                            {{ @$assignment->teacher->firstname }} {{ @$assignment->teacher->lastname }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">@lang('Duration (Minutes)')</label>
                                                <input type="number" name="class_duration" min="1" max="480" value="{{ $booking->class_duration ?: $course->default_class_duration }}" class="form-control">
                                            </div>
                                            <div class="col-md-2">
                                                <button type="submit" class="btn btn--success w-100">
                                                    {{ $booking->class_type === 'one_to_one' && in_array($booking->status, ['confirmed', 'assigned', 'zoom_conflict']) ? __('Reschedule') : __('Assign') }}
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            @if(isset($zoomAuditLogs) && $zoomAuditLogs->count() > 0)
                <div class="accordion-item">
                    <h2 class="accordion-header" id="zoomAuditHeading">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#zoomAuditCollapse">
                            {{ __('Zoom Audit Log') }}
                        </button>
                    </h2>
                    <div id="zoomAuditCollapse" class="accordion-collapse collapse">
                        <div class="accordion-body">
                            <div class="table-responsive">
                                <table class="table table--light">
                                    <thead>
                                        <tr>
                                            <th>@lang('Action')</th>
                                            <th>@lang('Actor')</th>
                                            <th>@lang('Session')</th>
                                            <th>@lang('Time')</th>
                                            <th>@lang('Details')</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($zoomAuditLogs as $log)
                                            <tr>
                                                <td>{{ ucfirst($log->action) }}</td>
                                                <td>{{ $log->actor_type }} {{ $log->actor_id ? '#'.$log->actor_id : '' }}</td>
                                                <td>{{ $log->live_session_id ? '#'.$log->live_session_id : '-' }}</td>
                                                <td>{{ showDateTime($log->created_at) }}</td>
                                                <td><code>{{ json_encode($log->meta) }}</code></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

@push('script')
    <script>
        (function () {
            const toggle = document.getElementById('lectureReleaseToggle');
            const label = document.getElementById('lectureReleaseToggleLabel');
            if (!toggle || !label) return;

            const syncLabel = () => {
                if (toggle.checked) {
                    label.textContent = @json(__('Release'));
                    label.classList.remove('text--danger');
                    label.classList.add('text--success');
                } else {
                    label.textContent = @json(__('Hide'));
                    label.classList.remove('text--success');
                    label.classList.add('text--danger');
                }
            };

            toggle.addEventListener('change', syncLabel);
            syncLabel();
        })();
    </script>
@endpush

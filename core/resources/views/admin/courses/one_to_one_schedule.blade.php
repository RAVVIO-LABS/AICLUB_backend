@extends('admin.layouts.app')

@section('panel')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="mb-0">@lang('One-To-One Meeting Scheduler')</h5>
                <small class="text-muted">@lang('Manage teacher assignment and lecture-wise meeting schedule from details popup')</small>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive table-responsive--sm">
                <table class="table table--light style--two custom-data-table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>@lang('Course')</th>
                            <th>@lang('Student')</th>
                            <th>@lang('Status')</th>
                            <th>@lang('Amount')</th>
                            <th class="text-end">@lang('Action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bookings as $booking)
                            @php
                                $sessionCount = collect($meetingSessionsByBooking[$booking->id] ?? collect())->count();
                                $status = strtolower((string) $booking->status);
                                $statusClass = match ($status) {
                                    'confirmed', 'assigned', 'started' => 'badge--success',
                                    'zoom_conflict', 'cancelled', 'rejected' => 'badge--danger',
                                    default => 'badge--warning',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ @$booking->course->title }}</div>
                                    <small class="text-muted">@lang('Meetings'): {{ $sessionCount }}</small>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ @$booking->user->firstname }} {{ @$booking->user->lastname }}</div>
                                </td>
                                <td>
                                    <span class="badge {{ $statusClass }}">{{ strtoupper($booking->status) }}</span>
                                </td>
                                <td>{{ showAmount($booking->purchase?->amount ?? 0) }}</td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline--primary" data-bs-toggle="modal" data-bs-target="#oneToOneScheduleModal{{ $booking->id }}">
                                        <i class="las la-calendar-alt"></i> @lang('Details')
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">{{ __($emptyMessage) }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($bookings->hasPages())
        <div class="card mt-3">
            <div class="card-body py-3">
                {{ paginateLinks($bookings) }}
            </div>
        </div>
    @endif

    @foreach($bookings as $booking)
        @php
            $defaultStartDate = $booking->start_date ? \Carbon\Carbon::parse($booking->start_date)->format('Y-m-d') : '';
            $defaultStartTime = $booking->start_time ? substr((string) $booking->start_time, 0, 5) : '';
            $todayDate = \Carbon\Carbon::now()->format('Y-m-d');
            $startDateValue = $defaultStartDate && $defaultStartDate >= $todayDate ? $defaultStartDate : $todayDate;
            $defaultStartHour24 = $defaultStartTime ? intval(substr($defaultStartTime, 0, 2)) : null;
            $defaultStartHour = $defaultStartHour24 !== null ? (($defaultStartHour24 % 12) === 0 ? 12 : $defaultStartHour24 % 12) : 9;
            $defaultStartAmPm = $defaultStartHour24 !== null && $defaultStartHour24 >= 12 ? 'PM' : 'AM';
            if ($defaultStartHour24 === null || $defaultStartHour24 < 9 || $defaultStartHour24 > 21) {
                $defaultStartHour = 9;
                $defaultStartAmPm = 'AM';
            }
            $meetingSessions = collect($meetingSessionsByBooking[$booking->id] ?? collect());
        @endphp

        <div class="modal fade" id="oneToOneScheduleModal{{ $booking->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title mb-0">@lang('One-To-One Scheduler')</h5>
                            <small class="text-muted">{{ @$booking->course->title }} | {{ @$booking->user->firstname }} {{ @$booking->user->lastname }}</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <form method="POST" action="{{ route('admin.courses.assign.booking.teacher', $booking->id) }}" style="display: flex; flex-direction: column; max-height: 85vh;">
                        @csrf
                        <div class="modal-body" style="overflow-y: auto; flex: 1 1 auto;">
                            <div class="border rounded p-3 mb-3">
                                <div class="row g-3">
                                    <div class="col-lg-12">
                                        <label class="form-label">@lang('When')</label>
                                        <div class="d-flex gap-2 align-items-center flex-wrap">
                                            <input type="date" name="start_date" class="form-control" value="{{ $startDateValue }}" min="{{ $todayDate }}" style="max-width: 190px;" required>
                                            <select name="start_hour" class="form-control" style="width:80px;">
                                                @for ($hour = 1; $hour <= 12; $hour++)
                                                    <option value="{{ $hour }}" @selected($defaultStartHour === $hour)>{{ $hour }}</option>
                                                @endfor
                                            </select>
                                            <span>:</span>
                                            <select name="start_minute" class="form-control" style="width:80px;">
                                                <option value="00" @selected(substr($defaultStartTime,3,2) === '00')>00</option>
                                                <option value="30" @selected(substr($defaultStartTime,3,2) === '30')>30</option>
                                            </select>
                                            <select name="start_ampm" class="form-control" style="width:90px;">
                                                <option value="AM" @selected($defaultStartAmPm === 'AM')>AM</option>
                                                <option value="PM" @selected($defaultStartAmPm === 'PM')>PM</option>
                                            </select>
                                        </div>
                                        <small class="text-muted d-block mt-2">@lang('End time:') <span class="auto-end-time">-</span></small>
                                    </div>
                                </div>
                            </div>

                            <div class="border rounded p-3 mb-3">
                                <div class="row g-3">
                                    <div class="col-lg-12">
                                        <label class="form-label">@lang('Duration')</label>
                                        <div class="d-flex gap-2 align-items-center flex-wrap">
                                            <input type="number" name="duration_hr" min="0" max="12" value="{{ floor(($booking->class_duration ?: @$booking->course->default_class_duration ?: 60)/60) }}" class="form-control" style="width:80px;" required>
                                            <div class="align-self-center">hr</div>
                                            <input type="number" name="duration_min" min="0" max="59" step="10" value="{{ ($booking->class_duration ?: @$booking->course->default_class_duration ?: 60) % 60 }}" class="form-control" style="width:100px;" required>
                                            <div class="align-self-center">min</div>
                                        </div>
                                        <input type="hidden" name="class_duration" value="{{ $booking->class_duration ?: @$booking->course->default_class_duration ?: 60 }}">
                                    </div>
                                </div>
                            </div>

                            <div class="border rounded p-3 mb-3">
                                <div class="row g-3">
                                    <div class="col-lg-12">
                                        <label class="form-label">@lang('Recurring meeting')</label>
                                        <div class="d-flex gap-2 align-items-center flex-wrap mb-3">
                                            <label class="mb-0">@lang('Recurrence')</label>
                                            <select name="recurrence_type" class="form-control recurrence-type" style="max-width: 180px;">
                                                <option value="none" selected>@lang('None')</option>
                                                <option value="daily">@lang('Daily')</option>
                                                <option value="weekly">@lang('Weekly')</option>
                                                <option value="monthly">@lang('Monthly')</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-3 mt-2 recurrence-settings">
                                    <div class="col-lg-4">
                                        <label class="form-label">@lang('Repeat every')</label>
                                        <div class="d-flex align-items-center gap-2">
                                            <input type="number" name="repeat_every" min="1" max="90" value="1" class="form-control recurrence-repeat" style="width:100px;">
                                            <small class="text-muted recurrence-unit">@lang('week(s)')</small>
                                        </div>
                                    </div>
                                    <div class="col-lg-8">
                                        <label class="form-label d-block">@lang('Occurs on')</label>
                                        <div class="d-flex flex-wrap gap-3 recurrence-weekdays">
                                            @foreach ([1 => 'Sun', 2 => 'Mon', 3 => 'Tue', 4 => 'Wed', 5 => 'Thu', 6 => 'Fri', 7 => 'Sat'] as $dayId => $dayLabel)
                                                <label class="d-flex align-items-center gap-1">
                                                    <input type="checkbox" name="weekly_days[]" value="{{ $dayId }}" class="weekly-day-checkbox">
                                                    <span>{{ $dayLabel }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-3 mt-2 recurrence-end-row">
                                    <div class="col-lg-4">
                                        <label class="form-label d-block">@lang('End date')</label>
                                        <div class="d-flex flex-wrap gap-3 align-items-center recurrence-end-type">
                                            <label class="d-flex align-items-center gap-1">
                                                <input type="radio" name="end_type" value="no_end" checked>
                                                <span>@lang('No end time')</span>
                                            </label>
                                            <label class="d-flex align-items-center gap-1">
                                                <input type="radio" name="end_type" value="by">
                                                <span>@lang('By')</span>
                                            </label>
                                            <label class="d-flex align-items-center gap-1">
                                                <input type="radio" name="end_type" value="after">
                                                <span>@lang('After')</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 end-by-block">
                                        <label class="form-label">@lang('By')</label>
                                        <div class="d-flex gap-2 flex-wrap align-items-center">
                                            <input type="date" name="end_date" class="form-control auto-end-date" style="max-width:220px;">
                                            <input type="text" class="form-control auto-end-time-by" style="max-width:160px;" value="" readonly>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 end-after-block">
                                        <label class="form-label">@lang('After')</label>
                                        <input type="number" name="occurrences" min="1" max="50" value="1" class="form-control">
                                    </div>
                                </div>

                                <small class="text-muted d-block mt-2">@lang('Recurring meeting settings. Meetings will be generated automatically based on these options.')</small>
                            </div>

                            <div class="border rounded p-3 mb-3">
                                <h6 class="mb-2">@lang('Teacher Availability')</h6>
                                <div class="text-muted small mb-2">@lang('Teacher selection is filtered by the selected time and recurrence criteria.')</div>
                                <div class="mb-3">
                                    <button type="button" class="btn btn--primary btn-sm check-availability-btn">@lang('Check Teacher Availability')</button>
                                </div>
                                <div class="row g-3">
                                    <div class="col-lg-12">
                                        <label class="form-label">@lang('Select teacher')</label>
                                        <select name="teacher_instructor_id" class="form-control availability-teacher-select" data-default-teacher-id="{{ $booking->assigned_teacher_id ?? '' }}" required>
                                            <option value="">@lang('Select teacher after checking availability')</option>
                                        </select>
                                    </div>
                                </div>
                                <small class="text-muted d-block mt-2 availability-status">@lang('Select the time and recurrence settings to load available teachers.')</small>
                            </div>

                            <div class="border rounded p-3 mb-3">
                                <h6 class="mb-2">@lang('Scheduled Meetings')</h6>
                                @if($meetingSessions->isNotEmpty())
                                    <div class="table-responsive">
                                        <table class="table table-sm table-striped align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>@lang('Time')</th>
                                                    <th>@lang('Teacher')</th>
                                                    <th>@lang('Meeting')</th>
                                                    <th class="text-end">@lang('Action')</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($meetingSessions as $session)
                                                    <tr>
                                                        <td>{{ $session->scheduled_at ? \Carbon\Carbon::parse($session->scheduled_at)->format('d-m-Y h:i A') : '-' }}</td>
                                                        <td>{{ $session->assignedTeacher?->firstname }} {{ $session->assignedTeacher?->lastname }}</td>
                                                        <td>
                                                            @if($session->zoom_join_url)
                                                                <a href="{{ $session->zoom_join_url }}" target="_blank" rel="noopener noreferrer">@lang('Join')</a>
                                                            @else
                                                                <span class="text-muted">@lang('Pending')</span>
                                                            @endif
                                                        </td>
                                                        <td class="text-end">
                                                            @if(is_numeric($session->id))
                                                                <button type="button" class="btn btn--primary btn--sm" data-bs-toggle="modal" data-bs-target="#sessionEditModal{{ $booking->id }}-{{ $session->id }}">@lang('Edit')</button>
                                                            @else
                                                                <span class="text-muted">-</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="text-muted">@lang('No scheduled meetings yet')</div>
                                @endif
                            </div>
                        </div>

                        <div class="modal-footer" style="position: sticky; bottom: 0; background: #fff; z-index: 2;">
                            <button type="button" class="btn btn-outline--dark" data-bs-dismiss="modal">@lang('Close')</button>
                            <button type="submit" class="btn btn--success">
                                {{ in_array($booking->status, ['confirmed', 'assigned', 'zoom_conflict']) ? __('Reschedule') : __('Assign') }}
                            </button>
                        </div>
                    </form>

        @foreach($meetingSessions as $session)
            @if(is_numeric($session->id))
                @php
                    $sessionDate = $session->scheduled_at ? \Carbon\Carbon::parse($session->scheduled_at) : null;
                    $sessionStartDate = $sessionDate ? $sessionDate->format('Y-m-d') : $todayDate;
                    $sessionStartHour24 = $sessionDate ? intval($sessionDate->format('H')) : 9;
                    $sessionStartHour = (($sessionStartHour24 % 12) === 0 ? 12 : $sessionStartHour24 % 12);
                    $sessionStartAmPm = $sessionDate && $sessionStartHour24 >= 12 ? 'PM' : 'AM';
                    $sessionStartMinute = $sessionDate ? $sessionDate->format('i') : '00';
                    $sessionDuration = $session->duration_minutes ?: ($booking->class_duration ?: @$booking->course->default_class_duration ?: 60);
                    $sessionTeacherId = $session->assigned_teacher_id ?: ($booking->assigned_teacher_id ?? '');
                    $sessionTeachers = $allTeachers ?? collect();
                @endphp
                <div class="modal fade" id="sessionEditModal{{ $booking->id }}-{{ $session->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title mb-0">@lang('Edit One-To-One Meeting')</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST" action="{{ route('admin.courses.one.to.one.session.update', $session->id) }}">
                                @csrf
                                <div class="modal-body">
                                    <input type="hidden" name="start_time" class="session-start-time-input" value="{{ $sessionDate ? $sessionDate->format('H:i') : '09:00' }}">
                                    <div class="row g-3">
                                        <div class="col-md-12">
                                            <label class="form-label">@lang('Meeting Title')</label>
                                            <input type="text" name="meeting_name" class="form-control" value="{{ $session->lecture?->title ?: $session->meeting_name ?: 'Live Session' }}" readonly>
                                            <small class="text-muted">@lang('This follows the existing lecture/session title.')</small>
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label">@lang('When')</label>
                                            <div class="d-flex gap-2 align-items-center flex-wrap">
                                                <input type="date" name="start_date" class="form-control session-start-date" value="{{ $sessionStartDate }}" min="{{ $todayDate }}" style="max-width: 190px;" required>
                                                <select name="start_hour" class="form-control session-start-hour" style="width:80px;">
                                                    @for ($hour = 1; $hour <= 12; $hour++)
                                                        <option value="{{ $hour }}" @selected($sessionStartHour === $hour)>{{ $hour }}</option>
                                                    @endfor
                                                </select>
                                                <span>:</span>
                                                <select name="start_minute" class="form-control session-start-minute" style="width:80px;">
                                                    <option value="00" @selected($sessionStartMinute === '00')>00</option>
                                                    <option value="30" @selected($sessionStartMinute === '30')>30</option>
                                                </select>
                                                <select name="start_ampm" class="form-control session-start-ampm" style="width:90px;">
                                                    <option value="AM" @selected($sessionStartAmPm === 'AM')>AM</option>
                                                    <option value="PM" @selected($sessionStartAmPm === 'PM')>PM</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label">@lang('Duration')</label>
                                            <div class="d-flex gap-2 align-items-center flex-wrap">
                                                <input type="number" name="duration_hr" min="0" max="12" value="{{ floor($sessionDuration / 60) }}" class="form-control" style="width:80px;" required>
                                                <div class="align-self-center">hr</div>
                                                <input type="number" name="duration_min" min="0" max="59" step="10" value="{{ $sessionDuration % 60 }}" class="form-control" style="width:100px;" required>
                                                <div class="align-self-center">min</div>
                                                <input type="hidden" name="class_duration" value="{{ $sessionDuration }}">
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label">@lang('Teacher')</label>
                                            <select name="teacher_instructor_id" class="form-control session-teacher-select" data-current-teacher-id="{{ $sessionTeacherId }}" required>
                                                <option value="">@lang('Select teacher')</option>
                                                @foreach($sessionTeachers as $teacherItem)
                                                    <option value="{{ $teacherItem->id }}" @selected((string) $sessionTeacherId === (string) $teacherItem->id)>{{ $teacherItem->firstname }} {{ $teacherItem->lastname }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline--dark" data-bs-dismiss="modal">@lang('Close')</button>
                                    <button type="submit" class="btn btn--success">@lang('Save Changes')</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
                    <script>
                        (function(){
                            document.querySelectorAll('[id^="sessionEditModal{{ $booking->id }}-"]').forEach(function(modalEl) {
                                if (modalEl.parentElement !== document.body) {
                                    document.body.appendChild(modalEl);
                                }
                                modalEl.style.zIndex = '1085';
                                const dialog = modalEl.querySelector('.modal-dialog');
                                if (dialog) {
                                    dialog.style.zIndex = '1086';
                                }
                                const startDate = modalEl.querySelector('.session-start-date');
                                const startHour = modalEl.querySelector('.session-start-hour');
                                const startMinute = modalEl.querySelector('.session-start-minute');
                                const startAmpm = modalEl.querySelector('.session-start-ampm');
                                const startTimeInput = modalEl.querySelector('.session-start-time-input');
                                const teacherSelect = modalEl.querySelector('.session-teacher-select');
                                const form = modalEl.querySelector('form');

                                const syncStartTime = function() {
                                    if (!startHour || !startMinute || !startAmpm || !startTimeInput) return;
                                    let hour = parseInt(startHour.value || '0', 10);
                                    const minute = (startMinute.value || '00').padStart(2, '0');
                                    const ampm = (startAmpm.value || 'AM').toUpperCase();
                                    if (ampm === 'PM' && hour < 12) hour += 12;
                                    if (ampm === 'AM' && hour === 12) hour = 0;
                                    startTimeInput.value = String(hour).padStart(2, '0') + ':' + minute;
                                };

                                [startHour, startMinute, startAmpm].forEach(function(el) {
                                    if (el) {
                                        el.addEventListener('change', syncStartTime);
                                    }
                                });

                                if (form) {
                                    form.addEventListener('submit', syncStartTime);
                                }

                                syncStartTime();

                                if (teacherSelect) {
                                    const currentTeacherId = teacherSelect.dataset.currentTeacherId || '';
                                    if (currentTeacherId) {
                                        const currentOptionExists = Array.from(teacherSelect.options).some(function(option) {
                                            return String(option.value) === String(currentTeacherId);
                                        });
                                        if (!currentOptionExists) {
                                            const currentOption = document.createElement('option');
                                            currentOption.value = currentTeacherId;
                                            currentOption.textContent = teacherSelect.options[0]?.textContent || 'Selected teacher';
                                            currentOption.selected = true;
                                            teacherSelect.appendChild(currentOption);
                                        }
                                        teacherSelect.value = currentTeacherId;
                                    }
                                }
                            });
                        })();

                        (function(){
                            const form = document.querySelector('#oneToOneScheduleModal{{ $booking->id }} form');
                            if (!form) return;
                            const availabilityUrl = @json(route('admin.courses.one.to.one.available.teachers', $booking->id));
                            const csrfToken = @json(csrf_token());

                            const recurrenceType = form.querySelector('[name="recurrence_type"]');
                            const repeatUnit = form.querySelector('.recurrence-unit');
                            const weekdayWrap = form.querySelector('.recurrence-weekdays');
                            const repeatInput = form.querySelector('.recurrence-repeat');
                            const endDateInput = form.querySelector('.auto-end-date');
                            const endTypeInputs = form.querySelectorAll('[name="end_type"]');
                            const endTimeLabel = form.querySelector('.auto-end-time');
                            const recurrenceSettings = form.querySelector('.recurrence-settings');
                            const recurrenceEndRow = form.querySelector('.recurrence-end-row');
                            const endByBlock = form.querySelector('.end-by-block');
                            const endAfterBlock = form.querySelector('.end-after-block');
                            const endByTimeInput = form.querySelector('.auto-end-time-by');
                            const teacherSelect = form.querySelector('.availability-teacher-select');
                            const currentTeacherId = form.querySelector('.session-teacher-select')?.dataset.currentTeacherId || '';
                            const currentTeacherLabel = form.querySelector('.session-teacher-select option:checked')?.textContent || 'Current teacher';
                            const availabilityStatus = form.querySelector('.availability-status');
                            const checkAvailabilityBtn = form.querySelector('.check-availability-btn');

                            const dayLabel = @json(__('day(s)'));
                            const weekLabel = @json(__('week(s)'));
                            const monthLabel = @json(__('month(s)'));
                            const selectTeacherText = @json(__('Select teacher'));
                            const selectTeacherAfterText = @json(__('Select teacher after checking availability'));
                            const loadingTeachersText = @json(__('Loading available teachers...'));
                            const noTeachersText = @json(__('No available teachers found for the selected criteria.'));
                            const loadTeachersFailedText = @json(__('Unable to load available teachers.'));
                            const selectTimeText = @json(__('Select the time and recurrence settings to load available teachers.'));
                            const checkingText = @json(__('Checking teacher availability...'));

                            const getStartDate = function() {
                                const dateEl = form.querySelector('[name="start_date"]');
                                return dateEl ? dateEl.value : '';
                            };

                            const getTodayYmd = function() {
                                const today = new Date();
                                const local = new Date(today.getTime() - (today.getTimezoneOffset() * 60000));
                                return local.toISOString().slice(0, 10);
                            };

                            const getStartTime = function() {
                                const hourEl = form.querySelector('[name="start_hour"]');
                                const minEl = form.querySelector('[name="start_minute"]');
                                const ampmEl = form.querySelector('[name="start_ampm"]');
                                if (!hourEl || !minEl || !ampmEl) {
                                    return '';
                                }

                                let hour = parseInt(hourEl.value || '0', 10);
                                const minute = (minEl.value || '00').padStart(2, '0');
                                const ampm = (ampmEl.value || 'AM').toUpperCase();

                                if (ampm === 'PM' && hour < 12) hour += 12;
                                if (ampm === 'AM' && hour === 12) hour = 0;

                                return String(hour).padStart(2, '0') + ':' + minute;
                            };

                            const syncHourOptions = function() {
                                const hourEl = form.querySelector('[name="start_hour"]');
                                const ampmEl = form.querySelector('[name="start_ampm"]');
                                if (!hourEl || !ampmEl) return;

                                const ampm = (ampmEl.value || 'AM').toUpperCase();
                                Array.from(hourEl.options).forEach(function(option) {
                                    const hour = parseInt(option.value || '0', 10);
                                    const disabled = ampm === 'AM' ? hour < 9 : hour > 9;
                                    option.disabled = disabled;
                                });

                                const currentHour = parseInt(hourEl.value || '9', 10);
                                const invalidCurrent = ampm === 'AM' ? currentHour < 9 : currentHour > 9;
                                if (invalidCurrent) {
                                    hourEl.value = '9';
                                }
                            };

                            const getDurationMinutes = function() {
                                const durHr = form.querySelector('[name="duration_hr"]');
                                const durMin = form.querySelector('[name="duration_min"]');
                                const hrs = parseInt(durHr?.value || '0', 10);
                                const mins = parseInt(durMin?.value || '0', 10);
                                return Math.max(1, (hrs * 60) + mins);
                            };

                            const getStartDateTime = function() {
                                const startDate = getStartDate();
                                const startTime = getStartTime();
                                if (!startDate || !startTime) return null;
                                const dt = new Date(startDate + 'T' + startTime + ':00');
                                return Number.isNaN(dt.getTime()) ? null : dt;
                            };

                            const pad2 = function(v) {
                                return String(v).padStart(2, '0');
                            };

                            const formatYmd = function(dateObj) {
                                return dateObj.getFullYear() + '-' + pad2(dateObj.getMonth() + 1) + '-' + pad2(dateObj.getDate());
                            };

                            const dayCodeFromDate = function(dateObj) {
                                return dateObj.getDay() + 1; // Sun=1 ... Sat=7
                            };

                            const ensureWeeklyDayFromStartDate = function() {
                                if ((recurrenceType?.value || 'none') !== 'weekly') {
                                    return;
                                }

                                const startDt = getStartDateTime();
                                if (!startDt) return;
                                const dayCode = String(dayCodeFromDate(startDt));
                                const checked = form.querySelectorAll('.weekly-day-checkbox:checked');
                                if (checked.length > 0) return;

                                const target = form.querySelector('.weekly-day-checkbox[value="' + dayCode + '"]');
                                if (target) {
                                    target.checked = true;
                                }
                            };

                            const updateEndTimeLabel = function() {
                                if (!endTimeLabel) return;
                                const startDt = getStartDateTime();
                                if (!startDt) {
                                    endTimeLabel.textContent = '-';
                                    if (endByTimeInput) endByTimeInput.value = '';
                                    return;
                                }
                                const end = new Date(startDt.getTime() + (getDurationMinutes() * 60000));
                                const hh24 = end.getHours();
                                const mm = pad2(end.getMinutes());
                                const ampm = hh24 >= 12 ? 'PM' : 'AM';
                                const hh12 = (hh24 % 12) || 12;
                                const timeLabel = hh12 + ':' + mm + ' ' + ampm;
                                endTimeLabel.textContent = timeLabel;
                                if (endByTimeInput) endByTimeInput.value = timeLabel;
                            };

                            const syncEndTypeUi = function() {
                                const endType = form.querySelector('[name="end_type"]:checked')?.value || 'no_end';

                                if (endByBlock) {
                                    endByBlock.style.display = endType === 'by' ? '' : 'none';
                                }

                                if (endAfterBlock) {
                                    endAfterBlock.style.display = endType === 'after' ? '' : 'none';
                                }

                                if (endDateInput) {
                                    endDateInput.required = endType === 'by';
                                }

                                const occurrencesInput = form.querySelector('[name="occurrences"]');
                                if (occurrencesInput) {
                                    occurrencesInput.required = endType === 'after';
                                }
                            };

                            const getCheckedWeeklyDayCodes = function() {
                                return Array.from(form.querySelectorAll('.weekly-day-checkbox:checked'))
                                    .map(function(el) { return parseInt(el.value || '0', 10); })
                                    .filter(function(v) { return v >= 1 && v <= 7; })
                                    .sort(function(a, b) { return a - b; });
                            };

                            const autoCalculateEndDate = function() {
                                if (!endDateInput) return;
                                const endType = form.querySelector('[name="end_type"]:checked')?.value || 'no_end';
                                const startDt = getStartDateTime();
                                if (!startDt) {
                                    endDateInput.readOnly = false;
                                    return;
                                }

                                if (endType === 'by') {
                                    endDateInput.readOnly = false;
                                    return;
                                }

                                if (endType === 'no_end') {
                                    endDateInput.value = '';
                                    endDateInput.readOnly = true;
                                    return;
                                }

                                // end_type === after => auto-calculate from occurrences
                                const occurrences = Math.max(1, parseInt(form.querySelector('[name="occurrences"]')?.value || '1', 10));
                                const type = (recurrenceType?.value || 'none').toLowerCase();
                                const interval = Math.max(1, parseInt(repeatInput?.value || '1', 10));

                                let target = new Date(startDt.getTime());
                                if (occurrences <= 1 || type === 'none') {
                                    endDateInput.value = formatYmd(target);
                                    endDateInput.readOnly = true;
                                    return;
                                }

                                if (type === 'daily') {
                                    target.setDate(target.getDate() + ((occurrences - 1) * interval));
                                } else if (type === 'monthly') {
                                    target.setMonth(target.getMonth() + ((occurrences - 1) * interval));
                                } else {
                                    // weekly: count matching selected weekdays across interval weeks
                                    let remaining = occurrences - 1;
                                    const weeklyDays = getCheckedWeeklyDayCodes();
                                    if (weeklyDays.length === 0) {
                                        weeklyDays.push(dayCodeFromDate(startDt));
                                    }

                                    while (remaining > 0) {
                                        target.setDate(target.getDate() + 1);
                                        const dayCode = dayCodeFromDate(target);

                                        const weekDiff = Math.floor(
                                            (new Date(target.getFullYear(), target.getMonth(), target.getDate()) - new Date(startDt.getFullYear(), startDt.getMonth(), startDt.getDate() - startDt.getDay())) / (7 * 24 * 60 * 60 * 1000)
                                        );
                                        const inIntervalWeek = weekDiff % interval === 0;
                                        if (inIntervalWeek && weeklyDays.includes(dayCode)) {
                                            remaining -= 1;
                                        }
                                    }
                                }

                                endDateInput.value = formatYmd(target);
                                endDateInput.readOnly = true;
                            };

                            const resetAvailabilityUi = function() {
                                if (teacherSelect) {
                                    teacherSelect.disabled = true;
                                    teacherSelect.innerHTML = '<option value="">' + selectTeacherAfterText + '</option>';
                                    if (currentTeacherId) {
                                        const selectedTeacherOption = document.createElement('option');
                                        selectedTeacherOption.value = currentTeacherId;
                                        selectedTeacherOption.textContent = currentTeacherLabel;
                                        selectedTeacherOption.selected = true;
                                        teacherSelect.appendChild(selectedTeacherOption);
                                    }
                                }
                                if (availabilityStatus) {
                                    availabilityStatus.textContent = selectTimeText;
                                }
                            };

                            const buildRecurringPayload = function() {
                                const payload = new FormData();
                                payload.append('_token', csrfToken);
                                payload.append('start_date', getStartDate());
                                payload.append('start_time', getStartTime());
                                payload.append('class_duration', String(getDurationMinutes()));
                                payload.append('recurrence_type', form.querySelector('[name="recurrence_type"]')?.value || 'none');
                                payload.append('repeat_every', form.querySelector('[name="repeat_every"]')?.value || '1');
                                payload.append('end_type', form.querySelector('[name="end_type"]:checked')?.value || 'no_end');
                                payload.append('end_date', form.querySelector('[name="end_date"]')?.value || '');
                                payload.append('occurrences', form.querySelector('[name="occurrences"]')?.value || '1');

                                form.querySelectorAll('[name="weekly_days[]"]:checked').forEach(function(el){
                                    payload.append('weekly_days[]', el.value);
                                });

                                return payload;
                            };

                            const setTeacherOptions = function(teachers) {
                                if (!teacherSelect) return;
                                const defaultTeacherId = teacherSelect.getAttribute('data-default-teacher-id') || '';
                                teacherSelect.innerHTML = '<option value="">' + selectTeacherText + '</option>';

                                teachers.forEach(function(teacher) {
                                    const option = document.createElement('option');
                                    option.value = teacher.id;
                                    option.textContent = [teacher.firstname || '', teacher.lastname || ''].join(' ').trim() || teacher.username || ('Teacher #' + teacher.id);
                                    if (String(defaultTeacherId) === String(teacher.id)) {
                                        option.selected = true;
                                    }
                                    teacherSelect.appendChild(option);
                                });

                                if (availabilityStatus) {
                                    availabilityStatus.textContent = teachers.length ? @json(__('Available teachers loaded.')) : noTeachersText;
                                }
                            };

                            const loadAvailableTeachers = function() {
                                if (!teacherSelect) return;

                                const startDate = getStartDate();
                                const startTime = getStartTime();
                                const today = getTodayYmd();
                                if (!startDate || !startTime) {
                                    teacherSelect.innerHTML = '<option value="">' + selectTeacherAfterText + '</option>';
                                    if (availabilityStatus) {
                                        availabilityStatus.textContent = selectTimeText;
                                    }
                                    return;
                                }

                                if (startDate < today) {
                                    teacherSelect.innerHTML = '<option value="">' + selectTeacherAfterText + '</option>';
                                    if (availabilityStatus) {
                                        availabilityStatus.textContent = @json(__('Meeting date cannot be in the past.'));
                                    }
                                    return;
                                }

                                teacherSelect.disabled = true;
                                teacherSelect.innerHTML = '<option value="">' + loadingTeachersText + '</option>';
                                if (availabilityStatus) {
                                    availabilityStatus.textContent = checkingText;
                                }

                                fetch(availabilityUrl, {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': csrfToken,
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'Accept': 'application/json',
                                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                                    },
                                    body: new URLSearchParams(buildRecurringPayload()).toString(),
                                })
                                    .then(function(response) {
                                        return response.json().then(function(json) {
                                            return { ok: response.ok, json: json };
                                        });
                                    })
                                    .then(function(result) {
                                        teacherSelect.disabled = false;
                                        if (!result.ok) {
                                            teacherSelect.innerHTML = '<option value="">' + selectTeacherAfterText + '</option>';
                                            if (availabilityStatus) {
                                                availabilityStatus.textContent = result.json?.message || loadTeachersFailedText;
                                            }
                                            return;
                                        }

                                        setTeacherOptions(result.json.teachers || []);
                                    })
                                    .catch(function() {
                                        teacherSelect.disabled = false;
                                        teacherSelect.innerHTML = '<option value="">' + selectTeacherAfterText + '</option>';
                                        if (availabilityStatus) {
                                            availabilityStatus.textContent = loadTeachersFailedText;
                                        }
                                    });
                            };

                            const syncRecurrenceUi = function(){
                                const type = (recurrenceType?.value || 'none').toLowerCase();

                                if (recurrenceSettings) {
                                    recurrenceSettings.style.display = type === 'none' ? 'none' : '';
                                }

                                if (recurrenceEndRow) {
                                    recurrenceEndRow.style.display = type === 'none' ? 'none' : '';
                                }

                                if (repeatUnit) {
                                    repeatUnit.textContent = type === 'daily' ? dayLabel : (type === 'monthly' ? monthLabel : weekLabel);
                                }

                                if (weekdayWrap) {
                                    weekdayWrap.style.display = type === 'weekly' ? '' : 'none';
                                }

                                // Keep weekly days meaningful when recurrence changes
                                if (type !== 'weekly') {
                                    form.querySelectorAll('.weekly-day-checkbox').forEach(function(el){ el.checked = false; });
                                } else {
                                    ensureWeeklyDayFromStartDate();
                                }

                                if (repeatInput) {
                                    repeatInput.value = type === 'none' ? 1 : repeatInput.value || 1;
                                }

                                syncEndTypeUi();
                                autoCalculateEndDate();
                                updateEndTimeLabel();
                            };

                            if (recurrenceType) {
                                recurrenceType.addEventListener('change', syncRecurrenceUi);
                                syncRecurrenceUi();
                            }

                            syncHourOptions();

                            endTypeInputs.forEach(function(input) {
                                input.addEventListener('change', function() {
                                    syncEndTypeUi();
                                    autoCalculateEndDate();
                                    resetAvailabilityUi();
                                });
                            });

                            ['change', 'input'].forEach(function(eventName) {
                                form.querySelectorAll('[name="start_date"], [name="start_hour"], [name="start_minute"], [name="start_ampm"], [name="duration_hr"], [name="duration_min"], [name="recurrence_type"], [name="repeat_every"], [name="end_type"], [name="end_date"], [name="occurrences"], [name="weekly_days[]"]').forEach(function(el) {
                                    el.addEventListener(eventName, function() {
                                        ensureWeeklyDayFromStartDate();
                                        autoCalculateEndDate();
                                        updateEndTimeLabel();
                                        syncHourOptions();
                                        resetAvailabilityUi();
                                    });
                                });
                            });

                            if (checkAvailabilityBtn) {
                                checkAvailabilityBtn.addEventListener('click', function() {
                                    syncHourOptions();
                                    loadAvailableTeachers();
                                });
                            }

                            resetAvailabilityUi();
                            ensureWeeklyDayFromStartDate();
                            syncEndTypeUi();
                            autoCalculateEndDate();
                            updateEndTimeLabel();
                            syncHourOptions();

                            form.addEventListener('submit', function(e){
                                // compute 24h start_time
                                const hourEl = form.querySelector('[name="start_hour"]');
                                const minEl = form.querySelector('[name="start_minute"]');
                                const ampmEl = form.querySelector('[name="start_ampm"]');
                                const dateEl = form.querySelector('[name="start_date"]');
                                if (hourEl && minEl && ampmEl && dateEl) {
                                    let hour = parseInt(hourEl.value || '0', 10);
                                    const minute = (minEl.value || '00').padStart(2,'0');
                                    const ampm = (ampmEl.value || 'AM').toUpperCase();
                                    if (ampm === 'PM' && hour < 12) hour += 12;
                                    if (ampm === 'AM' && hour === 12) hour = 0;
                                    const hh = String(hour).padStart(2,'0');
                                    const time = hh + ':' + minute;
                                    // place into hidden start_time input (create if missing)
                                    let st = form.querySelector('[name="start_time"]');
                                    if (!st) {
                                        st = document.createElement('input');
                                        st.type = 'hidden'; st.name = 'start_time';
                                        form.appendChild(st);
                                    }
                                    st.value = time;
                                }

                                // compute class_duration from hr/min inputs
                                const durHr = form.querySelector('[name="duration_hr"]');
                                const durMin = form.querySelector('[name="duration_min"]');
                                if (durHr && durMin) {
                                    const hrs = parseInt(durHr.value || '0',10);
                                    const mins = parseInt(durMin.value || '0',10);
                                    const total = Math.max(1, (hrs * 60) + mins);
                                    let cd = form.querySelector('[name="class_duration"]');
                                    if (!cd) {
                                        cd = document.createElement('input'); cd.type='hidden'; cd.name='class_duration'; form.appendChild(cd);
                                    }
                                    cd.value = String(total);
                                }
                            });
                        })();
                    </script>
                </div>
            </div>
        </div>
    @endforeach
@endsection

@push('breadcrumb-plugins')
    <x-search-form />
@endpush

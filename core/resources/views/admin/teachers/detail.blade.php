@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <div class="col-12">

            <div class="row gy-4 mt-3">
                <div class="col-xxl-6 col-sm-6">
                    <x-widget style="6" icon="las la-book-reader" title="Allocated Courses"
                        value="{{ $allocatedCourses->count() }}" bg="primary" />
                </div>
                <div class="col-xxl-6 col-sm-6">
                    <x-widget style="6" icon="las la-video" title="Scheduled Lectures"
                        value="{{ $scheduledLecturesFlat->count() }}" bg="info" />
                </div>
            </div>

            <div class="row gy-4 mt-3">
                <div class="col-xxl-3 col-sm-6">
                    <x-widget style="6" link="{{ route('admin.teachers.students', $teacher->id) }}" icon="las la-user-graduate" title="Assigned Students"
                        value="{{ $performanceStats['assigned_students'] ?? 0 }}" bg="warning" />
                </div>
                <div class="col-xxl-3 col-sm-6">
                    <x-widget style="6" icon="las la-chalkboard-teacher" title="Trial Classes"
                        value="{{ $performanceStats['trial_classes'] ?? 0 }}" bg="success" />
                </div>
                <div class="col-xxl-3 col-sm-6">
                    <x-widget style="6" icon="las la-check-circle" title="1:1 Completed"
                        value="{{ $performanceStats['one_to_one_completed'] ?? 0 }}" bg="primary" />
                </div>
                <div class="col-xxl-3 col-sm-6">
                    <x-widget style="6" icon="las la-check-double" title="Group Completed"
                        value="{{ $performanceStats['group_completed'] ?? 0 }}" bg="info" />
                </div>
                <div class="col-xxl-3 col-sm-6">
                    <x-widget style="6" icon="las la-times-circle" title="1:1 Cancelled"
                        value="{{ $performanceStats['one_to_one_cancelled'] ?? 0 }}" bg="danger" />
                </div>
                <div class="col-xxl-3 col-sm-6">
                    <x-widget style="6" icon="las la-ban" title="Group Cancelled"
                        value="{{ $performanceStats['group_cancelled'] ?? 0 }}" bg="dark" />
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">@lang('Allocated Courses & Scheduled Lectures')</h5>
                </div>
                <div class="card-body">
                    @if($allocatedCourses->isEmpty())
                        <p class="mb-0 text-muted">@lang('No allocated courses found for this teacher.')</p>
                    @else
                        <div class="accordion" id="teacherScheduleAccordion">
                            @foreach($allocatedCourses as $allocatedCourse)
                                @php
                                    $courseSessions = collect($scheduledLectures->get($allocatedCourse->id, []))->sortBy('scheduled_at')->values();
                                    $collapseId = 'courseSchedule'.$allocatedCourse->id;
                                    $headingId = 'courseHeading'.$allocatedCourse->id;
                                @endphp
                                <div class="accordion-item mb-2 border rounded">
                                    <h2 class="accordion-header" id="{{ $headingId }}">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                            data-bs-target="#{{ $collapseId }}" aria-expanded="false" aria-controls="{{ $collapseId }}">
                                            <div class="d-flex justify-content-between w-100 me-3">
                                                <span>{{ $allocatedCourse->title }}</span>
                                                <span class="text-muted">@lang('Scheduled Lectures'): {{ $courseSessions->count() }}</span>
                                            </div>
                                        </button>
                                    </h2>
                                    <div id="{{ $collapseId }}" class="accordion-collapse collapse" aria-labelledby="{{ $headingId }}"
                                        data-bs-parent="#teacherScheduleAccordion">
                                        <div class="accordion-body">
                                            @if($courseSessions->isEmpty())
                                                <p class="mb-0 text-muted">@lang('No scheduled lectures yet.')</p>
                                            @else
                                                <div class="table-responsive">
                                                    <table class="table table-striped mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th>@lang('Session')</th>
                                                                <th>@lang('Lecture')</th>
                                                                <th>@lang('Batch')</th>
                                                                <th>@lang('Meeting Time')</th>
                                                                <th>@lang('Meeting URL')</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($courseSessions as $session)
                                                                <tr>
                                                                    <td>#{{ $session->session_number }}</td>
                                                                    <td>{{ optional($session->lecture)->title ?? __('N/A') }}</td>
                                                                    <td>{{ optional($session->batch)->title ?? __('N/A') }}</td>
                                                                    <td>{{ showDateTime($session->scheduled_at, 'd M Y h:i A') }}</td>
                                                                    <td>
                                                                        @if($session->zoom_join_url)
                                                                            <a href="{{ $session->zoom_join_url }}" target="_blank" rel="noopener noreferrer">
                                                                                {{ \Illuminate\Support\Str::limit($session->zoom_join_url, 70) }}
                                                                            </a>
                                                                        @else
                                                                            <span class="text-muted">@lang('Not available')</span>
                                                                        @endif
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="d-flex flex-wrap gap-3 mt-4">

                <div class="flex-fill">
                    <a href="{{ route('admin.report.login.history') }}?search={{ $teacher->username }}"
                        class="btn btn--primary btn--shadow w-100 btn-lg">
                        <i class="las la-list-alt"></i>@lang('Logins')
                    </a>
                </div>

                <div class="flex-fill">
                    <a href="{{ route('admin.teachers.notification.log', $teacher->id) }}?type=teacher"
                        class="btn btn--secondary btn--shadow w-100 btn-lg">
                        <i class="las la-bell"></i>@lang('Notifications')
                    </a>
                </div>

                <div class="flex-fill">
                    <button type="button" id="openTeacherCalendar" class="btn btn--info btn--shadow w-100 btn-lg"
                        data-bs-toggle="modal" data-bs-target="#teacherCalendarModal">
                        <i class="las la-calendar-alt"></i>@lang('Teacher Calendar')
                    </button>
                </div>

                @if ($teacher->kyc_data)
                    <div class="flex-fill">
                        <a href="{{ route('admin.teachers.kyc.details', $teacher->id) }}" target="_blank"
                            class="btn btn--dark btn--shadow w-100 btn-lg">
                            <i class="las la-user-check"></i>@lang('KYC Data')
                        </a>
                    </div>
                @endif

                <div class="flex-fill">
                    @if ($teacher->status == Status::USER_ACTIVE)
                        <button type="button" class="btn btn--warning btn--shadow w-100 btn-lg userStatus"
                            data-bs-toggle="modal" data-bs-target="#userStatusModal">
                            <i class="las la-ban"></i>@lang('Ban Academy')
                        </button>
                    @else
                        <button type="button" class="btn btn--success btn--shadow w-100 btn-lg userStatus"
                            data-bs-toggle="modal" data-bs-target="#userStatusModal">
                            <i class="las la-undo"></i>@lang('Unban Academy')
                        </button>
                    @endif
                </div>
            </div>


            <div class="card mt-30">
                <div class="card-header">
                    <h5 class="card-title mb-0">@lang('Information of') {{ $teacher->fullname }}</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.teachers.update', $teacher->id) }}" method="POST"
                        enctype="multipart/form-data">
                        @csrf

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('First Name')</label>
                                    <input class="form-control" type="text" name="firstname" required
                                        value="{{ $teacher->firstname }}">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-control-label">@lang('Last Name')</label>
                                    <input class="form-control" type="text" name="lastname" required
                                        value="{{ $teacher->lastname }}">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Email')</label>
                                    <input class="form-control" type="email" name="email"
                                        value="{{ $teacher->email }}" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Mobile Number')</label>
                                    <div class="input-group ">
                                        <span class="input-group-text mobile-code">+{{ $teacher->dial_code }}</span>
                                        <input type="number" name="mobile" value="{{ $teacher->mobile }}"
                                            id="mobile" class="form-control checkUser" required>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="form-group ">
                                    <label>@lang('Address')</label>
                                    <input class="form-control" type="text" name="address"
                                        value="{{ @$teacher->address }}">
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6">
                                <div class="form-group">
                                    <label>@lang('City')</label>
                                    <input class="form-control" type="text" name="city"
                                        value="{{ @$teacher->city }}">
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6">
                                <div class="form-group ">
                                    <label>@lang('State')</label>
                                    <input class="form-control" type="text" name="state"
                                        value="{{ @$teacher->state }}">
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6">
                                <div class="form-group ">
                                    <label>@lang('Zip/Postal')</label>
                                    <input class="form-control" type="text" name="zip"
                                        value="{{ @$teacher->zip }}">
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6">
                                <div class="form-group ">
                                    <label>@lang('Country') <span class="text--danger">*</span></label>
                                    <select name="country" class="form-control select2">
                                        @foreach ($countries as $key => $country)
                                            <option data-mobile_code="{{ $country->dial_code }}"
                                                value="{{ $key }}" @selected($teacher->country_code == $key)>
                                                {{ __($country->country) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>


                            <div class="col-xl-3 col-md-6 col-12">
                                <div class="form-group">
                                    <label>@lang('Email Verification')</label>
                                    <input type="checkbox" data-width="100%" data-onstyle="-success"
                                        data-offstyle="-danger" data-bs-toggle="toggle" data-on="@lang('Verified')"
                                        data-off="@lang('Unverified')" name="ev"
                                        @if ($teacher->ev) checked @endif>
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6 col-12">
                                <div class="form-group">
                                    <label>@lang('Mobile Verification')</label>
                                    <input type="checkbox" data-width="100%" data-onstyle="-success"
                                        data-offstyle="-danger" data-bs-toggle="toggle" data-on="@lang('Verified')"
                                        data-off="@lang('Unverified')" name="sv"
                                        @if ($teacher->sv) checked @endif>
                                </div>
                            </div>
                            <div class="col-xl-3 col-12">
                                <div class="form-group">
                                    <label>@lang('2FA Verification') </label>
                                    <input type="checkbox" data-width="100%" data-height="50" data-onstyle="-success"
                                        data-offstyle="-danger" data-bs-toggle="toggle" data-on="@lang('Enable')"
                                        data-off="@lang('Disable')" name="ts"
                                        @if ($teacher->ts) checked @endif>
                                </div>
                            </div>
                            <div class="col-xl-3 col-12">
                                <div class="form-group">
                                    <label>@lang('KYC') </label>
                                    <input type="checkbox" data-width="100%" data-height="50" data-onstyle="-success"
                                        data-offstyle="-danger" data-bs-toggle="toggle" data-on="@lang('Verified')"
                                        data-off="@lang('Unverified')" name="kv"
                                        @if ($teacher->kv == Status::KYC_VERIFIED) checked @endif>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <button type="submit" class="btn btn--primary w-100 h-45">@lang('Submit')
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>



    <div id="teacherCalendarModal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Teacher Calendar')</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">@lang('From')</label>
                            <input type="date" class="form-control" id="teacherCalendarFrom">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">@lang('To')</label>
                            <input type="date" class="form-control" id="teacherCalendarTo">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="button" class="btn btn--primary w-100" id="loadTeacherCalendarBtn">@lang('Load Calendar')</button>
                        </div>
                    </div>

                    <div class="card mb-3 shadow-sm border-0">
                        <div class="card-header d-flex justify-content-between align-items-center bg-light">
                            <button type="button" class="btn btn--dark btn--sm" id="teacherCalendarPrevMonth">@lang('Prev')</button>
                            <strong id="teacherCalendarMonthLabel">@lang('Calendar')</strong>
                            <button type="button" class="btn btn--dark btn--sm" id="teacherCalendarNextMonth">@lang('Next')</button>
                        </div>
                        <div class="card-body p-2">
                            <div class="table-responsive">
                                <table class="table table-sm text-center mb-0">
                                    <thead>
                                        <tr>
                                            <th>@lang('Sun')</th>
                                            <th>@lang('Mon')</th>
                                            <th>@lang('Tue')</th>
                                            <th>@lang('Wed')</th>
                                            <th>@lang('Thu')</th>
                                            <th>@lang('Fri')</th>
                                            <th>@lang('Sat')</th>
                                        </tr>
                                    </thead>
                                    <tbody id="teacherCalendarGridBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm border-0">
                        <div class="card-header">
                            <h6 class="mb-0" id="teacherCalendarSelectedDate">@lang('Select a date')</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>@lang('Type')</th>
                                            <th>@lang('Title')</th>
                                            <th>@lang('Batch')</th>
                                            <th>@lang('Start')</th>
                                            <th>@lang('End')</th>
                                            <th>@lang('Reason')</th>
                                        </tr>
                                    </thead>
                                    <tbody id="teacherCalendarSelectedList">
                                        <tr><td colspan="6" class="text-center text-muted">@lang('Click Load Calendar to view schedule')</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="userStatusModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        @if ($teacher->status == Status::USER_ACTIVE)
                            @lang('Ban Academy')
                        @else
                            @lang('Unban Academy')
                        @endif
                    </h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <form action="{{ route('admin.teachers.status', $teacher->id) }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        @if ($teacher->status == Status::USER_ACTIVE)
                            <h6 class="mb-2">@lang('If you ban this user he/she won\'t able to access his/her dashboard.')</h6>
                            <div class="form-group">
                                <label>@lang('Reason')</label>
                                <textarea class="form-control" name="reason" rows="4" required></textarea>
                            </div>
                        @else
                            <p><span>@lang('Ban reason was'):</span></p>
                            <p>{{ $teacher->ban_reason }}</p>
                            <h4 class="text-center mt-3">@lang('Are you sure to unban this user?')</h4>
                        @endif
                    </div>
                    <div class="modal-footer">
                        @if ($teacher->status == Status::USER_ACTIVE)
                            <button type="submit" class="btn btn--primary h-45 w-100">@lang('Submit')</button>
                        @else
                            <button type="button" class="btn btn--dark"
                                data-bs-dismiss="modal">@lang('No')</button>
                            <button type="submit" class="btn btn--primary">@lang('Yes')</button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection



@push('script')
    <script>
        (function($) {
            "use strict"




            let mobileElement = $('.mobile-code');
            $('select[name=country]').on('change', function() {
                mobileElement.text(`+${$('select[name=country] :selected').data('mobile_code')}`);
            });


            const calendarUrl = '{{ route('admin.teachers.detail.calendar', $teacher->id) }}';
            let teacherCalendarItems = [];
            let teacherCalendarCurrentMonth = new Date();
            let teacherCalendarSelectedDateKey = '';

            function esc(value) {
                return String(value || '-')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/\"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function formatDateInput(dateObj) {
                const y = dateObj.getFullYear();
                const m = String(dateObj.getMonth() + 1).padStart(2, '0');
                const d = String(dateObj.getDate()).padStart(2, '0');
                return y + '-' + m + '-' + d;
            }

            function toDateKey(input) {
                const dt = new Date(input);
                if (Number.isNaN(dt.getTime())) return '';
                return formatDateInput(dt);
            }

            function monthLabel(dateObj) {
                return dateObj.toLocaleString('default', { month: 'long', year: 'numeric' });
            }

            function itemsByDate() {
                const map = {};
                teacherCalendarItems.forEach((item) => {
                    const key = toDateKey(item.start_at);
                    if (!key) return;
                    if (!map[key]) map[key] = [];
                    map[key].push(item);
                });
                return map;
            }

            function renderSelectedItems() {
                const grouped = itemsByDate();
                const rows = grouped[teacherCalendarSelectedDateKey] || [];
                $('#teacherCalendarSelectedDate').text(teacherCalendarSelectedDateKey || 'Select a date');
                if (!rows.length) {
                    $('#teacherCalendarSelectedList').html('<tr><td colspan="6" class="text-center text-muted">No items for selected date.</td></tr>');
                    return;
                }
                const html = rows.map((item) => {
                    return '<tr>' +
                        '<td>' + esc(item.type) + '</td>' +
                        '<td>' + esc(item.title) + '</td>' +
                        '<td>' + esc(item.batch) + '</td>' +
                                                '<td>' + esc(item.start_at) + '</td>' +
                        '<td>' + esc(item.end_at) + '</td>' +
                                                '<td>' + esc(item.reason || '-') + '</td>' +
                    '</tr>';
                }).join('');
                $('#teacherCalendarSelectedList').html(html);
            }

            function renderCalendarGrid() {
                const grouped = itemsByDate();
                const year = teacherCalendarCurrentMonth.getFullYear();
                const month = teacherCalendarCurrentMonth.getMonth();
                const firstDay = new Date(year, month, 1);
                const lastDay = new Date(year, month + 1, 0);
                const start = new Date(firstDay);
                start.setDate(firstDay.getDate() - firstDay.getDay());
                const end = new Date(lastDay);
                end.setDate(lastDay.getDate() + (6 - lastDay.getDay()));

                $('#teacherCalendarMonthLabel').text(monthLabel(teacherCalendarCurrentMonth));

                let html = '';
                let cursor = new Date(start);
                while (cursor <= end) {
                    html += '<tr>';
                    for (let i = 0; i < 7; i++) {
                        const key = toDateKey(cursor);
                        const count = (grouped[key] || []).length;
                        const isCurrentMonth = cursor.getMonth() === month;
                        const isSelected = key === teacherCalendarSelectedDateKey;
                        const cls = isSelected ? 'btn--primary text-white' : (isCurrentMonth ? 'btn btn-light text-dark border' : 'btn btn-light text-muted border opacity-75');
                        const dayNumberColor = isSelected ? '#ffffff' : '#111827';
                        html += '<td class="p-1">' +
                            '<button type="button" class="btn ' + cls + ' btn-sm w-100 teacher-calendar-day" data-date="' + key + '" style="min-height:58px;">' +
                            '<div class="d-flex justify-content-between align-items-start">' +
                                '<span class="fw-bold" style="font-size:14px;color:' + dayNumberColor + ';">' + cursor.getDate() + '</span>' +
                                (count ? '<span class="badge rounded-pill bg-warning text-dark" style="min-width:22px;height:22px;line-height:22px;padding:0 6px;font-size:11px;">' + count + '</span>' : '') +
                            '</div>' +
                            '</button>' +
                        '</td>';
                        cursor.setDate(cursor.getDate() + 1);
                    }
                    html += '</tr>';
                }

                $('#teacherCalendarGridBody').html(html);
                renderSelectedItems();
            }

            function loadTeacherCalendar() {
                const from = $('#teacherCalendarFrom').val();
                const to = $('#teacherCalendarTo').val();
                const query = $.param({ from, to });

                $('#teacherCalendarGridBody').html('<tr><td colspan="7" class="text-center text-muted">Loading...</td></tr>');
                $('#teacherCalendarSelectedList').html('<tr><td colspan="6" class="text-center text-muted">Loading...</td></tr>');

                $.get(calendarUrl + '?' + query)
                    .done(function(response) {
                        teacherCalendarItems = response?.data?.items || [];
                        teacherCalendarCurrentMonth = from ? new Date(from + 'T00:00:00') : new Date();
                        teacherCalendarSelectedDateKey = from || toDateKey(new Date());
                        renderCalendarGrid();
                    })
                    .fail(function(xhr) {
                        const msg = xhr?.responseJSON?.message?.[0] || 'Failed to load teacher calendar.';
                        $('#teacherCalendarGridBody').html('<tr><td colspan="7" class="text-center text-danger">' + esc(msg) + '</td></tr>');
                        $('#teacherCalendarSelectedList').html('<tr><td colspan="6" class="text-center text-danger">' + esc(msg) + '</td></tr>');
                    });
            }

            $(document).on('click', '.teacher-calendar-day', function() {
                teacherCalendarSelectedDateKey = $(this).data('date');
                renderCalendarGrid();
            });

            $('#teacherCalendarPrevMonth').on('click', function() {
                teacherCalendarCurrentMonth = new Date(teacherCalendarCurrentMonth.getFullYear(), teacherCalendarCurrentMonth.getMonth() - 1, 1);
                renderCalendarGrid();
            });

            $('#teacherCalendarNextMonth').on('click', function() {
                teacherCalendarCurrentMonth = new Date(teacherCalendarCurrentMonth.getFullYear(), teacherCalendarCurrentMonth.getMonth() + 1, 1);
                renderCalendarGrid();
            });

            $('#openTeacherCalendar').on('click', function() {
                const today = new Date();
                const after30 = new Date();
                after30.setDate(after30.getDate() + 30);
                $('#teacherCalendarFrom').val(formatDateInput(today));
                $('#teacherCalendarTo').val(formatDateInput(after30));
                loadTeacherCalendar();
            });

            $('#loadTeacherCalendarBtn').on('click', function() {
                loadTeacherCalendar();
            });
        })(jQuery);
    </script>
@endpush

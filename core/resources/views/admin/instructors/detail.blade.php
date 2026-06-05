@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <div class="col-12">
            <div class="row gy-4">

                <div class="col-xxl-3 col-sm-6">
                    <x-widget style="7" link="{{ route('admin.report.transaction', $instructor->id) }}" title="Balance"
                        icon="las la-money-bill-wave-alt" value="{{ showAmount($instructor->balance) }}" bg="indigo"
                        type="2" />
                </div>


                <div class="col-xxl-3 col-sm-6">
                    <x-widget style="7" link="{{ route('admin.report.transaction', $instructor->id) }}?type=instructor&remark=payment_received" title="Earnings"
                        icon="las la-wallet" value="{{ showAmount($totalEarning) }}" bg="8" type="2" />
                </div>

                <div class="col-xxl-3 col-sm-6">
                    <x-widget style="7" link="{{ route('admin.withdraw.data.all', $instructor->id) }}"
                        title="Withdrawals" icon="la la-bank" value="{{ showAmount($totalWithdrawals) }}" bg="6"
                        type="2" />
                </div>

                <div class="col-xxl-3 col-sm-6">
                    <x-widget style="7" link="{{ route('admin.report.transaction', $instructor->id) }}?type=instructor"
                        title="Transactions" icon="las la-exchange-alt" value="{{ $totalTransaction }}" bg="17"
                        type="2" />
                </div>

            </div>


            <div class="row gy-4 mt-3">

                <div class="col-xxl-3 col-sm-6">

                    <x-widget style="6" link="{{ route('admin.courses.index', $instructor->id) }}" icon="las la-book"
                        title="Total Courses" value="{{ $course['total_course'] }}" bg="primary" />
                </div><!-- dashboard-w1 end -->
                <div class="col-xxl-3 col-sm-6">
                    <x-widget style="6" link="{{ route('admin.courses.approved', $instructor->id) }}" icon="las la-book-open"
                        title="Total Approved Courses" value="{{ $course['total_approved_course'] }}" bg="success" />
                </div><!-- dashboard-w1 end -->

                <div class="col-xxl-3 col-sm-6">
                    <x-widget style="6" link="{{ route('admin.courses.pending', $instructor->id) }}" icon="las la-spinner"
                        title="Total Pending Courses" value="{{ $course['total_pending_course'] }}" bg="warning" />
                </div><!-- dashboard-w1 end -->

                <div class="col-xxl-3 col-sm-6">
                    <x-widget style="6" link="{{ route('admin.courses.rejected', $instructor->id) }}" icon="las la-times"
                        title="Total Rejected Courses" value="{{ $course['total_rejected_course'] }}" bg="danger" />
                </div><!-- dashboard-w1 end -->
            </div><!-- row end-->

            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">@lang('Kid Course & Session Insights')</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table--light style--two mb-0">
                            <thead>
                                <tr>
                                    <th>@lang('Kid')</th>
                                    <th>@lang('Course')</th>
                                    <th>@lang('Class Type')</th>
                                    <th>@lang('Sessions Covered')</th>
                                    <th>@lang('Sessions Left')</th>
                                    <th>@lang('Last Session')</th>
                                    <th>@lang('Academy')</th>
                                    <th>@lang('Trial Completed')</th>
                                    <th>@lang('Courses Bought')</th>
                                    <th>@lang('Zoom Links (Trial / 1:1 / Group)')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($kidCourseRows as $row)
                                    <tr>
                                        <td>
                                            <span class="fw-bold">{{ $row['kid_name'] ?: __('N/A') }}</span>
                                            <br>
                                            <small class="text-muted">{{ $row['kid_email'] ?: '@'.$row['kid_username'] }}</small>
                                        </td>
                                        <td>{{ $row['course_title'] ?: __('N/A') }}</td>
                                        <td>
                                            <span class="badge badge--info">{{ strtoupper(str_replace('_', ' ', (string) $row['class_type'])) }}</span>
                                        </td>
                                        <td>{{ $row['sessions_covered'] }}</td>
                                        <td>{{ $row['sessions_left'] }}</td>
                                        <td>
                                            @if($row['last_session_at'])
                                                {{ showDateTime($row['last_session_at']) }}<br>
                                                <small class="text-muted">{{ diffForHumans($row['last_session_at']) }}</small>
                                            @else
                                                <span class="text-muted">@lang('N/A')</span>
                                            @endif
                                        </td>
                                        <td>{{ $row['course_instructor'] }}</td>
                                        <td>
                                            @if($row['trial_completed_at'])
                                                {{ showDateTime($row['trial_completed_at']) }}
                                            @else
                                                <span class="text-muted">@lang('N/A')</span>
                                            @endif
                                        </td>
                                        <td>{{ $row['courses_bought'] }}</td>
                                        <td>
                                            <div class="d-flex flex-column gap-1">
                                                <div>
                                                    <small class="text-muted">@lang('Trial'):</small>
                                                    @if(collect($row['trial_zoom_links'])->isNotEmpty())
                                                        @foreach($row['trial_zoom_links']->take(2) as $link)
                                                            <a href="{{ $link }}" target="_blank" rel="noopener noreferrer" class="ms-1">@lang('Link')</a>
                                                        @endforeach
                                                        @if($row['trial_zoom_links']->count() > 2)
                                                            <small class="text-muted ms-1">+{{ $row['trial_zoom_links']->count() - 2 }}</small>
                                                        @endif
                                                    @else
                                                        <span class="text-muted ms-1">@lang('N/A')</span>
                                                    @endif
                                                </div>
                                                <div>
                                                    <small class="text-muted">@lang('1:1'):</small>
                                                    @if(collect($row['one_to_one_zoom_links'])->isNotEmpty())
                                                        @foreach($row['one_to_one_zoom_links']->take(2) as $link)
                                                            <a href="{{ $link }}" target="_blank" rel="noopener noreferrer" class="ms-1">@lang('Link')</a>
                                                        @endforeach
                                                        @if($row['one_to_one_zoom_links']->count() > 2)
                                                            <small class="text-muted ms-1">+{{ $row['one_to_one_zoom_links']->count() - 2 }}</small>
                                                        @endif
                                                    @else
                                                        <span class="text-muted ms-1">@lang('N/A')</span>
                                                    @endif
                                                </div>
                                                <div>
                                                    <small class="text-muted">@lang('Group'):</small>
                                                    @if(collect($row['group_zoom_links'])->isNotEmpty())
                                                        @foreach($row['group_zoom_links']->take(2) as $link)
                                                            <a href="{{ $link }}" target="_blank" rel="noopener noreferrer" class="ms-1">@lang('Link')</a>
                                                        @endforeach
                                                        @if($row['group_zoom_links']->count() > 2)
                                                            <small class="text-muted ms-1">+{{ $row['group_zoom_links']->count() - 2 }}</small>
                                                        @endif
                                                    @else
                                                        <span class="text-muted ms-1">@lang('N/A')</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="100%" class="text-center text-muted py-4">@lang('No enrolled kids found for this instructor')</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-3 mt-4">
                <div class="flex-fill">
                    <button data-bs-toggle="modal" data-bs-target="#addSubModal"
                        class="btn btn--success btn--shadow w-100 btn-lg bal-btn" data-act="add">
                        <i class="las la-plus-circle"></i> @lang('Balance')
                    </button>
                </div>

                <div class="flex-fill">
                    <button data-bs-toggle="modal" data-bs-target="#addSubModal"
                        class="btn btn--danger btn--shadow w-100 btn-lg bal-btn" data-act="sub">
                        <i class="las la-minus-circle"></i> @lang('Balance')
                    </button>
                </div>

                <div class="flex-fill">
                    <a href="{{ route('admin.report.login.history') }}?search={{ $instructor->username }}"
                        class="btn btn--primary btn--shadow w-100 btn-lg">
                        <i class="las la-list-alt"></i>@lang('Logins')
                    </a>
                </div>

                <div class="flex-fill">
                    <a href="{{ route('admin.instructors.notification.log', $instructor->id) }}?type=instructor"
                        class="btn btn--secondary btn--shadow w-100 btn-lg">
                        <i class="las la-bell"></i>@lang('Notifications')
                    </a>
                </div>

                @if ($instructor->kyc_data)
                    <div class="flex-fill">
                        <a href="{{ route('admin.instructors.kyc.details', $instructor->id) }}" target="_blank"
                            class="btn btn--dark btn--shadow w-100 btn-lg">
                            <i class="las la-user-check"></i>@lang('KYC Data')
                        </a>
                    </div>
                @endif

                <div class="flex-fill">
                    @if ($instructor->status == Status::USER_ACTIVE)
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
                    <h5 class="card-title mb-0">@lang('Information of') {{ $instructor->fullname }}</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.instructors.update', $instructor->id) }}" method="POST"
                        enctype="multipart/form-data">
                        @csrf

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('First Name')</label>
                                    <input class="form-control" type="text" name="firstname" required
                                        value="{{ $instructor->firstname }}">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-control-label">@lang('Last Name')</label>
                                    <input class="form-control" type="text" name="lastname" required
                                        value="{{ $instructor->lastname }}">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Email')</label>
                                    <input class="form-control" type="email" name="email"
                                        value="{{ $instructor->email }}" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Mobile Number')</label>
                                    <div class="input-group ">
                                        <span class="input-group-text mobile-code">+{{ $instructor->dial_code }}</span>
                                        <input type="number" name="mobile" value="{{ $instructor->mobile }}"
                                            id="mobile" class="form-control checkUser" required>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="form-group ">
                                    <label>@lang('Address')</label>
                                    <input class="form-control" type="text" name="address"
                                        value="{{ @$instructor->address }}">
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6">
                                <div class="form-group">
                                    <label>@lang('City')</label>
                                    <input class="form-control" type="text" name="city"
                                        value="{{ @$instructor->city }}">
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6">
                                <div class="form-group ">
                                    <label>@lang('State')</label>
                                    <input class="form-control" type="text" name="state"
                                        value="{{ @$instructor->state }}">
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6">
                                <div class="form-group ">
                                    <label>@lang('Zip/Postal')</label>
                                    <input class="form-control" type="text" name="zip"
                                        value="{{ @$instructor->zip }}">
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6">
                                <div class="form-group ">
                                    <label>@lang('Country') <span class="text--danger">*</span></label>
                                    <select name="country" class="form-control select2">
                                        @foreach ($countries as $key => $country)
                                            <option data-mobile_code="{{ $country->dial_code }}"
                                                value="{{ $key }}" @selected($instructor->country_code == $key)>
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
                                        @if ($instructor->ev) checked @endif>
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6 col-12">
                                <div class="form-group">
                                    <label>@lang('Mobile Verification')</label>
                                    <input type="checkbox" data-width="100%" data-onstyle="-success"
                                        data-offstyle="-danger" data-bs-toggle="toggle" data-on="@lang('Verified')"
                                        data-off="@lang('Unverified')" name="sv"
                                        @if ($instructor->sv) checked @endif>
                                </div>
                            </div>
                            <div class="col-xl-3 col-12">
                                <div class="form-group">
                                    <label>@lang('2FA Verification') </label>
                                    <input type="checkbox" data-width="100%" data-height="50" data-onstyle="-success"
                                        data-offstyle="-danger" data-bs-toggle="toggle" data-on="@lang('Enable')"
                                        data-off="@lang('Disable')" name="ts"
                                        @if ($instructor->ts) checked @endif>
                                </div>
                            </div>
                            <div class="col-xl-3 col-12">
                                <div class="form-group">
                                    <label>@lang('KYC') </label>
                                    <input type="checkbox" data-width="100%" data-height="50" data-onstyle="-success"
                                        data-offstyle="-danger" data-bs-toggle="toggle" data-on="@lang('Verified')"
                                        data-off="@lang('Unverified')" name="kv"
                                        @if ($instructor->kv == Status::KYC_VERIFIED) checked @endif>
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



    {{-- Add Sub Balance MODAL --}}
    <div id="addSubModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><span class="type"></span> <span>@lang('Balance')</span></h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <form action="{{ route('admin.instructors.add.sub.balance', $instructor->id) }}"
                    class="balanceAddSub disableSubmission" method="POST">
                    @csrf
                    <input type="hidden" name="act">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>@lang('Amount')</label>
                            <div class="input-group">
                                <input type="number" step="any" name="amount" class="form-control"
                                    placeholder="@lang('Please provide positive amount')" required>
                                <div class="input-group-text">{{ __(gs('cur_text')) }}</div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>@lang('Remark')</label>
                            <textarea class="form-control" placeholder="@lang('Remark')" name="remark" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn--primary h-45 w-100">@lang('Submit')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <div id="userStatusModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        @if ($instructor->status == Status::USER_ACTIVE)
                            @lang('Ban Academy')
                        @else
                            @lang('Unban Academy')
                        @endif
                    </h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <form action="{{ route('admin.instructors.status', $instructor->id) }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        @if ($instructor->status == Status::USER_ACTIVE)
                            <h6 class="mb-2">@lang('If you ban this user he/she won\'t able to access his/her dashboard.')</h6>
                            <div class="form-group">
                                <label>@lang('Reason')</label>
                                <textarea class="form-control" name="reason" rows="4" required></textarea>
                            </div>
                        @else
                            <p><span>@lang('Ban reason was'):</span></p>
                            <p>{{ $instructor->ban_reason }}</p>
                            <h4 class="text-center mt-3">@lang('Are you sure to unban this user?')</h4>
                        @endif
                    </div>
                    <div class="modal-footer">
                        @if ($instructor->status == Status::USER_ACTIVE)
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


            $('.bal-btn').on('click', function() {

                $('.balanceAddSub')[0].reset();

                var act = $(this).data('act');
                $('#addSubModal').find('input[name=act]').val(act);
                if (act == 'add') {
                    $('.type').text('Add');
                } else {
                    $('.type').text('Subtract');
                }
            });

            let mobileElement = $('.mobile-code');
            $('select[name=country]').on('change', function() {
                mobileElement.text(`+${$('select[name=country] :selected').data('mobile_code')}`);
            });

        })(jQuery);
    </script>
@endpush

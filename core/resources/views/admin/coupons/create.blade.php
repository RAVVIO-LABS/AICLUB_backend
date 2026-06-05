@extends('admin.layouts.app')

@section('panel')
    <form action="{{ route('admin.coupon.store', $coupon->id ?? 0) }}" method="POST">
        @csrf
        <div class="row gy-3">
            <div class="col-md-12">

                <div class="row gy-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">@lang('General Information')</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group row">
                                            <div class="col-md-3">
                                                <label>@lang('Coupon Name') </label>
                                            </div>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" name="coupon_name"
                                                    value="{{ old('coupon_name', @$coupon->coupon_name) }}" required />
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <div class="col-md-3">
                                                <label>@lang('Coupon Code')</label>
                                            </div>
                                            <div class="col-md-9">
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="coupon_code"
                                                        value="{{ old('coupon_code', @$coupon->coupon_code) }}" required>
                                                    <button type="button"
                                                        class="input-group-text generateCoupon">@lang('Generate')</button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <div class="col-md-3">
                                                <label>@lang('Discount Type')</label>
                                            </div>
                                            <div class="col-md-9">
                                                <select class="form-control" name="discount_type" required>
                                                    <option value="" selected hidden>@lang('Select One')</option>
                                                    <option value="{{ Status::DISCOUNT_FIXED }}">@lang('Flat')</option>
                                                    <option value="{{ Status::DISCOUNT_PERCENT }}">@lang('Percentage')
                                                    </option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <div class="col-md-3">
                                                <label>@lang('Amount')</label>
                                            </div>
                                            <div class="col-md-9">
                                                <div class="input-group">
                                                    <input type="number" step="any" class="form-control" name="amount"
                                                        value="{{ old('amount', @$coupon->coupon_amount) }}" required>
                                                    <span class="input-group-text" id="couponAmountType"></span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <div class="col-md-3">
                                                <label for="expired_at">@lang('Expired At')</label>
                                                <small class="text-muted" title="@lang('The coupon will expire on the selected date. Leave it empty if you do not want to set an expiry date.')"><i
                                                        class="la la-info-circle"></i></small>
                                            </div>

                                            @php $endDate = @$coupon->expired_at ? showDateTime(@$coupon->expired_at, 'Y-m-d h:i A') : null;@endphp

                                            <div class="col-md-9">
                                                <input type="text" name="expired_at" class="form-control"
                                                    data-language='en' value="{{ old('expired_at', $endDate) }}"
                                                    autocomplete="off">
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">@lang('Usage Limit')</h5>
                            </div>
                            <div class="card-body">
                                <div class="form-group row">
                                    <div class="col-md-3">
                                        <label>@lang('Limit Per Coupon')</label>
                                        <small><i class="la la-info-circle text-muted"
                                                title="@lang('How many times this coupon can be used. Keep empty if you do not want to set a limit.')"></i></small>
                                    </div>
                                    <div class="col-md-9">
                                        <input type="number" name="usage_limit_per_coupon" class="form-control"
                                            value="{{ old('usage_limit_per_coupon', @$coupon->usage_limit_per_coupon) }}">
                                    </div>
                                </div>

                                <div class="form-group row">
                                    <div class="col-md-3">
                                        <label>@lang('Limit Per Customer')</label>
                                        <small><i class="la la-info-circle text-muted"
                                                title="@lang('How many times a customer can use this coupon. Keep empty if you do not want to set a')"></i></small>
                                    </div>
                                    <div class="col-md-9">
                                        <input type="number" name="usage_limit_per_customer" class="form-control"
                                            value="{{ old('usage_limit_per_customer', @$coupon->usage_limit_per_user) }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-12">
                <div class="card ">
                    <div class="card-header">
                        <h5 class="card-title mb-0">@lang('Usage Restrictions')</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group row">
                                    <div class="col-md-3">
                                        <label for="minimum_spend">@lang('Minimum Spend')</label>
                                    </div>

                                    <div class="col-md-9">
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="minimum_spend"
                                                value="{{ old('minimum_spend', @$coupon->minimum_spend) }}" />
                                            <span class="input-group-text"> {{ gs('cur_text') }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group row">
                                    <div class="col-md-3">
                                        <label>@lang('Maximum Spend')</label>
                                    </div>

                                    <div class="col-md-9">
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="maximum_spend"
                                                value="{{ old('maximum_spend', @$coupon->maximum_spend) }}">
                                            <span class="input-group-text"> {{ gs('cur_text') }}</span>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn--primary w-100 h-45">@lang('Submit')</button>
            </div>
        </div>
    </form>
@endsection

@push('breadcrumb-plugins')
    <x-back route="{{ route('admin.coupon.index') }}"></x-back>
@endpush

@push('style-lib')
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/admin/css/daterangepicker.css') }}">
@endpush

@push('script-lib')
    <script src="{{ asset('assets/admin/js/moment.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/daterangepicker.min.js') }}"></script>
@endpush


@push('style')
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/admin/css/select2-custom.css') }}">
    <style>
        .categories-checkboxes {
            height: 295px;
            overflow-y: auto;
            border: 1px solid #ccc;
            border-radius: 5px;
            padding: 1rem 0;
        }
    </style>
@endpush


@push('script')
    <script>
        'use strict';
        (function($) {
            const discountTypeField = $('[name=discount_type]');
            const expiryDate = $('[name=expired_at]');
            const discountType = `{{ old('discount_type', @$coupon->discount_type) }}`;
            const categories = @json(@$coupon->categories?->pluck('id') ?? []);
            const products = @json(@$coupon->products?->pluck('id') ?? []);

            console.log(products);

            expiryDate.daterangepicker({
                timePicker: true,
                singleDatePicker: true,
                minDate: moment().startOf('day'), // Restrict past dates
                locale: {
                    format: 'YYYY-MM-DD hh:mm A'
                },
                applyButtonClasses: 'btn--primary',
                autoUpdateInput: false // Keeps the input field empty initially
            });

            expiryDate.on('apply.daterangepicker', function(ev, picker) {
                $(this).val(picker.startDate.format('YYYY-MM-DD hh:mm A'));
            });


            discountTypeField.val(discountType);

            const discountTypeChangeHandler = (value) => {
                if (discountTypeField.val() == '{{ Status::DISCOUNT_FIXED }}') {
                    $('#couponAmountType').text(`{{ gs('cur_text') }}`);
                } else if (discountTypeField.val() == '{{ Status::DISCOUNT_PERCENT }}') {
                    $('#couponAmountType').text(`%`);
                }
            }

            discountTypeChangeHandler();
            discountTypeField.on('change', () => discountTypeChangeHandler());





            const couponHandler = () => {
                let couponCode = '';
                const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                for (let i = 0; i < 6; i++) {
                    couponCode += characters.charAt(Math.floor(Math.random() * characters.length));
                }
                $('[name=coupon_code]').val(couponCode);
            }

            $('.generateCoupon').on('click', couponHandler)
        })(jQuery);
    </script>
@endpush

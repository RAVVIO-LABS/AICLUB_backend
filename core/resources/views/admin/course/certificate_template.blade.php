@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <div class="col-md-12">
            <div class="card overflow-hidden">
                <div class="card-body p-0">
                    <div class="table-responsive table-responsive--sm">
                        <table class="align-items-center table--light table">
                            <thead>
                                <tr>
                                    <th>@lang('Short Code') </th>
                                    <th>@lang('Description')</th>
                                </tr>
                            </thead>
                            <tbody class="list">
                                <tr>
                                    <td><span class="short-codes">@{{site_logo}}</span></td>
                                    <td>@lang('Name of your site')</td>
                                </tr>
                                <tr>
                                    <td><span class="short-codes">@{{site_name}}</span></td>
                                    <td>@lang('Name of your site')</td>
                                </tr>
                                <tr>
                                    <td><span class="short-codes">@{{student_name}}</span></td>
                                    <td>@lang('Full Name of Student')</td>
                                </tr>
                                <tr>
                                    <td><span class="short-codes">@{{course_title}}</span></td>
                                    <td>@lang('Course Title')</td>
                                </tr>
                                <tr>
                                    <td><span class="short-codes">@{{instructor_name}}</span></td>
                                    <td>@lang('Full name of course instructor')</td>
                                </tr>
                                <tr>
                                    <td><span class="short-codes">@{{course_based_message}}</span></td>
                                    <td>@lang('Message')</td>
                                </tr>
                                <tr>
                                    <td><span class="short-codes">@{{course_completion_date}}</span></td>
                                    <td>@lang('Course Completion Data')</td>
                                </tr>
                                <tr>
                                    <td><span class="short-codes">@{{qr_code}}</span></td>
                                    <td>@lang('Course Completion Data')</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-12">
            <div class="card mt-5">
                <div class="card-body">
                    <form action="{{ route('admin.certificate.template.update') }}" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>@lang('Certificate Verify Url')</label>
                                    <input class="form-control" placeholder="@lang('https://www.example.com/verify-certificate')" name="verify_url" value="{{ gs('verify_url') }}" required>
                                    <small class="text--primary"><i class="las la-info-circle"></i>@lang("The link should be ")https://yourfrontendsite.com/verify-certificate</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Email Body') </label>
                                    <textarea name="certificate_template" rows="20" class="form-control certificateTemplateEditor" id="htmlInput" placeholder="@lang('Your Certificate template')">{{ gs('certificate_template') }}</textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div id="previewContainer">
                                    <label>&nbsp;</label>
                                    <iframe id="iframePreview"></iframe>
                                </div>
                            </div>
                        </div>
                        <button class="btn w-100 btn--primary h-45" type="submit">@lang('Submit')</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('style')
    <style>
        #iframePreview {
            width: 100%;
            height: 400px;
            border: none;
        }
        .emailTemplateEditor {
            height: 400px;
        }
    </style>
@endpush
@push('script')
    <script>
        var iframe = document.getElementById('iframePreview');
        $(".certificateTemplateEditor").on('input', function() {
            var htmlContent = document.getElementById('htmlInput').value;
            iframe.src = 'data:text/html;charset=utf-8,' + encodeURIComponent(htmlContent);
        }).trigger('input');
    </script>
@endpush
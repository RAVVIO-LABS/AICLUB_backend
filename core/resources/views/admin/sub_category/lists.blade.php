@extends('admin.layouts.app')
@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive--sm table-responsive">
                        <table class="table table--light style--two bg-white">
                            <thead>
                                <tr>

                                    <th>@lang('Name')</th>
                                    <th>@lang('Category Name')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Actions')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($subCategories as $subCategory)
                                    <tr>
                                        <td>{{ __(@$subCategory->name) }}</td>
                                        <td>{{ __(@$subCategory?->category->name) }}</td>

                                        <td>
                                            @php
                                                echo $subCategory->statusBadge;
                                            @endphp
                                        </td>



                                        <td>
                                            <div class="button--group">
                                                <button class="btn btn-sm btn-outline--primary editCategory"
                                                    data-action="{{ route('admin.categories.sub.categories.save', $subCategory->id) }}"
                                                    data-subcategory="{{ $subCategory }}"><i
                                                        class="las la-pen"></i>@lang('Edit')</button>
                                                @if ($subCategory->status)
                                                    <button class="btn btn-sm btn-outline--danger confirmationBtn"
                                                        data-action="{{ route('admin.categories.sub.categories.status', $subCategory->id) }}"
                                                        data-question="@lang('Are you sure you want to disable this subcategory')"><i
                                                            class="las la-eye-slash"></i>@lang('Disable')</button>
                                                @else
                                                    <button class="btn btn-sm btn-outline--success confirmationBtn"
                                                        data-action="{{ route('admin.categories.sub.categories.status', $subCategory->id) }}"
                                                        data-question="@lang('Are you sure you want to enable this subcategory')"><i
                                                            class="las la-eye"></i>@lang('Enable')</button>
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

                        </table><!-- table end -->
                    </div>
                </div>
                @if ($subCategories->hasPages())
                <div class="card-footer py-4">
                    {{ paginateLinks($subCategories) }}
                </div>
                @endif
            </div><!-- card end -->
        </div>
    </div>

    <x-confirmation-modal />


    <div class="modal fade" id="subCategoryModal" tabindex="-1" role="dialog" a aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">@lang('Add Subcategory')</h4>
                    <button type="button" class="close" data-bs-dismiss="modal"><i class="las la-times"></i></button>
                </div>
                <form method="post" action="{{ route('admin.categories.sub.categories.save') }}"
                    enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">


                        <div class="form-group">
                            <label for="name">@lang('Category')</label>

                            <select name="category_id" class="form-control select2">
                                <option value="">@lang('Select One')</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="name">@lang('Name')</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>

                    </div>

                    <div class="modal-footer">
                        <button type="submit" class="btn btn--primary h-45 w-100">@lang('Submit')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('breadcrumb-plugins')
    <x-search-form />
    <button type="btn" class="btn btn-sm btn-outline--primary addSubCategory"><i
            class="las la-plus"></i>@lang('Add New')</button>
@endpush

@push('script')
    <script>
        (function($) {
            "use strict";

            $(document).ready(function() {
                $('.addSubCategory').on('click', function() {
                    const modal = $('#subCategoryModal');
                    modal.find('form').attr('action',"{{ route('admin.categories.sub.categories.save') }}");
                    modal.find('.modal-title').text("@lang('Add Subcategory')");
                    modal.find('[name="name"]').val('');
                    modal.find('[name="category_id"]').val('');
                    modal.modal('show');
                });

                $('.editCategory').on('click', function() {
                    const modal = $('#subCategoryModal');
                    const action = $(this).data('action');
                    const subcategory = $(this).data('subcategory');

                    if (subcategory) {
                        modal.find('form').attr('action', action);
                        modal.find('.modal-title').text("@lang('Edit Subcategory')");
                        modal.find('[name="name"]').val(subcategory.name);
                        modal.find('[name="category_id"]').val(subcategory.category_id).change();
                    }
                    modal.modal('show');
                });
            });

        })(jQuery);
    </script>
@endpush

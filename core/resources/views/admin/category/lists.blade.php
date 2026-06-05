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
                                    <th>@lang('Image')</th>
                                    <th>@lang('Name')</th>
                                    <th>@lang('Sort Description')</th>
                                    <th>@lang('Is Trending')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Actions')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categories as $category)
                                    @php
                                        $categoryImageUrl = $category->image_url;
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="user">
                                                <img src="{{ $categoryImageUrl }}" alt="{{ __($category->name) }}" class="plugin_bg">
                                              
                                            </div>
                                        </td>
                                        <td>{{ __($category->name) }}</td>
                                        <td>
                                            @php
                                                echo strLimit( $category->sort_description, 40)
                                            @endphp
                                        </td>
                                        <td>
                                            @if($category->is_trending)
                                                <span class="badge badge--primary">@lang('Yes')</span>
                                            @else
                                                <span class="badge badge--dark">@lang('No')</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                echo $category->statusBadge;
                                            @endphp
                                        </td>
                                        <td>
                                            <div class="button--group">
                                            @php
                                                $editPayload = [
                                                    'name' => $category->name,
                                                    'sort_description' => $category->sort_description,
                                                    'is_trending' => $category->is_trending,
                                                    'image_url' => $categoryImageUrl,
                                                ];
                                            @endphp
                                                <button class="btn btn-sm btn-outline--primary editCategory" data-action="{{ route('admin.categories.save', $category->id) }}" data-category='@json($editPayload)'><i class="las la-pen"></i>@lang('Edit')</button>
                                                @if($category->status)

                                                <button class="btn btn-sm btn-outline--danger confirmationBtn"  data-action="{{route('admin.categories.status', $category->id)}}" data-question="@lang('Are you sure you want to disable this category')" ><i class="las la-eye-slash"></i>@lang('Disable')</button>
                                                @else
                                                <button class="btn btn-sm btn-outline--success confirmationBtn"  data-action="{{route('admin.categories.status', $category->id)}}"  data-question="@lang('Are you sure you want to enable this category')" ><i class="las la-eye"></i>@lang('Enable')</button>

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
                @if ($categories->hasPages())
                <div class="card-footer py-4">
                    {{ paginateLinks($categories) }}
                </div>
                @endif

            </div><!-- card end -->
        </div>
    </div>

    <x-confirmation-modal />
    
    <div class="modal fade" id="categoryModal" tabindex="-1" role="dialog" a aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">@lang('Add Category')</h4>
                    <button type="button" class="close" data-bs-dismiss="modal"><i class="las la-times"></i></button>
                </div>
                <form  method="post" action="{{ route('admin.categories.save') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="from-group">
                            <label>@lang('Image')</label>
                            <x-image-uploader :imagePath="getImage(getFilePath('category'), getFileSize('category'))" :size="getFileSize('category')" class="w-100" id="uploadImage" :required="true"  />

                        </div>
                        <div class="form-group">
                            <label for="name">@lang('Name')</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="description">@lang('Sort Description')</label>
                            <textarea class="form-control"name="description" rows="3" required></textarea>
                        </div>

                        <div class="row form-group">
                            <div class="col-md-12">
                                <label for="inputName">@lang('Mark as Trending')</label>
                                <input type="checkbox" data-width="100%" data-height="40px" data-onstyle="-success" data-offstyle="-danger" data-bs-toggle="toggle" data-on="@lang('Yes')" data-off="@lang('No')" name="is_trending">
                            </div>

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
<x-search-form/>
    <button type="btn" class="btn btn-sm btn-outline--primary addCategory"><i
            class="las la-plus"></i>@lang('Add New')</button>
@endpush

@push('script')
    <script>
        (function($) {
            "use strict";

            $('.addCategory').on('click', function(){
                $('#categoryModal').modal('show');
            });
            $('.editCategory').on('click', function(){
                const modal = $('#categoryModal');
                const action = $(this).data('action');
                const category = $(this).data('category');
                modal.find('form').attr('action', action);
                modal.find('.modal-title').text("Edit Category");
                modal.find('[name="name"]').val(category.name);
                modal.find('[name="description"]').val(category.sort_description);
                modal.find('[name="image"]').attr('required', false);
                modal.find('.image-upload-preview ').css('background-image', 'url(' + category.image_url +')');
                if (category.is_trending == 1) {
                    modal.find('input[name=is_trending]').bootstrapToggle('on');
                } else {
                    modal.find('input[name=is_trending]').bootstrapToggle('off');
                }
                modal.modal('show');
            });
            
        })(jQuery);

    </script>
@endpush

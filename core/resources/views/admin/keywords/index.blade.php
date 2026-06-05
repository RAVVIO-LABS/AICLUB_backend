@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive--sm table-responsive">
                        <table class="table table--light style--two custom-data-table">
                            <thead>
                                <tr>
                                    <th>@lang('Keyword')</th>
                                    <th>@lang('Count By Seacrh')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($keywords as $item)
                                    <tr>
                                        <td>
                                            {{$item->keyword}}
                                        </td>
                                        <td>
                                            {{$item->count}}
                                        </td>
                                       
                                        
                                        <td>
                                            <div class="button--group">
                                                <button class="btn btn-sm btn-outline--primary editKeyword" data-action="{{ route('admin.update.keyword', $item->id) }}" data-keyword="{{ $item }}"><i class="las la-pen"></i>@lang('Edit')</button>

                                                <button class="btn btn-sm btn-outline--danger confirmationBtn" data-question="@lang('Are you sure you want to delete this keyword')?"  data-action="{{ route('admin.delete.keyword',$item->id) }}"><i class="las la-trash"></i>@lang('Delete')</button>

                                            </div>
                                            
                                        
                                            
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">{{ __($emptyMessage) }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($keywords->hasPages())
                    <div class="card-footer py-4">
                        {{ paginateLinks($keywords) }}
                    </div>
                @endif
            </div>
        </div>
    </div>


    {{-- modal bootstrap.modal --}}

  
    <div class="modal fade" id="keywordModal" tabindex="-1" role="dialog" a aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">@lang('Edit Keyword')</h4>
                    <button type="button" class="close" data-bs-dismiss="modal"><i class="las la-times"></i></button>
                </div>
                <form  method="post" action="">
                    @csrf
                    <div class="modal-body">
                       
                        <div class="form-group">
                            <label for="name">@lang('Keyword')</label>
                            <input type="text" class="form-control" name="keyword" required>
                        </div>
                        <div class="form-group">
                            <label for="name">@lang('Set Searching Count')</label>
                            <input type="number" class="form-control" name="count" required>
                        </div>
                    </div>
     
                    <div class="modal-footer">
                        <button type="submit" class="btn btn--primary h-45 w-100">@lang('Submit')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <x-confirmation-modal/>



@endsection

@push('breadcrumb-plugins')
   <x-search-form/>
@endpush

@push('script')
<script>
    $('.editKeyword').on('click', function(){
        let keyword = $(this).data('keyword');
        const modal = $('#keywordModal');

        modal.find('form').attr('action', $(this).data('action'));
        modal.find('input[name="keyword"]').val(keyword.keyword);
        modal.find('input[name="count"]').val(keyword.count);
        modal.modal('show');
    })
    
</script>
    
@endpush

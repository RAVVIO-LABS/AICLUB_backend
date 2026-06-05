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

                                    <th>@lang('Title')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Actions')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($goals as $goal)
                                    <tr>

                                        <td>{{ $goal->title }}</td>

                                        <td>
                                            @php
                                                echo $goal->statusBadge;
                                            @endphp
                                        </td>
                                        <td>
                                            <div class="button--group">
                                                <button class="btn btn-sm btn-outline--primary editGoal"
                                                    data-action="{{ route('admin.goal.save', $goal->id) }}"
                                                    data-goal="{{ $goal }}"><i
                                                        class="las la-pen"></i>@lang('Edit')</button>
                                                @if ($goal->status)
                                                    <button class="btn btn-sm btn-outline--danger confirmationBtn"
                                                        data-action="{{ route('admin.goal.status', $goal->id) }}"
                                                        data-question="@lang('Are you sure you want to disable this goal')"><i
                                                            class="las la-eye-slash"></i>@lang('Disable')</button>
                                                @else
                                                    <button class="btn btn-sm btn-outline--success confirmationBtn"
                                                        data-action="{{ route('admin.goal.status', $goal->id) }}"
                                                        data-question="@lang('Are you sure you want to enable this goal')"><i
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
                @if ($goals->hasPages())
                    <div class="card-footer py-4">
                        {{ paginateLinks($goals) }}
                    </div>
                @endif
            </div><!-- card end -->
        </div>
    </div>

    <x-confirmation-modal />




    <div class="modal fade" id="goalModal" tabindex="-1" role="dialog" a aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">@lang('Add Goal')</h4>
                    <button type="button" class="close" data-bs-dismiss="modal"><i class="las la-times"></i></button>
                </div>
                <form method="post" action="{{ route('admin.goal.save') }}">
                    @csrf
                    <div class="modal-body">

                        <div class="form-group">
                            <label for="name">@lang('Goal Title')</label>
                            <input type="text" class="form-control" name="title" required>
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
    <button type="btn" class="btn btn-sm btn-outline--primary addGoal"><i
            class="las la-plus"></i>@lang('Add New')</button>
@endpush

@push('script')
    <script>
        (function($) {
            "use strict";

            $('.addGoal').on('click', function() {
                $('#goalModal').modal('show');
            });


            $('.editGoal').on('click', function() {
                const modal = $('#goalModal');
                const action = $(this).data('action');
                const goal = $(this).data('goal');
                modal.find('form').attr('action', action);
                modal.find('.modal-title').text("Edit Goal");
                modal.find('[name="title"]').val(goal.title);
                modal.modal('show');
            });

        })(jQuery);
    </script>
@endpush

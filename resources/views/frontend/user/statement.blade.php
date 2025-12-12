@extends('frontend.layouts.user_panel')

@section('panel_content')
    <!-- <div class="aiz-titlebar mt-2 mb-4">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="h3">{{ translate('My Statement') }}</h1>
            </div>
        </div>
    </div>-->
    <div class="row gutters-10">
        <div class="col-md-4 mx-auto mb-3">
            <div class="bg-grad-3 text-white rounded-lg overflow-hidden">
                <span
                    class="size-30px rounded-circle mx-auto bg-soft-primary d-flex align-items-center justify-content-center mt-3">
                    <i class="las la-rupee-sign la-2x text-white"></i>
                </span>
                <div class="px-3 pt-3 pb-3">
                    <div class="h4 fw-700 text-center" id="divOpeningBalance">{{ single_price($dueAmount) }} {{ ($dueAmount <= 0) ?'Cr':'Dr' }}</div>
                    <div class="opacity-50 text-center">{{ translate('Due Balance') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mx-auto mb-3">
            <div class="bg-grad-1 text-white rounded-lg overflow-hidden">
                <span
                    class="size-30px rounded-circle mx-auto bg-soft-primary d-flex align-items-center justify-content-center mt-3">
                    <i class="las la-rupee-sign la-2x text-white"></i>
                </span>
                <div class="px-3 pt-3 pb-3">
                    <div class="h4 fw-700 text-center" id="divClosingBalance">{{ ($overdueAmount > 0) ?single_price($overdueAmount):'0' }} {{ ($overdueAmount <= 0) ?'Cr':'Dr' }}</div>
                    <div class="opacity-50 text-center">{{ translate('Overdue Balance') }}</div>
                </div>
            </div>
        </div>        
    </div>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0 h6">{{ translate('Accounts') }}</h5>
        </div>
        @if ($userAddressData)
            <div class="card-body">
                <table class="table aiz-table mb-0">
                    <thead>
                        <tr>
                            <th>{{ translate('Name')}}</th>
                            <th data-breakpoints="md">{{ translate('Party Name')}}</th>
                            <th data-breakpoints="md">{{ translate('Party Code')}}</th>
                            <th data-breakpoints="md">{{ translate('Ledger Code')}}</th>
                            <th data-breakpoints="md">{{ translate('GST No')}}</th>
                            <th class="text-right">{{ translate('Options')}}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if($userAddressData)
                            @foreach($userAddressData as $key=>$value)
                                <tr>
                                    <td><a href="#">{{$userData->name}}</a></td>
                                    <td>{{$value->company_name}}</td>
                                    <td>{{$value->acc_code}}</td>
                                    <td>{{str_replace(' ','_',$userData->name).$userData->party_code}}</td>
                                    <td>{{$value->gstin}}</td>
                                    <td class="text-right">
                                        <a href="{{route('statementDetails', encrypt($value->acc_code))}}" class="btn btn-soft-info btn-icon btn-circle btn-sm" title="{{ translate('View Statement Details') }}">
                                            <i class="las la-eye"></i>
                                        </a>
                                        <!-- <a class="btn btn-soft-warning btn-icon btn-circle btn-sm" href="#" title="{{ translate('Download Statement') }}">
                                            <i class="las la-download"></i>
                                        </a> -->
                                    </td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td col_span=6>No Record Found.</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
                <?php /*<div class="aiz-pagination">
                    {{ $orders->links() }}
              	</div>*/ ?>
            </div>
        @endif
    </div>
@endsection

@section('modal')
    @include('modals.cancel_modal')

    <div class="modal fade" id="order_details" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
            <div class="modal-content">
                <div id="order-details-modal-body">

                </div>
            </div>
        </div>
    </div>

@endsection

@section('script')
    <script type="text/javascript">
        $('#order_details').on('hidden.bs.modal', function () {
            location.reload();
        })
    </script>

@endsection

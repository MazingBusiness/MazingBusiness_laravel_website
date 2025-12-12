@extends('frontend.layouts.user_panel')
@php
    $creditDays = isset($creditDays) ? (int)$creditDays : (int) (optional(auth()->user())->credit_days ?? 0);
@endphp
@section('panel_content')
    <style>
        .ajax-loader {
            visibility: hidden;
            background-color: rgba(255, 255, 255, 0.7);
            position: fixed;
            z-index: 100;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            text-align: center;
        }

        .ajax-loader img {
            width: 150px; /* Adjust size as needed */
            margin-bottom: 20px;
        }

        .ajax-loader p {
        color: #074e86;
        font-family: Arial, sans-serif;
        font-size: 21px;
        font-weight: bold;
        }
        
    </style>
    <div id="searchAjaxLoader" class="ajax-loader">
        <img src="{{ url('https://mazingbusiness.com/public/assets/img/ajax-loader.gif') }}" class="img-responsive" />
        <p>Search is processing. Please wait for some time ....</p>
    </div>
    <div id="processing" class="ajax-loader">
        <img src="{{ url('https://mazingbusiness.com/public/assets/img/ajax-loader.gif') }}" class="img-responsive" />
        <p> Please wait for some time ....</p>
    </div>


    <div class="aiz-titlebar mt-2 mb-4">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="h3">{{ translate('My Statement') }}</h1>
            </div>
        </div>
    </div>
    <div class="row gutters-10">
        <div class="col-md-4 mx-auto mb-3">
            <div class="bg-grad-3 text-white rounded-lg overflow-hidden">
                <span
                    class="size-30px rounded-circle mx-auto bg-soft-primary d-flex align-items-center justify-content-center mt-3">
                    <i class="las la-rupee-sign la-2x text-white"></i>
                </span>
                <div class="px-3 pt-3 pb-3">
                    <div class="h4 fw-700 text-center" id="divOpeningBalance">{{ single_price((float)$dueAmount) }} {{$closeDrOrCr}}</div>
                    <div class="opacity-50 text-center">{{ translate('Due Balance') }}</div>
                </div>
               <!-- For Due Amount Pay Now Button -->
                @if($dueAmount > 0 && $duePaymentUrl)
                    <div class="text-center mb-2">
                        <button type="button" class="btn btn-primary pay-now-button" 
                                data-party-code="{{ $party_code }}" 
                                data-payment-url="{{ $duePaymentUrl }}" 
                                data-payment-amount="{{ $dueAmount }}"
                                data-payment-for="due_amount"> <!-- Set payment_for value as 'due_amount' -->
                            {{ translate('Pay Now') }}
                        </button>
                    </div>
                @endif
            </div>
        </div>
        <div class="col-md-4 mx-auto mb-3">
            <div class="bg-grad-1 text-white rounded-lg overflow-hidden">
                <span
                    class="size-30px rounded-circle mx-auto bg-soft-primary d-flex align-items-center justify-content-center mt-3">
                    <i class="las la-rupee-sign la-2x text-white"></i>
                </span>
                <div class="px-3 pt-3 pb-3">
                    <div class="h4 fw-700 text-center" id="divClosingBalance">{{ single_price((float)$overdueAmount)}} {{$overdueDrOrCr}}</div>
                    <div class="opacity-50 text-center">{{ translate('Overdue Balance') }}</div>
                </div>
                <!-- For Overdue Amount Pay Now Button -->
                @if($overdueAmount > 0 && $overduePaymentUrl)
                    <div class="text-center mb-2">
                        <button type="button" class="btn btn-primary pay-now-button" 
                                data-party-code="{{ $party_code }}" 
                                data-payment-url="{{ $overduePaymentUrl }}"  
                                data-payment-amount="{{ $overdueAmount }}"
                                data-payment-for="overdue_amount"> <!-- Set payment_for value as 'overdue_amount' -->
                            {{ translate('Pay Now') }}
                        </button>
                    </div>
                @endif
            </div>
        </div>        
    </div>
    <div class="card">
       <div class="card-header">
            <div class="d-flex flex-wrap align-items-center justify-content-between" style="width:100%">
                <input type="hidden" name="party_code" id="party_code" value="{{ encrypt($party_code) ?? '' }}">
                
                <div>From Date: 
                    <input type="date" name="from_date" id="from_date" 
                      value="{{ now()->month >= 4 ? now()->subYear()->format('Y') . '-04-01' : now()->subYears(2)->format('Y') . '-04-01' }}">
                </div>
                
                <div>To Date: 
                    <input type="date" name="to_date" id="to_date" value="{{ date('Y-m-d') }}">
                </div>
                
                <button type="button" class="btn btn-info" id="btnSearch">Search</button>
                
                <button type="button" class="btn btn-success" id="btnFreshStatement" style="white-space: nowrap; padding: 10px 20px;">
                    Refresh
                </button>
                
                <button type="button" class="btn btn-primary" id="btnWhatsapp" style="white-space: nowrap; padding: 10px 20px; background:#25D366; border-color:#25D366;">
                    Whatsapp
                </button>
                
                <button type="button" class="btn btn-primary" id="dnStatement" style="white-space: nowrap; padding: 10px 20px;">
                    Download
                </button>
                
                @if($customPaymentUrl)
                    <button type="button" class="btn btn-primary pay-now-button" 
                            style="white-space: nowrap; padding: 10px 20px;" 
                            data-party-code="{{ $party_code }}" 
                            data-payment-url="{{ $customPaymentUrl }}"  
                            data-payment-for="custom_amount">
                        {{ translate('Pay Custom Amount') }}
                    </button>
                @endif
            </div>
        </div>

        
        <div class="card-body">
            <table class="table mb-0" id="resultsTable">
                <thead>
                    <tr>
                        <th>{{ translate('Date')}}</th>
                        <th data-breakpoints="md" style="width:24%">{{ translate('Particulers')}}</th>
                        <th data-breakpoints="md">{{ translate('Txn No')}}</th>
                        <th data-breakpoints="md">{{ translate('Debit')}}</th>
                        <th data-breakpoints="md">{{ translate('Credit')}}</th>
                        <th data-breakpoints="md">{{ translate('Balance')}}</th>
                        <th class="text-right">{{ translate('Dr / Cr')}}</th>
                        <th class="text-right">{{ translate('Overdue By Day')}}</th>
                    </tr>
                </thead>
                <tbody>
                    @if (count($getData) > 0)
                        @php
                            $balance =0 ;
                            $drBalance = 0;
                            $crBalance = 0;
                            $clossingDrBalance="";
                            $clossingCrBalance="";
                        @endphp
                        @foreach($getData as $gKey=>$gValue)
                            @if($gValue['ledgername'] != 'closing C/f...')
                            <tr style="{{ isset($gValue['overdue_status']) && $gValue['overdue_status'] == 'Overdue' ? 'background-color: #ff00006b;' : (isset($gValue['overdue_status']) && $gValue['overdue_status'] == 'Partial Overdue' ? 'background-color: #ff00002b;' : '') }}">
                                    <td><a href="#">{{ date('d-m-Y', strtotime($gValue['trn_date'])) }}</a></td>
                                    <td>
                                        {{ strtoupper($gValue['vouchertypebasename']) }}
                                        @if(trim($gValue['narration']) != "")
                                            <p><small>({{$gValue['narration']}})</small></p>
                                        @endif
                                        {!! isset($gValue['overdue_status']) ? '<p><small>('.$gValue['overdue_status'].')</small></p>' : '' !!}
                                    </td>
                                     <td> 
                                        @if($gValue['trn_no'] != "")
                                            <a  target="_blank"  href="{{ route('generate.invoice', ['invoice_no' => encrypt($gValue['trn_no'])]) }}" 
                                               style="text-decoration: none; color:#074e86;">
                                                {{ $gValue['trn_no'] }}
                                            </a>
                                        @else
                                            <span></span> {{-- Optionally, display nothing or a placeholder here --}}
                                        @endif
                                    </td>
                                    <td><span style="color:#ff0707;">{{ $gValue['dramount'] != 0.00 ? single_price((float)$gValue['dramount']) : '' }}</spn></td>
                                    <td><span style="color:#8bc34a;">{{ $gValue['cramount'] != 0.00 ? single_price((float)$gValue['cramount']) : '' }}</span></td>
                                    <td>
                                    @if($gValue['ledgername'] == 'Opening b/f...')
                                        @php
                                            $balance = $gValue['dramount'] != 0.00 ? $gValue['dramount'] : -$gValue['cramount'];
                                        @endphp
                                    @else
                                        @php
                                            $balance += (float)$gValue['dramount'] - (float)$gValue['cramount'];
                                        @endphp
                                    @endif
                                    {{ single_price((float)trim($balance,'-'))}}
                                    </td>
                                    <td class="text-center">
                                    @php
                                        if ($gValue['dramount'] != 0.00) {
                                            $drBalance =$drBalance + $gValue['dramount'];
                                        } 
                                        if($gValue['cramount'] != 0.00) {
                                            $crBalance = $crBalance + $gValue['cramount'];
                                        }
                                    @endphp

                                    {!! $drBalance > $crBalance ? '<span style="color:#ff0707;">Dr</span>' : '<span style="color:#8bc34a;">Cr</span>' !!}

                                    </td>
                                    <td class="text-center">
                                        @php
                                            $rawDays   = $gValue['overdue_by_day'] ?? null;
                                            $baseDays  = null;

                                            if ($rawDays !== null && $rawDays !== '') {
                                                if (is_numeric($rawDays)) {
                                                    $baseDays = (int)$rawDays;
                                                } else {
                                                    // "134 days", "134 day", "134" — pehle integer ko pakdo
                                                    preg_match('/-?\d+/', (string)$rawDays, $m);
                                                    if (isset($m[0])) {
                                                        $baseDays = (int)$m[0];
                                                    }
                                                }
                                            }

                                            $displayDays = $baseDays !== null ? ($baseDays + (int)$creditDays) . ' days' : '';
                                        @endphp
                                        {{ $displayDays }}
                                    </td>

                                </tr>
                            @else
                                @php
                                    $clossingDrBalance = (float)$gValue['dramount'];
                                    $clossingCrBalance = (float)$gValue['cramount'];
                                @endphp     
                            @endif
                        @endforeach
                        <tr>
                            <td></td>
                            <td></td>
                            <td><strong>Total</strong></td>
                            <td>{{ single_price((float)$drBalance) }}</td>
                            <td>{{ single_price((float)$crBalance) }}</td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td></td>
                            <td></td>
                            <td><strong>Clossing Balance</strong></td>                            
                            <td>{{ single_price($clossingCrBalance) }}</td>
                            <td>{{ single_price($clossingDrBalance) }}</td>
                            <?php /* <td>{{ ($clossingCrBalance != 0.00) ? single_price($clossingCrBalance) : "" }}</td>
                            <td>{{ ($clossingDrBalance != 0.00) ? single_price($clossingDrBalance) : "" }}</td> */ ?>
                            
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td></td>
                            <td></td>
                            <td><strong>Grand Total</strong></td>
                            <td>{{ single_price((float)($drBalance + $clossingCrBalance)) }}</td>
                            <td>{{ single_price((float)($crBalance + $clossingDrBalance)) }}</td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                    @else
                        <tr>
                            <td colspan='8'>No Transaction Found</td>
                        </tr>
                    @endif
                </tbody>
            </table>
            <?php /* ?><div class="aiz-pagination">
                {{ $orders->links() }}
            </div> <?php */ ?>
        </div>        
    </div>
    <script>
        $(document).ready(function() {
            $('#btnSearch').click(function() {
                var party_code = $('#party_code').val();
                var from_date = $('#from_date').val();
                var to_date = $('#to_date').val();
                
                $.ajax({
                    url: '{{ route("searchStatementDetails") }}',  // Your API URL
                    method: 'POST',
                    beforeSend: function(){
                        $('#searchAjaxLoader').css("visibility", "visible");
                    },
                    data: {
                        _token: '{{ csrf_token() }}',
                        party_code: party_code,
                        from_date: from_date,
                        to_date: to_date
                    },
                    success: function(response) {
                        // Clear previous data from the table
                        $('#resultsTable tbody').empty();                        
                        console.log(response);
                        $('#resultsTable tbody').append(response.html);
                        // $('#divOpeningBalance').html('₹' +response.openingBalance);
                        // $('#divClosingBalance').html('₹' +response.closingBalance);

                        // Re-initialize AIZ table to make sure it renders properly
                        AIZ.plugins.bootstrapSelect('refresh');
                    },
                    complete: function(){
                    $('#searchAjaxLoader').css("visibility", "hidden");
                },
                    error: function() {
                        $('#resultsTable tbody').append('<tr><td colspan="6">Error fetching data.</td></tr>');
                    }
                });
            });

            $('#btnFreshStatement').click(function() {
                var party_code = $('#party_code').val();
                $.ajax({
                    url: '{{ route("refreshStatementDetails") }}',  // Your API URL
                    method: 'POST',
                    beforeSend: function(){
                        $('#searchAjaxLoader').css("visibility", "visible");
                    },
                    data: {
                        _token: '{{ csrf_token() }}',
                        party_code: party_code
                    },
                    success: function(response) {
                        // Clear previous data from the table
                        $('#resultsTable tbody').empty();                        
                        console.log(response);
                        $('#resultsTable tbody').append(response.html);
                        $('#divOpeningBalance').empty();
                        $('#divOpeningBalance').append(response.due_amount);
                        $('#divClosingBalance').empty();
                        $('#divClosingBalance').append(response.overdue_amount);
                        // $('#divOpeningBalance').html('₹' +response.openingBalance);
                        // $('#divClosingBalance').html('₹' +response.closingBalance);

                        // Re-initialize AIZ table to make sure it renders properly
                        AIZ.plugins.bootstrapSelect('refresh');
                    },
                    complete: function(){
                    $('#searchAjaxLoader').css("visibility", "hidden");
                },
                    error: function() {
                        $('#resultsTable tbody').append('<tr><td colspan="6">Error fetching data.</td></tr>');
                    }
                });
            });
            // WhatsApp Button functionality
            $('#btnWhatsapp').click(function() {
                var party_code = $('#party_code').val();
                var from_date = $('#from_date').val();
                var to_date = $('#to_date').val(); 
                $('#processing').css("visibility", "visible");

                $.ajax({
                    url: '{{ route("downloadStatementPdf") }}',  // Route to PDF generation method
                    method: 'POST',
                   
                    data: {
                        _token: '{{ csrf_token() }}',
                        party_code: party_code,
                        from_date: from_date,
                        to_date: to_date
                    },
                    success: function(response, status, xhr) {
                        $('#processing').css("visibility", "hidden");

                       // Show success message immediately
                        AIZ.plugins.notify('success', response.message);

                        // Introduce a short delay before prompting for download
                        // setTimeout(function() {
                            
                        //     if (confirm('Do you want to download the statement PDF now?')) {
                               
                        //         var link = document.createElement('a');
                        //         link.href = response.url; 
                        //         link.download = '';
                        //         document.body.appendChild(link);
                        //         link.click();
                        //         document.body.removeChild(link);
                        //     } else {
                               
                        //         AIZ.plugins.notify('info', 'Download cancelled.');
                        //     }
                        // }, 200);  
                    },
                    error: function(response, status, xhr) {
                        $('#processing').css("visibility", "hidden");
                        AIZ.plugins.notify('warning', response.error);
                    }
                });
            });

             $('.pay-now-button').on('click', function() {

                
                // Retrieve the data attributes from the button clicked
                var partyCode = $(this).data('party-code');
                var paymentUrl = $(this).data('payment-url');  // This will dynamically change based on the button clicked
               
                var paymentAmount = $(this).data('payment-amount');
                var paymentFor = $(this).data('payment-for'); // Get payment type (due_amount, custom_amount, overdue_amount)

                // Get the named route from Blade and pass it into the AJAX call
                var url = '{{ route("send.paynow.link") }}';  // Use the named route here
                $('#processing').css("visibility", "visible");
                // Send the AJAX request
                $.ajax({
                    url: url,  // The route that handles the request
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',  // Include CSRF token for security
                        party_code: partyCode,
                        payment_url: paymentUrl,  // Pass the dynamic payment URL
                        payment_amount: paymentAmount,
                        payment_for: paymentFor // Pass the payment_for field
                    },
                    success: function(response) {

                        $('#processing').css("visibility", "hidden");
                        if (response.success) {
                            //alert(response.message);
							  // Redirect to the payment URL in a new tab
                				window.open(paymentUrl, '_blank');
                        } else {
                            alert('Failed to send Pay Now link: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        // Log the error details for debugging
                        console.error('Status:', status);
                        console.error('Error:', error);
                        console.error('Response:', xhr.responseText);

                        // Show a detailed error message to the user
                        alert('An error occurred while sending the Pay Now link:\n' + xhr.responseText);
                    }
                });
            });


             // WhatsApp Button functionality
            $('#dnStatement').click(function() {
                var party_code = $('#party_code').val();
                var from_date = $('#from_date').val();
                var to_date = $('#to_date').val(); 
                $('#processing').css("visibility", "visible");
               
                $.ajax({
                    url: '{{ route("downloadStatementOnly") }}',  // Route to PDF generation method
                    method: 'POST',
                   
                    data: {
                        _token: '{{ csrf_token() }}',
                        party_code: party_code,
                        from_date: from_date,
                        to_date: to_date
                    },
                    success: function(response, status, xhr) {
                        $('#processing').css("visibility", "hidden");
                         var link = document.createElement('a');
                         link.href = response.url; // Assuming 'url' contains the link to the PDF
                         link.download = ''; // Optional: If you want to specify the file name, you can set it here
                         document.body.appendChild(link);
                         link.click();
                         document.body.removeChild(link);
                    },
                    error: function(response, status, xhr) {
                        $('#processing').css("visibility", "hidden");
                        AIZ.plugins.notify('warning', response.error);
                    }
                });
            });

        });


    </script>
@endsection
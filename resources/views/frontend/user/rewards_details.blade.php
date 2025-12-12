@extends('frontend.layouts.user_panel')
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
        
        /* NEW: subtle pill tag without background fill */
        .tag {
          display: inline-block;         /* was inline-inline */
          padding: 2px 8px;
          border: 1px solid #1e7e34;     /* darker green border */
          border-radius: 0;              /* sharp corners */
          font-size: 12px;
          line-height: 1.2;
          margin-left: 6px;
          color: #fff;                   /* white text for contrast */
          background: #28a745;           /* green background */
          font-weight: 600;
        }
        
        /* Center the tag perfectly, keep DR/CR on the left */
        .drcr-cell {
          display: grid;
          grid-template-columns: 1fr auto 1fr; /* left spacer | tag | right spacer */
          align-items: center;
        }
        
        /* Make sure tag doesn’t inherit the old margin-left */
        .drcr-cell .tag {
          margin-left: 0;
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
                <h1 class="h3">{{ translate('My Rewards') }}</h1>
            </div>
        </div>
    </div>
    <div class="card">
        <?php /* <div class="card-header">
            <div class="row" style="width:100%">
                <div class="col-md-2"><h5 class="mb-0 h6">{{ translate('Statement') }}</h5></div>
                <input type="hidden" name="party_code" id="party_code" value="{{ encrypt($party_code) ?? '' }}">
                <div class="col-md-2.5">From Date: <input type="date" name="from_date" id="from_date" value="{{ date('Y-04-01'); }}"></div>
                <div class="col-md-2.5">To Date:<input type="date" name="to_date" id="to_date" value="{{ date('Y-m-d') }}"></div>
                <div class="col-md-1"><button type="button" class="btn btn-info" id="btnSearch">Search</button></div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-success" id="btnFreshStatement" style="white-space: nowrap; width: auto; padding: 10px 20px;">
                      Refresh Statement</button>
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-primary" id="btnWhatsapp" style="white-space: nowrap; width: auto; padding: 10px 20px; background:#25D366; border-color:#25D366;">Whatsapp</button>
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-primary" id="dnStatement" style="white-space: nowrap; width: auto; padding: 10px 20px;position:relative;left:14px;" >
                       Download</button>
                </div>
            </div>
        </div> */ ?>    
        <div class="card-header">
            <div class="row" style="width:100%">
                    <div class="col-md-2">
                            <button type="button" class="btn btn-primary" onclick="window.location.href='{{ route('rewards.download') }}'" id="dnRewards" style="white-space: nowrap; width: auto; padding: 10px 20px;position:relative;left:14px;" >
                               Download</button>
                    </div>
                    <div class="col-md-2">
                         <input type="hidden" id="party_code" value="{{ Auth::user()->party_code }}">
                        <button type="button" class="btn btn-primary" id="btnRewardWhatsapp" style="white-space: nowrap; width: auto; padding: 10px 20px; background:#25D366; border-color:#25D366;">Whatsapp</button>
                    </div>
            </div>
        </div>

        <div class="card-body">
            <table class="table mb-0" id="resultsTable">
                <thead>
                    <tr>
                        <th data-breakpoints="md">{{ translate('Txn No')}}</th>
                        <th data-breakpoints="md">{{ translate('Rewards From')}}</th>
                        <th data-breakpoints="md">{{ translate('Debit')}}</th>
                        <th data-breakpoints="md">{{ translate('Credit')}}</th>
                        <th data-breakpoints="md">{{ translate('Balance')}}</th>
                        <th data-breakpoints="md">{{ translate('Dr / Cr')}}</th>
                        <!-- <th class="text-right">{{ translate('Overdue By Day')}}</th> -->
                    </tr>
                </thead>
                <tbody>
                    @if (count($getData) > 0)
                        @php
                            $grand_total = 0;
                            $dr_total = 0;
                            $cr_total = 0;
                        @endphp
                        @foreach($getData as $gKey=>$gValue)
                            @php
                             $gValue['dr_or_cr'] == 'dr' ? $grand_total += $gValue['rewards'] : $grand_total = $grand_total - $gValue['rewards'];
                             $gValue['dr_or_cr'] == 'dr' ? $dr_total += $gValue['rewards'] : '';
                             $gValue['dr_or_cr'] == 'cr' ? $cr_total += $gValue['rewards'] :'';
                            @endphp
                            <!--<tr style="{{ isset($gValue['dr_or_cr']) && $gValue['dr_or_cr'] == 'cr' ? 'background-color: #ff00006b;' : (isset($gValue['reward_complete_status']) && $gValue['reward_complete_status'] == '1' ? 'background-color: #27ff006b;' : (isset($gValue['reward_complete_status']) && $gValue['reward_complete_status'] == '2' ? 'background-color: #ff56006b;' : '' ) ) }}">-->
                            <tr>
                                <td>{{ strtoupper($gValue['invoice_no']) }}</td>
                                <td>{{ strtoupper($gValue['rewards_from']) }}</td>
                                <td>{{ $gValue['dr_or_cr'] == 'dr' ? single_price($gValue['rewards']) : ''; }}</td>
                                <td>{{ $gValue['dr_or_cr'] == 'cr' ? single_price($gValue['rewards']) : ''; }}</td>
                                <td>{{ single_price($grand_total) }}</td>
                                <td class="drcr-cell">
                                  <span class="drcr-text">{{ strtoupper($gValue['dr_or_cr']) }}</span>
                                  @if(isset($gValue['reward_complete_status']) && $gValue['reward_complete_status'] == '1')
                                      @if(isset($gValue['dr_or_cr']) && $gValue['dr_or_cr'] == 'dr')
                                          <span class="tag">Claimed</span>
                                      @elseif(isset($gValue['dr_or_cr']) && $gValue['dr_or_cr'] == 'cr')
                                          <span class="tag">Credit Note Issued</span>
                                      @endif
                                  @endif
                                </td>
                            </tr>
                        @endforeach
                        <tr>
                            <td></td>
                            <td><strong>Total</strong></td>
                            <td>{{ single_price($dr_total) }}</td>
                            <td>{{ single_price($cr_total) }}</td>
                            <td></td>
                            <td></td>                            
                        </tr>
                        <tr>
                            <td></td>
                            <td><strong>Clossing Balance</strong></td>
                            <td>{{ single_price($grand_total) }}</td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                    @else
                        <tr>
                            <td colspan='3'>No Transaction Found</td>
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


            $('#btnRewardWhatsapp').click(function () {
                var party_code = $('#party_code').val(); // Fetch party_code from the hidden input

                // Show the processing spinner or loader
                $('#processing').css("visibility", "visible");

                // Make an AJAX GET request to send the WhatsApp message
                $.ajax({
                    url: '{{ route("sendRewardWhatsapp") }}', // Route to send Reward WhatsApp
                    method: 'GET',
                    data: {
                        party_code: party_code,
                    },
                    success: function (response) {
                        $('#processing').css("visibility", "hidden");

                        if (response.url) {
                            // Open the generated reward statement URL in a new tab
                          

                            // Notify success message
                            AIZ.plugins.notify('success', 'Reward statement sent successfully via WhatsApp.');
                        } else if (response.message) {
                            // Notify success message (fallback if URL not provided)
                            AIZ.plugins.notify('success', response.message);
                        } else {
                            // Notify unknown success state
                            AIZ.plugins.notify('warning', 'Reward statement sent but no URL returned.');
                        }
                    },
                    error: function (response) {
                        $('#processing').css("visibility", "hidden");

                        // Handle error response
                        if (response.responseJSON && response.responseJSON.error) {
                            AIZ.plugins.notify('warning', response.responseJSON.error);
                        } else {
                            AIZ.plugins.notify('warning', 'Failed to send reward statement via WhatsApp.');
                        }
                    },
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
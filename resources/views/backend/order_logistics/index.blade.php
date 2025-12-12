@extends('backend.layouts.app')

@section('content')
<div class="aiz-titlebar text-left mt-3 mb-4">
    <h1 class="h3 text-primary font-weight-bold">{{ translate('Order Logistics') }}</h1>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ translate('Search By') }}</h5>
        <!-- Search Form -->
        <form method="GET" action="{{ route('order.logistics') }}" class="d-flex" style="width: 70%;">
            <div class="input-group">
                <!-- <input type="text" name="search" class="form-control form-control-sm" 
                       placeholder="{{ translate('Invoice No , LR Date (YYYY-MM-DD), Place of dispatch') }}" 
                       value="{{ old('search', $search ?? '') }}"> -->
                       <input type="text" name="search" class="form-control form-control-sm" 
                           placeholder="{{ translate('Invoice No, LR Date (YYYY-MM-DD), Place of Dispatch, or company name') }}" 
                           value="{{ old('search', $search ?? '') }}">
                <div class="input-group-append">
                    <button type="submit" class="btn btn-light btn-sm">
                        <i class="las la-search"></i> {{ translate('Search') }}
                    </button>
                    @if($search)
                        <a href="{{ route('order.logistics') }}" class="btn btn-light btn-sm ml-2" title="{{ translate('Clear Search') }}">
                            <i class="las la-times"></i>
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        @if($logisticsData->count())
        <!-- Table -->
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead class="bg-secondary text-white">
                    <tr>
                        <th>#</th>
                        <th>
                            <a href="{{ route('order.logistics', ['sort' => 'invoice_no', 'direction' => $sortField === 'invoice_no' && $sortDirection === 'asc' ? 'desc' : 'asc', 'search' => $search]) }}">
                                {{ translate('Invoice No') }}
                                @if ($sortField === 'invoice_no')
                                    <i class="las la-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }}"></i>
                                @endif
                            </a>
                        </th>
                       <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'invoice_date', 'direction' => request('direction') === 'asc' ? 'desc' : 'asc']) }}">
                                {{ translate('Invoice Date') }}
                                @if (request('sort') === 'invoice_date')
                                    <i class="las la-sort-{{ request('direction') === 'asc' ? 'up' : 'down' }}"></i>
                                @endif
                            </a>
                        </th>
                        <th>Order Date</th>

                        <th>
                            <a href="{{ route('order.logistics', ['sort' => 'lr_date', 'direction' => $sortField === 'lr_date' && $sortDirection === 'asc' ? 'desc' : 'asc', 'search' => $search]) }}">
                                {{ translate('LR Date') }}
                                @if ($sortField === 'lr_date')
                                    <i class="las la-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }}"></i>
                                @endif
                            </a>
                        </th>
                        <th>{{ translate('Place of Dispatch') }}</th> <!-- New Column -->
                        <th>{{ translate('Transport Name') }}</th>
                        <th>{{ translate('LR Number') }}</th>
                        <th>{{ translate('No. of Boxes') }}</th>
                        <th>{{ translate('LR Amount') }}</th>
                        <th>
                            <a href="{{ route('order.logistics', ['sort' => 'company_name', 'direction' => $sortField === 'company_name' && $sortDirection === 'asc' ? 'desc' : 'asc', 'search' => $search]) }}">
                                {{ translate('Company Name') }}
                                @if ($sortField === 'company_name')
                                    <i class="las la-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }}"></i>
                                @endif
                            </a>
                        </th>
                        <th>{{ translate('Attachments') }}</th>
                         <th>{{ translate('Actions') }}</th> <!-- New Actions Column -->
                    </tr>
                </thead>
                <tbody>
                    @foreach($logisticsData as $key => $logistic)
                    <tr class="{{ (int)$logistic->zoho_attachment_upload === 0 ? 'table-danger' : '' }}">
                        <td>{{ $logisticsData->firstItem() + $key }}</td>
                         <td class="{{ $logistic->invoice_date ? 'text-primary font-weight-bold' : 'text-secondary font-weight-bold' }}">
                            @if ($logistic->invoice_date)
                                <!-- Link when invoice_date is present -->
                                <a href="{{ route('generate.invoice', ['invoice_no' => encrypt($logistic->invoice_no)]) }}" 
                                   class="text-primary" 
                                   target="_blank" 
                                   rel="noopener noreferrer">
                                    {{ $logistic->invoice_no }}
                                </a>
                            @else
                                <!-- Plain text when invoice_date is blank -->
                                {{ $logistic->invoice_no }}
                            @endif
                        </td>
                        <td>{{ $logistic->invoice_date ?? 'N/A' }}</td> <!-- Invoice Date -->
                        <td>{{ $logistic->order_date ?? 'N/A' }}</td> <!-- Invoice Date -->
                        <td>{{ $logistic->lr_date ?? 'N/A' }}</td>
                        <td>{{ $logistic->place_of_dispatch ?? 'N/A' }}</td> <!-- Place of Dispatch -->
                        <td>{{ $logistic->transport_name ?? '-' }}</td>
                        <td>{{ $logistic->lr_no ?? '-' }}</td>
                        <td>{{ $logistic->no_of_boxes ?? 0 }}</td>
                        <td>{{ is_numeric($logistic->lr_amount) ? number_format($logistic->lr_amount, 2) : '0.00' }}</td>
                        <td>{{ $logistic->company_name ?? '-' }}</td>

                        <td style="text-align: center; vertical-align: middle; padding: 8px;">
                            @php
                                // Split the comma-separated URLs and get the first file
                                $attachments = explode(',', $logistic->attachment ?? '');
                                $firstFile = $attachments[0] ?? null;

                                // Fallback image link
                                $defaultImage = 'https://mazingbusiness.com/public/uploads/cw_acetools/default_image.jpg';

                                // Determine file type
                                $isPdf = $firstFile && preg_match('/\.pdf$/i', $firstFile);
                                $isHtml = $firstFile && preg_match('/\.html?$/i', $firstFile);

                                // Set the image, PDF, or HTML thumbnail and title
                                if ($isPdf) {
                                    $imageToShow = $defaultImage;
                                    $fileTitle = 'View PDF';
                                } elseif ($isHtml) {
                                    $imageToShow = $defaultImage;
                                    $fileTitle = 'View HTML';
                                } else {
                                    $imageToShow = $firstFile ?: $defaultImage;
                                    $fileTitle = 'View Image';
                                }
                            @endphp

                        <div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">
                            @if ($firstFile && $isPdf)
                                <!-- PDF icon -->
                                <a title="{{ $fileTitle }}" href="{{ $firstFile }}" target="_blank" rel="noopener noreferrer" style="text-decoration: none;">
                                    <div style="background-color: #f8d7da; color: #d9534f; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: 8px; border: 1px solid #d9534f;">
                                        <i class="las la-file-pdf" style="font-size: 24px;"></i>
                                    </div>
                                </a>
                                <span style="font-size: 12px; color: #333; font-weight: 600;">PDF File</span>
                            @elseif ($firstFile && $isHtml)
                                <!-- HTML file icon -->
                                <a title="{{ $fileTitle }}" href="{{ $firstFile }}" target="_blank" rel="noopener noreferrer" style="text-decoration: none;">
                                    <div style="background-color: #d4edda; color: #155724; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: 8px; border: 1px solid #155724;">
                                        <i class="las la-file-code" style="font-size: 24px;"></i>
                                    </div>
                                </a>
                                <span style="font-size: 12px; color: #333; font-weight: 600;">HTML File</span>
                            @elseif ($firstFile)
                                <!-- Image thumbnail -->
                                <a title="{{ $fileTitle }}" href="{{ $imageToShow }}" target="_blank" rel="noopener noreferrer">
                                    <img src="{{ $imageToShow }}" alt="Attachment" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd;">
                                </a>
                                <span style="font-size: 12px; color: #333; font-weight: 600;">Image</span>
                            @else
                                <!-- Unavailable -->
                                <div style="background-color: #f8f9fa; color: #6c757d; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: 8px; border: 1px solid #ccc;">
                                    <i class="las la-ban" style="font-size: 24px;"></i>
                                </div>
                                <span style="font-size: 12px; color: #6c757d; font-weight: 600;">Unavailable</span>
                            @endif
                        </div>
                    </td>


                         <!-- Action Buttons -->
                       <td style="white-space: nowrap;">
                            <!-- Edit Button -->
                            <a href="{{ route('order.logistics.edit', ['invoice_no' => encrypt($logistic->invoice_no)]) }}" 
                               class="btn btn-soft-warning btn-icon btn-circle btn-sm mx-1" 
                               title="Edit">
                                <i class="las la-edit"></i>
                            </a>

                            <!-- ✅ Zoho Attachment Push Button -->
                            @if(!empty($logistic->attachment) && (int)($logistic->zoho_attachment_upload ?? 0) === 0)
                                <a href="{{ route('order.logistics.push-zoho', ['invoice_no' => encrypt($logistic->invoice_no)]) }}"
                                   class="btn btn-soft-primary btn-icon btn-circle btn-sm mx-1"
                                   title="Push attachment to Zoho">
                                    <i class="las la-cloud-upload-alt"></i>
                                </a>
                            @endif

                            <!-- WhatsApp Button -->
                            <button 
                                data-url="{{ route('logistics.send-whatsapp', ['invoice_no' => encrypt($logistic->invoice_no),'id'=>$logistic->id]) }}" 
                                class="btn btn-sm btn-soft-success btn-circle mx-1 send-logistic-wahtsapp-pdf" 
                                title="{{ translate('Send') }}">
                                <i class="lab la-whatsapp"></i>
                            </button>

                       </td>


                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="card-footer bg-light d-flex justify-content-between">
            <span>
                {{ translate('Showing') }} {{ $logisticsData->firstItem() }} 
                {{ translate('to') }} {{ $logisticsData->lastItem() }} 
                {{ translate('of') }} {{ $logisticsData->total() }} {{ translate('entries') }}
            </span>
            {{ $logisticsData->links() }}
        </div>
        @else
        <p class="text-center text-muted py-4">{{ translate('No logistics data found.') }}</p>
        @endif
    </div>
</div>
@endsection


@section('script')
<script>
  $(document).ready(function () {

       // Edit functionality
        $('.edit-btn').on('click', function () {
            alert("test");

            const row = $(this).closest('tr');
           
        });

         $(document).on('click', '.send-logistic-wahtsapp-pdf', function () {

            let button = $(this); // Reference to the button
            let url = button.data('url'); // Get the data-url attribute value
            // window.open(url, '_blank'); // Open the URL in a new tab or window
            // return;
            // Disable button while processing
            button.prop('disabled', true).text('Sending...');
            // AJAX request
            $.ajax({
                url: url,  // Route URL
                method: 'GET', // Use GET method as per route configuration
                success: function (response) {
                    if (response.success) {
                        // Show success notification
                        AIZ.plugins.notify('success', response.message);
                    } else if (response.error) {
                        // Show error alert
                        alert('Error: ' + response.error);
                    }
                },
                error: function (xhr, status, error) {
                    // Handle unexpected errors
                    AIZ.plugins.notify('danger', "Whatsapp not sent!");
                    //alert('An error occurred: ' + (xhr.responseJSON?.error || error));
                },
                complete: function () {
                    // Re-enable button after processing
                    button.prop('disabled', false).html('<i class="lab la-whatsapp"></i>');
                }
            });
        });

  });
</script>
@endsection


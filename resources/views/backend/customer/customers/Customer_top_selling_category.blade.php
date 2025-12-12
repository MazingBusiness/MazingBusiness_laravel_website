@extends('backend.layouts.app')

@section('content')
<div class="aiz-titlebar d-flex justify-content-between align-items-center mt-3 mb-4">
    <h1 class="h3 text-primary font-weight-bold text-dark mb-0">
        {{ translate('Top Selling Categories') }}
    </h1>

    <!-- Buttons Container -->
    <div class="d-flex">
        <button id="exportBtn" class="btn btn-success btn-sm mr-2" style="background-color:#6A5ACD;">
            <i class="las la-file-excel"></i> {{ translate('Export') }}
            <span id="exportLoader" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
        </button>

  <button style="display: none;" id="generateCategoryPricelistPDf" class="btn btn-success btn-sm">
    <i class="las la-file-excel"></i> {{ translate('Generate Pdf') }}
    <span id="pdfLoader" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
</button>
    </div>
</div>



<div class="card shadow-sm border-0">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 style="padding-right: 10px;" class="mb-0 font-weight-bold">{{ translate('Search ') }}</h5>
        <form method="GET" action="{{ route('topSellingCategories') }}" class="d-flex align-items-center position-relative w-100">
            <div class="input-group flex-grow-1">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="{{ translate('Search by Company Name, Party Code, Manager etc.') }}" value="{{ request('search') }}">
                @if(request('search'))
                    <div class="input-group-append">
                        <a href="{{ route('topSellingCategories') }}" class="btn btn-light btn-sm" title="{{ translate('Clear Search') }}">
                            <i class="las la-times"></i>
                        </a>
                    </div>
                @endif
            </div>
            <button type="submit" class="btn btn-light btn-sm ml-2">
                <i class="las la-search"></i> 
            </button>
        <!--  <a href="{{ route('exportTopSellingCategories', request()->query()) }}" class="btn btn-success btn-sm">
    <i class="las la-file-excel"></i> {{ translate('Export') }}
</a> -->
        </form>
    </div>


  <div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-striped table-bordered mb-0 text-center">
            <thead class="bg-dark text-white">
                <tr>
                    <th>#</th>
                    <th>{{ translate('Company Name') }}</th>
                    <th>{{ translate('Party Code') }}</th>
                    <th>
                        <a href="?sort_by=due_amount&sort_order={{ request('sort_order') == 'asc' ? 'desc' : 'asc' }}">
                            {{ translate('Due Amount') }}
                        </a>
                    </th>
                    <th>
                        <a href="?sort_by=overdue_amount&sort_order={{ request('sort_order') == 'asc' ? 'desc' : 'asc' }}">
                            {{ translate('Overdue Amount') }}
                        </a>
                    </th>
                    <th>{{ translate('Manager') }}</th>
                    <th>{{ translate('Total Categories Amount') }}</th>
                    <th>{{ translate('Categories') }}</th>
                    <th>{{ translate('Action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    @php
                        $totalCategoriesAmount = $user->category_details->sum('total_spent');
                    @endphp
                    <tr class="align-middle">
                        <td class="text-center">{{ ($users->currentPage() - 1) * $users->perPage() + $loop->iteration }}</td>
                        <td class="text-left">{{ $user->company_name ?? '-' }}</td>
                        <td>{{ $user->party_code ?? '-' }}</td>
                        <td class="text-right font-weight-bold">₹{{ number_format(optional($user->total_due_amounts->first())->total_due_amount ?? 0, 2) }}</td>
                        <td class="text-right text-danger font-weight-bold">₹{{ number_format(optional($user->total_due_amounts->first())->total_overdue_amount ?? 0, 2) }}</td>

                         <td class="text-left">{{ isset($user->manager) ? $user->manager->name : 'Unassigned' }}</td>

                        <td class="text-right font-weight-bold text-success">₹{{ number_format($totalCategoriesAmount, 2) }}</td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary toggle-symbol" data-toggle="collapse" data-target="#categories-{{ $user->id }}" aria-expanded="false" aria-controls="categories-{{ $user->id }}" title="{{ translate('Toggle Categories') }}">
                                <i class="las la-plus"></i>
                            </button>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-success downloadPdfBtn" data-user-id="{{ $user->id }}">
                                <i class="las la-file-pdf"></i> <span class="downloadText">Download</span>
                            </button>
                            <button class="btn btn-sm btn-outline-success send-whatsapp-top-five-selling-pdf" data-url="/whatsapp-top-selling-category" data-user-id="{{ $user->id }}">
                                <i class="lab la-whatsapp"></i> <span class="sendText">Send PDF</span>
                            </button>
                        </td>
                    </tr>

                    <!-- Collapsible Child Table -->
                    <tr id="categories-{{ $user->id }}" class="collapse">
                        <td colspan="9" class="p-0">
                            @if($user->category_details && $user->category_details->isNotEmpty())
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>{{ translate('Category Name') }}</th>
                                                <th>{{ translate('Total Spent') }}</th>
                                                <th>{{ translate('Last Purchase Date') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($user->category_details as $category)
                                                <tr>
                                                    <td class="text-left">{{ $category['name'] }}</td>
                                                    <td class="text-right font-weight-bold">₹{{ number_format($category['total_spent'], 2) }}</td>
                                                    <td class="text-center">{{ \Carbon\Carbon::parse($category['latest_purchase_date'])->format('d M Y') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-muted text-center py-2">{{ translate('No categories available') }}</p>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-3">{{ translate('No data available') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card-footer d-flex justify-content-between align-items-center bg-light">
    <span class="text-muted">
        {{ translate('Showing') }} {{ $users->firstItem() ?? 0 }} {{ translate('to') }} {{ $users->lastItem() ?? 0 }} {{ translate('of') }} {{ $users->total() ?? 0 }} {{ translate('entries') }}
    </span>
    <div>
        {{ $users->links('pagination::bootstrap-4') }}
    </div>
</div>

</div>
@endsection

@section('script')
<script type="text/javascript">
    $(document).ready(function () {
        $('.toggle-symbol').on('click', function () {
            const icon = $(this).find('i');
            const target = $(this).data('target');

            $(target).collapse('toggle');

            icon.toggleClass('la-plus la-minus');
        });



        //  $(".downloadPdfBtn").click(function () {
        //     var btn = $(this);
        //     var userId = btn.data("user-id"); // Get user ID

        //     var downloadText = btn.find(".downloadText");

        //     // Show "Please wait..." text
        //     downloadText.text("Please wait...");
        //     btn.prop("disabled", true);

        //     $.ajax({
        //         url: "/download-top-selling-category",
        //         type: "GET",
        //         data: { user_id: userId },
        //         success: function (response) {
        //             // Trigger file download
        //             window.location.href = "/download-top-selling-category?user_id=" + userId;

        //             // Reset button text after 2 seconds
        //             setTimeout(function () {
        //                 downloadText.text("Download PDF");
        //                 btn.prop("disabled", false);
        //             }, 2000);
        //         },
        //         error: function () {
        //             alert("Error generating PDF. Please try again.");
        //             downloadText.text("Download PDF");
        //             btn.prop("disabled", false);
        //         }
        //     });
        // });

        $(".downloadPdfBtn").click(function () {
            var btn = $(this);
            var userId = btn.data("user-id"); // Get user ID
            var downloadText = btn.find(".downloadText");

            // Show "Please wait..." text
            downloadText.text("Please wait...");
            btn.prop("disabled", true);

            $.ajax({
                url: "/download-top-selling-category",
                type: "GET",
                data: { user_id: userId, _t: new Date().getTime() }, // Add timestamp to prevent caching
                success: function (response) {
                    // Force new download by adding a unique timestamp to the URL
                    window.location.href = "/download-top-selling-category?user_id=" + userId + "&_t=" + new Date().getTime();

                    // Reset button text after 2 seconds
                    setTimeout(function () {
                        downloadText.text("Download PDF");
                        btn.prop("disabled", false);
                    }, 2000);
                },
                error: function (xhr) {
                    let errorMessage = "Error generating PDF. Please try again.";
                    errorStatus="danger";
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMessage = xhr.responseJSON.error; // Extract error message from JSON response
                        errorStatus="warning";
                    } else if (xhr.responseText) {
                        errorMessage = xhr.responseText; // Fallback to raw response text
                        errorStatus="warning";
                    }
                    AIZ.plugins.notify(errorStatus, errorMessage);
                    downloadText.text("Download PDF");
                    btn.prop("disabled", false);
                }
            });
        });


          $(".send-whatsapp-top-five-selling-pdf").click(function () {
            var btn = $(this);
            var userId = btn.data("user-id"); // Get user ID
            var url = btn.data("url"); // Get the WhatsApp API URL
            var sendText = btn.find(".sendText");

            // Show "Please wait..." message
            sendText.text("Please wait...");
            btn.prop("disabled", true);

            $.ajax({
                url: url,
                type: "GET",
                data: { user_id: userId },
                success: function (response) {
                    if (response.success) {
                    
                        AIZ.plugins.notify('success', 'WhatsApp messages sent successfully.');
                    } else {
                        AIZ.plugins.notify('success',"Failed to send PDF. Please try again.");
                    }
                    sendText.text("Send PDF");
                    btn.prop("disabled", false);
                },
                error: function () {
                    alert("Error occurred while sending PDF.");
                    sendText.text("Send PDF");
                    btn.prop("disabled", false);
                }
            });
        });

           $("#exportBtn").click(function() {

               $("#exportBtn").prop("disabled", true);  // Disable button
               $("#exportLoader").removeClass("d-none");  // Show loader
              

                $.ajax({
                    url: "{{ route('exportTopSellingCategories') }}",
                    type: "GET",
                    success: function(response) {
                        AIZ.plugins.notify('success', 'Excel Exported successfully.');
                        window.location.href = "{{ route('exportTopSellingCategories') }}";
                    },
                    error: function(xhr) {
                        AIZ.plugins.notify('danger', "Export failed. Please try again.");
                        //alert("Export failed. Please try again.");
                    },
                    complete: function() {
                        $("#exportBtn").prop("disabled", false);  // Enable button
                        $("#exportLoader").addClass("d-none");  // Hide loader
                    }
                });
        });


             $("#generateCategoryPricelistPDf").click(function() {
        let button = $(this);
        let loader = $("#pdfLoader");

        // Disable button & show loader
        button.prop("disabled", true);
        loader.removeClass("d-none");

        // AJAX request to generate PDF
        $.ajax({
            url: "{{ url('/generate-category-pdf') }}",
            type: "GET",
            success: function(response) {
                if (response.success) {
                    // PDF generated, open in new tab
                    AIZ.plugins.notify('success', 'Pdf Generation Successfully');
                } else {
                    AIZ.plugins.notify('danger', 'Excel Exported successfully.');
                }
            },
            error: function(xhr) {
                let errorMessage = "Something went wrong. Please try again!";
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message; // Laravel error message
                }
                alert("Error: " + errorMessage);
            },
            complete: function() {
                // Enable button & hide loader
                button.prop("disabled", false);
                loader.addClass("d-none");
            }
        });
    });
    });
</script>
@endsection

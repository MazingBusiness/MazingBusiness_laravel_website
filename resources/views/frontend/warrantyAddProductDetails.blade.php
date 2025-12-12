@extends('frontend.layouts.app')
@section('content')
    <style>
        .spare-card{
            border:1px solid #e9ecef; border-radius:.5rem; padding:.5rem; height:100%; background:#fff;
            box-shadow:0 1px 2px rgba(0,0,0,.05); transition:transform .15s ease, box-shadow .15s ease;
        }
        .spare-card:hover{ transform:translateY(-2px); box-shadow:0 6px 16px rgba(0,0,0,.12); }
        .spare-card .head{
            background:linear-gradient(90deg,#0a6bb5,#074e86); color:#fff;
            border-radius:.5rem .5rem 0 0; padding:.35rem .5rem; display:flex; justify-content:space-between; align-items:center;
        }
        .spare-card .name{ color:#6c757d; font-size:.875rem; margin-top:.4rem; line-height:1.3; }
        .product-head{
            background:linear-gradient(90deg,#0a6bb5,#074e86); color:#fff; border-radius:.5rem; padding:.5rem .75rem;
            display:flex; justify-content:space-between; align-items:center;
        }
        .product-head .title{ font-weight:600; }
        .product-head .meta{ opacity:.9; font-size:.875rem; }
        .badge-mixed-success { /* works for BS4 & BS5 */
            background:#28a745; color:#fff; border-radius:9999px; padding:.2rem .5rem; font-size:.8rem;
        }
        .badge-mixed-secondary {
            background:#6c757d; color:#fff; border-radius:9999px; padding:.2rem .5rem; font-size:.8rem;
        }
        /* disabled look */
        .spare-disabled{
            opacity:.55; filter:grayscale(100%); cursor:not-allowed; pointer-events:none;
        }
        .spare-disabled .head{ background:#adb5bd !important; }
        .spare-disabled .name{ color:#9aa0a6 !important; }
        #warehouseAddress { white-space: normal; line-height: 1.4; }
    </style>
    <section class="gry-bg py-5">
        <div class="profile">
            <div class="container">
                <div class="row">
                    <div class="mx-auto" style="width: 100%;">
                        <div class="card">
                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <div class="font-weight-bold mb-1">Please fix the following:</div>
                                    <ul class="mb-0">
                                    @foreach (collect($errors->all())->unique() as $err)
                                        <li>{{ $err }}</li>
                                    @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if (session('success'))
                                <div class="alert alert-success">
                                    {{ session('success') }}
                                </div>
                            @endif
                            <form action="{{ route('warrantySubmit') }}" id="warrantyForm" name="warrantyForm" method="POST" enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="warehouse_id" id="warehouse_id_hidden" value="{{ old('warehouse_id') }}">
                                <input type="hidden" name="warehouse_address" id="warehouse_address_hidden" value="{{ old('warehouse_address') }}">
                                <div class="card">
                                    <div class="card-body"> 
                                        <div class="form-group row">
                                            <div class="col-md-6">
                                                <div class="row">
                                                    <label class="col-md-3 col-form-label" style="text-align:right;"><b>{{ translate('Name*') }} :</b></label>
                                                    <div class="col-md-9">
                                                        <input type="text" class="form-control" placeholder="Enter Your Name" name="company_name" value="{{ $addresses[0]['company_name'] }}" required>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="row">
                                                    <label class="col-md-3 col-form-label" style="text-align:right;"><b>{{ translate('Phone*') }} :</b></label>
                                                    <div class="col-md-9">
                                                        <input type="text" class="form-control" placeholder="Enter Your Name" name="name" value="{{ $userData->phone }}" readOnly required>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <div class="col-md-6">
                                                <div class="row">
                                                    <label class="col-md-3 col-form-label" style="text-align:right;"><b>{{ translate('Email*') }} : </b></label>
                                                    <div class="col-md-9">
                                                        <input type="email" class="form-control" placeholder="{{ translate('Email') }}" name="email" value="{{ $userData->email }}" required>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                @if($addresses[0]['gstin'] != '' OR $addresses[0]['gstin'] != NULL)
                                                    <div class="row">
                                                        <label class="col-md-3 col-form-label" style="text-align:right;"><b>{{ translate('GST') }} : </b></label>
                                                        <div class="col-md-9">
                                                            <!-- <input type="text" class="form-control" placeholder="{{ translate('GST') }}" name="gst" value="{{ $userData->gstin }}"> -->
                                                            <select name="gst" id="gst" class="form-control">
                                                                @foreach($addresses as $addKey=>$adValue)
                                                                    <option value="{{ $adValue['gstin'] }}">{{ $adValue['gstin'] }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                @else
                                                    <div class="row">
                                                        <label class="col-md-3 col-form-label" style="text-align:right;"><b>{{ translate('Aadhar') }} : </b></label>
                                                        <div class="col-md-9">
                                                            <input type="text" class="form-control" placeholder="{{ translate('Aadhar') }}" name="aadhar" value="{{ $userData->aadhar_card }}">
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <div class="col-md-6">
                                                <div class="row">
                                                    <label class="col-md-3 col-form-label" style="text-align:right;"><b>{{ translate('Address*') }} :</b></label>
                                                    <div class="col-md-9">
                                                        <textarea class="form-control" placeholder="Enter Your Address" name="address" required>{{ $addresses[0]['address'] }}</textarea>
                                                        <input type="hidden" name="address_id" id="address_id" value="{{ $addresses[0]['id'] }}" />
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="row">
                                                    <label class="col-md-3 col-form-label" style="text-align:right;"><b>{{ translate('Address 2') }} : </b></label>
                                                    <div class="col-md-9">
                                                        <textarea class="form-control" placeholder="Enter Your Address" name="address_2">{{ $addresses[0]['address_2'] }}</textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <div class="col-md-6">
                                                <div class="row">
                                                    <label class="col-md-3 col-form-label" style="text-align:right;"><b>{{ translate('City') }} :</b></label>
                                                    <div class="col-md-9">
                                                        <input type="text" class="form-control" placeholder="{{ translate('City') }}" name="city" value="{{ $addresses[0]['city'] }}">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="row">
                                                    <label class="col-md-3 col-form-label" style="text-align:right;"><b>{{ translate('Pin Code*') }} : </b></label>
                                                    <div class="col-md-9">
                                                        <input type="text" class="form-control" placeholder="{{ translate('Pin Code') }}" name="postal_code" value="{{ $addresses[0]['postal_code'] }}" required>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <hr>
                                        <div id="dynamic-section">
                                            <div class="form-group row input-section" data-row="0">
                                                <!-- BARCODE -->
                                                <div class="col-md-5">
                                                <div class="row">
                                                    <label class="col-md-3 col-form-label text-right"><b>BAR CODE :</b></label>
                                                    <div class="col-md-9">
                                                    <input type="text" class="form-control barcode-input"
                                                            name="rows[0][barcode]" placeholder="Enter Bar Code" required>
                                                    <small class="form-text mt-1 barcode-status"></small>
                                                    </div>
                                                </div>
                                                </div>

                                                <!-- INVOICE -->
                                                <div class="col-md-3">
                                                <div class="row">
                                                    <label class="col-md-4 col-form-label text-right"><b>Invoice No :</b></label>
                                                    <div class="col-md-8">
                                                    <input type="text" class="form-control invoice-input"
                                                            name="rows[0][invoice]" placeholder="Invoice No" required>
                                                    <small class="form-text mt-1 invoice-status"></small>
                                                    </div>
                                                </div>
                                                </div>

                                                <!-- DATE -->
                                                <div class="col-md-4">
                                                <div class="row">
                                                    <label class="col-md-4 col-form-label text-right"><b>Purchase Date :</b></label>
                                                    <div class="col-md-6">
                                                    <input type="date" class="form-control date-input"
                                                            name="rows[0][purchase_date]" placeholder="Purchase Date" required>
                                                    <small class="form-text mt-1 date-status"></small>
                                                    </div>
                                                    <div class="col-md-2">
                                                    <button type="button" class="btn btn-danger remove-btn"><i class="las la-trash fs-4"></i></button>
                                                    </div>
                                                </div>
                                                </div>

                                                <!-- FILES -->
                                                <div class="col-md-6" style="margin-top:10px;">
                                                    <div class="row">
                                                        <label class="col-md-3 col-form-label text-right"><b>Upload Invoice :</b></label>
                                                        <div class="col-md-9">
                                                        <input type="file" class="file-input invoice-upload"
                                                            name="rows[0][upload_invoice]" accept="application/pdf,image/*">
                                                        <small class="form-text mt-1 invoice-status"></small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6" style="margin-top:10px;">
                                                    <div class="row">
                                                        <label class="col-md-4 col-form-label text-right"><b>Upload Warranty Card :</b></label>
                                                        <div class="col-md-8">
                                                        <input type="file" class="file-input warranty-card-upload"
                                                            name="rows[0][warranty_card]" accept="application/pdf,image/*">
                                                        <small class="form-text mt-1 invoice-status"></small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- Spares get injected here -->
                                                <div class="col-12">
                                                <div class="spares-holder mt-2"></div>
                                                </div>
                                            </div>
                                            <hr>
                                        </div>
                                        <!-- Add More button -->
                                        <div class="form-group text-right">
                                            <button type="button" id="add-more" class="btn btn-success">+ Add More Product</button>
                                        </div>
                                        <div class="form-group text-right">
                                            <input type="checkbox" name="terms_and_condition" id="terms_and_condition" value="1" checked required/>
                                            <label class="col-form-label text-right"><b>Check Terms and Condition</b></label>
                                            <button type="submit" id="submitBtn" class="btn btn-primary">{{ translate('Submit') }}</button>
                                        </div>
                                    </div>
                                </div>
                            </form>        
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    {{-- Choose Warehouse Modal --}}
    <div class="modal fade" id="warehouseModal" tabindex="-1" role="dialog" aria-labelledby="warehouseModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="warehouseModalLabel">Select Preferred Warehouse For Shipping</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                <label for="warehouseSelect" class="font-weight-bold mb-1">Warehouse</label>
                <select id="warehouseSelect" class="form-control">
                    <option value="">-- Select a warehouse --</option>
                    @foreach($warehouses as $wh)
                        @php
                            $addr = trim(implode("\n", array_filter([
                                $wh->address,', '.($wh->pincode ?? '')
                            ])));
                        @endphp
                        <option value="{{ $wh->id }}" data-address="{{ $addr }}" {{ old('warehouse_id') == $wh->id ? 'selected' : '' }}>
                            {{ $wh->name }}
                        </option>
                    @endforeach
                </select>
                <small id="warehouseError" class="text-danger d-none">Please select a warehouse.</small>
                </div>

                <div id="warehouseAddressWrap" class="d-none">
                <label class="font-weight-bold mb-1">Address</label>
                <div id="warehouseAddress" class="border rounded p-2 bg-light small"></div>
                </div>
            </div>

            <div class="modal-footer py-2">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmWarehouseBtn">Use This Warehouse</button>
            </div>
            </div>
        </div>
    </div>

@endsection
@section('script')
    <script>
        var addresses = @json($addresses);
        $(document).on('change', '#gst', function () {
            let selectedGstin = $(this).val();

            // find address record matching selected GSTIN
            let addr = addresses.find(a => a.gstin === selectedGstin);

            if (addr) {
                // fill fields
                $('[name="company_name"]').val(addr.company_name || '');
                $('[name="address"]').val(addr.address || '');
                $('[name="address"]').val(addr.address || '');
                $('[name="address_2"]').val(addr.address_2 || '');
                $('[name="city"]').val(addr.city || '');
                $('[name="postal_code"]').val(addr.postal_code || '');
            }
        });

        // 0) Include CSRF header for AJAX
        $.ajaxSetup({
            headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')}
        });

        // 1) Clone + clear
        $("#add-more").on("click", function () {
            let newSection = $(".input-section:first").clone();
            newSection.find("input").val("");
            newSection.find(".barcode-status").text("").removeClass("text-success text-danger text-muted");
            newSection.find(".barcode-input").removeClass("is-valid is-invalid");
            newSection.find(".invoice-status").text("").removeClass("text-success text-danger text-muted");
            newSection.find(".invoice-input").removeClass("is-valid is-invalid");
            newSection.find(".date-status").text("").removeClass("text-success text-danger text-muted");
            newSection.find(".date-input").removeClass("is-valid is-invalid");
            
            // also clear table in the cloned row
            // newSection.find(".spares-holder").empty();
            // $("#dynamic-section").append(newSection);
        });

        // Remove section
        $(document).on("click", ".remove-btn", function () {
            setTimeout(updateSubmitState, 0);
            if ($(".input-section").length > 1) {
                $(this).closest(".input-section").remove();
            } else {
                alert("At least one section is required.");
            }
        });

        $(function(){
            $('.input-section').each(function(){
                toggleInvoiceDate($(this), false);
                applyPurchaseDateState($(this), { lock:true, clear:true });
            });
        });

        // 2) Per-input debounce helper
        function debouncePerInput(fn, wait) {
            return function(e) {
                const $input = $(this);
                const key = '__debounceTimer';
                clearTimeout($input.data(key));
                const t = setTimeout(() => fn.call(this, e), wait);
                $input.data(key, t);
            };
        }

        function buildSparesCards(rows, productDetails, isWarranty = "", rowIndex = 0) {
            const esc  = s => String(s ?? '')
                .replace(/&/g,'&amp;').replace(/</g,'&lt;')
                .replace(/>/g,'&gt;').replace(/"/g,'&quot;')
                .replace(/'/g,'&#039;');
            const slug = s => String(s ?? '')
                .toLowerCase().replace(/\s+/g,'-').replace(/[^a-z0-9_-]/g,'');

            const hasRows = Array.isArray(rows) && rows.length > 0;
            let html = '<div class="row">';

            if (productDetails) {
                const name = esc(productDetails.name || '');
                const pno  = esc(productDetails.part_no || '');
                const warranty_duration   = esc(productDetails.warranty_duration || '');
                const description   = esc(productDetails.description || '');
                const product_image = esc(productDetails.product_image || '');
                html += `
                <div class="col-6 mb-2">
                    <label class="d-block mb-1 font-weight-bold">Product Details</label>
                    <div style="border:1px solid #e9ecef; border-radius:.5rem; padding:.5rem;">
                    <div class="product-head"><div class="title">${name}</div></div>
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="meta">Part No: ${pno}</div>
                            <div class="meta">Warranty Duration: <strong>${warranty_duration} months </strong></div>
                            <div class="meta">Description: ${description}</div>
                        </div>
                        <div class="col-md-6 text-right">
                            ${product_image ? `<img src="${product_image}" alt="" style="width:87px;">` : ''}
                        </div>
                    </div>
                    </div>
                </div>`;
            }

            if (hasRows) {
                html += `<div class="col-6">
                <label class="d-block mb-1 font-weight-bold">Covered in warranty</label>
                <div class="row">`;

                rows.forEach(r => {
                    const part       = (r.part_number ?? r.part_no) ?? '';
                    const name       = (r.product_name ?? r.name) ?? '';
                    const spareImage = esc(r.spareImage ?? '');
                    const chkId      = 'sp_' + slug(part);
                    const isApplied  = String(r.already_applied ?? '0') === '1';
                    const cardClass  = 'spare-card' + (isApplied ? ' spare-disabled' : '');
                    const rightHeader = isApplied
                        ? `<span class="badge ${'bg-secondary badge-secondary'}" title="Already applied">Applied</span>`
                        : `<div class="form-check m-0">
                            <input class="form-control spare-select" style="width:20px;" type="checkbox"
                                    id="${chkId}" name="rows[${rowIndex}][suitable_spares][]"
                                    value="${esc(part)}">
                        </div>`;
                    html += `
                        <div class="col-12 col-sm-6 mb-2">
                            <div class="${cardClass}" ${isApplied ? 'aria-disabled="true"' : ''}>
                                <div class="head">
                                    <span class="font-weight-bold">${esc(part)}</span>
                                    ${rightHeader}
                                </div>
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <label for="${chkId}" class="name d-block mb-0">${esc(name)}</label>
                                    </div>
                                    <div class="col-md-6 text-right">
                                        ${spareImage ? `<img src="${spareImage}" alt="" style="width:87px;">` : ''}
                                    </div>
                                </div>
                            </div>
                        </div>`;
                });

                html += `</div></div>`;
            } else {
                html += `
                <div class="col-6">
                    <label class="d-block mb-1 font-weight-bold">Covered in warranty</label>
                    <div class="text-muted small">No suitable spares found.</div>
                </div>`;
            }

            html += `</div>`;
            return html;
        }

        function toggleInvoiceDate($section, enabled) {
            const $invoice = $section.find('input[name="invoice[]"]');
            const $date    = $section.find('input[name="purchase_date[]"]');

            // readonly per your requirement
            $invoice.prop('readonly', !enabled);
            $date.prop('readonly', !enabled);

            // optional UI polish
            $invoice.toggleClass('bg-light', !enabled);
            $date.toggleClass('bg-light', !enabled);

            if (enabled) {
                $invoice.focus();
            } else {
                $invoice.val('');
                $date.val('');
            }
        }

        function applyPurchaseDateState($section, opts) {
            const $date = $section.find('input[name="purchase_date[]"]');
            if (opts.lock) {
                if (opts.value) $date.val(opts.value);
                $date.prop('readonly', true).addClass('bg-light');
            } else {
                $date.val('');
                $date.prop('readonly', false).removeClass('bg-light');

            }
        }

        $(document).on('input', '.barcode-input', debouncePerInput(function (e) {
            const $input   = $(this);
            const val      = $input.val().trim();
            const $status  = $input.siblings('.barcode-status');
            const $section = $input.closest('.input-section');
            const $holder  = $section.find('.spares-holder');
            const $invInput = $section.find('.invoice-input');

            const resetReqState = () => {
                $input.removeData('inFlight').removeData('lastSent');
            };

            // Always (re)scan & update submit button on any change
            const hasDupNow = updateSubmitState();
            if (hasDupNow) {
                // If duplicates exist, stop here (don’t call server)
                $holder.empty();
                return;
            }

            if (!val) {
                $status.text('').removeClass('text-success text-danger text-muted');
                $input.removeClass('is-valid is-invalid');
                $holder.empty();
                toggleInvoiceDate($section, false);
                resetReqState();
                return;
            }

            if (val.length < 23) {
                $status.text('Waiting for full barcode...').removeClass('text-success text-danger').addClass('text-muted');
                $input.removeClass('is-valid is-invalid');
                if ($invInput.length) $invInput.removeClass('is-valid is-invalid');
                $holder.empty();
                toggleInvoiceDate($section, false);
                resetReqState();
                const $btn = $('#warrantyForm [type="submit"]');
                $btn.prop('disabled', true).toggleClass('disabled', true);
                return;
            }

            if ($input.data('inFlight') && $input.data('lastSent') === val) return;

            $status.text('Checking...').removeClass('text-success text-danger').addClass('text-muted');
            $input.data('inFlight', true).data('lastSent', val);

            $.post("{{ route('warrantyBarcodeCheck') }}", { barcode: val })
                .done(function (resp) {
                if (!resp.found) {
                    $status.text('Barcode not found.').removeClass('text-success text-muted').addClass('text-danger');
                    $input.removeClass('is-valid').addClass('is-invalid');
                    $holder.empty();
                    toggleInvoiceDate($section, false);
                    
                    $btn.prop('disabled', true).toggleClass('disabled', true);
                    return;
                }

                const isWarranty = (resp.is_warranty === true || resp.is_warranty === 1 || resp.is_warranty === '1');

                if (isWarranty) {
                    $status.text('Validated Barcode.').removeClass('text-danger text-muted').addClass('text-success');
                    $input.removeClass('is-invalid').addClass('is-valid');
                    toggleInvoiceDate($section, true);
                    const $btn = $('#warrantyForm [type="submit"]');
                    $btn.prop('disabled', false).toggleClass('disabled', false);
                } else {
                    $status.text('Not in Warranty').removeClass('text-success text-muted').addClass('text-danger');
                    $input.removeClass('is-valid').addClass('is-invalid');
                    toggleInvoiceDate($section, false);
                    const $btn = $('#warrantyForm [type="submit"]');
                    $btn.prop('disabled', true).toggleClass('disabled', true);
                }

                const rowIndex = $section.closest('#dynamic-section').find('.input-section').index($section);
                const html = buildSparesCards(resp.suitableSpareParts || [], resp.productDetails || null, isWarranty, rowIndex);
                $holder.html(html);

                // After rendering, re-check duplicates & submit state just in case
                updateSubmitState();
                })
                .fail(function () {
                    $status.text('Error checking barcode.').removeClass('text-success text-muted').addClass('text-danger');
                    $input.addClass('is-invalid');
                    $holder.empty();
                    toggleInvoiceDate($section, false);
                    const $btn = $('#warrantyForm [type="submit"]');
                    $btn.prop('disabled', true).toggleClass('disabled', true);
                })
                .always(function () {
                    $input.data('inFlight', false);
                });
        }, 300));

        $('form').on('submit', function(e){
            let ok = true;
            $('.barcode-input').each(function(){
                const $input = $(this);
                const statusText = $input.siblings('.barcode-status').text();
                if (!$input.val().trim() || statusText === 'Barcode not found.' || statusText === 'Error checking barcode.') {
                    ok = false;
                    $input.focus();
                    return false; // break
                }
            });
            if (!ok) {
                e.preventDefault();
                alert('Please fix barcode errors before submitting.');
            }
             if (scanDuplicateBarcodes()) {
                e.preventDefault();
                alert('Duplicate barcodes found. Please remove or change duplicates before submitting.');
            }
        });

        $(document).on('input', '.invoice-input', debouncePerInput(function () {
            const $invInput = $(this);
            const invoice   = $invInput.val().trim();
            const $section  = $invInput.closest('.input-section');
            const $status   = $invInput.siblings('.invoice-status');
            const barcode   = ($section.find('.barcode-input').val() || '').trim();

            // ✅ get date controls from the same row
            const $dateInput  = $section.find('.date-input');
            const $dateStatus = $section.find('.date-status');
            const $holder     = $section.find('.spares-holder');

            // Reset when empty
            if (!invoice) {
                $status.text('').removeClass('text-success text-danger text-muted');
                $dateStatus.text('').removeClass('text-success text-danger text-muted');
                $dateInput.val('').removeClass('is-valid is-invalid');
                applyPurchaseDateState($section, { lock:false, clear:true });
                $invInput.removeData('inFlight').removeData('lastSent');
                return;
            }

            // Skip while the same value is in-flight
            if ($invInput.data('inFlight') && $invInput.data('lastSent') === invoice) {
                return;
            }

            $status.text('Checking invoice...').removeClass('text-success text-danger').addClass('text-muted');
            $invInput.data('inFlight', true).data('lastSent', invoice);

            // ✅ clear date input + its status WHEN the API call starts
            $dateInput.val('').removeClass('is-valid is-invalid');
            $dateStatus.text('').removeClass('text-success text-danger text-muted');

            $.post("{{ route('warrantyInvoiceCheck') }}", { invoice_no: invoice, barcode })
                .done(function (res) {
                    if (res && res.found) {
                        // lock date with server date
                        applyPurchaseDateState($section, { lock:true, value: res.purchase_date });
                        $status.text(res.message || 'Invoice found. Date filled.')
                            .removeClass('text-muted text-danger').addClass('text-success');
                        $invInput.removeClass('is-invalid').addClass('is-valid');
                    } else {
                        // unlock date so user can select
                        applyPurchaseDateState($section, { lock:false, clear:false });
                        $status.text((res && res.message)).removeClass('text-muted text-success').addClass('text-danger');

                        if (res && res.message) {
                            $invInput.removeClass('is-valid').addClass('is-invalid');
                        } else {
                            $invInput.removeClass('is-valid is-invalid');
                        }

                        if (res && res.warranty_expired) {
                        // if you prefer to keep editable to correct, leave as-is
                        // or force lock blank:
                        // applyPurchaseDateState($section, { lock:true, value: '' });
                        }
                    }
                    const isWarranty = (res.is_warranty === true || res.is_warranty === 1 || res.is_warranty === '1');
                    const rowIndex = $section.closest('#dynamic-section').find('.input-section').index($section);
                    const html = buildSparesCards(res.suitableSpareParts || [], res.productDetails || null, isWarranty, rowIndex);
                    $holder.html(html);
                })
                .fail(function () {
                    applyPurchaseDateState($section, { lock:false, clear:false });
                    $status.text('Error checking invoice.').removeClass('text-muted text-success').addClass('text-danger');
                    $invInput.removeClass('is-valid').addClass('is-invalid');
                })
                .always(function () {
                    $invInput.data('inFlight', false);
                });
            }, 300
        ));

        // helper: today's local YYYY-MM-DD
        function todayYYYYMMDD(){
            const d = new Date();
            const mm = String(d.getMonth()+1).padStart(2,'0');
            const dd = String(d.getDate()).padStart(2,'0');
            return `${d.getFullYear()}-${mm}-${dd}`;
        }

        $(document).on('input', '.date-input', debouncePerInput(function () {
            const $dateInput = $(this);
            const date       = ($dateInput.val() || '').trim();
            const $section   = $dateInput.closest('.input-section');
            const $status    = $dateInput.siblings('.date-status');

            const barcode = ($section.find('.barcode-input').val() || '').trim();
            const invoice = ($section.find('.invoice-input').val() || '').trim();

            if (!date) return;

            // Disallow future dates
            const today = todayYYYYMMDD();
            if (date > today) {
                $status.text('Purchase date cannot be in the future.')
                    .removeClass('text-success').addClass('text-danger');
                $dateInput.addClass('is-invalid').removeClass('is-valid');
                return; // stop here, don't call API
            } else {
                $dateInput.removeClass('is-invalid');
            }

            if (barcode.length < 23) {
                $status.text('Enter a full barcode first.').removeClass('text-success').addClass('text-danger');
                return;
            }
            if (!invoice) {
                $status.text('Enter invoice number first.').removeClass('text-success').addClass('text-danger');
                return;
            }

            const sig = `${barcode}|${invoice}|${date}`;
            if ($dateInput.data('inFlight') && $dateInput.data('lastSent') === sig) return;

            $status.text('Checking date...').removeClass('text-success text-danger').addClass('text-muted');
            $dateInput.data('inFlight', true).data('lastSent', sig);

            $.post("{{ route('warrantyDateCheck') }}", { date, barcode, invoice })
                .done(function (res) {
                    if (res && res.found) {
                        $status.text('Within warranty.').removeClass('text-muted text-danger').addClass('text-success');
                    } else {
                        $status.text((res && res.message) || 'Not within warranty.')
                            .removeClass('text-muted text-success').addClass('text-danger');
                    }
                })
                .fail(function () {
                    $status.text('Error checking date.').removeClass('text-muted text-success').addClass('text-danger');
                })
                .always(function () {
                    $dateInput.data('inFlight', false);
                });
            }, 300
        ));

        function reindexRows() {
            $('#dynamic-section .input-section').each(function(i){
                $(this).attr('data-row', i);
                $(this).find('.barcode-input')        .attr('name', `rows[${i}][barcode]`);
                $(this).find('.invoice-input')        .attr('name', `rows[${i}][invoice]`);
                $(this).find('.date-input')           .attr('name', `rows[${i}][purchase_date]`);
                $(this).find('.invoice-upload')       .attr('name', `rows[${i}][upload_invoice]`);
                $(this).find('.warranty-card-upload') .attr('name', `rows[${i}][warranty_card]`);

                // Clear any spares rendered from a different index; they’ll be rebuilt on barcode check
                // OR if you’re okay with keeping them, also rename their names:
                $(this).find('input.spare-select').each(function(){
                const part = this.value || '';
                $(this).attr('name', `rows[${i}][suitable_spares][]`);
                });
            });
        }

        // On load
        $(function(){ reindexRows(); });

            // After you clone:
            $('#add-more').on('click', function(){
            const $first = $('#dynamic-section .input-section:first');
            const $clone = $first.clone(true, true);

            // clear values/status in clone
            $clone.find('input[type="text"], input[type="date"]').val('').removeClass('is-valid is-invalid');
            $clone.find('.barcode-status,.invoice-status,.date-status').text('').removeClass('text-success text-danger text-muted');
            $clone.find('.spares-holder').empty();
            $clone.find('.invoice-upload, .warranty-card-upload').val('');

            $('#dynamic-section').append($clone);
            reindexRows();
        });

        (function () {
            let allowSubmit = false;

            // ---- helpers ----
            function rowFilesAreOk($row) {
                const $inv = $row.find('.invoice-upload');
                const $wc  = $row.find('.warranty-card-upload');

                const hasInv = ($inv[0]?.files?.length || 0) > 0 || !!$inv.val();
                const hasWC  = ($wc[0]?.files?.length  || 0) > 0 || !!$wc.val();

                // clear file errors
                $inv.removeClass('is-invalid');
                $wc.removeClass('is-invalid');
                $row.find('.file-error').remove();

                if (!hasInv && !hasWC) {
                $inv.addClass('is-invalid');
                $wc.addClass('is-invalid');

                const $wcWrap = $wc.closest('.col-md-8, .col-md-9').length 
                                    ? $wc.closest('.col-md-8, .col-md-9') 
                                    : $wc.parent();
                $('<small class="form-text text-danger file-error">Please upload either the Invoice or the Warranty Card for this product.</small>')
                    .appendTo($wcWrap);
                return false;
                }
                return true;
            }

            function rowSparesAreOk($row) {
                const $spares = $row.find('.spare-select');
                const anyChecked = $spares.filter(':checked').length > 0;

                // clear spare errors / highlighting
                $row.find('.spares-error').remove();
                const $sparesHolder = $row.find('.spares-holder').first();
                $sparesHolder.removeClass('is-invalid-spares');

                if (!anyChecked) {
                // light highlight for the spares block (you can tweak styles below)
                $sparesHolder.addClass('is-invalid-spares');
                $('<small class="d-block mt-1 text-danger spares-error">Please select at least one suitable spare for this product.</small>')
                    .appendTo($sparesHolder);
                return false;
                }
                return true;
            }

            // Validate one row (files + spares)
            function validateRow($row) {
                const filesOK  = rowFilesAreOk($row);
                const sparesOK = rowSparesAreOk($row);
                return filesOK && sparesOK;
            }

            // Validate all rows; scroll to first invalid
            function validateAllRows() {
                let allGood = true;
                let $firstBad = null;

                $('#dynamic-section .input-section').each(function() {
                const ok = validateRow($(this));
                if (!ok) {
                    allGood = false;
                    if (!$firstBad) $firstBad = $(this);
                }
                });

                if (!allGood && $firstBad) {
                $('html, body').animate({ scrollTop: $firstBad.offset().top - 120 }, 300);
                // focus priority: spare error first, then file
                const $focus = $firstBad.find('.spare-select').first().length
                    ? $firstBad.find('.spare-select').first()
                    : $firstBad.find('.invoice-upload.is-invalid, .warranty-card-upload.is-invalid').first();
                $focus.trigger('focus');
                }
                return allGood;
            }

            // Re-validate a row when either file input changes
            $(document).on('change', '.invoice-upload, .warranty-card-upload', function() {
                const $row = $(this).closest('.input-section');
                rowFilesAreOk($row);   // only re-check files on this event
            });

            // Re-validate spares for the row on checkbox change
            $(document).on('change', '.spare-select', function() {
                const $row = $(this).closest('.input-section');
                rowSparesAreOk($row);  // only re-check spares on this event
            });

            // Intercept submit to enforce both validations before showing modal
            $(document).on('submit', '#warrantyForm', function (e) {
                const form = this;

                if (allowSubmit) return;

                if (!form.checkValidity()) {
                e.preventDefault();
                form.reportValidity();
                return;
                }

                // Per-row: (Invoice OR Warranty Card) AND (≥1 suitable spare)
                if (!validateAllRows()) {
                e.preventDefault();
                return; // stop—do not open the warehouse modal yet
                }

                // If rows valid, then enforce warehouse modal as before
                if (!$('#warehouse_id_hidden').val()) {
                e.preventDefault();
                $('#warehouseModal').modal('show');
                }
            });

            // Optional: visual style for invalid spares block
            const style = document.createElement('style');
            style.textContent = `
                .is-invalid-spares {
                border: 1px solid #dc3545 !important;
                border-radius: .5rem;
                padding: .5rem;
                }
            `;
            document.head.appendChild(style);

            // Warehouse change → show address
            $('#warehouseSelect').on('change', function () {
                const addr = $(this).find(':selected').data('address') || '';
                if (this.value) {
                $('#warehouseError').addClass('d-none').text('');
                $('#warehouseAddress').html(addr.replace(/\n/g, '<br>'));
                $('#warehouseAddressWrap').removeClass('d-none');
                } else {
                $('#warehouseAddressWrap').addClass('d-none');
                $('#warehouseAddress').empty();
                }
            });

            // Confirm button → set hidden fields then submit
            $('#confirmWarehouseBtn').on('click', function () {
                const $opt = $('#warehouseSelect option:selected');
                const id   = $opt.val();
                if (!id) {
                $('#warehouseError').removeClass('d-none').text('Please select a warehouse.');
                return;
                }
                const addr = $opt.data('address') || '';
                $('#warehouse_id_hidden').val(id);
                $('#warehouse_address_hidden').val(addr);

                allowSubmit = true;
                $('#warehouseModal').modal('hide');
                $('#warrantyForm').trigger('submit');
            });
        })();

        // Normalize for comparison
        function normalizeBarcode(s){ return (s||'').trim().toUpperCase(); }

        // Mark as duplicate
        function markDuplicate($inp, msg){
            const $status = $inp.siblings('.barcode-status');
            $status.text(msg).removeClass('text-success text-muted').addClass('text-danger');
            $inp.removeClass('is-valid').addClass('is-invalid');
        }

        // Clear duplicate visuals
        function clearDuplicate($inp){
            const $status = $inp.siblings('.barcode-status');
            if ($status.text().includes('Duplicate barcode')) {
                $status.text('').removeClass('text-danger');
            }
            // do NOT force valid state here; just remove invalid if it was our duplicate mark
            $inp.removeClass('is-invalid');
        }

        // Scan all barcodes, mark duplicates, return true if any duplicate exists
        function scanDuplicateBarcodes(){
        const map = {};
        let hasDup = false;
        const $inputs = $('.barcode-input');

        // clear all previous duplicate visuals first
        $inputs.each(function(){ clearDuplicate($(this)); });

        // build value → inputs map
        $inputs.each(function(){
        const v = normalizeBarcode($(this).val());
            if (!v) return;
            (map[v] = map[v] || []).push($(this));
        });

        // mark duplicates (any value seen > 1 times)
        Object.keys(map).forEach(v => {
            if (map[v].length > 1) {
            hasDup = true;
            map[v].forEach($inp => markDuplicate($inp, 'Duplicate barcode in another row.'));
            }
        });

        return hasDup;
        }

        // Show/hide or enable/disable submit based on duplicates
        function updateSubmitState(){
            const hasDup = scanDuplicateBarcodes();
            const $btn = $('#warrantyForm [type="submit"]');
            $btn.prop('disabled', hasDup).toggleClass('disabled', hasDup);
            return hasDup;
        }

        // Call on load
        $(function(){ updateSubmitState(); });

        // If you dynamically add/remove rows, call updateSubmitState() afterwards.
        // $(document).on('click', '.remove-btn', function(){
        //     setTimeout(updateSubmitState, 0);
        // });
    </script>
@endsection
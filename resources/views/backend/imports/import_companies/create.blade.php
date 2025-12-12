@extends('backend.layouts.app')

@section('content')
<style>
    /* Meta-style upload card */
    .meta-upload-card {
        border-radius: 14px;
        border: 1px solid #e5e7eb;
        background: #f9fafb;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
    }
    .meta-upload-left {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
    }
    .meta-upload-icon {
        width: 40px;
        height: 40px;
        border-radius: 999px;
        background: #eef2ff;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 12px;
        color: #4f46e5;
        font-size: 20px;
    }
    .meta-upload-text-title {
        font-size: 13px;
        font-weight: 600;
        color: #111827;
    }
    .meta-upload-text-sub {
        font-size: 12px;
        color: #6b7280;
    }
    .meta-upload-btn {
        margin-top: 8px;
        font-size: 12px;
        padding: 4px 10px;
    }
    .meta-upload-hidden-input {
        display: none;
    }

    .meta-upload-preview-box {
        width: 150px;
        height: 150px;
        border-radius: 12px;
        border: 1px dashed #d1d5db;
        background: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-top: 10px;
    }
    .meta-upload-preview-placeholder {
        text-align: center;
        color: #9ca3af;
        font-size: 11px;
        padding: 6px;
    }
    .meta-upload-preview-placeholder i {
        font-size: 22px;
        display: block;
        margin-bottom: 4px;
    }
    .meta-upload-preview-box img {
        max-width: 100%;
        max-height: 100%;
        border-radius: 10px;
        object-fit: contain;
    }

    /* GSTIN status label */
    .gstin-status {
        font-size: 11px;
        margin-top: 3px;
    }
</style>

<div class="aiz-main-content">
    <div class="px-15px px-lg-25px">

        {{-- Page Header --}}
        <div class="row mb-3 align-items-center">
            <div class="col-md-8">
                <h4 class="mb-0">Add Import Company</h4>
                <small class="text-muted">
                    Create a new import company used for BL / CI / Packing List.
                </small>
            </div>
            <div class="col-md-4 text-md-right mt-3 mt-md-0">
                <a href="{{ route('import_companies.index') }}" class="btn btn-sm btn-secondary">
                    <i class="las la-list"></i> Import Company List
                </a>
            </div>
        </div>

        {{-- Validation Errors --}}
        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>There were some problems with your input:</strong>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Form Card --}}
        <div class="card shadow-sm">
            <div class="card-body">
                <form id="import_company_form"
                      action="{{ route('import_companies.store') }}"
                      method="POST"
                      enctype="multipart/form-data">
                    @csrf

                    {{-- Company + Email --}}
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Company Name <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" id="company_name_input" class="form-control"
                                   value="{{ old('company_name') }}" required>
                        </div>

                        <div class="form-group col-md-6">
                            <label>Email ID</label>
                            <input type="email" name="email" class="form-control"
                                   value="{{ old('email') }}">
                        </div>
                    </div>

                    {{-- Address --}}
                    <div class="form-group">
                        <label>Address Line 1 <span class="text-danger">*</span></label>
                        <input type="text" name="address_1" class="form-control"
                               value="{{ old('address_1') }}" required>
                    </div>

                    <div class="form-group">
                        <label>Address Line 2</label>
                        <input type="text" name="address_2" class="form-control"
                               value="{{ old('address_2') }}">
                    </div>

                    {{-- Country / State / City / Pincode --}}
                    <div class="form-row">
                        {{-- Country --}}
                        <div class="form-group col-md-3">
                            <label>Country <span class="text-danger">*</span></label>
                            <select name="country" id="country_select" class="form-control" required>
                                <option value="">{{ __('Select Country') }}</option>
                                @foreach($countries as $country)
                                    <option value="{{ $country->name }}"
                                            data-id="{{ $country->id }}"
                                            {{ old('country', 'India') == $country->name ? 'selected' : '' }}>
                                        {{ $country->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- State --}}
                        <div class="form-group col-md-3">
                            <label>State <span class="text-danger">*</span></label>
                            <select name="state" id="state_select" class="form-control" required>
                                <option value="">{{ __('Select State') }}</option>
                                {{-- options via AJAX --}}
                            </select>
                        </div>

                        {{-- City --}}
                        <div class="form-group col-md-3">
                            <label>City <span class="text-danger">*</span></label>
                            <select name="city" id="city_select" class="form-control" required>
                                <option value="">{{ __('Select City') }}</option>
                                {{-- options via AJAX --}}
                            </select>
                        </div>

                        {{-- Pincode --}}
                        <div class="form-group col-md-3">
                            <label>Pincode <span class="text-danger">*</span></label>
                            <input type="text" name="pincode" class="form-control"
                                   value="{{ old('pincode') }}" required>
                        </div>
                    </div>

                    {{-- GSTIN / IEC / Phone --}}
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>GSTIN</label>
                            <input type="text"
                                   name="gstin"
                                   id="gstin_input"
                                   class="form-control"
                                   maxlength="15"
                                   value="{{ old('gstin') }}">
                            <small id="gstin_status" class="gstin-status text-muted">
                                Enter full 15-digit GSTIN to auto-fill company name & address from GST data.
                            </small>
                        </div>
                        <div class="form-group col-md-4">
                            <label>IEC No.</label>
                            <input type="text" name="iec_no" class="form-control"
                                   value="{{ old('iec_no') }}">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Phone</label>
                            <input type="text" name="phone" class="form-control"
                                   value="{{ old('phone') }}">
                        </div>
                    </div>

                    {{-- Buyer Stamp - Meta style upload with preview --}}
                    <div class="form-row">
                        <div class="form-group col-md-8">
                            <label>Buyer Stamp</label>
                            <div class="meta-upload-card">

                                {{-- LEFT: icon + text + button --}}
                                <div class="meta-upload-left">
                                    <div class="meta-upload-icon">
                                        <i class="las la-image"></i>
                                    </div>
                                    <div>
                                        <div class="meta-upload-text-title">
                                            Upload buyer stamp (image or PDF)
                                        </div>
                                        <div class="meta-upload-text-sub">
                                            Recommended: square stamp PNG/JPG. Max size 2 MB.
                                        </div>

                                        <button type="button"
                                                class="btn btn-sm btn-primary meta-upload-btn"
                                                id="buyer_stamp_btn">
                                            <i class="las la-upload mr-1"></i>
                                            Choose file
                                        </button>

                                        <input type="file"
                                               name="buyer_stamp"
                                               id="buyer_stamp_input"
                                               class="meta-upload-hidden-input"
                                               accept=".jpg,.jpeg,.png,.gif,.webp,.pdf">
                                    </div>
                                </div>

                                {{-- RIGHT: preview --}}
                                <div class="meta-upload-preview-box" id="buyer_stamp_preview">
                                    <div class="meta-upload-preview-placeholder" id="buyer_stamp_preview_placeholder">
                                        <i class="las la-images"></i>
                                        <div>No file selected</div>
                                        <div>Preview will appear here</div>
                                    </div>
                                </div>
                            </div>

                            <small class="text-muted d-block mt-1">
                                Supported formats: JPG, JPEG, PNG, GIF, WEBP or PDF.
                            </small>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="las la-save"></i> Save Company
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    (function ($) {
        "use strict";

        const stateRoute      = "{{ route('import.ajax.states') }}";
        const cityRoute       = "{{ route('import.ajax.cities') }}";
        const gstCheckRoute   = "{{ route('import.ajax.check_gstin') }}";

        // ✅ AppyFlow GST API config
        const gstApiUrl  = "https://appyflow.in/api/verifyGST";
        const gstApiKey  = "{{ env('APPYFLOW_KEYSECRET', '') }}";

        let gstinExists = false; // duplicate flag

        // ----- Helper: States load -----
        function loadStates(countryId, selectedState = null, cb = null) {
            $('#state_select').html('<option value="">{{ __('Select State') }}</option>');
            $('#city_select').html('<option value="">{{ __('Select City') }}</option>');

            if (!countryId) {
                if (typeof cb === 'function') cb();
                return;
            }

            $.get(stateRoute, { country_id: countryId }, function (data) {
                let options = '<option value="">{{ __('Select State') }}</option>';
                data.forEach(function (state) {
                    options += '<option value="'+ state.name +'" data-id="'+ state.id +'">'+ state.name +'</option>';
                });
                $('#state_select').html(options);

                if (selectedState) {
                    $('#state_select').val(selectedState);
                }

                if (typeof cb === 'function') cb();
            });
        }

        // ----- Helper: Cities load -----
        function loadCities(stateId, selectedCity = null, cb = null) {
            $('#city_select').html('<option value="">{{ __('Select City') }}</option>');

            if (!stateId) {
                if (typeof cb === 'function') cb();
                return;
            }

            $.get(cityRoute, { state_id: stateId }, function (data) {
                let options = '<option value="">{{ __('Select City') }}</option>';
                data.forEach(function (city) {
                    options += '<option value="'+ city.name +'">'+ city.name +'</option>';
                });
                $('#city_select').html(options);

                if (selectedCity) {
                    $('#city_select').val(selectedCity);
                }

                if (typeof cb === 'function') cb();
            });
        }

        // ----- Country change -> load states -----
        $('#country_select').on('change', function () {
            const countryId = $(this).find('option:selected').data('id');
            loadStates(countryId);
        });

        // ----- State change -> load cities -----
        $('#state_select').on('change', function () {
            const stateId = $(this).find('option:selected').data('id');
            loadCities(stateId);
        });

        // ===== GSTIN verify + duplicate check + auto-fill =====
        $('#gstin_input').on('blur', function () {
            let gstin = $(this).val().trim().toUpperCase();
            const statusEl = $('#gstin_status');
            gstinExists = false;

            if (!gstin) {
                statusEl
                    .removeClass('text-success text-danger')
                    .addClass('text-muted')
                    .text('Enter full 15-digit GSTIN to auto-fill company name & address from GST data.');
                return;
            }

            if (gstin.length !== 15) {
                statusEl
                    .removeClass('text-success text-muted')
                    .addClass('text-danger')
                    .text('GSTIN must be 15 characters. Currently: ' + gstin.length);
                return;
            }

            // 🔍 Step 1: DB duplicate check
            $.get(gstCheckRoute, { gstin: gstin }, function (res) {
                if (res && res.exists) {
                    gstinExists = true;
                    statusEl
                        .removeClass('text-success text-muted')
                        .addClass('text-danger')
                        .text('This GSTIN already exists in Import Companies. You cannot use it again.');
                    return;
                }

                // Agar duplicate nahi hai tab hi API call
                if (!gstApiKey) {
                    statusEl
                        .removeClass('text-success text-muted')
                        .addClass('text-danger')
                        .text('GST API key is not configured. Please set APPYFLOW_KEYSECRET in .env');
                    return;
                }

                statusEl
                    .removeClass('text-success text-danger')
                    .addClass('text-muted')
                    .text('Verifying GSTIN...');

                // Step 2: AppyFlow API call
                $.ajax({
                    url: gstApiUrl,
                    method: 'GET',
                    dataType: 'json',
                    data: {
                        gstNo: gstin,
                        key_secret: gstApiKey,
                        output_format: 'JSON'
                    },
                    success: function (response) {
                        try {
                            const info = response && response.taxpayerInfo ? response.taxpayerInfo : null;
                            if (!info || !info.pradr || !info.pradr.addr) {
                                statusEl
                                    .removeClass('text-success text-muted')
                                    .addClass('text-danger')
                                    .text('GSTIN verified, but address details not available.');
                                return;
                            }

                            const addr      = info.pradr.addr;
                            const legalName = info.lgnm || '';
                            const tradeName = info.tradeNam || '';

                            // Company Name
                            if (legalName || tradeName) {
                                $('#company_name_input').val(legalName || tradeName);
                            }

                            // Address 1 & 2
                            const cmp_address = [
                                addr.bnm || '',
                                addr.st  || '',
                                addr.loc || ''
                            ].filter(Boolean).join(', ');

                            const cmp_address2 = [
                                addr.bno || '',
                                addr.dst || ''
                            ].filter(Boolean).join(', ');

                            $('input[name="address_1"]').val(cmp_address);
                            $('input[name="address_2"]').val(cmp_address2);

                            // Country / State / City / Pincode
                            const countryName = 'India';
                            const stateName   = addr.stcd || '';
                            const cityName    = addr.loc  || '';
                            const pincode     = addr.pncd || '';

                            $('input[name="pincode"]').val(pincode);

                            // Country set = India, then states & cities
                            let selectedCountryOption = null;
                            $('#country_select option').each(function () {
                                if ($(this).val() === countryName) {
                                    selectedCountryOption = $(this);
                                    return false;
                                }
                            });

                            if (selectedCountryOption) {
                                $('#country_select').val(countryName);
                                const countryId = selectedCountryOption.data('id');

                                loadStates(countryId, stateName, function () {
                                    const stateId = $('#state_select').find('option:selected').data('id');
                                    loadCities(stateId, cityName);
                                });
                            }

                            statusEl
                                .removeClass('text-danger text-muted')
                                .addClass('text-success')
                                .text('GSTIN verified successfully. Company name & address auto-filled.');
                        } catch (e) {
                            console.error(e);
                            statusEl
                                .removeClass('text-success text-muted')
                                .addClass('text-danger')
                                .text('Unable to parse GST response. Please enter details manually.');
                        }
                    },
                    error: function (xhr) {
                        console.error(xhr);
                        statusEl
                            .removeClass('text-success text-muted')
                            .addClass('text-danger')
                            .text('Failed to verify GSTIN. Please check the number or try again later.');
                    }
                });
            });

        });

        // 🔐 Form submit guard – block if GSTIN duplicate hai
        $('#import_company_form').on('submit', function (e) {
            if (gstinExists) {
                e.preventDefault();
                alert('This GSTIN already exists in Import Companies. Please use a different GSTIN.');
            }
        });

        // ===== Buyer stamp upload (Meta style) =====
        $('#buyer_stamp_btn').on('click', function () {
            $('#buyer_stamp_input').trigger('click');
        });

        $('#buyer_stamp_input').on('change', function (e) {
            const file = e.target.files[0];
            const previewBox  = $('#buyer_stamp_preview');
            const placeholder = $('#buyer_stamp_preview_placeholder');

            if (!file) {
                previewBox.html(placeholder);
                return;
            }

            const ext = file.name.split('.').pop().toLowerCase();

            // PDF -> icon preview
            if (ext === 'pdf') {
                previewBox.html(
                    '<div class="meta-upload-preview-placeholder">' +
                        '<i class="las la-file-pdf"></i>' +
                        '<div>' + file.name + '</div>' +
                        '<div>PDF file selected</div>' +
                    '</div>'
                );
                return;
            }

            // Image -> thumbnail preview
            if (['jpg','jpeg','png','gif','webp'].indexOf(ext) !== -1) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    previewBox.html('<img src="'+ e.target.result +'" alt="Buyer Stamp Preview">');
                };
                reader.readAsDataURL(file);
            } else {
                // Unknown format
                previewBox.html(
                    '<div class="meta-upload-preview-placeholder">' +
                        '<i class="las la-file"></i>' +
                        '<div>' + file.name + '</div>' +
                        '<div>Preview not available</div>' +
                    '</div>'
                );
            }
        });

    })(jQuery);
</script>
@endsection

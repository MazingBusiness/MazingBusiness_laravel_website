@extends('backend.layouts.app')

@section('content')
<style>
    /* Meta-style upload card (same as Import Company) */
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

    /* Bank accounts section */
    .bank-account-card {
        border-radius: 10px;
        border: 1px solid #e5e7eb;
    }
    .bank-account-card .card-header {
        background: #f9fafb;
        border-bottom: 1px solid #e5e7eb;
        padding: 8px 12px;
    }
    .bank-account-card .card-body {
        padding: 12px;
    }
</style>

<div class="aiz-main-content">
    <div class="px-15px px-lg-25px">

        {{-- Page Header --}}
        <div class="row mb-3 align-items-center">
            <div class="col-md-8">
                <h4 class="mb-0">Add Supplier</h4>
                <small class="text-muted">
                    Create a new supplier used in Import BL / CI / Summary.
                </small>
            </div>
            <div class="col-md-4 text-md-right mt-3 mt-md-0">
                <a href="{{ route('import_suppliers.index') }}" class="btn btn-sm btn-secondary">
                    <i class="las la-list"></i> Supplier List
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
                <form action="{{ route('import_suppliers.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    {{-- Name + Email --}}
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Supplier Name <span class="text-danger">*</span></label>
                            <input type="text" name="supplier_name" class="form-control"
                                   value="{{ old('supplier_name') }}" required>
                        </div>

                        <div class="form-group col-md-6">
                            <label>Email ID</label>
                            <input type="email" name="email" class="form-control"
                                   value="{{ old('email') }}">
                        </div>
                    </div>

                    {{-- Address --}}
                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" name="address" class="form-control"
                               value="{{ old('address') }}">
                    </div>

                    {{-- Country / State / City / Zip --}}
                    <div class="form-row">
                        {{-- Country --}}
                        <div class="form-group col-md-3">
                            <label>Country</label>
                            <select name="country" id="country_select" class="form-control">
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

                        {{-- State / District --}}
                        <div class="form-group col-md-3">
                            <label>State / District</label>
                            <select name="district" id="state_select" class="form-control">
                                <option value="">{{ __('Select State') }}</option>
                                {{-- options via AJAX --}}
                            </select>
                        </div>

                        {{-- City --}}
                        <div class="form-group col-md-3">
                            <label>City</label>
                            <select name="city" id="city_select" class="form-control">
                                <option value="">{{ __('Select City') }}</option>
                                {{-- options via AJAX --}}
                            </select>
                        </div>

                        {{-- Zip --}}
                        <div class="form-group col-md-3">
                            <label>Zip / Postal Code</label>
                            <input type="text" name="zip_code" class="form-control"
                                   value="{{ old('zip_code') }}">
                        </div>
                    </div>

                    {{-- Contact --}}
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Contact No.</label>
                            <input type="text" name="contact" class="form-control"
                                   value="{{ old('contact') }}">
                        </div>
                    </div>

                    {{-- ===== Bank Accounts Section ===== --}}
                    @php
                        $oldBankAccounts = old('bank_accounts');
                    @endphp

                    <div class="mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="mb-0">Bank Accounts</h5>
                            <button type="button"
                                    class="btn btn-sm btn-outline-primary"
                                    id="add-bank-account">
                                <i class="las la-plus"></i> Add Bank Account
                            </button>
                        </div>
                        <small class="text-muted d-block mb-2">
                            Add one or more bank accounts for this supplier (e.g. USD bank, EUR bank, etc.).
                        </small>

                        <div id="bank-accounts-wrapper">
                            @if(is_array($oldBankAccounts) && count($oldBankAccounts))
                                @foreach($oldBankAccounts as $idx => $bank)
                                    @php
                                        $currency = $bank['currency'] ?? 'USD';
                                    @endphp
                                    <div class="card mb-3 bank-account-card" data-index="{{ $idx }}">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <strong>Bank Account #{{ $idx + 1 }}</strong>
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-bank-account">
                                                <i class="las la-trash-alt"></i> Remove
                                            </button>
                                        </div>
                                        <div class="card-body">
                                            <div class="form-row">
                                                <div class="form-group col-md-3">
                                                    <label>Currency</label>
                                                    <select name="bank_accounts[{{ $idx }}][currency]" class="form-control">
                                                        <option value="USD" {{ $currency === 'USD' ? 'selected' : '' }}>USD</option>
                                                        <option value="EUR" {{ $currency === 'EUR' ? 'selected' : '' }}>EUR</option>
                                                        <option value="INR" {{ $currency === 'INR' ? 'selected' : '' }}>INR</option>
                                                    </select>
                                                </div>
                                                <div class="form-group col-md-5">
                                                    <label>Intermediary Bank Name</label>
                                                    <input type="text"
                                                           name="bank_accounts[{{ $idx }}][intermediary_bank_name]"
                                                           class="form-control"
                                                           value="{{ $bank['intermediary_bank_name'] ?? '' }}"
                                                           placeholder="e.g. CITIBANK N.A., NEW YORK">
                                                </div>
                                                <div class="form-group col-md-4">
                                                    <label>Intermediary SWIFT Code</label>
                                                    <input type="text"
                                                           name="bank_accounts[{{ $idx }}][intermediary_swift_code]"
                                                           class="form-control"
                                                           value="{{ $bank['intermediary_swift_code'] ?? '' }}"
                                                           placeholder="e.g. CITIUS33">
                                                </div>
                                            </div>

                                            <div class="form-row">
                                                <div class="form-group col-md-6">
                                                    <label>Account Bank Name</label>
                                                    <input type="text"
                                                           name="bank_accounts[{{ $idx }}][account_bank_name]"
                                                           class="form-control"
                                                           value="{{ $bank['account_bank_name'] ?? '' }}"
                                                           placeholder="e.g. CHINA CONSTRUCTION BANK JINHUA BRANCH">
                                                </div>
                                                <div class="form-group col-md-6">
                                                    <label>Account Bank SWIFT Code</label>
                                                    <input type="text"
                                                           name="bank_accounts[{{ $idx }}][account_swift_code]"
                                                           class="form-control"
                                                           value="{{ $bank['account_swift_code'] ?? '' }}"
                                                           placeholder="e.g. PCBCCNBJZJG">
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label>Account Bank Address</label>
                                                <textarea name="bank_accounts[{{ $idx }}][account_bank_address]"
                                                          class="form-control"
                                                          rows="2"
                                                          placeholder="Bank address">{{ $bank['account_bank_address'] ?? '' }}</textarea>
                                            </div>

                                            <div class="form-row">
                                                <div class="form-group col-md-6">
                                                    <label>Beneficiary Name</label>
                                                    <input type="text"
                                                           name="bank_accounts[{{ $idx }}][beneficiary_name]"
                                                           class="form-control"
                                                           value="{{ $bank['beneficiary_name'] ?? '' }}"
                                                           placeholder="e.g. JINHUA TAS TIGER GLOBAL TRADE CO., LTD">
                                                </div>
                                                <div class="form-group col-md-6">
                                                    <label>Account Number</label>
                                                    <input type="text"
                                                           name="bank_accounts[{{ $idx }}][account_number]"
                                                           class="form-control"
                                                           value="{{ $bank['account_number'] ?? '' }}"
                                                           placeholder="e.g. 33050167722700002491">
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label>Beneficiary Address</label>
                                                <textarea name="bank_accounts[{{ $idx }}][beneficiary_address]"
                                                          class="form-control"
                                                          rows="2"
                                                          placeholder="Beneficiary address">{{ $bank['beneficiary_address'] ?? '' }}</textarea>
                                            </div>

                                            <div class="form-row">
                                                <div class="form-group col-md-4">
                                                    <label>Default</label>
                                                    <div class="form-check mt-2">
                                                        <input class="form-check-input bank-default-checkbox"
                                                               type="checkbox"
                                                               name="bank_accounts[{{ $idx }}][is_default]"
                                                               value="1"
                                                               {{ !empty($bank['is_default']) ? 'checked' : '' }}>
                                                        <label class="form-check-label">
                                                            Set as default account
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                {{-- Default first blank bank account --}}
                                <div class="card mb-3 bank-account-card" data-index="0">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <strong>Bank Account #1</strong>
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-bank-account">
                                            <i class="las la-trash-alt"></i> Remove
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-row">
                                            <div class="form-group col-md-3">
                                                <label>Currency</label>
                                                <select name="bank_accounts[0][currency]" class="form-control">
                                                    <option value="USD" selected>USD</option>
                                                    <option value="EUR">EUR</option>
                                                    <option value="INR">INR</option>
                                                </select>
                                            </div>
                                            <div class="form-group col-md-5">
                                                <label>Intermediary Bank Name</label>
                                                <input type="text"
                                                       name="bank_accounts[0][intermediary_bank_name]"
                                                       class="form-control"
                                                       placeholder="e.g. CITIBANK N.A., NEW YORK">
                                            </div>
                                            <div class="form-group col-md-4">
                                                <label>Intermediary SWIFT Code</label>
                                                <input type="text"
                                                       name="bank_accounts[0][intermediary_swift_code]"
                                                       class="form-control"
                                                       placeholder="e.g. CITIUS33">
                                            </div>
                                        </div>

                                        <div class="form-row">
                                            <div class="form-group col-md-6">
                                                <label>Account Bank Name</label>
                                                <input type="text"
                                                       name="bank_accounts[0][account_bank_name]"
                                                       class="form-control"
                                                       placeholder="e.g. CHINA CONSTRUCTION BANK JINHUA BRANCH">
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label>Account Bank SWIFT Code</label>
                                                <input type="text"
                                                       name="bank_accounts[0][account_swift_code]"
                                                       class="form-control"
                                                       placeholder="e.g. PCBCCNBJZJG">
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label>Account Bank Address</label>
                                            <textarea name="bank_accounts[0][account_bank_address]"
                                                      class="form-control"
                                                      rows="2"
                                                      placeholder="Bank address"></textarea>
                                        </div>

                                        <div class="form-row">
                                            <div class="form-group col-md-6">
                                                <label>Beneficiary Name</label>
                                                <input type="text"
                                                       name="bank_accounts[0][beneficiary_name]"
                                                       class="form-control"
                                                       placeholder="e.g. JINHUA TAS TIGER GLOBAL TRADE CO., LTD">
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label>Account Number</label>
                                                <input type="text"
                                                       name="bank_accounts[0][account_number]"
                                                       class="form-control"
                                                       placeholder="e.g. 33050167722700002491">
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label>Beneficiary Address</label>
                                            <textarea name="bank_accounts[0][beneficiary_address]"
                                                      class="form-control"
                                                      rows="2"
                                                      placeholder="Beneficiary address"></textarea>
                                        </div>

                                        <div class="form-row">
                                            <div class="form-group col-md-4">
                                                <label>Default</label>
                                                <div class="form-check mt-2">
                                                    <input class="form-check-input bank-default-checkbox"
                                                           type="checkbox"
                                                           name="bank_accounts[0][is_default]"
                                                           value="1"
                                                           checked>
                                                    <label class="form-check-label">
                                                        Set as default account
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Supplier Stamp - Meta style upload with preview --}}
                    <div class="form-group mt-4">
                        <label>Supplier Stamp (optional)</label>

                        <div class="meta-upload-card">

                            {{-- LEFT: icon + text + button --}}
                            <div class="meta-upload-left">
                                <div class="meta-upload-icon">
                                    <i class="las la-image"></i>
                                </div>
                                <div>
                                    <div class="meta-upload-text-title">
                                        Upload supplier stamp (image or PDF)
                                    </div>
                                    <div class="meta-upload-text-sub">
                                        Recommended: square stamp PNG/JPG. Max size 2 MB.
                                    </div>

                                    <button type="button"
                                            class="btn btn-sm btn-primary meta-upload-btn"
                                            id="supplier_stamp_btn">
                                        <i class="las la-upload mr-1"></i>
                                        Choose file
                                    </button>

                                    <input type="file"
                                           name="stamp"
                                           id="supplier_stamp_input"
                                           class="meta-upload-hidden-input"
                                           accept=".jpg,.jpeg,.png,.gif,.webp,.pdf">
                                </div>
                            </div>

                            {{-- RIGHT: preview --}}
                            <div class="meta-upload-preview-box" id="supplier_stamp_preview">
                                <div class="meta-upload-preview-placeholder" id="supplier_stamp_preview_placeholder">
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

                    <button type="submit" class="btn btn-primary">
                        <i class="las la-save"></i> Save Supplier
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

        const stateRoute = "{{ route('import.ajax.states') }}";
        const cityRoute  = "{{ route('import.ajax.cities') }}";

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

        // ----- On page load: if old country/state/city values hain to reload -----
        $(document).ready(function () {
            const oldCountry = "{{ old('country', 'India') }}";
            const oldState   = "{{ old('district') }}";
            const oldCity    = "{{ old('city') }}";

            let selectedCountryOption = null;
            $('#country_select option').each(function () {
                if ($(this).val() === oldCountry) {
                    selectedCountryOption = $(this);
                    return false;
                }
            });

            if (selectedCountryOption) {
                $('#country_select').val(oldCountry);
                const countryId = selectedCountryOption.data('id');

                loadStates(countryId, oldState, function () {
                    const stateId = $('#state_select').find('option:selected').data('id');
                    loadCities(stateId, oldCity);
                });
            }
        });

        // ===== Supplier stamp upload preview (Meta style) =====
        $('#supplier_stamp_btn').on('click', function () {
            $('#supplier_stamp_input').trigger('click');
        });

        $('#supplier_stamp_input').on('change', function (e) {
            const file = e.target.files[0];
            const previewBox  = $('#supplier_stamp_preview');
            const placeholder = $('#supplier_stamp_preview_placeholder');

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
                    previewBox.html('<img src="'+ e.target.result +'" alt="Supplier Stamp Preview">');
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

        // ===== Bank Accounts dynamic add/remove =====
        let bankIndex = $('#bank-accounts-wrapper .bank-account-card').length;
        if (!bankIndex) {
            bankIndex = 1;
        }

        function addBankAccountCard(index) {
            const template =
                '<div class="card mb-3 bank-account-card" data-index="'+ index +'">' +
                    '<div class="card-header d-flex justify-content-between align-items-center">' +
                        '<strong>Bank Account #' + (index + 1) + '</strong>' +
                        '<button type="button" class="btn btn-sm btn-outline-danger remove-bank-account">' +
                            '<i class="las la-trash-alt"></i> Remove' +
                        '</button>' +
                    '</div>' +
                    '<div class="card-body">' +
                        '<div class="form-row">' +
                            '<div class="form-group col-md-3">' +
                                '<label>Currency</label>' +
                                '<select name="bank_accounts['+ index +'][currency]" class="form-control">' +
                                    '<option value="USD" selected>USD</option>' +
                                    '<option value="EUR">EUR</option>' +
                                    '<option value="INR">INR</option>' +
                                '</select>' +
                            '</div>' +
                            '<div class="form-group col-md-5">' +
                                '<label>Intermediary Bank Name</label>' +
                                '<input type="text" name="bank_accounts['+ index +'][intermediary_bank_name]" class="form-control" placeholder="e.g. CITIBANK N.A., NEW YORK">' +
                            '</div>' +
                            '<div class="form-group col-md-4">' +
                                '<label>Intermediary SWIFT Code</label>' +
                                '<input type="text" name="bank_accounts['+ index +'][intermediary_swift_code]" class="form-control" placeholder="e.g. CITIUS33">' +
                            '</div>' +
                        '</div>' +
                        '<div class="form-row">' +
                            '<div class="form-group col-md-6">' +
                                '<label>Account Bank Name</label>' +
                                '<input type="text" name="bank_accounts['+ index +'][account_bank_name]" class="form-control" placeholder="e.g. CHINA CONSTRUCTION BANK JINHUA BRANCH">' +
                            '</div>' +
                            '<div class="form-group col-md-6">' +
                                '<label>Account Bank SWIFT Code</label>' +
                                '<input type="text" name="bank_accounts['+ index +'][account_swift_code]" class="form-control" placeholder="e.g. PCBCCNBJZJG">' +
                            '</div>' +
                        '</div>' +
                        '<div class="form-group">' +
                            '<label>Account Bank Address</label>' +
                            '<textarea name="bank_accounts['+ index +'][account_bank_address]" class="form-control" rows="2" placeholder="Bank address"></textarea>' +
                        '</div>' +
                        '<div class="form-row">' +
                            '<div class="form-group col-md-6">' +
                                '<label>Beneficiary Name</label>' +
                                '<input type="text" name="bank_accounts['+ index +'][beneficiary_name]" class="form-control" placeholder="e.g. JINHUA TAS TIGER GLOBAL TRADE CO., LTD">' +
                            '</div>' +
                            '<div class="form-group col-md-6">' +
                                '<label>Account Number</label>' +
                                '<input type="text" name="bank_accounts['+ index +'][account_number]" class="form-control" placeholder="e.g. 33050167722700002491">' +
                            '</div>' +
                        '</div>' +
                        '<div class="form-group">' +
                            '<label>Beneficiary Address</label>' +
                            '<textarea name="bank_accounts['+ index +'][beneficiary_address]" class="form-control" rows="2" placeholder="Beneficiary address"></textarea>' +
                        '</div>' +
                        '<div class="form-row">' +
                            '<div class="form-group col-md-4">' +
                                '<label>Default</label>' +
                                '<div class="form-check mt-2">' +
                                    '<input class="form-check-input bank-default-checkbox" type="checkbox" name="bank_accounts['+ index +'][is_default]" value="1">' +
                                    '<label class="form-check-label">Set as default account</label>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>';

            $('#bank-accounts-wrapper').append(template);
        }

        $('#add-bank-account').on('click', function () {
            addBankAccountCard(bankIndex);
            bankIndex++;
        });

        $(document).on('click', '.remove-bank-account', function () {
            $(this).closest('.bank-account-card').remove();

            // If all removed, add one blank card
            if ($('#bank-accounts-wrapper .bank-account-card').length === 0) {
                addBankAccountCard(0);
                bankIndex = 1;
            }
        });

        // Ensure only one default account is checked
        $(document).on('change', '.bank-default-checkbox', function () {
            if ($(this).is(':checked')) {
                $('.bank-default-checkbox').not(this).prop('checked', false);
            }
        });

    })(jQuery);
</script>
@endsection
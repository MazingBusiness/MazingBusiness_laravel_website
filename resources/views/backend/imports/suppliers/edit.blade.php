@extends('backend.layouts.app')

@section('content')
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">{{ translate('Edit Supplier') }}</h5>
    </div>

    <div class="card-body">
        <form action="{{ route('import_suppliers.update', $supplier->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            {{-- Supplier basic details --}}
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="col-form-label">{{ translate('Supplier Name') }} <span class="text-danger">*</span></label>
                        <input type="text"
                               name="supplier_name"
                               class="form-control @error('supplier_name') is-invalid @enderror"
                               value="{{ old('supplier_name', $supplier->supplier_name) }}"
                               required>
                        @error('supplier_name')
                        <small class="text-danger d-block">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="col-form-label">{{ translate('Address') }}</label>
                        <input type="text"
                               name="address"
                               class="form-control"
                               value="{{ old('address', $supplier->address) }}">
                    </div>

                    <div class="form-group">
                        <label class="col-form-label">{{ translate('City') }}</label>
                        <input type="text"
                               name="city"
                               class="form-control"
                               value="{{ old('city', $supplier->city) }}">
                    </div>

                    <div class="form-group">
                        <label class="col-form-label">{{ translate('State / District') }}</label>
                        <input type="text"
                               name="district"
                               class="form-control"
                               value="{{ old('district', $supplier->district) }}">
                    </div>

                    <div class="form-group">
                        <label class="col-form-label">{{ translate('Country') }}</label>
                        <input type="text"
                               name="country"
                               class="form-control"
                               value="{{ old('country', $supplier->country) }}">
                    </div>

                    <div class="form-group">
                        <label class="col-form-label">{{ translate('Zip / Postal Code') }}</label>
                        <input type="text"
                               name="zip_code"
                               class="form-control"
                               value="{{ old('zip_code', $supplier->zip_code) }}">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label class="col-form-label">{{ translate('Contact Number') }}</label>
                        <input type="text"
                               name="contact"
                               class="form-control"
                               value="{{ old('contact', $supplier->contact) }}">
                    </div>

                    <div class="form-group">
                        <label class="col-form-label">{{ translate('Email') }}</label>
                        <input type="email"
                               name="email"
                               class="form-control"
                               value="{{ old('email', $supplier->email) }}">
                    </div>

                    <div class="form-group">
                        <label class="col-form-label d-block">
                            {{ translate('Supplier Stamp (JPG / PNG / PDF)') }}
                        </label>

                        @if($supplier->stamp)
                            <p class="mb-2">
                                @if($supplier->stamp)
                                    <img src="{{ env('UPLOADS_BASE_URL') . '/' . ltrim($supplier->stamp, '/') }}"
                                         alt="Supplier Stamp"
                                         style="max-height: 80px;">
                                @endif
                            </p>
                        @endif

                        <input type="file"
                               name="stamp"
                               class="form-control-file @error('stamp') is-invalid @enderror">
                        <small class="text-muted d-block">
                            {{ translate('Leave blank to keep existing stamp.') }}
                        </small>
                        @error('stamp')
                        <small class="text-danger d-block">{{ $message }}</small>
                        @enderror
                    </div>
                </div>
            </div>

            <hr>

            {{-- Bank Accounts --}}
                @php
                    // If validation failed, prefer old() data
                    $oldBankAccounts = old('bank_accounts');
                
                    if (!empty($oldBankAccounts)) {
                        $bankAccounts = collect($oldBankAccounts);
                    } else {
                        // Use existing accounts from DB or at least one empty
                        $bankAccounts = $supplier->bankAccounts->count()
                            ? $supplier->bankAccounts
                            : collect([new \App\Models\SupplierBankAccount()]);
                    }
                @endphp
                
                <hr class="mt-4 mb-3">
                
                <h5 class="mb-3">Bank Accounts</h5>
                
                <div id="bank-accounts-wrapper">
                    @foreach ($bankAccounts as $index => $bank)
                        <div class="card mb-3 bank-account-card" data-index="{{ $index }}">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">Account #{{ $index + 1 }}</h6>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-danger remove-bank-account">
                                        Remove
                                    </button>
                                </div>
                
                                <div class="row">
                                    {{-- Currency --}}
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Currency</label>
                                        <input type="text"
                                               class="form-control"
                                               name="bank_accounts[{{ $index }}][currency]"
                                               value="{{ old("bank_accounts.$index.currency", $bank->currency ?? 'USD') }}"
                                               placeholder="USD">
                                    </div>
                
                                    {{-- Intermediary Bank Name --}}
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Intermediary Bank Name</label>
                                        <input type="text"
                                               class="form-control"
                                               name="bank_accounts[{{ $index }}][intermediary_bank_name]"
                                               value="{{ old("bank_accounts.$index.intermediary_bank_name", $bank->intermediary_bank_name ?? '') }}">
                                    </div>
                
                                    {{-- Intermediary SWIFT Code --}}
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Intermediary SWIFT Code</label>
                                        <input type="text"
                                               class="form-control"
                                               name="bank_accounts[{{ $index }}][intermediary_swift_code]"
                                               value="{{ old("bank_accounts.$index.intermediary_swift_code", $bank->intermediary_swift_code ?? '') }}">
                                    </div>
                
                                    {{-- Account Bank Name --}}
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Account Bank Name</label>
                                        <input type="text"
                                               class="form-control"
                                               name="bank_accounts[{{ $index }}][account_bank_name]"
                                               value="{{ old("bank_accounts.$index.account_bank_name", $bank->account_bank_name ?? '') }}">
                                    </div>
                
                                    {{-- Account SWIFT Code --}}
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Account SWIFT Code</label>
                                        <input type="text"
                                               class="form-control"
                                               name="bank_accounts[{{ $index }}][account_swift_code]"
                                               value="{{ old("bank_accounts.$index.account_swift_code", $bank->account_swift_code ?? '') }}">
                                    </div>
                
                                    {{-- Account Bank Address --}}
                                    <div class="col-md-9 mb-3">
                                        <label class="form-label">Account Bank Address</label>
                                        <input type="text"
                                               class="form-control"
                                               name="bank_accounts[{{ $index }}][account_bank_address]"
                                               value="{{ old("bank_accounts.$index.account_bank_address", $bank->account_bank_address ?? '') }}">
                                    </div>
                
                                    {{-- Beneficiary Name --}}
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Beneficiary Name</label>
                                        <input type="text"
                                               class="form-control"
                                               name="bank_accounts[{{ $index }}][beneficiary_name]"
                                               value="{{ old("bank_accounts.$index.beneficiary_name", $bank->beneficiary_name ?? '') }}">
                                    </div>
                
                                    {{-- Beneficiary Address --}}
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Beneficiary Address</label>
                                        <input type="text"
                                               class="form-control"
                                               name="bank_accounts[{{ $index }}][beneficiary_address]"
                                               value="{{ old("bank_accounts.$index.beneficiary_address", $bank->beneficiary_address ?? '') }}">
                                    </div>
                
                                    {{-- Account Number --}}
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Account Number</label>
                                        <input type="text"
                                               class="form-control"
                                               name="bank_accounts[{{ $index }}][account_number]"
                                               value="{{ old("bank_accounts.$index.account_number", $bank->account_number ?? '') }}">
                                    </div>
                
                                    {{-- Is Default --}}
                                    <div class="col-md-1 mb-3 d-flex align-items-end">
                                        @php
                                            $isDefaultOld = old("bank_accounts.$index.is_default");
                                            $isDefault = !is_null($isDefaultOld)
                                                ? (bool)$isDefaultOld
                                                : (bool)($bank->is_default ?? false);
                                        @endphp
                                        <div class="form-check">
                                            <input type="checkbox"
                                                   class="form-check-input"
                                                   id="bank_default_{{ $index }}"
                                                   name="bank_accounts[{{ $index }}][is_default]"
                                                   value="1"
                                                   {{ $isDefault ? 'checked' : '' }}>
                                            <label class="form-check-label" for="bank_default_{{ $index }}">
                                                Default
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                
                <button type="button"
                        class="btn btn-outline-primary btn-sm"
                        id="add-bank-account">
                    Add another bank account
                </button>
                
                {{-- Hidden template for NEW bank account blocks --}}
                <template id="bank-account-template">
                    <div class="card mb-3 bank-account-card" data-index="__INDEX__">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Account #__ACCOUNT_NO__</h6>
                                <button type="button"
                                        class="btn btn-sm btn-outline-danger remove-bank-account">
                                    Remove
                                </button>
                            </div>
                
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Currency</label>
                                    <input type="text"
                                           class="form-control"
                                           name="bank_accounts[__INDEX__][currency]"
                                           value="USD"
                                           placeholder="USD">
                                </div>
                
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Intermediary Bank Name</label>
                                    <input type="text"
                                           class="form-control"
                                           name="bank_accounts[__INDEX__][intermediary_bank_name]">
                                </div>
                
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Intermediary SWIFT Code</label>
                                    <input type="text"
                                           class="form-control"
                                           name="bank_accounts[__INDEX__][intermediary_swift_code]">
                                </div>
                
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Account Bank Name</label>
                                    <input type="text"
                                           class="form-control"
                                           name="bank_accounts[__INDEX__][account_bank_name]">
                                </div>
                
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Account SWIFT Code</label>
                                    <input type="text"
                                           class="form-control"
                                           name="bank_accounts[__INDEX__][account_swift_code]">
                                </div>
                
                                <div class="col-md-9 mb-3">
                                    <label class="form-label">Account Bank Address</label>
                                    <input type="text"
                                           class="form-control"
                                           name="bank_accounts[__INDEX__][account_bank_address]">
                                </div>
                
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Beneficiary Name</label>
                                    <input type="text"
                                           class="form-control"
                                           name="bank_accounts[__INDEX__][beneficiary_name]">
                                </div>
                
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Beneficiary Address</label>
                                    <input type="text"
                                           class="form-control"
                                           name="bank_accounts[__INDEX__][beneficiary_address]">
                                </div>
                
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Account Number</label>
                                    <input type="text"
                                           class="form-control"
                                           name="bank_accounts[__INDEX__][account_number]">
                                </div>
                
                                <div class="col-md-1 mb-3 d-flex align-items-end">
                                    <div class="form-check">
                                        <input type="checkbox"
                                               class="form-check-input"
                                               id="bank_default___INDEX__"
                                               name="bank_accounts[__INDEX__][is_default]"
                                               value="1">
                                        <label class="form-check-label" for="bank_default___INDEX__">
                                            Default
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

            <div class="text-right">
                <button type="submit" class="btn btn-primary">
                    {{ translate('Update Supplier') }}
                </button>
                <a href="{{ route('import_suppliers.index') }}" class="btn btn-secondary">
                    {{ translate('Cancel') }}
                </a>
            </div>
        </form>
    </div>
</div>

{{-- simple JS to clone a bank-account-row --}}
@section('script')
<script>
    (function () {
        let wrapper = document.getElementById('bank-accounts-wrapper');
        let addBtn  = document.getElementById('add-new-bank-row');

        if (!wrapper || !addBtn) return;

        addBtn.addEventListener('click', function (e) {
            e.preventDefault();
            let rows = wrapper.querySelectorAll('.bank-account-row');
            let index = rows.length;

            let clone = rows[rows.length - 1].cloneNode(true);

            // reset inputs & update indexes
            clone.querySelectorAll('input, textarea').forEach(function (input) {
                let name = input.getAttribute('name') || '';
                name = name.replace(/\[\d+\]/, '[' + index + ']');
                input.setAttribute('name', name);

                if (input.type === 'checkbox') {
                    input.checked = false;
                } else {
                    input.value = '';
                }
            });

            wrapper.appendChild(clone);
        });

        wrapper.addEventListener('click', function (e) {
            if (e.target.classList.contains('remove-bank-account')) {
                e.preventDefault();
                let row = e.target.closest('.bank-account-row');
                if (!row) return;

                if (wrapper.querySelectorAll('.bank-account-row').length > 1) {
                    row.remove();
                }
            }
        });
    })();
</script>
<script>
    (function ($) {
        // Start index after last existing account
        let nextBankIndex = {{ $bankAccounts->count() }};

        // Add new bank account
        $('#add-bank-account').on('click', function () {
            let tpl = $('#bank-account-template').html();

            tpl = tpl.replace(/__INDEX__/g, nextBankIndex)
                     .replace(/__ACCOUNT_NO__/g, nextBankIndex + 1);

            $('#bank-accounts-wrapper').append(tpl);
            nextBankIndex++;
        });

        // Remove a bank account block
        $(document).on('click', '.remove-bank-account', function () {
            $(this).closest('.bank-account-card').remove();
        });
    })(jQuery);
</script>
@endsection
@endsection
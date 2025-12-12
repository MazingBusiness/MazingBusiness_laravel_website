@extends('backend.layouts.app')

@section('content')
<div class="aiz-main-content">
    <div class="px-15px px-lg-25px">

        {{-- Page Header --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="mb-1">{{ translate('PDF Content Mapping') }}</h5>
                <div class="small text-muted">
                    {{ translate('Map offers / products / categories / brands with a PDF link.') }}
                </div>
            </div>
        </div>

        {{-- Alerts --}}
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row">
            {{-- LEFT: Form --}}
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">{{ translate('Create / Update PDF Content') }}</h6>
                    </div>
                    <div class="card-body">

                        <form action="{{ route('pdf_contents.store') }}" method="POST" id="pdf-content-form">
                            @csrf

                            {{-- PDF Type (SELECT) --}}
                            <div class="form-group row">
                                <label class="col-md-3 col-from-label">
                                    {{ translate('PDF Type') }}
                                </label>
                                <div class="col-md-9">
                                    <select name="pdf_type" class="form-control aiz-selectpicker" required>
                                        <option value="">{{ translate('Select PDF Type') }}</option>

                                        <option value="invoice">Invoice</option>
                                        <option value="statement">Statement</option>
                                        <option value="abandon_cart">Abandon Cart</option>
                                        <option value="early_payment_reminder">Early Payment reminder</option>
                                        <option value="order">Order</option>
                                        <option value="quotation">Quotation</option>
                                        <option value="reward_statement">Reward statement</option>
                                        <option value="price_list">Price list</option>
                                    </select>
                                </div>
                            </div>

                            {{-- PDF URL --}}
                            <div class="form-group row">
                                <label class="col-md-3 col-from-label">
                                    {{ translate('URL (Optional)') }}
                                </label>
                                <div class="col-md-9">
                                    <input type="text" name="url" class="form-control"
                                           placeholder="https://... or relative path">
                                </div>
                            </div>

                            {{-- Placement Type (first / last) --}}
                            <div class="form-group row">
                                <label class="col-md-3 col-from-label">
                                    {{ translate('Placement Type') }}
                                </label>
                                <div class="col-md-9">
                                    <select name="placement_type" class="form-control aiz-selectpicker">
                                        <option value="">{{ translate('Select Placement Type') }}</option>
                                        <option value="first">{{ translate('First') }}</option>
                                        <option value="last">{{ translate('Last') }}</option>
                                    </select>
                                    <small class="text-muted">
                                        {{ translate('Choose whether this PDF will be used first or last for this type.') }}
                                    </small>
                                </div>
                            </div>


                            {{-- Posters per Row --}}
                            <div class="form-group row">
                                <label class="col-md-3 col-from-label">
                                    {{ translate('Posters per Row') }}
                                </label>
                                <div class="col-md-9">
                                    <input type="number"
                                           name="no_of_poster"
                                           class="form-control"
                                           min="0"
                                           placeholder="{{ translate('Enter posters per row') }}">
                                    <small class="text-muted">
                                        {{ translate('Enter posters per row') }}
                                    </small>
                                </div>
                            </div>


                            {{-- Content Type --}}
                            <div class="form-group row">
                                <label class="col-md-3 col-from-label">
                                    {{ translate('Content Type') }}
                                </label>
                                <div class="col-md-9">
                                    <select name="content_type" id="content_type"
                                            class="form-control aiz-selectpicker" required>
                                        <option value="">{{ translate('Select Content Type') }}</option>
                                        <option value="offer">{{ translate('Offer') }}</option>
                                        <option value="product">{{ translate('Product') }}</option>
                                        <option value="category">{{ translate('Category') }}</option>
                                        <option value="brand">{{ translate('Brand') }}</option>
                                    </select>
                                </div>
                            </div>

                            {{-- Hidden: comma separated IDs --}}
                            <input type="hidden" name="content_ids" id="content_ids" value="">

                            {{-- ================= OFFER SECTION (MULTI SELECT + TABLE) ================= --}}
                            <div id="section-offer" class="content-section mt-4 d-none">
                                <h6 class="mb-2">{{ translate('Select Offers') }}</h6>

                                <div class="form-group row">
                                    <label class="col-md-3 col-from-label">{{ translate('Offers') }}</label>
                                    <div class="col-md-9">
                                        <select id="offer_ids"
                                                class="form-control aiz-selectpicker"
                                                data-live-search="true"
                                                multiple
                                                data-selected-text-format="count > 3">
                                            @foreach($offers as $offer)
                                                <option value="{{ $offer->id }}"
                                                        data-name="{{ $offer->offer_name ?? $offer->offer_id }}"
                                                        data-banner="{{ $offer->offer_banner ? uploaded_asset($offer->offer_banner) : '' }}"
                                                        data-start="{{ $offer->offer_validity_start }}"
                                                        data-end="{{ $offer->offer_validity_end }}">
                                                    {{ $offer->offer_name ?? $offer->offer_id }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">
                                            {{ translate('You can select multiple offers.') }}
                                        </small>
                                    </div>
                                </div>

                                <div class="table-responsive mt-3" style="max-height: 320px; overflow-y:auto;">
                                    <table class="table table-sm table-bordered mb-0" id="selected-offers-table">
                                        <thead>
                                            <tr>
                                                <th width="40"></th>
                                                <th>{{ translate('Banner') }}</th>
                                                <th>{{ translate('Offer Name') }}</th>
                                                <th>{{ translate('Validity') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {{-- JS will fill --}}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- ================= PRODUCT SECTION ================= --}}
                            <div id="section-product" class="content-section mt-4 d-none">
                                <h6 class="mb-2">{{ translate('Search & Add Products by Part No') }}</h6>

                                <div class="form-group row">
                                    <label class="col-md-3 col-from-label">{{ translate('Part No') }}</label>
                                    <div class="col-md-7">
                                        <input type="text" id="product-search-input"
                                               class="form-control"
                                               placeholder="{{ translate('Type part no and press Enter') }}">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" id="btn-add-product"
                                                class="btn btn-primary btn-block">
                                            {{ translate('Add') }}
                                        </button>
                                    </div>
                                </div>

                                <div class="table-responsive" style="max-height: 320px; overflow-y:auto;">
                                    <table class="table table-sm table-bordered mb-0"
                                           id="selected-products-table">
                                        <thead>
                                            <tr>
                                                <th width="40"></th>
                                                <th>{{ translate('Image') }}</th>
                                                <th>{{ translate('Part No') }}</th>
                                                <th>{{ translate('Name') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {{-- JS will append rows --}}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- ================= CATEGORY SECTION (MULTI SELECT + TABLE) ================= --}}
                            <div id="section-category" class="content-section mt-4 d-none">
                                <h6 class="mb-2">{{ translate('Select Categories') }}</h6>

                                <div class="form-group row">
                                    <label class="col-md-3 col-from-label">{{ translate('Categories') }}</label>
                                    <div class="col-md-9">
                                        <select id="category_ids"
                                                class="form-control aiz-selectpicker"
                                                data-live-search="true"
                                                multiple
                                                data-selected-text-format="count > 3">
                                            @foreach($categories as $cat)
                                                <option value="{{ $cat->id }}"
                                                        data-name="{{ $cat->name }}"
                                                        data-banner="{{ !empty($cat->banner) ? uploaded_asset($cat->banner) : '' }}">
                                                    {{ $cat->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">
                                            {{ translate('You can select multiple categories.') }}
                                        </small>
                                    </div>
                                </div>

                                <div class="table-responsive mt-3" style="max-height: 320px; overflow-y:auto;">
                                    <table class="table table-sm table-bordered mb-0" id="selected-categories-table">
                                        <thead>
                                            <tr>
                                                <th width="40"></th>
                                                <th>{{ translate('Image') }}</th>
                                                <th>{{ translate('Category Name') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {{-- JS will fill --}}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- ================= BRAND SECTION (MULTI SELECT + TABLE) ================= --}}
                            <div id="section-brand" class="content-section mt-4 d-none">
                                <h6 class="mb-2">{{ translate('Select Brands') }}</h6>

                                <div class="form-group row">
                                    <label class="col-md-3 col-from-label">{{ translate('Brands') }}</label>
                                    <div class="col-md-9">
                                        <select id="brand_ids"
                                                class="form-control aiz-selectpicker"
                                                data-live-search="true"
                                                multiple
                                                data-selected-text-format="count > 3">
                                            @foreach($brands as $brand)
                                                <option value="{{ $brand->id }}"
                                                        data-name="{{ $brand->name }}"
                                                        data-logo="{{ !empty($brand->logo) ? uploaded_asset($brand->logo) : '' }}">
                                                    {{ $brand->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">
                                            {{ translate('You can select multiple brands.') }}
                                        </small>
                                    </div>
                                </div>

                                <div class="table-responsive mt-3" style="max-height: 320px; overflow-y:auto;">
                                    <table class="table table-sm table-bordered mb-0" id="selected-brands-table">
                                        <thead>
                                            <tr>
                                                <th width="40"></th>
                                                <th>{{ translate('Logo') }}</th>
                                                <th>{{ translate('Brand Name') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {{-- JS will fill --}}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- SUBMIT --}}
                            <div class="text-right mt-4">
                                <button type="submit" class="btn btn-primary">
                                    {{ translate('Save PDF Content') }}
                                </button>
                            </div>

                        </form>

                    </div>
                </div>
            </div>

            {{-- RIGHT: Existing mappings --}}
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">{{ translate('Existing PDF Contents') }}</h6>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>{{ translate('Type') }}</th>
                                    <th>{{ translate('Content IDs') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pdfContents as $row)
                                    <tr>
                                        <td class="align-middle">
                                            <div class="small font-weight-bold">{{ $row->pdf_type }}</div>
                                            <div class="small text-muted">{{ $row->content_type }}</div>
                                        </td>
                                        <td class="align-middle small">
                                            {{ \Illuminate\Support\Str::limit($row->content_products, 40) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="text-center text-muted">
                                            {{ translate('No records yet.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="card-footer">
                        {{ $pdfContents->links() }}
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>
@endsection

@section('script')
<script>
    (function () {
        'use strict';

        let selectedProductIds = [];

        const $contentType        = $('#content_type');
        const $contentIds         = $('#content_ids');
        const $productTableBody   = $('#selected-products-table tbody');
        const $offerTableBody     = $('#selected-offers-table tbody');
        const $categoryTableBody  = $('#selected-categories-table tbody');
        const $brandTableBody     = $('#selected-brands-table tbody');

        function resetAllSelections() {
            $contentIds.val('');
            selectedProductIds = [];
            $productTableBody.empty();

            $('#offer_ids').val([]).change();
            $('#category_ids').val([]).change();
            $('#brand_ids').val([]).change();

            $offerTableBody.empty();
            $categoryTableBody.empty();
            $brandTableBody.empty();
        }

        // Switch visible section based on content_type
        function switchSection() {
            $('.content-section').addClass('d-none');
            resetAllSelections();

            const type = $contentType.val();

            if (type === 'offer') {
                $('#section-offer').removeClass('d-none');
            } else if (type === 'product') {
                $('#section-product').removeClass('d-none');
            } else if (type === 'category') {
                $('#section-category').removeClass('d-none');
            } else if (type === 'brand') {
                $('#section-brand').removeClass('d-none');
            }
        }

        $contentType.on('change', switchSection);
        switchSection(); // initial: sab hidden

        // ---------- OFFERS: multi-select + table ----------
        function refreshOfferTableAndIds() {
            const ids = $('#offer_ids').val() || [];
            $contentIds.val(ids.join(','));

            $offerTableBody.empty();

            $('#offer_ids option:selected').each(function () {
                const $opt   = $(this);
                const id     = $opt.val();
                const name   = $opt.data('name') || '';
                const banner = $opt.data('banner') || '';
                const start  = $opt.data('start') || '';
                const end    = $opt.data('end') || '';

                const imgHtml = banner
                    ? `<img src="${banner}" alt="banner" style="height:40px;">`
                    : `<span class="text-muted">{{ translate('No Banner') }}</span>`;

                const validity = (start || end)
                    ? `${start ?? ''} &rarr; ${end ?? ''}`
                    : `<span class="text-muted">{{ translate('Not Set') }}</span>`;

                const row = `
                    <tr data-id="${id}">
                        <td class="text-center align-middle">
                            <button type="button" class="btn btn-xs btn-danger btn-remove-offer-row">
                                <i class="las la-times"></i>
                            </button>
                        </td>
                        <td class="align-middle">${imgHtml}</td>
                        <td class="align-middle">${name}</td>
                        <td class="align-middle small">${validity}</td>
                    </tr>
                `;
                $offerTableBody.append(row);
            });
        }

        $('#offer_ids').on('changed.bs.select', function () {
            refreshOfferTableAndIds();
        });

        // remove row -> deselect offer from select
        $(document).on('click', '.btn-remove-offer-row', function () {
            const $tr = $(this).closest('tr');
            const id  = $tr.data('id').toString();

            const $select = $('#offer_ids');
            let vals = $select.val() || [];
            vals = vals.filter(function (v) { return v !== id; });
            $select.val(vals);
            $select.selectpicker('refresh');
            refreshOfferTableAndIds();
        });

        // ---------- CATEGORIES: multi-select + table ----------
        function refreshCategoryTableAndIds() {
            const ids = $('#category_ids').val() || [];
            $contentIds.val(ids.join(','));

            $categoryTableBody.empty();

            $('#category_ids option:selected').each(function () {
                const $opt   = $(this);
                const id     = $opt.val();
                const name   = $opt.data('name') || '';
                const banner = $opt.data('banner') || '';

                const imgHtml = banner
                    ? `<img src="${banner}" alt="banner" style="height:40px;">`
                    : `<span class="text-muted">{{ translate('No Image') }}</span>`;

                const row = `
                    <tr data-id="${id}">
                        <td class="text-center align-middle">
                            <button type="button" class="btn btn-xs btn-danger btn-remove-category-row">
                                <i class="las la-times"></i>
                            </button>
                        </td>
                        <td class="align-middle">${imgHtml}</td>
                        <td class="align-middle">${name}</td>
                    </tr>
                `;
                $categoryTableBody.append(row);
            });
        }

        $('#category_ids').on('changed.bs.select', function () {
            refreshCategoryTableAndIds();
        });

        $(document).on('click', '.btn-remove-category-row', function () {
            const $tr = $(this).closest('tr');
            const id  = $tr.data('id').toString();

            const $select = $('#category_ids');
            let vals = $select.val() || [];
            vals = vals.filter(function (v) { return v !== id; });
            $select.val(vals);
            $select.selectpicker('refresh');
            refreshCategoryTableAndIds();
        });

        // ---------- BRANDS: multi-select + table ----------
        function refreshBrandTableAndIds() {
            const ids = $('#brand_ids').val() || [];
            $contentIds.val(ids.join(','));

            $brandTableBody.empty();

            $('#brand_ids option:selected').each(function () {
                const $opt  = $(this);
                const id    = $opt.val();
                const name  = $opt.data('name') || '';
                const logo  = $opt.data('logo') || '';

                const imgHtml = logo
                    ? `<img src="${logo}" alt="logo" style="height:40px;">`
                    : `<span class="text-muted">{{ translate('No Logo') }}</span>`;

                const row = `
                    <tr data-id="${id}">
                        <td class="text-center align-middle">
                            <button type="button" class="btn btn-xs btn-danger btn-remove-brand-row">
                                <i class="las la-times"></i>
                            </button>
                        </td>
                        <td class="align-middle">${imgHtml}</td>
                        <td class="align-middle">${name}</td>
                    </tr>
                `;
                $brandTableBody.append(row);
            });
        }

        $('#brand_ids').on('changed.bs.select', function () {
            refreshBrandTableAndIds();
        });

        $(document).on('click', '.btn-remove-brand-row', function () {
            const $tr = $(this).closest('tr');
            const id  = $tr.data('id').toString();

            const $select = $('#brand_ids');
            let vals = $select.val() || [];
            vals = vals.filter(function (v) { return v !== id; });
            $select.val(vals);
            $select.selectpicker('refresh');
            refreshBrandTableAndIds();
        });

        // ---------- PRODUCT SECTION (search & add) ----------
        function addProductRow(product) {
            const idStr = product.id.toString();
            if (selectedProductIds.indexOf(idStr) !== -1) {
                return; // already added
            }
            selectedProductIds.push(idStr);
            $contentIds.val(selectedProductIds.join(','));

            const row = `
                <tr data-id="${idStr}">
                    <td class="text-center align-middle">
                        <button type="button" class="btn btn-xs btn-danger btn-remove-product">
                            <i class="las la-times"></i>
                        </button>
                    </td>
                    <td class="align-middle">
                        <img src="${product.thumbnail_url}" alt="" style="height:40px;">
                    </td>
                    <td class="align-middle">${product.part_no}</td>
                    <td class="align-middle">${product.name}</td>
                </tr>
            `;
            $productTableBody.append(row);
        }

        function searchAndAddProduct() {
            const q = $('#product-search-input').val().trim();
            if (!q) return;

            $.get('{{ route('pdf_contents.search_products') }}', { q: q }, function (res) {
                if (res.success && res.products.length > 0) {
                    addProductRow(res.products[0]); // first match
                } else {
                    AIZ.plugins.notify('warning',
                        '{{ translate('No product found with this part no.') }}'
                    );
                }
            });
        }

        $('#btn-add-product').on('click', function () {
            searchAndAddProduct();
        });

        $('#product-search-input').on('keypress', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                searchAndAddProduct();
            }
        });

        $(document).on('click', '.btn-remove-product', function () {
            const $tr = $(this).closest('tr');
            const id  = $tr.data('id').toString();

            selectedProductIds = selectedProductIds.filter(function (x) {
                return x !== id;
            });
            $contentIds.val(selectedProductIds.join(','));
            $tr.remove();
        });

    })();
</script>
@endsection

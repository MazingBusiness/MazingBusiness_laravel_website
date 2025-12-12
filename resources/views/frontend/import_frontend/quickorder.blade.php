@extends('frontend.layouts.app')

@section('content')
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
        width: 150px;
        margin-bottom: 20px;
    }

    .ajax-loader p {
        color: #074e86;
        font-family: Arial, sans-serif;
        font-size: 21px;
        font-weight: bold;
    }

    .custom-margin-top {
        margin-top: 10px;
    }

    .select2-container {
        margin-top: 10px;
    }
    .select2-container--default .select2-selection--multiple,
    .select2-container--default .select2-selection--single {
        min-height: 40px !important;
    }

    .view-offer {
        transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out;
    }

    .view-offer:hover {
        transform: scale(1.1);
        opacity: 0.8;
    }
</style>

{{-- Global loaders --}}
<div id="pdfAjaxLoader" class="ajax-loader">
    <p>PDF is being generated. Please wait for some time or you can continue your browsing (Don't close your browser)....</p>
</div>
<div id="searchAjaxLoader" class="ajax-loader">
    <img src="{{ url('https://mazingbusiness.com/public/assets/img/ajax-loader.gif') }}" class="img-responsive" />
    <p>Search is processing. Please wait for some time ....</p>
</div>

<section class="mb-4">
    <div class="container-fluid sm-px-0">
        {{-- MAIN SEARCH FORM (GET on same route) --}}
        <form id="search-form" action="{{ route('import.quickorder.company', $company->id) }}" method="GET">
            <div class="row">

                {{-- FILTER ROW --}}
                <div class="col-12 mb-4 mb-lg-0">
                    <div class="row gutters-10 position-relative mb-4 d-flex align-items-center">

                        {{-- Product name / Part No --}}
                        <div class="col-xl-2 col-md-3">
                            <input type="text"
                                   value="{{ $srch_prod_name }}"
                                   id="prod_name"
                                   name="prod_name"
                                   class="form-control"
                                   placeholder="Product Name or Part No">
                        </div>

                        {{-- Category Group (multi-select) --}}
                        <div class="col-xl-3 col-md-3">
                            <select class="js-select-cat-group abc cats_grp_drop form-control"
                                    multiple="multiple"
                                    id="cat_group_drop"
                                    name="cat_groups[]">
                                @php
                                    // priority 1 & 8 first, then alpha
                                    $category_groups = $category_groups->sortBy(fn($g)=>$g->id==1?0:($g->id==8?1:2))->values();
                                    $category_groups = $category_groups->sortBy(fn($g)=>($g->id==1||$g->id==8)?'':$g->name)->values();
                                    $is41 = !empty($is41Manager);
                                @endphp

                                @foreach ($category_groups as $group)
                                    @php
                                        $q = \App\Models\Product::where('group_id', $group->id);

                                        if ($is41) {
                                            // Manager-41 flow → only is_manager_41
                                            $q->where('is_manager_41', 1);
                                        } else {
                                            // Normal flow → only current_stock
                                            $q->where('current_stock', 1);
                                        }

                                        $count = $q->count();
                                    @endphp

                                    @if ($count > 0)
                                        <option value="{{ $group->id }}"
                                            {{ in_array($group->id, $selected_cat_groups ?? []) ? 'selected' : '' }}>
                                            {{ strtoupper($group->name) }} ({{ $count }})
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        {{-- Categories (multi-select) --}}
                        <div class="col-xl-3 col-md-3">
                            <select class="js-select-category category_drop form-control"
                                    multiple="multiple"
                                    id="cat_drop"
                                    name="categories[]">
                                <option value="">{{ translate('All Categories') }}</option>
                                @php
                                    $categories = $categories->sortBy('name');
                                @endphp
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}"
                                        {{ in_array($category->id, $selected_categories ?? []) ? 'selected' : '' }}>
                                        {{ strtoupper($category->name) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Brands (multi-select) --}}
                        <div class="col-xl-3 col-md-3">
                            <select class="js-select-all-brand b_drop form-control"
                                    multiple="multiple"
                                    id="brand_drop"
                                    name="brands[]">
                                <option value="">{{ translate('All Brands') }}</option>
                                @foreach ($brands as $brand)
                                    <option value="{{ $brand->id }}"
                                        {{ in_array($brand->id, $selected_brands ?? []) ? 'selected' : '' }}>
                                        {{ $brand->getTranslation('name') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Delivery Time (Inhouse) --}}
                        <div class="col-xl-2 col-md-3 mb-3 d-flex align-items-center">
                            <select id="inhouseDropdown"
                                    name="inhouse"
                                    class="form-control select2"
                                    style="width: 100%; height: 30px; font-size: 14px; padding: 2px;">
                                <option disabled {{ empty($inhouse ?? null) ? 'selected' : '' }}>Select Delivery Time</option>
                                <option value="1" {{ ($inhouse ?? null) == 1 ? 'selected' : '' }}>Delivery in 3 to 4 days</option>
                                <option value="2" {{ ($inhouse ?? null) == 2 ? 'selected' : '' }}>Delivery in 6 to 7 days</option>
                            </select>
                        </div>

                        {{-- Search Button --}}
                        <div class="col-xl-1 col-md-3" style="position: relative; left: 50px;">
                            <input type="button" id="btnSearch"
                                   value="Search"
                                   class="d-block rounded btn-primary btn-block text-light p-2 shadow-sm">
                        </div>

                    </div>
                </div>

                {{-- HEADING + DOWNLOAD BUTTONS --}}
                <div class="col-12" style="margin-bottom:10px;">
                    <div class="text-left">
                        <div class="row gutters-5 flex-wrap align-items-center">

                            <div class="col-md-8 col-8 col-lg-auto flex-fill">
                                <h1 class="h4 mb-lg-0 fw-600 text-body">
                                    @if ($category_group)
                                        {{ translate('All ' . \Illuminate\Support\Str::ucfirst($category_group->name)) }}
                                    @else
                                        {{ translate('All Products') }}
                                    @endif
                                    <small class="d-block mt-1 text-muted">
                                        {{ $company->company_name ?? '' }}
                                    </small>
                                </h1>
                            </div>

                            @php
                                $user = Auth::user();
                            @endphp

                            @if ($user)
                                @php
                                    $userId = $user->id;
                                @endphp
                                <input type="hidden" id="userId" value="{{ $userId }}">

                                {{-- PDF Button --}}
                                <div class="col-2 align-items-center">
                                    <a target="_blank"
                                       id="downloadPDFLink"
                                       class="d-block rounded btn-primary btn-block text-light p-2 shadow-sm"
                                       style="cursor: pointer;">
                                        <div class="text-truncate fs-12 fw-700 text-center">
                                            Download Net Price (PDF)
                                        </div>
                                    </a>
                                </div>

                                {{-- Excel Button --}}
                                <div class="col-2 align-items-center">
                                    <a target="_blank"
                                       id="downloadExcelLink"
                                       class="d-block rounded btn-success btn-block text-light p-2 shadow-sm"
                                       style="cursor: pointer;">
                                        <div class="text-truncate fs-12 fw-700 text-center">
                                            Download Net Price (EXCEL)
                                        </div>
                                    </a>
                                </div>
                            @endif

                        </div>
                    </div>
                </div>

                {{-- PRODUCT LIST + INFINITE SCROLL --}}
                <div class="col-12">
                    <div class="row gutters-5 row-cols-1">
                        <div id="postsContainer">
                            <div class="col" id="post-data">
                                @if(!($is41Manager ?? false))
                                    @include('frontend.partials.quickorder_list_box', [
                                        'products'    => $products,
                                        'order_id'    => null,
                                        'sub_order_id'=> null,
                                    ])
                                @else
                                    @include('frontend.partials.manager41_quickorder_list_box', [
                                        'products'    => $products,
                                        'order_id'    => null,
                                        'sub_order_id'=> null,
                                    ])
                                @endif
                            </div>
                            <div class="text-center p-3 d-none" id="loading">Loading...</div>
                        </div>
                        <input type="hidden" id="searchActive" value="0">
                    </div>
                </div>

            </div>
        </form>
    </div>
</section>

{{-- PDF Download Status Modal --}}
<div class="modal fade" id="downloadPDFModal" tabindex="-1" role="dialog" aria-labelledby="downloadPDFModal" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">PDF Download Status</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                Your price list download might take few minutes. You can continue browsing. Your file will be downloaded in the download folder.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- PDF Error Modal --}}
<div class="modal fade" id="downloadPDFErrorModal" tabindex="-1" role="dialog" aria-labelledby="downloadPDFErrorModal" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Message for you</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                Please fill any one of the fields before proceeding with the price list download.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script type="text/javascript">
    $(document).ready(function() {
        alert("Trst");
        return;
        // --- Select2 inits ---
        $(".js-select-cat-group").select2({
            closeOnSelect: false,
            placeholder: "Category Group",
            allowHtml: true,
            allowClear: true,
            tags: true
        });
        $(".js-select-category").select2({
            closeOnSelect: false,
            placeholder: "All Categories",
            allowHtml: true,
            allowClear: true,
            tags: true
        });
        $(".js-select-all-brand").select2({
            closeOnSelect: false,
            placeholder: "All Brands",
            allowHtml: true,
            allowClear: true,
            tags: true
        });

        $('#inhouseDropdown').select2({
            placeholder: "Select Delivery Time",
            allowClear: true,
            minimumResultsForSearch: Infinity,
            dropdownParent: $('#inhouseDropdown').parent()
        });

        $('#inhouseDropdown').next('.select2-container').css({
            'height': '30px',
            'font-size': '14px'
        });

        $('.select2-selection--single').css({
            'height': '30px',
            'line-height': '30px',
            'font-size': '14px',
            'width':'225px'
        });

        function getUrlParameterArray(name) {
            var results = [];
            var regex = new RegExp('[\\?&]' + name + '%5B%5D=([^&#]*)', 'g');
            var result;
            while ((result = regex.exec(location.search)) !== null) {
                results.push(decodeURIComponent(result[1].replace(/\+/g, ' ')));
            }
            return results;
        }

        // ================== Infinite Scroll ==================
        var page = 1;
        $(window).scroll(bindScroll);

        function bindScroll() {
            if (($(window).scrollTop() + $(window).height()) >= ($(document).height() - $('#footerstart').height() - 300)) {
                $(window).unbind('scroll');
                page++;
                var searchActive = $('#searchActive').val();
                if (searchActive == 0) {
                    loadMoreData(page);
                }
            }
        }

        function loadMoreData(page) {
            
            $("#loading").removeClass('d-none');
            $.ajax({
                data: { 'page': page },
                type: "GET"
            }).done(function(data) {
                $("#loading").addClass('d-none');
                if (data.html) {
                    $("#post-data").append(data.html);
                    $(window).bind('scroll', bindScroll);
                }
            });
        }

        // ============== CATEGORY GROUP → CATEGORIES & BRANDS ==============
        $('#cat_group_drop').change(function () {
            var category_group_id = $(this).val();

            if (category_group_id && category_group_id.length !== 0) {
                $.ajax({
                    url: '{{ route("getcategories") }}',
                    type: 'GET',
                    data: { category_group_id: category_group_id },
                    dataType: 'json',
                    success: function (response) {
                        $('#cat_drop').empty();
                        $('#cat_drop').prop('disabled', false);

                        var selectedCategories = getUrlParameterArray('categories');
                        $.each(response, function (key, value) {
                            var option = $('<option></option>')
                                .attr('value', value.id)
                                .text(value.name);
                            if (selectedCategories.includes(value.id.toString())) {
                                option.prop('selected', true);
                            }
                            $('#cat_drop').append(option);
                        });
                    },
                    error: function (xhr) {
                        console.error(xhr.responseText);
                    }
                });
                $('#brand_drop').empty().prop('disabled', true);
            } else {
                $('#cat_drop').empty().prop('disabled', true);

                $.ajax({
                    url: '{{ route("getbrands") }}',
                    type: 'GET',
                    data: { category_group_id: 0, category_id: 0 },
                    dataType: 'json',
                    success: function (response) {
                        $('#brand_drop').empty();
                        $('#brand_drop').prop('disabled', false);
                        $.each(response, function (key, value) {
                            var option = $('<option></option>')
                                .attr('value', value.id)
                                .text(value.name);
                            $('#brand_drop').append(option);
                        });
                    },
                    error: function (xhr) {
                        console.error(xhr.responseText);
                    }
                });
            }
        });

        // ============== CATEGORIES → BRANDS ==============
        $('#cat_drop').change(function () {
            var category_id = $(this).val();
            var category_group_id = $("#cat_group_drop").val();

            if (category_group_id && category_id) {
                $.ajax({
                    url: '{{ route("getbrands") }}',
                    type: 'GET',
                    data: { category_group_id: category_group_id, category_id: category_id },
                    dataType: 'json',
                    success: function (response) {
                        $('#brand_drop').empty();
                        $('#brand_drop').prop('disabled', false);

                        $.each(response, function (key, value) {
                            var option = $('<option></option>')
                                .attr('value', value.id)
                                .text(value.name);
                            $('#brand_drop').append(option);
                        });
                    },
                    error: function (xhr) {
                        console.error(xhr.responseText);
                    }
                });
            } else {
                $.ajax({
                    url: '{{ route("getbrands") }}',
                    type: 'GET',
                    data: { category_group_id: 0, category_id: 0 },
                    dataType: 'json',
                    success: function (response) {
                        $('#brand_drop').empty();
                        $('#brand_drop').prop('disabled', true);
                    },
                    error: function (xhr) {
                        console.error(xhr.responseText);
                    }
                });
            }
        });

        // ============== SEARCH BUTTON CLICK ==============
        $('#btnSearch').click(function(){

            var prod_name   = $('#prod_name').val();
            var cat_groups  = $('#cat_group_drop').val();
            var categories  = $('#cat_drop').val();
            var brands      = $('#brand_drop').val();
            var inhouse     = $('#inhouseDropdown').val();

            // If nothing selected, just reload full list
            if(prod_name == "" && !cat_groups && !categories && !brands && !inhouse){
                location.href = '{{ route("import.quickorder.company", $company->id) }}';
                return;
            }

            $('#searchActive').val('1');

            $.ajax({
                url: '{{ route("quickOrderSearchList") }}',
                type: 'GET',
                beforeSend: function(){
                    $('#searchAjaxLoader').css("visibility", "visible");
                },
                data: {
                    prod_name: prod_name,
                    cat_groups: cat_groups,
                    categories: categories,
                    brands: brands,
                    inhouse: inhouse
                },
                dataType: 'json',
                success: function (response) {
                    $('#postsContainer').empty();
                    $('#postsContainer').append(response.html);
                },
                complete: function(){
                    $('#searchAjaxLoader').css("visibility", "hidden");
                },
                error: function (xhr) {
                    console.error(xhr.responseText);
                }
            });
        });

        // Trigger search on Enter in product name
        $('#prod_name').keypress(function(e) {
            if (e.which == 13) {
                e.preventDefault();
                $('#btnSearch').click();
            }
        });

        // ============== INHOUSE CHANGE → AUTO SEARCH ==============
        $('#inhouseDropdown').change(function () {
            var inhouse    = $(this).val();
            var prod_name  = $('#prod_name').val();
            var cat_groups = $('#cat_group_drop').val();
            var categories = $('#cat_drop').val();
            var brands     = $('#brand_drop').val();

            if (!inhouse) {
                return;
            }

            $('#searchActive').val('1');

            $.ajax({
                url: '{{ route("quickOrderSearchList") }}',
                type: 'GET',
                beforeSend: function () {
                    $('#searchAjaxLoader').css("visibility", "visible");
                },
                data: {
                    prod_name: prod_name,
                    cat_groups: cat_groups,
                    categories: categories,
                    brands: brands,
                    inhouse: inhouse
                },
                dataType: 'json',
                success: function (response) {
                    $('#postsContainer').empty();
                    $('#postsContainer').append(response.html);
                },
                complete: function () {
                    $('#searchAjaxLoader').css("visibility", "hidden");
                },
                error: function (xhr) {
                    console.error(xhr.responseText);
                }
            });
        });

        // ============== EXCEL DOWNLOAD ==============
        $('#downloadExcelLink').click(function(){
            var prod_name  = $('#prod_name').val();
            var cat_groups = $('#cat_group_drop').val();
            var categories = $('#cat_drop').val();
            var brands     = $('#brand_drop').val();
            var userId     = $('#userId').val();

            var form = $('<form>', {
                action: 'https://mazingbusiness.com/api/products_excel.php',
                method: 'GET',
                target: '_blank'
            });

            form.append($('<input>', { type: 'hidden', name: 'prod_name',   value: prod_name }));
            form.append($('<input>', { type: 'hidden', name: 'cat_groups',  value: cat_groups }));
            form.append($('<input>', { type: 'hidden', name: 'categories',  value: categories }));
            form.append($('<input>', { type: 'hidden', name: 'brands',      value: brands }));
            form.append($('<input>', { type: 'hidden', name: 'user_id',     value: userId }));
            form.append($('<input>', { type: 'hidden', name: 'type',        value: 'net' }));

            $('body').append(form);
            form.submit();
        });

        // ============== PDF DOWNLOAD (via queue + job) ==============
        $('#downloadPDFLink').click(function(event){
            event.preventDefault();

            var prod_name  = $('#prod_name').val();
            var cat_groups = $('#cat_group_drop').val();
            var categories = $('#cat_drop').val();
            var brands     = $('#brand_drop').val();
            var userId     = $('#userId').val();
            var inhouse    = $('#inhouseDropdown').val();

            // Require at least one filter to be filled
            if (prod_name == "" && (!cat_groups || cat_groups.length==0) && (!categories || categories.length==0) && (!brands || brands.length==0) && !inhouse) {
                $('#downloadPDFErrorModal').modal('show');
                return;
            }

            $('#downloadPDFModal').modal('show');

            $.ajax({
                url: '{{ route("generatePdfFileName") }}',
                type: 'POST',
                data: {
                    user_id: userId,
                    _token: '{{ csrf_token() }}'
                },
                dataType: 'json',
                success: function (response) {
                    if(response.status === true){
                        var formData = new FormData();
                        formData.append('prod_name',  prod_name);
                        formData.append('cat_groups', cat_groups);
                        formData.append('categories', categories);
                        formData.append('brands',     brands);
                        formData.append('userId',     userId);
                        formData.append('type',       'net');
                        formData.append('inhouse',    inhouse);
                        formData.append('_token',     '{{ csrf_token() }}');

                        fetch('/generate-pdf', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.filename) {
                                // checkPdfAvailability() is defined globally (footer)
                                if (typeof checkPdfAvailability === 'function') {
                                    checkPdfAvailability(data.filename);
                                }
                            }
                        })
                        .catch(error => console.error('Error:', error));
                    } else {
                        console.log(response);
                    }
                },
                error: function (xhr) {
                    console.error(xhr.responseText);
                }
            });
        });

    });
</script>
@endsection

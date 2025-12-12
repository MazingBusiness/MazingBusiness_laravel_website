@extends('backend.layouts.app')

@section('content')

  @php
    CoreComponentRepository::instantiateShopRepository();
    CoreComponentRepository::initializeCache();
  @endphp

  <div class="aiz-titlebar text-left mt-2 mb-3">
    <h5 class="mb-0 h6">{{ translate('Add New Notification') }}</h5>
  </div>
  <div class="">
    <!-- Error Meassages -->
    @if ($errors->any())
      <div class="alert alert-danger">
        <div class="font-weight-600 mb-1">Please fix the errors below:</div>
        <ul class="mb-0 pl-3">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    @if (session('error'))
      <div class="alert alert-danger">
        {{ session('error') }}   {{-- from your catch() → with('error', ...) --}}
      </div>
    @endif

    @if (session('success'))
      <div class="alert alert-success">
        {{ session('success') }}
      </div>
    @endif
    <form class="form form-horizontal mar-top" action="{{ route('submitNotifications') }}" method="POST" enctype="multipart/form-data" id="choice_form">
      <div class="row gutters-5">
        <div class="col-lg-8">
          @csrf
          <div class="card">
            <div class="card-header">
              <h5 class="mb-0 h6">{{ translate('Notification Information') }}</h5>
            </div>
            <div class="card-body">
              <div class="form-group row">
                <label class="col-md-3 col-from-label">{{ translate('Title') }} <span class="text-danger">*</span></label>
                <div class="col-md-8">
                  <input type="text" class="form-control" name="title" placeholder="{{ translate('Title') }}" required>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-md-3 col-from-label">{{ translate('Body') }} <span
                    class="text-danger">*</span></label>
                <div class="col-md-8">
                  <textarea class="form-control" name="body" required></textarea>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-md-3 col-from-label">{{ translate('Type') }}</label>
                <div class="col-md-8">
                  <select class="form-control aiz-selectpicker" name="type" id="type">
                    <option value="product">Product</option>
                    <option value="offers">Offers</option>
                    <option value="category">Category</option>
                    <option value="brand">Brand</option>
                    <option value="cart">Cart</option>
                  </select>
                </div>
              </div>

              <div class="form-group row" id="type_details">
                <label class="col-md-3 col-from-label">{{ translate('Part number') }}</label>
                <div class="col-md-8">
                  <input type="text" class="form-control" name="part_number" id="part_number" placeholder="{{ translate('Part number') }}" required>
                  <input type="hidden" name="product_id" id="product_id">
                  <div id="product_lookup_result" class="mt-1 small text-muted"></div>
                </div>
              </div>

              <div class="form-group row">
                <label class="col-md-3 col-form-label" for="signinSrEmail">{{ translate('Upload Images') }}</label>
                <div class="col-md-8">
                  <div class="input-group" data-toggle="aizuploader" data-type="image" data-multiple="true">
                    <div class="input-group-prepend">
                      <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse') }}</div>
                    </div>
                    <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                    <input type="hidden" name="photos" class="selected-files">
                  </div>
                  <div class="file-preview box sm">
                  </div>
                  <small
                    class="text-muted">{{ translate('These images are visible in notification.') }}</small>
                </div>
              </div>
            </div>
          </div>
          <div class="card">
            <div class="card-header">
              <h5 class="mb-0 h6">{{ translate('User Information') }}</h5>
            </div>
            <div class="card-body">
              <div class="form-group row">
                <label class="col-md-3 col-from-label">{{ translate('Branch') }}</label>
                <div class="col-md-8">
                  <select class="form-control aiz-selectpicker" name="branch" id="branch">
                    <option value="all">All Branch</option>
                    @foreach($branches as $branch)
                      <option value="{{$branch->id}}">{{$branch->name}}</option>
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-md-3 col-from-label">{{ translate('Managers') }}</label>
                <div class="col-md-8">
                  <select class="form-control aiz-selectpicker" name="manager" id="manager">
                    <option value="all">All Managers</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="card">
            <div class="card-header">
              <h5 class="mb-0 h6">{{ translate('Notification type') }}</h5>
            </div>
            <div class="card-body">
              <div class="form-group row">
                <label class="col-md-6 col-from-label">{{ translate('Pop up message') }}</label>
                <div class="col-md-6">
                  <label class="aiz-switch aiz-switch-success mb-0">
                    <input type="checkbox" name="pop_up_message" value="1">
                    <span></span>
                  </label>
                </div>
              </div>
            </div>
          </div>
          <div class="card" id="div_pop_up_message" style="display:none;">
            <div class="card-header">
              <h5 class="mb-0 h6">
                {{ translate('Show On Screens') }}
              </h5>
            </div>
            <div class="card-body">
              <div class="form-group row">
                <label class="col-md-6 col-from-label">{{ translate('Dashboard') }}</label>
                <div class="col-md-6">
                  <label class="aiz-switch aiz-switch-success mb-0">
                    <input type="checkbox" name="show_on_screen[]" value="Dashboard">
                    <span></span>
                  </label>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-md-6 col-from-label">{{ translate('AllProducts') }}</label>
                <div class="col-md-6">
                  <label class="aiz-switch aiz-switch-success mb-0">
                    <input type="checkbox" name="show_on_screen[]" value="AllProducts">
                    <span></span>
                  </label>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-md-6 col-from-label">{{ translate('Offers') }}</label>
                <div class="col-md-6">
                  <label class="aiz-switch aiz-switch-success mb-0">
                    <input type="checkbox" name="show_on_screen[]" value="Offers">
                    <span></span>
                  </label>
                </div>
              </div>
            </div>
          </div>
          <div class="card">
            <div class="card-header">
              <h5 class="mb-0 h6">{{ translate('Date & Time For Notification') }}</h5>
            </div>
            <div class="card-body">
              <div class="form-group row">
                <label class="col-md-6 col-from-label">{{ translate('Date Time') }}</label>
                <div class="col-md-6">
                  <label class="mb-0">
                    <input type="text" class="form-control" name="date_time" id="date_time" value="{{ old('date_time', now()->format('Y-m-d H:i')) }}" placeholder="YYYY-MM-DD HH:MM">
                  </label>
                </div>
              </div>
            </div>
          </div>
          <div class="card" id="div_to_date" style="display:none;">
            <div class="card-header">
              <h5 class="mb-0 h6">{{ translate('To Date & Time For Popup msg') }}</h5>
            </div>
            <div class="card-body">
              <div class="form-group row">
                <label class="col-md-6 col-from-label">{{ translate('To Date & Time') }}</label>
                <div class="col-md-6">
                  <label class="mb-0">
                    <input type="text" class="form-control" name="to_date" id="to_date" value="{{ old('to_date', now()->addDay()->format('Y-m-d H:i')) }}" placeholder="YYYY-MM-DD HH:MM">
                  </label>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-8">
          
        </div>
        <div class="col-12">
          <div class="btn-toolbar float-left mb-3" role="toolbar" aria-label="Toolbar with button groups">
            <!-- <div class="btn-group mr-2" role="group" aria-label="Third group">
              <button type="submit" name="button" value="unpublish"
                class="btn btn-primary action-btn">{{ translate('Save & Unpublish') }}</button>
            </div> -->
            <div class="btn-group" role="group" aria-label="Second group">
              <button type="submit" name="button" id="btn_save_send" value="publish" class="btn btn-success action-btn">{{ translate('Save & Send') }}</button>
            </div>
          </div>
        </div>
      </div>
    </form>
  </div>

@endsection

@section('script')
  <script type="text/javascript">
    $('form').bind('submit', function(e) {
      if ($(".action-btn").attr('attempted') == 'true') {
        //stop submitting the form because we have already clicked submit.
        e.preventDefault();
      } else {
        $(".action-btn").attr("attempted", 'true');
      }
    });
    (function () {
      /* =========================
      * Helpers: reset + popup
      * ========================= */
      function resetFields($root) {
        $root.find('input[type="checkbox"], input[type="radio"]')
          .prop('checked', false)
          .trigger('change'); // keep any UI in sync

        $root.find('select').val('').trigger('change');
        $root.find('input[type="text"], input[type="number"], input[type="email"], input[type="url"], textarea')
          .val('');
      }

      function togglePopupFields() {
        var isOn = $('input[name="pop_up_message"]').is(':checked');
        if (isOn) {
          $('#div_pop_up_message').stop(true, true).slideDown(150);
          $('#div_to_date').stop(true, true).slideDown(150);
        } else {
          // clear all inputs inside and hide
          resetFields($('#div_pop_up_message'));
          // resetFields($('#div_to_date'));
          $('#div_pop_up_message').stop(true, true).slideUp(150);
          $('#div_to_date').stop(true, true).slideUp(150);
        }
      }

      // init + bind
      $(togglePopupFields);
      $(document).on('change', 'input[name="pop_up_message"]', togglePopupFields);

      /* =========================
      * Manager List (by branch)
      * ========================= */
      const $branch  = $('#branch');
      const $manager = $('#manager');

      function refreshManagers() {
        const warehouseId = $branch.val();

        // Disable + loading state
        $manager.prop('disabled', true)
          .empty()
          .append('<option value="">Loading…</option>')
          .selectpicker('refresh'); // AIZ bootstrap-select

        $.get('{{ route('ajax.managersListByWarehouse') }}', { warehouse_id: warehouseId })
          .done(function (list) {
            $manager.empty();
            $manager.append('<option value="all">All Managers</option>');

            if (Array.isArray(list) && list.length) {
              list.forEach(function (m) {
                $manager.append('<option value="'+ m.id +'">'+ m.name +'</option>');
              });
            } else {
              $manager.append('<option value="">No managers found</option>');
            }

            $manager.prop('disabled', false).selectpicker('refresh');
          })
          .fail(function () {
            $manager.empty()
              .append('<option value="">Error loading managers</option>')
              .prop('disabled', false)
              .selectpicker('refresh');
          });
      }

      // Bind + initial populate (optional)
      $(document).on('change', '#branch', refreshManagers);
      $branch.trigger('change');

      /* =========================
      * Type-driven UI (product/category/brand/others)
      * ========================= */
      const $type    = $('#type');
      const $details = $('#type_details');
      const $submit  = $('#btn_save_send'); // submit button

      const cache = { categories: null, brands: null };

      // Button visibility/enable rule:
      // - If type==='product': show ONLY when #product_id has a value
      // - Else: always show and enable
      const isProductType = () => $type.val() === 'product';
      function updateSubmit() {
        if (isProductType()) {
          const hasProduct = !!$('#product_id').val();
          $submit.toggle(hasProduct).prop('disabled', !hasProduct);
        } else {
          $submit.show().prop('disabled', false);
        }
      }

      function renderPartNumber(){
        $details.show().html(`
          <label class="col-md-3 col-from-label">{{ translate('Part number') }}</label>
          <div class="col-md-8">
            <input type="text" class="form-control" name="part_number" id="part_number" placeholder="{{ translate('Part number') }}" required>
            <input type="hidden" name="product_id" id="product_id">
            <div id="product_lookup_result" class="mt-1 small text-muted"></div>
          </div>
        `);
        updateSubmit(); // hidden until a valid product is found
      }

      function renderCategory(list){
        const options = list.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
        $details.show().html(`
          <label class="col-md-3 col-from-label">{{ translate('Category') }}</label>
          <div class="col-md-8">
            <select class="form-control aiz-selectpicker" name="category_id" data-live-search="true" required>
              <option value="">{{ translate('Select a category') }}</option>
              ${options}
            </select>
          </div>
        `);
        $('.aiz-selectpicker').selectpicker('refresh');
        updateSubmit(); // show + enable for non-product
      }

      function renderBrand(list){
        const options = list.map(b => `<option value="${b.id}">${b.name}</option>`).join('');
        $details.show().html(`
          <label class="col-md-3 col-from-label">{{ translate('Brand') }}</label>
          <div class="col-md-8">
            <select class="form-control aiz-selectpicker" name="brand_id" data-live-search="true" required>
              <option value="">{{ translate('Select a brand') }}</option>
              ${options}
            </select>
          </div>
        `);
        $('.aiz-selectpicker').selectpicker('refresh');
        updateSubmit(); // show + enable for non-product
      }

      function hideDetails(){
        $details.hide().empty();
        updateSubmit(); // show + enable for non-product paths (offers/cart/etc.)
      }

      function onTypeChange(){
        const val = $type.val();

        if (val === 'product') {
          renderPartNumber();
        } else if (val === 'category') {
          if (cache.categories) {
            renderCategory(cache.categories);
          } else {
            $.get(`{{ route('ajax.categories') }}`).done(list => {
              cache.categories = list || [];
              renderCategory(cache.categories);
            });
          }
        } else if (val === 'brand') {
          if (cache.brands) {
            renderBrand(cache.brands);
          } else {
            $.get(`{{ route('ajax.brands') }}`).done(list => {
              cache.brands = list || [];
              renderBrand(cache.brands);
            });
          }
        } else {
          // offers / cart / others
          hideDetails();
        }

        updateSubmit();
      }

      $(document).on('change', '#type', onTypeChange);
      onTypeChange(); // init based on current value

      /* =========================
      * Product lookup by part number (delegated)
      * ========================= */
      const url = '{{ route('ajax.product.by.partno') }}';
      let debounceTimer = null;
      let inflight = null;
      let lastQuery = '';

      function clearResult(msg) {
        $('#product_id').val('');
        $('#product_lookup_result').text(msg || '');
        updateSubmit(); // hides button in product type
      }

      function search(q) {
        if (inflight && inflight.readyState !== 4) inflight.abort();

        $('#product_lookup_result').text('Searching…');
        updateSubmit(); // while searching, button still hidden if no product_id

        inflight = $.get(url, { part_no: q })
          .done(function(res){
            if (res && res.ok) {
              $('#product_id').val(res.id);
              $('#product_lookup_result').html(`<span class="text-success" style="font-size:12px;">${res.name}</span>`);
              updateSubmit(); // shows button (valid product)
            } else {
              clearResult('Not found');
            }
          })
          .fail(function(xhr){
            const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Not found';
            clearResult(msg); // hides button
          });
      }

      // Bind input on dynamically added #part_number
      $(document).on('input', '#part_number', function () {
        const q = $(this).val().trim();
        if (q.length < 7) {
          clearResult('');
          lastQuery = '';
          if (inflight && inflight.readyState !== 4) inflight.abort();
          return;
        }
        if (q === lastQuery) return;
        lastQuery = q;

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function(){ search(q); }, 300);
      });

      /* =========================
      * Submit guard (only for product type)
      * ========================= */
      $('form').on('submit', function(e){
        if (isProductType() && !$('#product_id').val()) {
          e.preventDefault();
          $('#product_lookup_result').html('<span class="text-danger" style="font-size:12px;">Please enter a valid part number.</span>');
          updateSubmit();
        }
      });

      // Final initial state
      updateSubmit();
    })();

    // =========================
    // Submit guard (only for product type) — robust
    // =========================
    const $form = $('#btn_save_send').closest('form'); // target the correct form

    $form.on('submit', function(e){
      // Only block when type=product and no valid product selected
      if (isProductType() && !$('#product_id').val()) {
        e.preventDefault();
        e.stopImmediatePropagation(); // stop other handlers that might disable buttons
        $('#product_lookup_result').html(
          '<span class="text-danger" style="font-size:12px;">Please enter a valid part number.</span>'
        );
        // Undo any auto-disable done by other scripts
        $submit.prop('disabled', false).show();
        return false;
      }
    });

    // Extra safety: stop before form.submit if invalid (prevents auto-disable handlers from running)
    $submit.on('click', function(e){
      if (isProductType() && !$('#product_id').val()) {
        e.preventDefault();
        $('#product_lookup_result').html(
          '<span class="text-danger" style="font-size:12px;">Please enter a valid part number.</span>'
        );
        $(this).prop('disabled', false).show();
        return false;
      }
    });
  </script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script>
    // Flatpickr will read the current input value
    flatpickr('#date_time', {
      enableTime: true,
      dateFormat: 'Y-m-d H:i',
      time_24hr: true,
      minuteIncrement: 1
    });
    flatpickr('#to_date', {
      enableTime: true,
      dateFormat: 'Y-m-d H:i',
      time_24hr: true,
      minuteIncrement: 1
    });

    document.addEventListener('DOMContentLoaded', function () {
      const fmt = 'Y-m-d H:i';

      // Init "to" first (we'll set its minDate from the "from" value)
      const fpTo = flatpickr('#to_date', {
        enableTime: true,
        dateFormat: fmt,
        time_24hr: true,
        minuteIncrement: 1
      });

      // "From" picker: block past dates
      const fpFrom = flatpickr('#date_time', {
        enableTime: true,
        dateFormat: fmt,
        time_24hr: true,
        minuteIncrement: 1,
        minDate: 'today',               // ⬅️ disables all dates before today
        onReady: syncToWithFrom,
        onChange: syncToWithFrom
      });

      function syncToWithFrom() {
        const fromEl = document.getElementById('date_time');
        const toEl   = document.getElementById('to_date');

        const from = flatpickr.parseDate(fromEl.value, fmt);
        if (!from) {
          // If "from" is cleared, remove the constraint on "to"
          fpTo.set('minDate', null);
          return;
        }

        // Compute tomorrow (same time as "from")
        const minTo = new Date(from.getTime());
        minTo.setDate(minTo.getDate() + 1);

        // Enforce "to" >= tomorrow
        fpTo.set('minDate', minTo);

        const to = flatpickr.parseDate(toEl.value, fmt);
        if (!to || to < minTo) {
          fpTo.setDate(minTo, true);    // set & trigger change
        }
      }

      // Run once on load for any prefilled values
      syncToWithFrom();
    });
  </script>
@endsection

<div class="card-header mb-3 pl-0 pb-0">
  <h3 class="h6">{{ translate('Add Your Cart Base Coupon') }}</h3>
</div>
<div class="form-group row">
  <label class="col-lg-3 col-from-label" for="customer_id">{{ translate('Customer') }}</label>
  <div class="col-lg-9">
    <select class="form-control aiz-selectpicker" name="customer_id" id="customer_id" data-live-search="true">
      <option value="">All Customers</option>
      @foreach (\App\Models\User::where('user_type', 'customer')->get() as $customer)
        <option value="{{ $customer->id }}">{{ $customer->name }} [{{ $customer->company_name }}]</option>
      @endforeach
    </select>
  </div>
</div>
<div class="form-group row">
  <label class="col-lg-3 col-from-label" for="code">{{ translate('Coupon code') }}</label>
  <div class="col-lg-9">
    <input type="text" placeholder="{{ translate('Coupon code') }}" id="code" name="code"
      class="form-control" required>
  </div>
</div>
<div class="form-group row">
  <label class="col-lg-3 col-from-label" for="description">{{ translate('Coupon Description') }}</label>
  <div class="col-lg-9">
    <input type="text" placeholder="{{ translate('Coupon Description') }}" id="description" name="description"
      class="form-control" required>
  </div>
</div>
<div class="form-group row">
  <label class="col-lg-3 col-from-label">{{ translate('Max. Usage Count') }}</label>
  <div class="col-lg-9">
    <input type="number" lang="en" min="1" step="1" value="1"
      placeholder="{{ translate('Max. Usage Count') }}" name="max_usage_count" class="form-control" required>
  </div>
</div>
<div class="form-group row">
  <label class="col-lg-3 col-from-label">{{ translate('Minimum Shopping') }}</label>
  <div class="col-lg-9">
    <input type="number" lang="en" min="0" step="0.01"
      placeholder="{{ translate('Minimum Shopping') }}" name="min_buy" class="form-control" required>
  </div>
</div>
<div class="form-group row">
  <label class="col-lg-3 col-from-label">{{ translate('Discount') }}</label>
  <div class="col-lg-6">
    <input type="number" lang="en" min="0" step="0.01" placeholder="{{ translate('Discount') }}"
      name="discount" class="form-control" required>
  </div>
  <div class="col-lg-3">
    <select class="form-control aiz-selectpicker" name="discount_type">
      <option value="amount">{{ translate('Amount') }}</option>
      <option value="percent">{{ translate('Percent') }}</option>
    </select>
  </div>
</div>
<div class="form-group row">
  <label class="col-lg-3 col-from-label">{{ translate('Maximum Discount Amount') }}</label>
  <div class="col-lg-9">
    <input type="number" lang="en" min="0" step="0.01"
      placeholder="{{ translate('Maximum Discount Amount') }}" name="max_discount" class="form-control" required>
  </div>
</div>
<div class="form-group row">
  <label class="col-lg-3 col-from-label" for="new_user_only">{{ translate('New User Only?') }}</label>
  <div class="col-lg-9">
    <label class="aiz-switch aiz-switch-success mb-0">
      <input type="checkbox" name="new_user_only" value="1">
      <span></span>
    </label>
  </div>
</div>
<div class="form-group row">
  <label class="col-sm-3 control-label" for="start_date">{{ translate('Date') }}</label>
  <div class="col-sm-9">
    <input type="text" class="form-control aiz-date-range" name="date_range"
      placeholder="{{ translate('Select Date') }}">
  </div>
</div>

<script type="text/javascript">
  $(document).ready(function() {
    $('.aiz-selectpicker').selectpicker();
    $('.aiz-date-range').daterangepicker();
  });
</script>

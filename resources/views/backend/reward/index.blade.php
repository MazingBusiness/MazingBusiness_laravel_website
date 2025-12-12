@extends('backend.layouts.app')
@section('content')
  
  @php
    CoreComponentRepository::instantiateShopRepository();
    CoreComponentRepository::initializeCache();
  @endphp

  <div class="aiz-titlebar text-left mt-2 mb-3">
    <div class="row align-items-center">
      <div class="col-auto">
        <h1 class="h3">{{ translate('All Users') }}</h1>
      </div>
      <div class="col text-right">
      <div class="col-md-12" style="margin-top:10px">
          <a href="javascript:void(0)" class="btn btn-circle btn-success" id="syncUser">
            <span>{{ translate('Sync New User') }}</span> 
          </a>
          @php /*<a href="javascript:void(0)" class="btn btn-circle btn-warning" id="pullPartyCodeBtn">
            <span>{{ translate('Pull All User\'s Into Google Sheet') }}</span>
          </a>
          <a href="javascript:void(0)" class="btn btn-circle btn-info" id="exportBtn">
            <span>{{ translate('Update Records From Sheet to Database') }}</span>
          </a>
           <a href="javascript:void(0)" class="btn btn-circle btn-success" id="pullBtn">
            <span>{{ translate('Pull Records From Database') }}</span> 
          </a>*/ @endphp
        </div>
      </div>
    </div>
  </div>
  <br>
  <div class="card">
    <form class="" id="frm_userList" action="" method="GET">
      <div class="card-header row gutters-5">
        <div class="col-md-2 ml-auto">
          <select class="form-control" name="city" id="city_drop" title="{{ translate('Select City') }}" onchange="sort_userList()">
          <option value="">{{ translate('All City') }}</option>
            @foreach($cityList as $key=>$value)
              <option value="{{$value->city}}" @if(isset($city) && $city == $value->city ) selected @endif>{{$value->city}}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <select class="form-control" name="assigned_warehouse" id="assigned_warehouse_drop" title="{{ translate('Select Assigned Warehouse') }}" onchange="sort_userList()">
            <option value="">{{ translate('All Assigned Warehouse') }}</option>
            @foreach($assignedWarehouseList as $key=>$value)
              <option value="{{$value->assigned_warehouse}}" @if(isset($assigned_warehouse) && $assigned_warehouse == $value->assigned_warehouse ) selected @endif>{{$value->assigned_warehouse}}</option>
            @endforeach 
          </select>
        </div>
        <div class="col-md-3">
          <select class="form-control" name="manager" id="manager_drop" title="{{ translate('Select Manager') }}" onchange="sort_userList()">
            <option value="">{{ translate('All Manager') }}</option>
            @foreach($managerList as $key=>$value)
              <option value="{{$value['manager_id']}}" @if(isset($manager) && $manager == $value['manager_id'] ) selected @endif>{{$value['manager_name']}}</option>
            @endforeach 
          </select>
        </div>
        <div class="col-md-3">
          <input type="text"class="form-control" name="search_text" id="search_text" placeholder="Company Name , Partycode" value="{{ $search_text }}">
        </div>
        <?php /* <div class="col-md-2 ml-auto">
          <select class="form-control form-control-sm aiz-selectpicker mb-2 mb-md-0" name="brands[]" id="brand_drop" multiple="multiple" title="{{ translate('Select Brand') }}">
            <option disabled value="">{{ translate('Select Brand') }}</option>
            @foreach($brands as $key=>$value)
              <option value="{{$value->id}}">{{$value->name}}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <select class="form-control form-control-sm aiz-selectpicker mb-2 mb-md-0" name="stock" id="stock">
            <option disabled selected value="">{{ translate('Select Stock') }}</option>
            <option value="2">{{ translate('All') }}</option>
            <option value="1">{{ translate('In Stock') }}</option>
            <option value="0">{{ translate('Out of Stock') }}</option>
          </select>
        </div> */ ?>
        
      </div>
    </form>
      
      <div class="card-header row gutters-5">
        <div class="col">
          <h5 class="mb-md-0 h6">{{ translate('All Users') }} </h5> <span style="color:#F00;">*Press Enter after put rewards percentage for update.</span>
        </div>
      <!-- 
        <div class="dropdown mb-2 mb-md-0">
          <button class="btn border dropdown-toggle" type="button" data-toggle="dropdown">
            {{ translate('Bulk Action') }}
          </button>
          <div class="dropdown-menu dropdown-menu-right">
            <a class="dropdown-item" href="#" onclick="bulk_delete()"> {{ translate('Delete selection') }}</a>
          </div>
        </div>

        <div class="col-md-2 ml-auto">
          <select class="form-control form-control-sm aiz-selectpicker mb-2 mb-md-0" name="type" id="type"
            onchange="sort_userList()">
            <option value="">{{ translate('Sort By') }}</option>
            <option value="rating,desc"
              @isset($col_name, $query) @if ($col_name == 'rating' && $query == 'desc') selected @endif @endisset>
              {{ translate('Rating (High > Low)') }}</option>
            <option value="rating,asc"
              @isset($col_name, $query) @if ($col_name == 'rating' && $query == 'asc') selected @endif @endisset>
              {{ translate('Rating (Low > High)') }}</option>
            <option
              value="num_of_sale,desc"@isset($col_name, $query) @if ($col_name == 'num_of_sale' && $query == 'desc') selected @endif @endisset>
              {{ translate('Num of Sale (High > Low)') }}</option>
            <option
              value="num_of_sale,asc"@isset($col_name, $query) @if ($col_name == 'num_of_sale' && $query == 'asc') selected @endif @endisset>
              {{ translate('Num of Sale (Low > High)') }}</option>
          </select>
        </div>
        <div class="col-md-2">
          <div class="form-group mb-0">
            <input type="text" class="form-control form-control-sm" id="search"
              name="search"@isset($sort_search) value="{{ $sort_search }}" @endisset
              placeholder="{{ translate('Type & Enter') }}">
          </div>
        </div> -->
      </div>

      <div class="card-body">
        <table class="table aiz-table mb-0">
          <thead>
            <tr>
              <th>{{ translate('User Company Name') }}</th>
              <th>{{ translate('User\'s City') }}</th>
              <th>{{ translate('Assigned Warehouse') }}</th>
              <th data-breakpoints="md">{{ translate('Party Code') }}</th>
              <th data-breakpoints="md">{{ translate('Kolkata') }}</th>
              <th data-breakpoints="md">{{ translate('Kolkata Rewards Percentage') }}</th>
              <th data-breakpoints="lg">{{ translate('Delhi') }}</th>
              <th data-breakpoints="md">{{ translate('Delhi Rewards Percentage') }}</th>
              <th data-breakpoints="lg">{{ translate('Mumbai') }}</th>
              <th data-breakpoints="md">{{ translate('Mumbai Rewards Percentage') }}</th>
            </tr>
          </thead>
          <tbody>
          @foreach ($userList as $key => $user)
              <tr>
                <td>
                  <div class="form-group d-inline-block">
                    <div class="col">
                      <span class="text-muted text-truncate-2">{{ $user['company_name'] }}</span>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="text-muted text-truncate-2">{{ $user['city'] }}</span>
                </td>
                <td>
                  <span class="text-muted text-truncate-2">{{ $user['assigned_warehouse'] }}</span>
                </td>
                <td>
                  <span class="text-muted text-truncate-2">{{ $user['party_code'] }}</span>
                </td>
                <td>
                  @if(isset($user['warehouses'][0]))
                    <label class="aiz-switch aiz-switch-success mb-0">
                      <input onchange="update_preferance(this,{{ $user['warehouses'][0]['reward_id'] }})" value="{{ $user['warehouses'][0]['reward_id'] }}" type="checkbox"
                        <?php if ($user['warehouses'][0]['preference'] == 1) {
                            echo 'checked';
                        } ?>>
                      <span class="slider round"></span>
                    </label>
                  @endif
                </td>
                <td>
                  @if($user['warehouses'][0])
                    <div id="div_rewards_percentage_{{ $user['warehouses'][0]['reward_id'] }}">
                      @if($user['warehouses'][0]['preference'] == 1)
                        <input class="form-control rewards-percentage" type="number" name="rewards_percentage_{{ $user['warehouses'][0]['reward_id'] }}" id="rewards_percentage_{{ $user['warehouses'][0]['reward_id'] }}" value="{{ $user['warehouses'][0]['rewards_percentage'] }}" data-id="{{ $user['warehouses'][0]['reward_id'] }}">
                      @else
                        @if(isset($user['warehouses'][0]['rewards_percentage']))
                          <span class="text-muted text-truncate-2">{{ $user['warehouses'][0]['rewards_percentage'] }}</span>
                        @endif
                      @endif
                    </div>
                  @endif
                </td>
                <td>
                  @if(isset($user['warehouses'][1]))
                    <label class="aiz-switch aiz-switch-success mb-0">
                      <input onchange="update_preferance(this,{{ $user['warehouses'][1]['reward_id'] }})" value="{{ $user['warehouses'][1]['reward_id'] }}" type="checkbox"
                        <?php if ($user['warehouses'][1]['preference'] == 1) {
                            echo 'checked';
                        } ?>>
                      <span class="slider round"></span>
                    </label>
                  @endif
                </td>
                <td>
                  @if(isset($user['warehouses'][1]))
                    <div id="div_rewards_percentage_{{ $user['warehouses'][1]['reward_id'] }}">
                      @if($user['warehouses'][1]['preference'] == 1)
                        <input class="form-control rewards-percentage" type="number" name="rewards_percentage_{{ $user['warehouses'][1]['reward_id'] }}" id="rewards_percentage_{{ $user['warehouses'][1]['reward_id'] }}" value="{{ $user['warehouses'][1]['rewards_percentage'] }}" data-id="{{ $user['warehouses'][1]['reward_id'] }}">
                      @else
                        @if(isset($user['warehouses'][1]['rewards_percentage']))
                          <span class="text-muted text-truncate-2">{{ $user['warehouses'][1]['rewards_percentage'] }}</span>
                        @endif
                      @endif
                    </div>
                  @endif
                </td>                
                <td>
                  @if(isset($user['warehouses'][4]))
                    <label class="aiz-switch aiz-switch-success mb-0">
                      <input onchange="update_preferance(this,{{ $user['warehouses'][4]['reward_id'] }})" value="{{ $user['warehouses'][4]['reward_id'] }}" type="checkbox"
                        <?php if ($user['warehouses'][4]['preference'] == 1) {
                            echo 'checked';
                        } ?>>
                      <span class="slider round"></span>
                    </label>
                  @endif
                </td>
                <td>
                  @if(isset($user['warehouses'][4]))
                    <div id="div_rewards_percentage_{{ $user['warehouses'][4]['reward_id'] }}">
                      @if($user['warehouses'][4]['preference'] == 1)
                        <input class="form-control rewards-percentage" type="number" name="rewards_percentage_{{ $user['warehouses'][4]['reward_id'] }}" id="rewards_percentage_{{ $user['warehouses'][4]['reward_id'] }}" value="{{ $user['warehouses'][4]['rewards_percentage'] }}" data-id="{{ $user['warehouses'][4]['reward_id'] }}">
                      @else
                        @if(isset($user['warehouses'][4]['rewards_percentage']))
                          <span class="text-muted text-truncate-2">{{ $user['warehouses'][4]['rewards_percentage'] }}</span>
                        @endif
                      @endif
                    </div>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
        <div class="aiz-pagination">
          {{ $userList->appends(request()->input())->links() }}
        </div>
      </div>
    
  </div>
@endsection

@section('modal')
  @include('modals.delete_modal')
@endsection


@section('script')
  
  <script type="text/javascript">

    $(document).ready(function() {
      //$('#container').removeClass('mainnav-lg').addClass('mainnav-sm');
      $('#seller_drop').change(function () {
          var seller_drop = $('#seller_drop').val();
          if (seller_drop.length != 0) {
              // AJAX call to get brand
              $.ajax({
                  url: '{{ route("getBrandsFromAdmin") }}',
                  type: 'GET',
                  data: { seller_id: seller_drop, category_group_id: 0, category_id: 0 },
                  dataType: 'json',
                  success: function (response) {
                      $('#brand_drop').empty();
                      $('#brand_drop').prop('disabled', false);
                      // Append options
                      $.each(response, function (key, value) {
                          var option = $('<option></option>')
                              .attr('value', value.id)
                              .text(value.name);
                          $('#brand_drop').append(option);
                      });
                      // Refresh the aiz-selectpicker
                      $('#brand_drop').selectpicker('refresh');
                  },
                  error: function (xhr, status, error) {
                      console.error(xhr.responseText);
                  }
              });
              // AJAX call to get cat group
              $.ajax({
                  url: '{{ route("getCatGroupBySellerWise") }}',
                  type: 'GET',
                  data: { seller_id: seller_drop },
                  dataType: 'json',
                  success: function (response) {
                      $('#cat_group_drop').empty();
                      $('#cat_group_drop').prop('disabled', false);
                      // Append options
                      $.each(response, function (key, value) {
                          var option = $('<option></option>')
                              .attr('value', value.id)
                              .text(value.name);
                          $('#cat_group_drop').append(option);
                      });
                      // Refresh the aiz-selectpicker
                      $('#cat_group_drop').selectpicker('refresh');
                  },
                  error: function (xhr, status, error) {
                      console.error(xhr.responseText);
                  }
              });
              $('#categories_drop').empty();
              $('#categories_drop').prop('disabled', true);
              $('#categories_drop').selectpicker('refresh');
          } else {
              // AJAX call to get brand
              $.ajax({
                  url: '{{ route("getBrandsFromAdmin") }}',
                  type: 'GET',
                  data: { seller_id: 0, category_group_id: 0, category_id: 0 },
                  dataType: 'json',
                  success: function (response) {
                      $('#brand_drop').empty();
                      $('#brand_drop').prop('disabled', false);
                      // Append options
                      $.each(response, function (key, value) {
                          var option = $('<option></option>')
                              .attr('value', value.id)
                              .text(value.name);
                          $('#brand_drop').append(option);
                      });
                      // Refresh the aiz-selectpicker
                      $('#brand_drop').selectpicker('refresh');
                  },
                  error: function (xhr, status, error) {
                      console.error(xhr.responseText);
                  }
              });
              // AJAX call to get cat group
              $.ajax({
                  url: '{{ route("getCatGroupBySellerWise") }}',
                  type: 'GET',
                  data: { seller_id: 0 },
                  dataType: 'json',
                  success: function (response) {
                      $('#cat_group_drop').empty();
                      $('#cat_group_drop').prop('disabled', false);
                      // Append options
                      $.each(response, function (key, value) {
                          var option = $('<option></option>')
                              .attr('value', value.id)
                              .text(value.name);
                          $('#cat_group_drop').append(option);
                      });
                      // Refresh the aiz-selectpicker
                      $('#cat_group_drop').selectpicker('refresh');
                  },
                  error: function (xhr, status, error) {
                      console.error(xhr.responseText);
                  }
              });
              $('#categories_drop').empty();
              $('#categories_drop').prop('disabled', true);
              $('#categories_drop').selectpicker('refresh');
          }
      });

      $('#cat_group_drop').change(function () {
          var category_group_id = $('#cat_group_drop').val();
          
          if (category_group_id.length == 0) {
            var category_group_id = 0;
          }
          var seller_id = $('#seller_drop').val();
          if(seller_id.length == 0){
            var seller_id = 0;
          }
          var category_id = $('#categories_drop').val();
          if(category_id.length == 0){
            var category_id = 0;
          }
          if (category_group_id != 0) {
              // AJAX call to fetch child options
              $.ajax({
                  url: '{{ route("getCategoriesFromAdmin") }}',
                  type: 'GET',
                  data: { seller_id: seller_id, category_group_id: category_group_id },
                  dataType: 'json',
                  success: function (response) {
                      $('#categories_drop').empty();
                      $('#categories_drop').prop('disabled', false);

                      // Append options
                      $.each(response, function (key, value) {
                          var option = $('<option></option>')
                              .attr('value', value.id)
                              .text(value.name);
                          $('#categories_drop').append(option);
                      });
                      // Refresh the aiz-selectpicker
                      $('#categories_drop').selectpicker('refresh');
                  },
                  error: function (xhr, status, error) {
                      console.error(xhr.responseText);
                  }
              });
          } else {
              $('#categories_drop').empty().prop('disabled', true);
              $('#categories_drop').selectpicker('refresh');
          }
          $.ajax({
              url: '{{ route("getBrandsFromAdmin") }}',
              type: 'GET',
              data: { seller_id: seller_id, category_group_id: category_group_id, category_id: category_id },
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
                  // Refresh the aiz-selectpicker
                  $('#brand_drop').selectpicker('refresh');
              },
              error: function (xhr, status, error) {
                  console.error(xhr.responseText);
              }
          });
      });

      $('#categories_drop').change(function () {
        var seller_drop = $('#seller_drop').val();
        if(seller_drop.length == 0){
          seller_drop = 0;
        }
        var cat_group_drop = $('#cat_group_drop').val();
        if(cat_group_drop.length == 0){
          cat_group_drop = 0;
        }
        var categories_drop = $('#categories_drop').val();
        if(categories_drop.length == 0){
          categories_drop = 0;
        }
        $.ajax({
            url: '{{ route("getBrandsFromAdmin") }}',
            type: 'GET',
            data: { seller_id: seller_drop, category_group_id: cat_group_drop, category_id: categories_drop },
            dataType: 'json',
            success: function (response) {
                $('#brand_drop').empty();
                $('#brand_drop').prop('disabled', false);
                // Append options
                $.each(response, function (key, value) {
                    var option = $('<option></option>')
                        .attr('value', value.id)
                        .text(value.name);
                    $('#brand_drop').append(option);
                });
                // Refresh the aiz-selectpicker
                $('#brand_drop').selectpicker('refresh');
            },
            error: function (xhr, status, error) {
                console.error(xhr.responseText);
            }
        });
      });

      $('#pullPartyCodeBtn').on('click', function () {
        $.ajax({
            url: '{{ route("reward.pullPartyCodeIntoGoogleSheet") }}',
            type: 'GET',
            beforeSend: function(){
              $('.ajax-loader').css("visibility", "visible");
            },
            dataType: 'json',
            success: function (response) {
                AIZ.plugins.notify('success', '{{ translate("Successfully Updated From Google sheet.") }}');
            },
            complete: function(){
              $('.ajax-loader').css("visibility", "hidden");
            },
            error: function (xhr, status, error) {
                console.error(xhr.responseText);
            }
        });
      });

      $('#syncUser').on('click', function () {
        $.ajax({
            url: '{{ route("reward.syncNewUser") }}',
            type: 'GET',
            beforeSend: function(){
              $('.ajax-loader').css("visibility", "visible");
            },
            dataType: 'json',
            success: function (response) {
                AIZ.plugins.notify('success', response+' User synce successfully.');
            },
            complete: function(){
              $('.ajax-loader').css("visibility", "hidden");
            },
            error: function (xhr, status, error) {
                console.error(xhr.responseText);
            }
        });
      });

      $('#exportBtn').on('click', function () {
          $.ajax({
              url: '{{ route("reward.insertDataFromGoogleSheet") }}',
              type: 'GET',
              beforeSend: function(){
                $('.ajax-loader').css("visibility", "visible");
              },
              dataType: 'json',
              success: function (response) {
                  // AIZ.plugins.notify('success', '{{ translate('Successfully Update into table.') }}');
              },
              complete: function(){
                $('.ajax-loader').css("visibility", "hidden");
              },
              error: function (xhr, status, error) {
                  console.error(xhr.responseText);
              }
          });
      });

      $('#pullBtn').on('click', function () {
          $.ajax({
              url: '{{ route("reward.exportDataFromDatabase") }}',
              type: 'GET',
              beforeSend: function(){
                $('.ajax-loader').css("visibility", "visible");
              },
              dataType: 'json',
              success: function (response) {
                  AIZ.plugins.notify('success', '{{ translate('Successfully Export Data into google sheet.') }}');
              },
              complete: function(){
                $('.ajax-loader').css("visibility", "hidden");
              },
              error: function (xhr, status, error) {
                  console.error(xhr.responseText);
              }
          });
      });
    });

    // $('#city_drop').change(function() {
    //   var value = $(this).val();
    //   if(value != ""){
    //     $('#frm_userList').submit();
    //   }
    // });
    // function sort_userList() {
    //   alert();
    //   $('#frm_userList').submit();
    // }
    
      
    function update_preferance(el,id) {
      if (el.checked) {
        var status = 1;
      } else {
        var status = 0;
      }
      $.post('{{ route('reward.updatePreferance') }}', {
        _token: '{{ csrf_token() }}',
        id: el.value,
        status: status
      }, function(data) {
        if (data.status == 1) {
          if(data.preference == 1){
            $('#div_rewards_percentage_'+id).empty();
            var input = `<input class="form-control rewards-percentage" type="number" name="rewards_percentage_${id}" id="rewards_percentage_${id}" value="${data.rewards_percentage}"  data-id="${id}">`;
            $('#div_rewards_percentage_' + id).append(input);  
          }else if(data.preference == 0){
            $('#div_rewards_percentage_'+id).empty();
            var input = `<span class="text-muted text-truncate-2">${data.rewards_percentage}</span>`;
            $('#div_rewards_percentage_' + id).append(input); 
          }
          AIZ.plugins.notify('success', '{{ translate('Preferance updated successfully.') }}');
        } else {
          AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
        }
      });
    }

    $(document).on('keypress', '.rewards-percentage', function(e) {
        if (e.which == 13) { // Detect the Enter key (keyCode 13)
            var id = $(this).data('id'); // Get the reward ID from the data attribute
            update_reward(this, id); // Call the function and pass the element and ID
        }
    });

    function update_reward(el, id) {
        // Get the value of the input
        var value = $(el).val();
        if(value < 0){
          value = 0;
          alert('Value will be minimum 0');
        }else if(value > 2.5){
          value = 2.5;
          alert('Value will be maximum 2.5');
        }
        $('#rewards_percentage_' + id).val(value);
        $.post('{{ route('reward.updateReward') }}', {
            _token: '{{ csrf_token() }}',
            id: id,
            rewards_percentage: value
        }, function(data) {
            if (data.status == 1) {
                AIZ.plugins.notify('success', '{{ translate('Reward updated successfully.') }}');
            } else {
                AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
            }
        });
    }

    function exportBtn(){
      alert();
    }   
  
  </script>
  <script>
    document.addEventListener("DOMContentLoaded", function () {
        function sort_userList() {
            document.getElementById('frm_userList').submit();
        }
        window.sort_userList = sort_userList;
    });
    document.getElementById("search_text").addEventListener("keydown", function(event) {
        if (event.key === "Enter") {
            event.preventDefault();
            document.getElementById("frm_userList").submit();
        }
    });
  </script>
@endsection

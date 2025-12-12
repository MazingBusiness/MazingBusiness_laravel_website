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

        <div style="color: black !important;" class="text-muted large mb-2">Showing rewards from <strong> 
          Early Payment</strong>, <strong>Offers</strong>, and <strong>Manual</strong> only (Logistics excluded). 
      </div>
      </div>
      <div class="col text-right">
      <div class="row" style="margin-top:10px">
        <div class="col-md-10">
          <form method="GET" action="{{ route('reward.exportRewards') }}">
              <button type="submit" style="border-radius:42px;background-color:#6A5ACD;" class="btn btn-success">
                  <i class="fas fa-file-export"></i> Export
              </button>
          </form>
        </div>
        <div class="col-md-2">
          <button type="button" style="border-radius:42px;background-color:#5acd9d;" class="btn btn-success" data-toggle="modal" data-target="#exampleModalCenter">
            Upload
          </button>
        </div>
          <?php /*<a href="javascript:void(0)" class="btn btn-circle btn-info" id="exportBtn">
            <span>{{ translate('Update Records From Sheet to Database') }}</span>
          </a>
          <a href="javascript:void(0)" class="btn btn-circle btn-success" id="pullBtn">
            <span>{{ translate('Pull Records From Database') }}</span>
          </a> */ ?>
        </div>
      </div>
    </div>
  </div>
  <br>
  <div class="card">
    <form class="" id="frm_userList" action="" method="GET">
      <div class="card-header row gutters-5">
        <?php /* <div class="col-md-2 ml-auto">
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
        </div> */ ?>

        {{-- ✅ WhatsApp Status Filter --}}
      <div class="col-md-2">
        <select class="form-control" name="wa_status" id="wa_status" onchange="sort_userList()">
            <option value="">{{ translate('All Status') }}</option>
            <option value="sent"      {{ ($wa_status ?? '') === 'sent' ? 'selected' : '' }}>{{ translate('Sent') }}</option>
            <option value="delivered" {{ ($wa_status ?? '') === 'delivered' ? 'selected' : '' }}>{{ translate('Delivered') }}</option>
            <option value="read"      {{ ($wa_status ?? '') === 'read' ? 'selected' : '' }}>{{ translate('Read') }}</option>
        </select>
      </div>
        <div class="col-md-3">
          <input type="text"class="form-control" name="search_text" id="search_text" placeholder="Partycode, Company Name" value="{{ $search_text }}">
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
    
      <!-- <div class="text-muted small mb-2">Showing rewards from <strong>Early Payment</strong>, <strong>Offers</strong>, and <strong>Manual</strong> only (Logistics excluded).
      </div> -->

      <div class="card-header row gutters-5">
        <div class="col">
          <h5 class="mb-md-0 h6">{{ translate('All Users') }}</h5>
        </div>
        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif
      </div>

      <div class="card-body">
        <table class="table aiz-table mb-0">
          <thead>
            <tr>
              <th data-breakpoints="md">{{ translate('Party Code') }}</th>
              <th>{{ translate('User Company Name') }}</th>
              <th>{{ translate('User\'s City') }}</th>
              <th>{{ translate('Assigned Warehouse') }}</th>
              <th>{{ translate('Assigned Manager') }}</th>              
              <th data-breakpoints="md">{{ translate('Dr Balance') }}</th>
              <th data-breakpoints="md">{{ translate('Cr Balance') }}</th> 
              <th data-breakpoints="md">{{ translate('Remaining Balance') }}</th>  
              <th data-breakpoints="md">{{ translate('Status') }}</th>
              <th>Action</th>            
            </tr>
          </thead>
          <tbody>
          @foreach ($userList as $key => $user)
              <tr>
                  <td>
                      <span class="text-muted text-truncate-2">{{ $user['party_code'] ?? 'N/A' }}</span>
                  </td>
                  <td>
                      <div class="form-group d-inline-block">
                          <div class="col">
                              <span class="text-muted text-truncate-2">
                                  {{ $user['user_data']['company_name'] ?? 'N/A' }}
                              </span>
                          </div>
                      </div>
                  </td>
                  <td>
                      <span class="text-muted text-truncate-2">
                          {{ $user['get_user_addresses']['city'] ?? 'N/A' }}
                      </span>
                  </td>
                  <td>
                      <span class="text-muted text-truncate-2">
                          {{ $user['warehouse']['name'] ?? 'N/A' }}
                      </span>
                  </td>
                  <td>
                      <span class="text-muted text-truncate-2">
                          {{ $user['user_data']['get_manager']['name'] ?? 'N/A' }}
                      </span>
                  </td> 
                                 
                  <td>
                      <span class="text-muted text-truncate-2">
                          {{ $user['total_dr_rewards'] ?? 0 }}
                      </span>
                  </td>
                  <td>
                      <span class="text-muted text-truncate-2">
                          {{ $user['total_cr_rewards'] ?? 0 }}
                      </span>
                  </td>
                  <td>
                      <span class="text-muted text-truncate-2">
                          {{ ($user['total_dr_rewards'] ?? 0) - ($user['total_cr_rewards'] ?? 0) }}
                      </span>
                  </td>

                  <td>
                  @php $st = optional($user->latestCloudResponse)->status; @endphp
                  @if($st)
                    <span style="width:auto;" class="badge badge-{{ $st === 'read' ? 'success' : ($st === 'delivered' ? 'info' : 'secondary') }}">
                      {{ ucfirst($st) }}
                    </span>
                  @else
                    <span class="text-muted">N/A</span>
                  @endif
                </td>

                  <td>
                    <div style="display:inline-flex; align-items:center; gap:10px;">
                      <button class="btn btn-primary btn-sm my_pdf"
                              data-party-code="{{ $user['party_code'] ?? '' }}"
                              data-user-id="{{ $user['id'] ?? '' }}">
                        Pdf
                      </button>

                      <button class="btn btn-success btn-sm send-wa"
                              data-url="{{ route('reward.claim.whatsapp.single', ['partyCode' => $user['party_code'] ?? '' ]) }}"
                              data-party-code="{{ $user['party_code'] ?? '' }}">
                        WhatsApp
                      </button>
                    </div>
                  </td>
              </tr>
          @endforeach

          </tbody>
        </table>
        <div class="aiz-pagination">
          {{ $userList->withQueryString()->links() }}
        </div>
      </div>   
  </div>

  <div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <form name="" id="" action="{{ route('reward.importCreditNoteRewards') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLongTitle">Upload Rewards Creadit Note</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <input type="file" class="form-control" id="excel_file" name="excel_file" accept=".xls,.xlsx">
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary">Upload</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="modal fade" id="pdfModal" tabindex="-1" role="dialog" aria-labelledby="pdfModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" style="height: 90vh;"> <!-- Set modal height -->
            <div class="modal-header">
                <h5 class="modal-title" id="pdfModalLabel">View PDF</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 0; height: 100%;">
                <!-- Embed PDF here -->
                <iframe id="pdfViewer" src="" frameborder="0" width="100%" height="100%" style="height: 100%;"></iframe>
            </div>
        </div>
    </div>
</div>
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

      $('#exportBtn').on('click', function () {
        $.ajax({
            url: '{{ route("reward.exportRewards") }}',
            type: 'GET',
            beforeSend: function(){
              $('.ajax-loader').css("visibility", "visible");
            },
            dataType: 'json',
            success: function (response) {
                AIZ.plugins.notify('success', '{{ translate("Successfully Export.") }}');
            },
            complete: function(){
              $('.ajax-loader').css("visibility", "hidden");
            },
            error: function (xhr, status, error) {
                console.error(xhr.responseText);
            }
        });
      });



      // $('#exportBtn').on('click', function () {
      //     $.ajax({
      //         url: '{{ route("reward.insertDataFromGoogleSheet") }}',
      //         type: 'GET',
      //         beforeSend: function(){
      //           $('.ajax-loader').css("visibility", "visible");
      //         },
      //         dataType: 'json',
      //         success: function (response) {
      //             // AIZ.plugins.notify('success', '{{ translate('Successfully Update into table.') }}');
      //         },
      //         complete: function(){
      //           $('.ajax-loader').css("visibility", "hidden");
      //         },
      //         error: function (xhr, status, error) {
      //             console.error(xhr.responseText);
      //         }
      //     });
      // });

      $(document).on('click', '.my_pdf', function(event) {
            event.preventDefault(); // Prevent default link behavior
    
            // Get user ID from data attribute
            let party_code = $(this).data('party-code');

            
            // alert("some thing went wrong!");
            // return;

            // Make an AJAX request to get the PDF URL
            $.ajax({
                url: `/admin/reward-pdf/${party_code}`, // Updated to match the correct route
                type: 'GET',
                success: function(response) {
                    console.log("AJAX Response:", response); // Log the response for debugging
                    if (response) {
                        // Set the PDF URL in the iframe
                        $('#pdfViewer').attr('src', response);


                        // Show the modal
                        $('#pdfModal').modal('show');
                    } else {
                      AIZ.plugins.notify('info',"Failed to generate PDF. Please try again.");
                        
                    }
                },
                error: function(xhr, status, error) {
                    
                    AIZ.plugins.notify('danger',"An error occurred while generating the PDF. ");
                    //alert("An error occurred while generating the PDF. Please check the console for details.");
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


    // WhatsApp send (single party)
    $(document).on('click', '.send-wa', function (e) {
        e.preventDefault();

        const $btn = $(this);
        const url  = $btn.data('url') || '';
        const party = $btn.data('party-code') || '';

        if (!url || !party) {
            AIZ.plugins.notify('danger', 'Missing party code or URL.');
            return;
        }

        // disable + show working state
        const oldHtml = $btn.html();
        $btn.prop('disabled', true).html('Sending…');

        $.ajax({
            url: url,     // GET /admin/reward/claim-wa/{partyCode}
            type: 'GET',
            success: function (res) {
                // {ok: true/false, message: "...", to: "+91..."}
                if (res && res.ok) {
                    AIZ.plugins.notify('success', `WhatsApp sent to ${res.to || 'customer'}.`);
                } else {
                    AIZ.plugins.notify('warning', (res && res.message) ? res.message : 'Could not send on WhatsApp.');
                }
            },
            error: function (xhr) {
                AIZ.plugins.notify('danger', 'WhatsApp send failed.');
            },
            complete: function () {
                $btn.prop('disabled', false).html(oldHtml);
            }
        });
    });
  </script>
@endsection

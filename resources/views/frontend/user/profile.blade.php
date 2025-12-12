@extends('frontend.layouts.user_panel')

@section('panel_content')
  <style>
    .ajax-loader {
      visibility: hidden;
      background-color: rgba(255,255,255,0.7);
      position: absolute;
      z-index: 999999 !important;
      width: 100%;
      height:100%;
    }

    .ajax-loader img {
      position: relative;
      top:50%;
      left:50%;
    }
  </style>
  <div class="ajax-loader">
    <img src="{{ url('https://mazingbusiness.com/public/assets/img/ajax-loader.gif') }}" class="img-responsive" />
  </div>
  <div class="aiz-titlebar mt-2 mb-4">
    <div class="row align-items-center">
      <div class="col-md-6">
        <h1 class="h3">{{ translate('Manage Profile') }}</h1>
      </div>
    </div>
  </div>
  <form action="{{ route('user.profile.update') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <!-- Basic Info-->
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0 h6 w-100">{{ translate('Basic Info') }}<span class="float-right">Party Code:
            {{ Auth::user()->party_code }}</span></h5>
      </div>
      <div class="card-body">
        <div class="form-group row">
          <label class="col-md-2 col-form-label">{{ translate('Full Name') }}</label>
          <div class="col-md-10">
            <input type="text" class="form-control" placeholder="{{ translate('Your Name') }}" name="name"
              value="{{ Auth::user()->name }}">
          </div>
        </div>

        <div class="form-group row">
          <label class="col-md-2 col-form-label">{{ translate('Company Name') }}</label>
          <div class="col-md-10">
            <input disabled="disabled" type="text" class="form-control" placeholder="{{ translate('Company Name') }}" name="company_name"
              value="{{ Auth::user()->company_name }}">
          </div>
        </div>

        <div class="form-group row">
          <label class="col-md-2 col-form-label">{{ translate('Aadhar Card') }}</label>
          <div class="col-md-10">
            <input type="text" class="form-control" placeholder="{{ translate('Aadhar Card') }}" name="aadhar_card"
              value="{{ Auth::user()->aadhar_card }}">
          </div>
        </div>

        <div class="form-group row">
          <label class="col-md-2 col-form-label">{{ translate('Phone No.') }}</label>
          <div class="col-md-10">
            <input type="text" class="form-control" name="phone" value="{{ Auth::user()->phone }}"
              disabled="disabled" readonly="readonly">
          </div>
        </div>

        <div class="form-group row">
          <label class="col-md-2 col-form-label">{{ translate('GSTIN') }}</label>
          <div class="col-md-10">
            <input type="text" class="form-control" placeholder="{{ translate('GSTIN') }}" name="gstin"
              value="{{ Auth::user()->gstin }}"
              @if (Auth::user()->gstin) disabled="disabled" readonly="readonly" @endif>
          </div>
        </div>

        <div class="form-group row">
          <label class="col-md-2 col-form-label">{{ translate('Photo') }}</label>
          <div class="col-md-10">
            <div class="input-group" data-toggle="aizuploader" data-type="image">
              <div class="input-group-prepend">
                <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse') }}</div>
              </div>
              <div class="form-control file-amount">{{ translate('Choose File') }}</div>
              <input type="hidden" name="photo" value="{{ Auth::user()->avatar_original }}" class="selected-files">
            </div>
            <div class="file-preview box sm">
            </div>
          </div>
        </div>
        {{-- <div class="form-group row">
          <label class="col-md-2 col-form-label">{{ translate('Your Password') }}</label>
          <div class="col-md-10">
            <input type="password" class="form-control" placeholder="{{ translate('New Password') }}"
              name="new_password">
          </div>
        </div> --}}

        <div class="form-group row">
            <label class="col-md-2 col-form-label">{{ translate('Your Password') }}</label>
            <div class="col-md-8">
              <input type="password" class="form-control" placeholder="{{ translate('New Password') }}" name="new_password" id="passwordField">
            </div>
            <div class="col-md-2">
              <button type="button" class="btn btn-outline-secondary toggle-password"  onclick="myFunction()" id="togglePassword" aria-label="Toggle password visibility">
                Show
              </button>
            </div>
          </div>


        <div class="form-group row">
          <label class="col-md-2 col-form-label">{{ translate('Confirm Password') }}</label>
          <div class="col-md-10">
            <input type="password" class="form-control" placeholder="{{ translate('Confirm Password') }}"
              name="confirm_password">
          </div>
        </div>

      </div>
    </div>

    <div class="form-group mb-0 text-right">
      <button type="submit" class="btn btn-primary">{{ translate('Update Profile') }}</button>
    </div>
  </form>

  <br>

  <!-- Address -->
  <div class="card">
    <div class="card-header">
      <h5 class="mb-0 h6">{{ translate('Your Company Details') }}</h5>
    </div>
    <div class="card-body">
      <div class="row gutters-10">
        @php
            $count = 10;
        @endphp
        @foreach (Auth::user()->addresses as $key => $address)

          <div class="col-lg-6">
            <style>
                 .text-muted {
                    color: #6c757d !important;
                    font-size:13px !important;
                 }
            </style>
            <div><span class="small-text text-muted">{{ Auth::user()->party_code . "" . $count }}</span></div>
            <div
              class="border p-3 pr-5 rounded mb-3 position-relative @if ($address->set_default) border-success @endif">
              {{-- <div>
                <span class="w-50 fw-600">{{ translate('Company Name') }}:</span>
                <span class="ml-2">{{ $address->company_name }}</span>
                <span>{{Auth::user()->party_code."_".$count}}</span>
              </div> --}}

              <div>
                <span class="w-50 fw-600">{{ translate('Company Name') }}:</span>
                <span class="ml-2">{{ $address->company_name }}</span>

              </div>
              @if ($address->gstin)
                <div>
                  <span class="w-50 fw-600">{{ translate('GSTIN') }}:</span>
                  <span class="ml-2">{{ $address->gstin }}</span>
                </div>
              @endif
              <div>
                <span class="w-50 fw-600">{{ translate('Address') }}:</span>
                <span class="ml-2">{{ $address->address }}</span>
              </div>
              <div>
                <span class="w-50 fw-600">{{ translate('Pincode') }}:</span>
                <span class="ml-2">{{ $address->postal_code }}</span>
              </div>
              <div>
                <span class="w-50 fw-600">{{ translate('City') }}:</span>
                <?php /* ?><span class="ml-2">{{ optional($address->city)->name }}</span><?php */ ?>
                <span class="ml-2">{{ $address->city }}</span>
              </div>
              <div>
                <span class="w-50 fw-600">{{ translate('State') }}:</span>
                <span class="ml-2">{{ optional($address->state)->name }}</span>
              </div>
              <div>
                <span class="w-50 fw-600">{{ translate('Country') }}:</span>
                <span class="ml-2">{{ optional($address->country)->name }}</span>
              </div>
              <div>
                <span class="w-50 fw-600">{{ translate('Phone') }}:</span>
                <span class="ml-2">{{ $address->phone }}</span>
              </div>
              <div class="dropdown position-absolute right-0 top-0">
                <button class="btn bg-gray px-2" type="button" data-toggle="dropdown">
                  <i class="la la-ellipsis-v"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" onclick="edit_address('{{ $address->id }}')">
                    {{ translate('Edit') }}
                  </a>
                  @if (!$address->set_default)
                    <a class="dropdown-item"
                      href="{{ route('addresses.set_default', $address->id) }}">{{ translate('Make This Default') }}</a>
                  @endif
                </div>
              </div>
            </div>
          </div>
          @php
              $count++;
          @endphp
        @endforeach
        <div class="col-lg-6 mx-auto" onclick="add_new_address()">
          <div class="border p-3 rounded mb-3 c-pointer text-center bg-light">
            <i class="la la-plus la-2x"></i>
            <div class="alpha-7">{{ translate('Add New Company Detail') }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>


  <!-- Change Email -->
  <form action="{{ route('user.change.email') }}" method="POST">
    @csrf
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0 h6">{{ translate('Change your email') }}</h5>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-2">
            <label>{{ translate('Your Email') }}</label>
          </div>
          <div class="col-md-10">
            <div class="input-group mb-3">
              <input type="email" class="form-control" placeholder="{{ translate('Your Email') }}" name="email"
                value="{{ Auth::user()->email }}" />
              <div class="input-group-append">
                <button type="button" class="btn btn-outline-secondary new-email-verification">
                  <span class="d-none loading">
                    <span class="spinner-border spinner-border-sm" role="status"
                      aria-hidden="true"></span>{{ translate('Sending Email...') }}
                  </span>
                  <span class="default">{{ translate('Verify') }}</span>
                </button>
              </div>
            </div>
            <div class="form-group mb-0 text-right">
              <button type="submit" class="btn btn-primary">{{ translate('Update Email') }}</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </form>
  
    
  <div class="card">
    <div class="card-header">
      <h5 class="mb-0 h6">{{ translate('Active to order your OWN brand') }}</h5>
    </div> 
    <div class="card-body">
      <div id="own_brand_msg">
      @if(Auth::user()->own_brand == 1 AND Auth::user()->admin_approved_own_brand == 0)
        <div class="alert alert-success" role="alert">Thank you. Your request had been sent. Please wait for admin approved.</div>
      @elseif(Auth::user()->own_brand == 1 AND Auth::user()->admin_approved_own_brand == 1)
        <div class="alert alert-success" role="alert">Your OWN Brand request had been approved by Admin.</div>
      @endif
      </div>
      <div class="row">
        <div class="col-md-2">
          <label>{{ translate('Send Request') }}</label>
        </div>
        <div class="col-md-10">
          <div class="input-group mb-3">
            <select id="own_brand" name="own_brand" class="form-control">
              <option value="">------ Select Option ------</option>
              <option value="1" @if(Auth::user()->own_brand == 1) selected @endif>Active My Profile for OWN Brand</option>
              <option value="0" @if(Auth::user()->own_brand == 0 AND Auth::user()->admin_approved_own_brand == 1) selected @endif>In active My Profile for OWN Brand</option>
            </select>
            <div class="input-group-append">
              <button type="button" class="btn btn-primary" id="btnOwnBrandSubmit"><span class="default">{{ translate('Submit Request') }}</span></button>
            </div>
          </div>
          <!-- <div class="form-group mb-0 text-right">
            <button type="submit" class="btn btn-primary">{{ translate('Update Email') }}</button>
          </div> -->
        </div>
      </div>
    </div>
  </div>
  
  <script>
    function myFunction() {
        var x = document.getElementById("passwordField");
        var button=document.getElementById("togglePassword");
        if (x.type === "password") {
            x.type = "text";

            button.textContent = "Hide";
        } else {
            button.textContent = "Show";
            x.type = "password";
        }
    }
    $(document).ready(function() {
      $('#btnOwnBrandSubmit').click(function(){
        var own_brand = $('#own_brand').val();
        if(own_brand == 0){
          var conf = confirm('You profile will de active from OWN Brand!');
          if(conf==false){
            return false;
          }
        }else{
          var conf = confirm('You profile will active for OWN Brand!');
          if(conf==false){
            return false;
          }
        }
        if(own_brand != ""){
          $.ajax({
              url: '{{ route("ownBrandRequestSubmit") }}',
              type: 'POST',
              beforeSend: function(){
                $('.ajax-loader').css("visibility", "visible");
              },
              data: { own_brand: own_brand , _token: '{{ csrf_token() }}'},
              dataType: 'json',
              success: function (response) {
                  // console.log(response); // Log the response for debugging
                  $('#own_brand_msg').empty(); // Clear the div before appending new data
                  $('#own_brand_msg').append(response.html); // Append the response data
              },
              complete: function(){
                $('.ajax-loader').css("visibility", "hidden");
              },
              error: function (xhr, status, error) {
                  console.error(xhr.responseText);
              }
          });
        }else{
          return false;
        }
      });
    });
</script>
@endsection

@section('modal')
  @include('frontend.partials.address_modal')
@endsection

@section('script')
  <script type="text/javascript"> 
    $('.new-email-verification').on('click', function() {
      $(this).find('.loading').removeClass('d-none');
      $(this).find('.default').addClass('d-none');
      var email = $("input[name=email]").val();

      $.post('{{ route('user.new.verify') }}', {
        _token: '{{ csrf_token() }}',
        email: email
      }, function(data) {
        data = JSON.parse(data);
        $('.default').removeClass('d-none');
        $('.loading').addClass('d-none');
        if (data.status == 2)
          AIZ.plugins.notify('warning', data.message);
        else if (data.status == 1)
          AIZ.plugins.notify('success', data.message);
        else
          AIZ.plugins.notify('danger', data.message);
      });
    });
  </script>

  @if (get_setting('google_map') == 1)
    @include('frontend.partials.google_map')
  @endif
@endsection




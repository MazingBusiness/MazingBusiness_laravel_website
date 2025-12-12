@extends('frontend.layouts.app')
@section('content')
    <section class="gry-bg py-5">
        <div class="profile">
            <div class="container">
                <div class="row">
                    <div class="mx-auto" style="width: 100%;">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex flex-wrap align-items-center justify-content-between" style="width:100%">                                 
                                    <button type="button" class="btn btn-primary" id="newWarrentyClaim" style="white-space: nowrap; padding: 10px 20px;" @if($warrantyUser->user_type == "") data-toggle="modal" data-target="#warrentyClaimModal" data-warranty_user_id="{{ $warrantyUserId }}" @endif>
                                        New Warrenty Claim
                                    </button>                                    
                                </div>
                            </div>                            
                            <div class="card-body">
                                <span style="font-size:20px;">Warrenty Claim History</span>
                                <table class="table table-bordered text-center" id="resultsTable">
                                    <thead style="background-color: #007baf; color: #fff;">
                                        <tr style="text-align: center;">
                                            <th>Date</th>
                                            <th data-breakpoints="md">Ticket Number</th>
                                            <!-- <th data-breakpoints="md">Product Name</th> -->
                                            <th data-breakpoints="md">Status</th>
                                            <th data-breakpoints="md">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if(count($warrantyClaim)>0 )
                                            @foreach($warrantyClaim as $wcKey)
                                                <tr style="text-align: center;">
                                                    <td><a href="{{ route('warrantyDetails',['ticket_id'=>$wcKey->ticket_id])}}">{{ $wcKey->created_at->format('d-m-Y') }}</a></td>
                                                    <td><a href="{{ route('warrantyDetails',['ticket_id'=>$wcKey->ticket_id])}}">{{ $wcKey->ticket_id}}</a></td>
                                                    <!-- <td>OPEL 5332 - ELECTRIC BLOWER 600WATTS</td> -->
                                                    <td class="text-center">{{ ucfirst($wcKey->status)}}</td>
                                                    <td class="text-center">
                                                        @if($wcKey->corrier_info != NULL)
                                                            <a href="{{ route('warrantyDetails',['ticket_id'=>$wcKey->ticket_id])}}" class="btn btn-soft-info btn-icon btn-circle btn-sm" title="View Status">
                                                                <i class="las la-eye"></i>
                                                            </a>
                                                            <a href="{{ $wcKey->pdf_link }}" class="btn btn-soft-success btn-icon btn-circle btn-sm" title="Download Shipment attachment" target="_blank">
                                                                <i class="las la-cloud-download-alt"></i>
                                                            </a>
                                                        @else
                                                            <a href="javascript:void(0)" class="btn btn-soft-danger btn-icon btn-circle btn-sm" title="Upload Courier Information" data-toggle="modal" data-target="#courierUploadModal" data-claim-id="{{ $wcKey->id }}" data-ticket="{{ $wcKey->ticket_id }}">
                                                                <i class="las la-cloud-upload-alt"></i>
                                                            </a>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @else
                                            <tr style="text-align: center;">
                                                <td colspan="4">No Record Found.</td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>        
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <div class="modal fade" id="warrentyClaimModal" tabindex="-1" role="dialog" aria-labelledby="warrentyClaimModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="warrentyClaimModalLabel">Enter Your Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <?php /*<form id="warrentyUser" name="warrentyUser" method="POST" action="{{ route('warrantyUserTypePost') }}">
                    @csrf <!-- CSRF token for security -->
                    <input type="hidden" name="warranty_user_id" id="warranty_user_id">                
                    <div class="form-group">
                        <label for="party-name">User Type</label>
                        <select class="form-control" name="update_user_type" id="update_user_type">
                            <option value="">---- Select User Type ----</option>
                            <!-- <option value="deller" @if($warrantyUser->user_type == 'deller' OR $warrantyUser->user_type == 'customer') selected  @endif>Deller</option> -->
                            <option value="sub_deller" @if($warrantyUser->user_type == 'sub_deller') selected  @endif>Sub Deller</option>
                            <option value="end_user" @if($warrantyUser->user_type == 'end_user') selected  @endif>End User</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" id="next">Next</button>
                </form> */ ?>
                <form id="reg-form" class="form-default" role="form" id="frmWarrantyUserType" name="frmWarrantyUserType" action="{{ route('warrantyUserTypePost') }}" method="POST">
                    @csrf
                    <div class="form-group">
                        <!-- <label for="party-name">User Type</label>
                        <select class="form-control" name="update_user_type" id="update_user_type">
                            <option value="">---- Select User Type ----</option>
                            <option value="sub_deller" @if($warrantyUser->user_type == 'sub_deller') selected  @endif>Sub Deller</option>
                            <option value="end_user" @if($warrantyUser->user_type == 'end_user') selected  @endif>End User</option>
                        </select> -->
                        <div class="row">
                            <div class="col-md-6">
                                <input type="radio" name="user_type" value="customer" checked>
                                <label for="party-name">Sub Delear</label>
                            </div>
                            <div class="col-md-6">
                                <input type="radio" name="user_type" value="end_user">
                                <label for="party-name">End User</label>                                
                            </div>
                        </div>
                    </div>
                    <div class="form-group {{ old('name') || old('company_name') || old('aadhar_card') || old('email') || old('postal_code') || $errors->has('name') || $errors->has('company_name') || $errors->has('aadhar_card') || $errors->has('email') || $errors->has('postal_code') ? 'd-none' : '' }}" id="divGstin">
                        <input type="text" class="form-control{{ $errors->has('gstin') ? ' is-invalid' : '' }}"
                            value="{{ old('gstin') }}" placeholder="{{ translate('GSTIN') }}" name="gstin" id="gstin">
                        <small id="gstin_status" class="form-text mt-1"></small> <!-- <-- status text here -->
                        @if ($errors->has('gstin'))
                            <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('gstin') }}</strong>
                            </span>
                        @endif
                    </div>
                    <small id="gstin_status" class="form-text mt-1"></small>
                    <!-- <span id="gstin_err" class="text-danger"></span> -->
                    <div class="form-group phone-form-group mb-3">
                      <input type="tel" id="phone-code"
                        class="form-control{{ $errors->has('phone') ? ' is-invalid' : '' }}" value="{{ $warrantyUser->phone }}"
                        placeholder="Phone No." name="phone" autocomplete="off" max=15 readonly><br/>                        
                        @if ($errors->has('phone'))
                          <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('phone') }}</strong>
                          </span>
                        @endif
                    </div>
                    <span id="phone_err" class="text-danger"></span>
                    <div class="form-group email-form-group mb-3">
                      <input type="email" class="form-control {{ $errors->has('email') ? ' is-invalid' : '' }}"
                        value="{{ old('email') }}" placeholder="{{ translate('Email') }}" name="email" id="email"
                        autocomplete="off">
                      <input type="hidden" name="email_err_flag" id="email_err_flag" value = "0">
                      @if ($errors->has('email'))
                        <span class="invalid-feedback" role="alert">
                          <strong>{{ $errors->first('email') }}</strong>
                        </span>
                      @endif
                    </div> 
                    <span id="email_err" class="text-danger"></span>                   
                    <input type="hidden" name="country_code" value="91">
                    <div class="{{ $errors->count() && !old('gstin') && !$errors->has('gstin') ? '' : 'd-none' }}" id="no-gstin">
                      <div class="form-group">
                        <input type="text" class="form-control{{ $errors->has('name') ? ' is-invalid' : '' }}"
                          value="{{ old('name') }}" placeholder="{{ translate('Full Name') }}" name="name" id="name">
                        @if ($errors->has('name'))
                          <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('name') }}</strong>
                          </span>
                        @endif
                      </div>
                      <span id="name_err" class="text-danger"></span>
                      <div class="form-group">
                        <input type="text" class="form-control{{ $errors->has('company_name') ? ' is-invalid' : '' }}"
                          value="{{ old('company_name') }}" placeholder="{{ translate('Company Name') }}"
                          name="company_name" id="company_name">
                        @if ($errors->has('company_name'))
                          <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('company_name') }}</strong>
                          </span>
                        @endif
                      </div>
                      <span id="company_name_err" class="text-danger"></span>
                      <div class="form-group">
                        <input type="number" class="form-control{{ $errors->has('aadhar_card') ? ' is-invalid' : '' }}"
                          value="{{ old('aadhar_card') }}" placeholder="{{ translate('Aadhar No.') }}"
                          name="aadhar_card" id="aadhar_card">
                        @if ($errors->has('aadhar_card'))
                          <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('aadhar_card') }}</strong>
                          </span>
                        @endif
                      </div>
                      <span id="aadhar_card_err" class="text-danger"></span>

                      <div class="form-group">
                        <input type="text" min="100000" max="999999"
                          class="form-control{{ $errors->has('address') ? ' is-invalid' : '' }}"
                          value="{{ old('address') }}" placeholder="{{ translate('Address') }}" id="address" name="address">
                        @if ($errors->has('address'))
                          <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('address') }}</strong>
                          </span>
                        @endif
                      </div>
                      <div class="form-group">
                        <input type="text" min="100000" max="999999"
                          class="form-control{{ $errors->has('address2') ? ' is-invalid' : '' }}"
                          value="{{ old('address2') }}" placeholder="{{ translate('Address 2') }}" id="address2" name="address2">
                        @if ($errors->has('address2'))
                          <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('address2') }}</strong>
                          </span>
                        @endif
                      </div>
                      <span id="address_err" class="text-danger"></span>
                      <div class="form-group">
                        <input type="text" min="100000" max="999999"
                          class="form-control{{ $errors->has('city') ? ' is-invalid' : '' }}"
                          value="{{ old('City') }}" placeholder="{{ translate('City') }}" id="city" name="city">
                        @if ($errors->has('city'))
                          <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('city') }}</strong>
                          </span>
                        @endif
                      </div>

                      <?php /* <div class="form-group">
                        <select class="form-control{{ $errors->has('state') ? ' is-invalid' : '' }}" id="state" name="state">
                            <option value="" disabled selected>{{ translate('Select State') }}</option>
                            @foreach ($states as $state)
                                <option value="{{ $state->id }}" {{ old('state') == $state->name ? 'selected' : '' }}>
                                    {{ $state->name }}
                                </option>
                            @endforeach
                        </select>
                        @if ($errors->has('state'))
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $errors->first('state') }}</strong>
                            </span>
                        @endif
                      </div> */ ?>
                      <span id="city_err" class="text-danger"></span>
                      <div class="form-group">
                            <input type="number" min="100000" max="999999"
                                class="form-control{{ $errors->has('postal_code') ? ' is-invalid' : '' }}"
                                value="{{ old('postal_code') }}" placeholder="{{ translate('Pincode') }}" id="postal_code" name="postal_code">
                            @if ($errors->has('postal_code'))
                                <span class="invalid-feedback" role="alert">
                                <strong>{{ $errors->first('postal_code') }}</strong>
                                </span>
                            @endif
                      </div>
                      <span id="postal_code_err" class="text-danger"></span>
                    </div>
                    <input type="hidden" name="gst_data" id="gst_data" value="{{ old('gst_data') }}">
                    <div class="mb-0">
                      <label class="aiz-checkbox">
                        <input type="checkbox" name="no_gstin" id="no_gstin"
                          {{ $errors->count() && !old('gstin') && !$errors->has('gstin') ? 'checked="checked"' : '' }}>
                        <span class=opacity-60>{{ translate('Don\'t have a GSTIN?') }}</span>
                        <span class="aiz-square-check"></span>
                        @if ($errors->has('no_gstin'))
                          <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('no_gstin') }}</strong>
                          </span>
                        @endif
                      </label>
                    </div>
                    <div class="mb-5">
                      <button type="submit" class="btn btn-primary btn-block fw-600" id="createAccountBtn">{{ translate('Next') }}</button>
                    </div>
                </form>
            </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="courierUploadModal" tabindex="-1" role="dialog" aria-labelledby="courierUploadModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
            <form action="{{ route('warrantyCorrierInfoUpload') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                <h5 class="modal-title" id="courierUploadModalLabel">Upload Courier Info</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <!-- BS4 -->
                    <span aria-hidden="true">&times;</span>
                </button>
                </div>

                <div class="modal-body">
                <input type="hidden" name="claim_id" id="courier_claim_id">
                <input type="hidden" name="ticket_id" id="courier_ticket_id">

                <div class="form-group mb-2">
                    <label class="mb-1">File (PDF / Image)</label>
                    <input type="file" name="courier_file" class="form-control" required
                        accept=".pdf,.jpg,.jpeg,.png,.webp">
                    <small class="text-muted d-block mt-1">Max 5 MB</small>
                </div>
                <!-- Optional extra fields-->
                <div class="form-group">
                    <label>Courier Name</label>
                    <input type="text" name="courier_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Consignment No </label>
                    <input type="text" name="tracking_no" class="form-control" required>
                </div>
                
                </div>

                <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
            </div>
        </div>
    </div>

@endsection

@section('script')
    <script type="text/javascript">

        $('#courierUploadModal').on('show.bs.modal', function (e) {
            var btn = $(e.relatedTarget);
            $('#courier_claim_id').val(btn.data('claim-id'));
            $('#courier_ticket_id').val(btn.data('ticket'));
        });

        document.getElementById('newWarrentyClaim').addEventListener('click', function () {
            @if($warrantyUser->user_type != "")
                // redirect if user_type not empty
                window.location.href = "{{ route('warrantyAddProductDetails') }}";
            @endif
        });

        $(document).ready(function() {
            $("#no_gstin").on('change', function() {
                $('#email_err').html('');
                // $('#gstin_err').html('');
                $('#phone_err').html('');
                // $('#gstin_success').html('');
                if ($(this).prop('checked') == true) {
                    $('#no-gstin').removeClass('d-none');
                    $('#gstin').addClass('d-none');
                    $('#gstin').val('');
                    $('#gst_data').val('');
                    $('#name').val('');
                    $('#company_name').val('');
                    $('#address').val('');
                    $('#address2').val('');
                    $('#city').val('');
                    $('#postal_code').val('');
                } else {
                    $('#gstin').removeClass('d-none');
                    $('#no-gstin').addClass('d-none');
                }
            });
        });

        // GST
        /** Debounce helper **/
        function debounce(fn, wait) {
        let t;
        return function() {
            clearTimeout(t);
            const ctx = this, args = arguments;
            t = setTimeout(() => fn.apply(ctx, args), wait);
        };
        }

        function setGstinStatusHtml(html) {
            const $el = $('#gstin_status'); // your status element
            $el.removeClass('text-success').addClass('text-danger').html(html);
            $el.find('a').attr({ rel: 'noopener noreferrer' });
        }

        /** Set status helper **/
        function setGstinStatus(text, cls) {
        $('#gstin_status')
            .text(text || '')
            .removeClass('text-success text-danger text-muted')
            .addClass(cls || '');
        }

        /** Clear all GSTIN UI states **/
        function clearGstinUI() {
        setGstinStatus('', '');
        $('#gstin').removeClass('is-valid is-invalid');
            //   $('#gstin_success').html('');
        // don't touch #gstin_err here — leave server-side error messages intact unless we set a new one
        }

        /** Main listener: input/blur + debounce **/
        $(document).on('input', '#gstin', debounce(function() {
            var gstin = $(this).val().trim().toUpperCase();
            $('#gstin').val(gstin); // normalize to uppercase

            // If "Don't have a GSTIN" is checked, skip live validation
            if ($('#no_gstin').is(':checked')) {
                clearGstinUI();
                $('#createAccountBtn').prop('disabled', false);
                return;
            }

            if (gstin.length < 15) {
                clearGstinUI();
                $('#createAccountBtn').prop('disabled', true);
                return;
            }

            // Show "Checking..." UI
            setGstinStatus('Checking...', 'text-muted');
                //   $('#gstin_err').html('');
                //   $('#gstin_success').html('');
            $('#gstin').removeClass('is-valid is-invalid');
            $('#createAccountBtn').prop('disabled', true);
            $('.ajax-loader').css('visibility', 'visible');

            // 1) Verify from AppyFlow
            $.ajax({
                    url: "https://appyflow.in/api/verifyGST",
                    type: 'POST',
                    headers: { "Content-Type": "application/json" },
                    data: JSON.stringify({
                        key_secret: "H50csEwe27SjLf7J2qP9Av28uOm2",
                    gstNo: gstin
                })
            })
            .done(function(response) {
                if (!response || response.hasOwnProperty('error')) {
                    // Invalid from AppyFlow
                    setGstinStatus('Invalid GST', 'text-danger');
                    //   $('#gstin_err').html('Invalid GST');
                    $('#gstin').removeClass('is-valid').addClass('is-invalid');
                    $('#createAccountBtn').prop('disabled', true);
                    return;
                }

                // 2) Check duplication in your DB
                $.ajax({
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    url: '{{ route("checkGsitnExistForWarranty") }}',
                    type: 'POST',
                    data: { gstin: gstin },
                    dataType: 'json'
                })
                .done(function(res) {
                    if (res && res.hasOwnProperty('error')) {
                        setGstinStatusHtml(res.message || 'GSTIN already exists.');
                        $('#gstin').removeClass('is-valid').addClass('is-invalid');
                        $('#createAccountBtn').prop('disabled', true);
                        return;
                    }

                    // ✅ Valid & allowed — fill fields and show success
                    setGstinStatus('Valid GST', 'text-success');
                    //   $('#gstin_err').html('');
                        //   $('#gstin_success').html('Valid GST');
                    $('#gstin').removeClass('is-invalid').addClass('is-valid');

                    // Fill fields from AppyFlow
                    $('#company_name').val(response.taxpayerInfo.tradeNam || '');
                    $('#name').val(response.taxpayerInfo.lgnm || '');
                    $('#gst_data').val(JSON.stringify(response));

                    var addr = response.taxpayerInfo?.pradr?.addr || {};
                    var address  = [(addr.bnm||''), (addr.st||''), (addr.loc||'')].join(', ').replace(/^[, ]+|[, ]+$/g, '');
                    var address2 = [(addr.bno||''), (addr.dst||'')].join(', ').replace(/^[, ]+|[, ]+$/g, '');

                    $('#address').val(address);
                    $('#address2').val(address2);
                    $('#postal_code').val(addr.pncd || '');

                    // Optional helpers if you show them elsewhere
                    $('#gstinHelp').html(gstin);
                    $('#companyNameHelp').html(response.taxpayerInfo.tradeNam || '');
                    $('#namenHelp').html(response.taxpayerInfo.lgnm || '');
                    $('#addressHelp').html(address);
                    $('#address2Help').html(address2);
                    $('#postalCodeHelp').html(addr.pncd || '');

                    // Clear other errors and enable Next
                    // $('#phone-code').val('');
                    $('#phone_err, #address_err, #city_err').html('');
                    $('#createAccountBtn').prop('disabled', false);
                    })
                    .fail(function() {
                    setGstinStatus('Server error while checking GST.', 'text-danger');
                    //   $('#gstin_err').html('Server error while checking GST.');
                    $('#gstin').addClass('is-invalid');
                    $('#createAccountBtn').prop('disabled', true);
                });

            })
            .fail(function() {
                setGstinStatus('Error contacting GST service.', 'text-danger');
                // $('#gstin_err').html('Error contacting GST service.');
                $('#gstin').addClass('is-invalid');
                $('#createAccountBtn').prop('disabled', true);
            })
            .always(function() {
                $('.ajax-loader').css('visibility', 'hidden');
            });

        }, 300));

        /** If "Don't have GSTIN" is toggled, clear UI and unlock Next **/
        $(document).on('change', '#no_gstin', function() {
        if (this.checked) {
            clearGstinUI();
            setGstinStatus('', '');
            $('#gstin').val('').removeClass('is-valid is-invalid');
            $('#createAccountBtn').prop('disabled', false);
        } else {
            // If they uncheck and have 15+ chars, trigger validation
            const v = $('#gstin').val().trim();
            if (v.length >= 15) $('#gstin').trigger('input');
            else $('#createAccountBtn').prop('disabled', true);
        }
        });

        $('#reg-form').on('submit', function(e){
            e.preventDefault();
            $.ajax({
                url: $(this).attr('action'),
                method: 'POST',
                data: $(this).serialize(),
                success: function(resp){
                    if(resp.success){
                        AIZ.plugins.notify('success', 'Submitted successfully ✅');
                        // redirect if needed
                        if (resp.redirect) {
                            window.location.href = resp.redirect; // ⬅️ navigate here
                        }
                    }
                },
                error: function(xhr){
                    if(xhr.status === 422){
                        // Extract the first error message
                        let errors = xhr.responseJSON.errors;
                        let firstError = Object.values(errors)[0][0]; 
                        AIZ.plugins.notify('danger', firstError + ' ❌');
                    } else {
                        AIZ.plugins.notify('danger', 'Something went wrong ❌');
                    }
                }
            });
        });

    </script>
@endsection

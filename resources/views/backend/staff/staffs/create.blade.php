@extends('backend.layouts.app')

@section('content')
  <div class="row">
    <div class="col-lg-6 mx-auto">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0 h6">{{ translate('Staff Information') }}</h5>
        </div>

        <form class="form-horizontal" action="{{ route('staffs.store') }}" method="POST" enctype="multipart/form-data">
          @csrf
          <div class="card-body">

            {{-- Show all validation errors (optional, keeps per-field errors too) --}}
            @if ($errors->any())
              <div class="alert alert-danger">
                <ul class="mb-0">
                  @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                  @endforeach
                </ul>
              </div>
            @endif

            {{-- Name --}}
            <div class="form-group row">
              <label class="col-sm-3 col-from-label" for="name">
                {{ translate('Name') }} <span class="text-danger">*</span>
              </label>
              <div class="col-sm-9">
                <input
                  type="text"
                  id="name"
                  name="name"
                  class="form-control"
                  placeholder="{{ translate('Name') }}"
                  value="{{ old('name') }}"
                  required
                >
                @error('name')
                  <div class="text-danger small">{{ $message }}</div>
                @enderror
              </div>
            </div>

            {{-- Email --}}
            <div class="form-group row">
              <label class="col-sm-3 col-from-label" for="email">
                {{ translate('Email') }} <span class="text-danger">*</span>
              </label>
              <div class="col-sm-9">
                <input
                  type="text"
                  id="email"
                  name="email"
                  class="form-control"
                  placeholder="{{ translate('Email') }}"
                  value="{{ old('email') }}"
                  required
                >
                @error('email')
                  <div class="text-danger small">{{ $message }}</div>
                @enderror
              </div>
            </div>

            {{-- Phone --}}
              <div class="form-group row">
                  <label class="col-sm-3 col-from-label" for="mobile">
                      {{ translate('Phone') }} <span class="text-danger">*</span>
                  </label>
                  <div class="col-sm-9">
                      <div class="input-group">
                          <div class="input-group-prepend">
                              <span class="input-group-text">+91</span>
                          </div>
                          <input
                              type="text"
                              id="mobile"
                              name="mobile"
                              class="form-control"
                              placeholder="{{ translate('Enter 10 digit number') }}"
                              value="{{ old('mobile') }}"
                              maxlength="10"
                              pattern="\d{10}"
                              required
                          >
                      </div>
                      @error('mobile')
                          <div class="text-danger small">{{ $message }}</div>
                      @enderror
                  </div>
              </div>


            {{-- Password (never repopulate for security) --}}
            <div class="form-group row">
              <label class="col-sm-3 col-from-label" for="password">
                {{ translate('Password') }} <span class="text-danger">*</span>
              </label>
              <div class="col-sm-9">
                <input
                  type="password"
                  id="password"
                  name="password"
                  class="form-control"
                  placeholder="{{ translate('Password') }}"
                  required
                >
                @error('password')
                  <div class="text-danger small">{{ $message }}</div>
                @enderror
              </div>
            </div>

            {{-- Role --}}
            <div class="form-group row">
              <label class="col-sm-3 col-from-label" for="role_id">
                {{ translate('Role') }} <span class="text-danger">*</span>
              </label>
              <div class="col-sm-9">
                <select
                  name="role_id"
                  id="role_id"
                  class="form-control aiz-selectpicker"
                  required
                >
                  @foreach ($roles as $role)
                    <option
                      value="{{ $role->id }}"
                      {{ (string) old('role_id') === (string) $role->id ? 'selected' : '' }}
                    >
                      {{ $role->name }}
                    </option>
                  @endforeach
                </select>
                @error('role_id')
                  <div class="text-danger small">{{ $message }}</div>
                @enderror
              </div>
            </div>

            {{-- User Title --}}
            <div class="form-group row">
              <label class="col-sm-3 col-from-label" for="user_title">
                {{ translate('User Title') }} <span class="text-danger">*</span>
              </label>
              <div class="col-sm-9">
                <select
                  name="user_title"
                  id="user_title"
                  class="form-control aiz-selectpicker"
                  required
                >
                  <option value="">{{ translate('Select Title') }}</option>
                  <option value="dispatch"       {{ old('user_title') === 'dispatch' ? 'selected' : '' }}>Dispatch</option>
                  <option value="head_manager"   {{ old('user_title') === 'head_manager' ? 'selected' : '' }}>Head Manager</option>
                  <option value="super_boss"     {{ old('user_title') === 'super_boss' ? 'selected' : '' }}>Super Boss</option>
                  <option value="manager"        {{ old('user_title') === 'manager' ? 'selected' : '' }}>Manager</option>
                  {{-- NEW: 41 Manager (store as "manager_41") --}}
                  <option value="manager_41"     {{ old('user_title') === 'manager_41' ? 'selected' : '' }}>41 Manager</option>
                </select>
                @error('user_title')
                  <div class="text-danger small">{{ $message }}</div>
                @enderror
              </div>
            </div>

            {{-- Warehouse --}}
            <div class="form-group row">
              <div class="col-md-3">
                <label class="col-from-label">
                  {{ translate('Warehouse') }} <span class="text-danger">*</span>
                </label>
              </div>
              <div class="col-md-9">
                <select
                  class="form-control aiz-selectpicker"
                  data-live-search="true"
                  data-placeholder="{{ translate('Select the Warehouse') }}"
                  name="warehouse_id"
                  id="warehouse_id"
                  required
                >
                  <option value="">{{ translate('Select the Warehouse') }}</option>
                  @foreach (\App\Models\Warehouse::get() as $key => $warehouse)
                    <option
                      value="{{ $warehouse->id }}"
                      {{ (string) old('warehouse_id') === (string) $warehouse->id ? 'selected' : '' }}
                    >
                      {{ $warehouse->name }}
                    </option>
                  @endforeach
                </select>
                @error('warehouse_id')
                  <div class="text-danger small">{{ $message }}</div>
                @enderror
              </div>
            </div>

            <div class="form-group mb-0 text-right">
              <button type="submit" class="btn btn-sm btn-primary">
                {{ translate('Save') }}
              </button>
            </div>
          </div>
        </form>

      </div>
    </div>
  </div>
@endsection

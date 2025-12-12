@extends('backend.layouts.app')

@section('content')
  <div class="row">
    <div class="col-lg-6 mx-auto">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0 h6">{{ translate('Staff Information') }}</h5>
        </div>

        <form action="{{ route('staffs.update', $staff->id) }}" method="POST">
          @method('PATCH')
          @csrf

          <div class="card-body">

            {{-- Show all errors (optional) --}}
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
              <label class="col-sm-3 col-from-label" for="name">{{ translate('Name') }}</label>
              <div class="col-sm-9">
                <input type="text" id="name" name="name" class="form-control"
                       placeholder="{{ translate('Name') }}"
                       value="{{ old('name', $staff->user->name) }}" required>
                @error('name') <div class="text-danger small">{{ $message }}</div> @enderror
              </div>
            </div>

            {{-- Email --}}
            <div class="form-group row">
              <label class="col-sm-3 col-from-label" for="email">{{ translate('Email') }}</label>
              <div class="col-sm-9">
                <input type="text" id="email" name="email" class="form-control"
                       placeholder="{{ translate('Email') }}"
                       value="{{ old('email', $staff->user->email) }}" required>
                @error('email') <div class="text-danger small">{{ $message }}</div> @enderror
              </div>
            </div>

            {{-- Phone (+91 prefix UI, user types only 10 digits) --}}
            @php
              $existing = preg_replace('/\D+/', '', (string) $staff->user->phone);
              if (strlen($existing) > 10) { $existing = substr($existing, -10); }
            @endphp
            <div class="form-group row">
              <label class="col-sm-3 col-from-label" for="mobile">{{ translate('Phone') }}</label>
              <div class="col-sm-9">
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text">+91</span>
                  </div>
                  <input type="text" id="mobile" name="mobile" class="form-control"
                         placeholder="{{ translate('Enter 10 digit number') }}"
                         value="{{ old('mobile', $existing) }}"
                         maxlength="10" pattern="\d{10}" required>
                </div>
                @error('mobile') <div class="text-danger small">{{ $message }}</div> @enderror
              </div>
            </div>

            {{-- Password (leave blank to keep same) --}}
            <div class="form-group row">
              <label class="col-sm-3 col-from-label" for="password">{{ translate('Password') }}</label>
              <div class="col-sm-9">
                <input type="password" id="password" name="password" class="form-control"
                       placeholder="{{ translate('Leave blank to keep unchanged') }}">
                @error('password') <div class="text-danger small">{{ $message }}</div> @enderror
              </div>
            </div>

            {{-- Role --}}
            <div class="form-group row">
              <label class="col-sm-3 col-from-label" for="role_id">{{ translate('Role') }}</label>
              <div class="col-sm-9">
                <select name="role_id" id="role_id" required class="form-control aiz-selectpicker">
                  @foreach ($roles as $role)
                    <option value="{{ $role->id }}"
                      {{ (string) old('role_id', $staff->role_id) === (string) $role->id ? 'selected' : '' }}>
                      {{ $role->name }}
                    </option>
                  @endforeach
                </select>
                @error('role_id') <div class="text-danger small">{{ $message }}</div> @enderror
              </div>
            </div>

            {{-- User Title --}}
            <div class="form-group row">
              <label class="col-sm-3 col-from-label" for="user_title">{{ translate('User Title') }}</label>
              <div class="col-sm-9">
                <select name="user_title" id="user_title" class="form-control aiz-selectpicker" required>
                  @php $title = old('user_title', $staff->user->user_title); @endphp
                  <option value="dispatch"     {{ $title === 'dispatch' ? 'selected' : '' }}>Dispatch</option>
                  <option value="head_manager" {{ $title === 'head_manager' ? 'selected' : '' }}>Head Manager</option>
                  <option value="super_boss"   {{ $title === 'super_boss' ? 'selected' : '' }}>Super Boss</option>
                  <option value="manager"      {{ $title === 'manager' ? 'selected' : '' }}>Manager</option>
                </select>
                @error('user_title') <div class="text-danger small">{{ $message }}</div> @enderror
              </div>
            </div>

            {{-- Warehouse --}}
            <div class="form-group row">
              <label class="col-sm-3 col-from-label" for="warehouse_id">{{ translate('Warehouse') }}</label>
              <div class="col-sm-9">
                <select name="warehouse_id" id="warehouse_id" required class="form-control aiz-selectpicker" data-live-search="true">
                  @foreach (\App\Models\Warehouse::get() as $warehouse)
                    <option value="{{ $warehouse->id }}"
                      {{ (string) old('warehouse_id', $staff->user->warehouse_id) === (string) $warehouse->id ? 'selected' : '' }}>
                      {{ $warehouse->name }}
                    </option>
                  @endforeach
                </select>
                @error('warehouse_id') <div class="text-danger small">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="form-group mb-0 text-right">
              <button type="submit" class="btn btn-sm btn-primary">{{ translate('Save') }}</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection

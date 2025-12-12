@extends('backend.layouts.app')

@section('content')
  <div class="row">
    <div class="col-lg-8 mx-auto">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0 h6">{{ translate('Category Group Information') }}</h5>
        </div>
        <div class="card-body">
          <form class="form-horizontal" action="{{ route('category-groups.store') }}" method="POST"
            enctype="multipart/form-data">
            @csrf
            <div class="form-group row">
              <label class="col-md-3 col-form-label">{{ translate('Name') }}</label>
              <div class="col-md-9">
                <input type="text" placeholder="{{ translate('Name') }}" id="name" name="name"
                  class="form-control" required>
              </div>
            </div>
            <div class="form-group row">
              <label class="col-md-3 col-form-label">
                {{ translate('Order') }}
              </label>
              <div class="col-md-9">
                <input type="number" name="order" class="form-control" id="order"
                  placeholder="{{ translate('Order') }}">
                <small>{{ translate('Lower number will be shown on top') }}</small>
              </div>
            </div>
            <div class="form-group row">
              <label class="col-md-3 col-form-label" for="banner">{{ translate('Banner') }}
                <small>({{ translate('200x200') }})</small></label>
              <div class="col-md-9">
                <div class="input-group" data-toggle="aizuploader" data-type="image">
                  <div class="input-group-prepend">
                    <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse') }}</div>
                  </div>
                  <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                  <input type="hidden" name="banner" class="selected-files">
                </div>
                <div class="file-preview box sm">
                </div>
              </div>
            </div>
            <div class="form-group row">
              <label class="col-md-3 col-form-label" for="icon">{{ translate('Icon') }}
                <small>({{ translate('32x32') }})</small></label>
              <div class="col-md-9">
                <div class="input-group" data-toggle="aizuploader" data-type="image">
                  <div class="input-group-prepend">
                    <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse') }}</div>
                  </div>
                  <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                  <input type="hidden" name="icon" class="selected-files">
                </div>
                <div class="file-preview box sm">
                </div>
              </div>
            </div>
            <div class="form-group row">
              <label class="col-md-3 col-form-label">{{ translate('Meta Title') }}</label>
              <div class="col-md-9">
                <input type="text" class="form-control" name="meta_title" placeholder="{{ translate('Meta Title') }}">
              </div>
            </div>
            <div class="form-group row">
              <label class="col-md-3 col-form-label">{{ translate('Meta Description') }}</label>
              <div class="col-md-9">
                <textarea name="meta_description" rows="5" class="form-control"></textarea>
              </div>
            </div>
            <div class="form-group mb-0 text-right">
              <button type="submit" class="btn btn-primary">{{ translate('Save') }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection

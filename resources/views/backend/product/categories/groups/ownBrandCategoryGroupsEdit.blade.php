@extends('backend.layouts.app')

@section('content')
  <div class="aiz-titlebar text-left mt-2 mb-3">
    <h5 class="mb-0 h6">{{ translate('Category Group Information') }}</h5>
  </div>

  <div class="row">
    <div class="col-lg-8 mx-auto">
      <div class="card">
        <div class="card-body p-0">
          <form class="p-4" action="{{ route('category-groups.ownBrandCategoryGroupsUpdate', $category_group->id) }}" method="POST" enctype="multipart/form-data">
            <input name="_method" type="hidden" value="PATCH">
            <input type="hidden" name="lang" value="{{ $lang }}">
            @csrf
            @method('POST')
            <div class="form-group row">
              <label class="col-md-3 col-form-label">{{ translate('Name') }} <i class="las la-language text-danger"
                  title="{{ translate('Translatable') }}"></i></label>
              <div class="col-md-9">
                <input type="text" name="name" value="{{ $category_group->name }}" class="form-control"
                  id="name" placeholder="{{ translate('Name') }}" required>
              </div>
            </div>
            <div class="form-group row">
              <label class="col-md-3 col-form-label" for="signinSrEmail">{{ translate('Banner') }}
                <small>({{ translate('200x200') }})</small></label>
              <div class="col-md-9">
                <div class="input-group" data-toggle="aizuploader" data-type="image">
                  <div class="input-group-prepend">
                    <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse') }}</div>
                  </div>
                  <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                  <input type="hidden" name="banner" class="selected-files" value="{{ $category_group->banner }}">
                </div>
                <div class="file-preview box sm">
                </div>
              </div>
            </div>
            <div class="form-group row">
              <label class="col-md-3 col-form-label" for="signinSrEmail">{{ translate('Icon') }}
                <small>({{ translate('32x32') }})</small></label>
              <div class="col-md-9">
                <div class="input-group" data-toggle="aizuploader" data-type="image">
                  <div class="input-group-prepend">
                    <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse') }}</div>
                  </div>
                  <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                  <input type="hidden" name="icon" class="selected-files" value="{{ $category_group->icon }}">
                </div>
                <div class="file-preview box sm">
                </div>
              </div>
            </div>
            <div class="form-group row">
              <label class="col-md-3 col-form-label">{{ translate('Meta Title') }}</label>
              <div class="col-md-9">
                <input type="text" class="form-control" name="meta_title" value="{{ $category_group->meta_title }}"
                  placeholder="{{ translate('Meta Title') }}">
              </div>
            </div>
            <div class="form-group row">
              <label class="col-md-3 col-form-label">{{ translate('Meta Description') }}</label>
              <div class="col-md-9">
                <textarea name="meta_description" rows="5" class="form-control">{{ $category_group->meta_description }}</textarea>
              </div>
            </div>
            <div class="form-group row">
              <label class="col-md-3 col-form-label">{{ translate('Slug') }}</label>
              <div class="col-md-9">
                <input type="text" placeholder="{{ translate('Slug') }}" id="slug" name="slug"
                  value="{{ $category_group->slug }}" class="form-control">
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

@extends('backend.layouts.app')

@section('content')
  @php
    CoreComponentRepository::instantiateShopRepository();
    CoreComponentRepository::initializeCache();
  @endphp

  <div class="row">
    <div class="col-12 mx-auto">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0 h6">{{ translate('Category Information') }}</h5>
        </div>
        <div class="card-body">
          <form class="form-horizontal" action="{{ route('categories.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="form-group row">
              <label class="col-md-3 col-form-label">{{ translate('Name') }}</label>
              <div class="col-md-9">
                <input type="text" placeholder="{{ translate('Name') }}" id="name" name="name"
                  class="form-control" required>
              </div>
            </div>
            <div class="form-group row">
              <label class="col-md-3 col-form-label">{{ translate('Category Group') }}</label>
              <div class="col-md-9">
                <select class="select2 form-control aiz-selectpicker" name="category_group_id" data-toggle="select2"
                  data-placeholder="Choose ..."data-live-search="true">
                  <option value="0">{{ translate('No Group') }}</option>
                  @foreach ($category_groups as $groups)
                    <option value="{{ $groups->id }}">{{ $groups->name }}</option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="form-group row">
              <label class="col-md-3 col-form-label">{{ translate('Parent Category') }}</label>
              <div class="col-md-9">
                <select class="select2 form-control aiz-selectpicker" name="parent_id" data-toggle="select2"
                  data-placeholder="Choose ..." data-live-search="true">
                  <option value="0">{{ translate('No Parent') }}</option>
                  @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->getTranslation('name') }}</option>
                    @foreach ($category->childrenCategories as $childCategory)
                      @include('categories.child_category', ['child_category' => $childCategory])
                    @endforeach
                  @endforeach
                </select>
              </div>
            </div>
            <div class="form-group row">
              <label class="col-md-3 col-form-label">
                {{ translate('Ordering Number') }}
              </label>
              <div class="col-md-9">
                <input type="number" name="order_level" class="form-control" id="order_level"
                  placeholder="{{ translate('Order Level') }}">
                <small>{{ translate('Higher number has high priority') }}</small>
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
                  <input type="hidden" name="banner" class="selected-files">
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
            <div class="form-group row">
              <label class="col-md-3 col-form-label">{{ translate('Meta Keywords') }}</label>
              <div class="col-md-9">
                <textarea name="meta_keywords" rows="5" class="form-control"></textarea>
              </div>
            </div>
            <div class="form-group row">
              <label class="col-md-3 col-from-label" for="name">{{ translate('Category SEO Content') }}</label>
              <div class="col-md-9">
                <textarea class="aiz-text-editor form-control" placeholder="{{ translate('Content..') }}"
                  data-buttons='[["font", ["bold", "underline", "italic", "clear"]],["para", ["ul", "ol", "paragraph"]],["style", ["style"]],["color", ["color"]],["table", ["table"]],["insert", ["link", "picture", "video"]],["view", ["fullscreen", "codeview", "undo", "redo"]]]'
                  data-min-height="300" name="page_description"></textarea>
              </div>
            </div>
            <div class="form-group row">
              <label class="col-md-3 col-form-label">{{ translate('Markup') }}</label>
              <div class="col-md-9 input-group">
                <input type="number" lang="en" min="0" step="0.01"
                  placeholder="{{ translate('Markup') }}" id="markup" name="markup" class="form-control">
                <div class="input-group-append">
                  <span class="input-group-text">%</span>
                </div>
              </div>
            </div>
            <div class="form-group row">
              <label class="col-md-3 col-form-label">{{ translate('Filtering Attributes') }}</label>
              <div class="col-md-9">
                <select class="select2 form-control aiz-selectpicker" name="filtering_attributes[]"
                  data-toggle="select2" data-placeholder="Choose ..."data-live-search="true" multiple>
                  @foreach (\App\Models\Attribute::all() as $attribute)
                    <option value="{{ $attribute->id }}">{{ $attribute->getTranslation('name') }}</option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="form-group row">
              <label class="col-md-3 col-form-label">{{ translate('Linked Categories') }}</label>
              <div class="col-md-9">
                <select class="select2 form-control aiz-selectpicker" name="linked_categories[]" data-toggle="select2"
                  data-placeholder="Choose ..."data-live-search="true" multiple>
                  @foreach ($categories as $acategory)
                    <option value="{{ $acategory->id }}">{{ $acategory->getTranslation('name') }}</option>
                    @foreach ($acategory->childrenCategories as $childCategory)
                      @include('categories.child_category', ['child_category' => $childCategory])
                    @endforeach
                  @endforeach
                </select>
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

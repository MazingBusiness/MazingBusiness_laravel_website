@extends('backend.layouts.app')

@section('content')
  @php
    CoreComponentRepository::instantiateShopRepository();
    CoreComponentRepository::initializeCache();
  @endphp

  <div class="aiz-titlebar text-left mt-2 mb-3">
    <h5 class="mb-0 h6">{{ translate('Category Information') }}</h5>
  </div>

  <div class="row">
    <div class="col mx-auto">
      <div class="card">
        <div class="card-body p-0">
          <ul class="nav nav-tabs nav-fill border-light">
            @foreach (\App\Models\Language::all() as $key => $language)
              <li class="nav-item">
                <a class="nav-link text-reset @if ($language->code == $lang) active @else bg-soft-dark border-light border-left-0 @endif py-3"
                  href="{{ route('categories.edit', ['id' => $category->id, 'lang' => $language->code]) }}">
                  <img src="{{ static_asset('assets/img/flags/' . $language->code . '.png') }}" height="11"
                    class="mr-1">
                  <span>{{ $language->name }}</span>
                </a>
              </li>
            @endforeach
          </ul>
          <form class="p-4" action="{{ route('category.ownBrandCategoryUpdate', $category->id) }}" method="POST"
            enctype="multipart/form-data">
            <input name="_method" type="hidden" value="PATCH">
            <input type="hidden" name="lang" value="{{ $lang }}">
            @csrf
            @method('POST')
            <div class="form-group row">
              <label class="col-md-3 col-form-label">{{ translate('Name') }} <i class="las la-language text-danger"
                  title="{{ translate('Translatable') }}"></i></label>
              <div class="col-md-9">
                <input type="text" name="name" value="{{ $category->name }}"
                  class="form-control" id="name" placeholder="{{ translate('Name') }}" required>
              </div>
            </div>
            <div class="form-group row">
              <label class="col-md-3 col-form-label">{{ translate('Category Group') }}</label>
              <div class="col-md-9">
                <select class="select2 form-control aiz-selectpicker" name="category_group_id" data-toggle="select2"
                  data-placeholder="Choose ..."data-live-search="true"
                  data-selected="{{ $category->category_group_id }}">
                  <option value="0">{{ translate('No Group') }}</option>
                  @foreach ($category_groups as $groups)
                    <option value="{{ $groups->id }}">{{ $groups->name }}</option>
                  @endforeach
                </select>
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
                  <input type="hidden" name="banner" class="selected-files" value="{{ $category->banner }}">
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
                  <input type="hidden" name="icon" class="selected-files" value="{{ $category->icon }}">
                </div>
                <div class="file-preview box sm">
                </div>
              </div>
            </div>
            <div class="form-group row">
              <label class="col-md-3 col-form-label">{{ translate('Meta Title') }}</label>
              <div class="col-md-9">
                <input type="text" class="form-control" name="meta_title" value="{{ $category->meta_title }}"
                  placeholder="{{ translate('Meta Title') }}">
              </div>
            </div>
            <div class="form-group row">
              <label class="col-md-3 col-form-label">{{ translate('Meta Description') }}</label>
              <div class="col-md-9">
                <textarea name="meta_description" rows="5" class="form-control">{{ $category->meta_description }}</textarea>
              </div>
            </div>
            <div class="form-group row">
              <label class="col-md-3 col-form-label">{{ translate('Meta Keywords') }}</label>
              <div class="col-md-9">
                <textarea name="meta_keywords" rows="5" class="form-control">{{ $category->meta_keywords }}</textarea>
              </div>
            </div>
            <div class="form-group row">
              <label class="col-md-3 col-from-label" for="name">{{ translate('Category SEO Content') }}</label>
              <div class="col-md-9">
                <textarea class="aiz-text-editor form-control" placeholder="{{ translate('Content..') }}"
                  data-buttons='[["font", ["bold", "underline", "italic", "clear"]],["para", ["ul", "ol", "paragraph"]],["style", ["style"]],["color", ["color"]],["table", ["table"]],["insert", ["link", "picture", "video"]],["view", ["fullscreen", "codeview", "undo", "redo"]]]'
                  data-min-height="300" name="page_description">{!! $category->page_description !!}</textarea>
              </div>
            </div>
            <div class="form-group row">
              <label class="col-md-3 col-form-label">{{ translate('Slug') }}</label>
              <div class="col-md-9">
                <input type="text" placeholder="{{ translate('Slug') }}" id="slug" name="slug"
                  value="{{ $category->slug }}" class="form-control">
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

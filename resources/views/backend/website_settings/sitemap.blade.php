@extends('backend.layouts.app')

@section('content')
  <div class="aiz-titlebar text-left mt-2 mb-3">
    <div class="row align-items-center">
      <div class="col">
        <h1 class="h3">{{ translate('Sitemap') }}</h1>
      </div>
    </div>
  </div>
  <div class="row">
    <div class="col-md-12 mx-auto">
      <div class="card">
        <div class="card-header">
          <h6 class="mb-0">{{ translate('Sitemap.xml') }}</h6>
        </div>
        <div class="card-body">
          <form action="{{ route('website.update-sitemap') }}" method="POST">
            @csrf
            <div class="form-group row">
              <label class="col-sm-2 col-from-label" for="name">{{ translate('Add Content') }} <span
                  class="text-danger">*</span></label>
              <div class="col-sm-10">
                <textarea class="form-control" placeholder="{{ translate('Content..') }}" rows="30" name="content" required>{!! $content !!}</textarea>
              </div>
            </div>
            <div class="text-right">
              <button type="submit" class="btn btn-primary">{{ translate('Update') }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection

@extends('backend.layouts.app')

@section('content')
  <div class="aiz-titlebar text-left mt-2 mb-3">
    <div class="align-items-center">
      <h1 class="h3">{{ translate('All Brands') }}</h1>
    </div>
  </div>

  <div class="row">
    <div class="@if (auth()->user()->can('add_brand')) col-lg-7 @else col-lg-12 @endif">
      <div class="card">
        <div class="card-header row gutters-5">
          <div class="col text-center text-md-left">
            <h5 class="mb-md-0 h6">{{ translate('Brands') }}</h5>
          </div>
          <div class="col-md-4">
            <form class="" id="sort_brands" action="" method="GET">
              <div class="input-group input-group-sm">
                <input type="text" class="form-control" id="search"
                  name="search"@isset($sort_search) value="{{ $sort_search }}" @endisset
                  placeholder="{{ translate('Type name & Enter') }}">
              </div>
            </form>
          </div>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table aiz-table mb-0">
              <thead>
                <tr>
                  <th>#</th>
                  <th>{{ translate('Name') }}</th>
                  <th>{{ translate('Logo') }}</th>
                  <th>{{ translate('Markup') }}</th>
                  <th class="text-right" style="min-width: 120px;">{{ translate('Options') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($brands as $key => $brand)
                @php
                  $file_path = $brand->logo_image 
                  ? url('public/' . $brand->logo_image) 
                  : url('public/assets/img/placeholder.jpg');
                @endphp
                  <tr>
                    <td>{{ $key + 1 + ($brands->currentPage() - 1) * $brands->perPage() }}</td>
                    <td>{{ $brand->getTranslation('name') }}</td>
                    <td>
                      <img src="{{ $file_path }}" alt="{{ translate('Brand') }}" class="h-50px">
                    </td>
                    <td>{{ $brand->markup }} %</td>
                    <td class="text-right" style="min-width: 120px;">
                      @can('edit_brand')
                        <a class="btn btn-soft-primary btn-icon btn-circle btn-sm"
                          href="{{ route('brands.edit', ['id' => $brand->id, 'lang' => env('DEFAULT_LANGUAGE')]) }}"
                          title="{{ translate('Edit') }}">
                          <i class="las la-edit"></i>
                        </a>
                      @endcan
                      @can('delete_brand')
                        <a href="#" class="btn btn-soft-danger btn-icon btn-circle btn-sm confirm-delete"
                          data-href="{{ route('brands.destroy', $brand->id) }}" title="{{ translate('Delete') }}">
                          <i class="las la-trash"></i>
                        </a>
                      @endcan
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
            <div class="aiz-pagination">
              {{ $brands->appends(request()->input())->links() }}
            </div>
          </div>
        </div>
      </div>
    </div>
    @can('add_brand')
      <div class="col-md-5">
        <div class="card">
          <div class="card-header">
            <h5 class="mb-0 h6">{{ translate('Add New Brand') }}</h5>
          </div>
          <div class="card-body">
            <form action="{{ route('brands.store') }}" method="POST">
              @csrf
              <div class="form-group mb-3">
                <label for="name">{{ translate('Name') }}</label>
                <input type="text" placeholder="{{ translate('Name') }}" name="name" class="form-control" required>
              </div>
              <div class="form-group mb-3">
                <label for="name">{{ translate('Markup') }}</label>
                <div class="input-group">
                  <input type="text" placeholder="{{ translate('Markup') }}" name="markup" class="form-control">
                  <div class="input-group-append">
                    <span class="input-group-text">%</span>
                  </div>
                </div>
              </div>
              <div class="form-group mb-3">
                <label for="name">{{ translate('Logo') }} <small>({{ translate('120x80') }})</small></label>
                <div class="input-group" data-toggle="aizuploader" data-type="image">
                  <div class="input-group-prepend">
                    <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse') }}</div>
                  </div>
                  <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                  <input type="hidden" name="logo" class="selected-files">
                </div>
                <div class="file-preview box sm"></div>
              </div>
              <div class="form-group mb-3">
                <label for="name">{{ translate('Meta Title') }}</label>
                <input type="text" class="form-control" name="meta_title" placeholder="{{ translate('Meta Title') }}">
              </div>
              <div class="form-group mb-3">
                <label for="name">{{ translate('Meta Description') }}</label>
                <textarea name="meta_description" rows="5" class="form-control"></textarea>
              </div>
              <div class="form-group mb-3">
                <label for="name">{{ translate('Meta Keywords') }}</label>
                <textarea name="meta_keywords" rows="5" class="form-control"></textarea>
              </div>
              <div class="form-group row">
                <label class="col-md-3 col-from-label" for="name">{{ translate('Brand SEO Content') }}</label>
                <div class="col-md-9">
                  <textarea class="aiz-text-editor form-control" placeholder="{{ translate('Content..') }}"
                    data-buttons='[["font", ["bold", "underline", "italic", "clear"]],["para", ["ul", "ol", "paragraph"]],["style", ["style"]],["color", ["color"]],["table", ["table"]],["insert", ["link", "picture", "video"]],["view", ["fullscreen", "codeview", "undo", "redo"]]]'
                    data-min-height="300" name="page_description"></textarea>
                </div>
              </div>
              <div class="form-group mb-3 text-right">
                <button type="submit" class="btn btn-primary">{{ translate('Save') }}</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    @endcan
  </div>
@endsection

@section('modal')
  @include('modals.delete_modal')
@endsection

@section('script')
  <script type="text/javascript">
    function sort_brands(el) {
      $('#sort_brands').submit();
    }
  </script>
@endsection

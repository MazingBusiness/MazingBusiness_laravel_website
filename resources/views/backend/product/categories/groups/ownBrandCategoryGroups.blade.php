@extends('backend.layouts.app')

@section('content')
  <div class="aiz-titlebar text-left mt-2 mb-3">
    <div class="row align-items-center">
      <div class="col-md-6">
        <h1 class="h3">{{ translate('Category Groups') }}</h1>
      </div>
      <div class="col-md-6 text-md-right">
        @if (auth()->user()->can('add_product_category_groups'))
          <a href="{{ route('category-groups.ownBrandCategoryGroupsCreate') }}" class="btn btn-info">
            <span>{{ translate('Add New Category Group') }}</span>
          </a>
        @endif
        <a href="{{ route('categories.ownBrandCategories') }}" class="btn btn-primary">
          <span>{{ translate('View Categories') }}</span>
        </a>
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-header d-block d-md-flex">
      <h5 class="mb-0 h6">{{ translate('Category Groups') }}</h5>
      <form class="" id="sort_categories" action="" method="GET">
        <div class="box-inline pad-rgt pull-left">
          <div class="" style="min-width: 200px;">
            <input type="text" class="form-control" id="search"
              name="search"@isset($sort_search) value="{{ $sort_search }}" @endisset
              placeholder="{{ translate('Type name & Enter') }}">
          </div>
        </div>
      </form>
    </div>
    <div class="card-body">
      <table class="table aiz-table mb-0">
        <thead>
          <tr>
            <th data-breakpoints="lg">#</th>
            <th>{{ translate('Name') }}</th>
            <th data-breakpoints="lg">{{ translate('Banner') }}</th>
            <th data-breakpoints="lg">{{ translate('Icon') }}</th>
            <th width="10%" class="text-right">{{ translate('Options') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($category_groups as $key => $category)
            <tr>
              <td>{{ $key + 1 + ($category_groups->currentPage() - 1) * $category_groups->perPage() }}</td>
              <td>{{ $category->name }}</td>
              <td>
                @if ($category->banner != null)
                  <img src="{{ uploaded_asset($category->banner) }}" alt="{{ translate('Banner') }}" class="h-50px">
                @else
                  —
                @endif
              </td>
              <td>
                @if ($category->icon != null)
                  <span class="avatar avatar-square avatar-xs">
                    <img src="{{ uploaded_asset($category->icon) }}" alt="{{ translate('icon') }}">
                  </span>
                @else
                  —
                @endif
              </td>
              <td class="text-right">
                @can('edit_product_category_groups')
                  <a class="btn btn-soft-primary btn-icon btn-circle btn-sm"
                    href="{{ route('category-groups.ownBrandCategoryGroupsEdit', ['id' => $category->id, 'lang' => env('DEFAULT_LANGUAGE')]) }}"
                    title="{{ translate('Edit') }}">
                    <i class="las la-edit"></i>
                  </a>
                @endcan
                @can('delete_product_category_groups')
                  <a href="#" class="btn btn-soft-danger btn-icon btn-circle btn-sm confirm-delete"
                    data-href="{{ route('category-groups.ownBrandCategoryGroupDelete', $category->id) }}" title="{{ translate('Delete') }}">
                    <i class="las la-trash"></i>
                  </a>
                @endcan
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
      <div class="aiz-pagination">
        {{ $category_groups->appends(request()->input())->links() }}
      </div>
    </div>
  </div>
@endsection


@section('modal')
  @include('modals.delete_modal')
@endsection

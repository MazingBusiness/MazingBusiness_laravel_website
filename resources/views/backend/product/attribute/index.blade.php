@extends('backend.layouts.app')

@section('content')
  <div class="aiz-titlebar text-left mt-2 mb-3">
    <div class="align-items-center">
      <h1 class="h3">{{ translate('All Attributes') }}</h1>
    </div>
  </div>

  <div class="row">
    <div class="@if (auth()->user()->can('add_product_attribute')) col-lg-7 @else col-lg-12 @endif">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0 h6">{{ translate('Attributes') }}</h5>
        </div>
        <div class="card-body">
          <table class="table aiz-table mb-0">
            <thead>
              <tr>
                <th>#</th>
                <th>{{ translate('Name') }}</th>
                <th>{{ translate('Type') }}</th>
                <th>{{ translate('Values') }}</th>
                <th class="text-right">{{ translate('Options') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($attributes as $key => $attribute)
                <tr>
                  <td>{{ $key + 1 }}</td>
                  <td>{{ $attribute->getTranslation('name') }}</td>
                  <td>{{ ucfirst($attribute->type) }}</td>
                  <td>
                    @foreach ($attribute->attribute_values as $key => $value)
                      <span class="badge badge-inline badge-md bg-soft-dark">{{ $value->value }}</span>
                    @endforeach
                  </td>
                  <td class="text-right">
                    @can('view_product_attribute_values')
                      <a class="btn btn-soft-info btn-icon btn-circle btn-sm"
                        href="{{ route('attributes.show', $attribute->id) }}" title="{{ translate('Attribute values') }}">
                        <i class="las la-cog"></i>
                      </a>
                    @endcan
                    @can('edit_product_attribute')
                      <a class="btn btn-soft-primary btn-icon btn-circle btn-sm"
                        href="{{ route('attributes.edit', ['id' => $attribute->id, 'lang' => env('DEFAULT_LANGUAGE')]) }}"
                        title="{{ translate('Edit') }}">
                        <i class="las la-edit"></i>
                      </a>
                    @endcan
                    @can('delete_product_attribute')
                      <a href="#" class="btn btn-soft-danger btn-icon btn-circle btn-sm confirm-delete"
                        data-href="{{ route('attributes.destroy', $attribute->id) }}" title="{{ translate('Delete') }}">
                        <i class="las la-trash"></i>
                      </a>
                    @endcan
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
           <!-- Pagination Links -->
      <div class="mt-3">
        {{ $attributes->links() }}
      </div>
        </div>
      </div>
    </div>


    @can('add_product_attribute')
      <div class="col-md-5">
        <div class="card">
          <div class="card-header">
            <h5 class="mb-0 h6">{{ translate('Add New Attribute') }}</h5>
          </div>
          <div class="card-body">
            <form action="{{ route('attributes.store') }}" method="POST">
              @csrf
              <div class="form-group mb-3">
                <label for="name">{{ translate('Name') }}</label>
                <input type="text" placeholder="{{ translate('Name') }}" id="name" name="name"
                  class="form-control" required>
              </div>
              <div class="form-group mb-3">
                <label for="type">{{ translate('Type of Attribute') }}</label>
                <select class="select2 form-control aiz-selectpicker" name="type" data-toggle="select2"
                  data-placeholder="Choose ...">
                  <option value="filter">Filter Only</option>
                  <option value="variant">Creates Variant</option>
                  <option value="data">Data Only</option>
                </select>
              </div>
              <div class="form-group mb-3 text-right">
                <button type="submit" class="btn btn-primary">{{ translate('Save') }}</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    @endcan

    <!-- Import Section -->
<div class="col-md-12 mt-4">
    <div class="card shadow-lg border-0 rounded">
        <div class="card-header bg-gradient-primary text-white d-flex justify-content-between align-items-center rounded-top">
            <h5 style="color
            :black;" class="mb-0"><i class="fas fa-file-import mr-2"></i>{{ translate('Import Attributes') }}</h5>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show rounded-0 mb-0" role="alert">
                <strong><i class="fas fa-check-circle"></i> {{ session('success') }}</strong>
                <button type="button" class="close text-success" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show rounded-0 mb-0" role="alert">
                <strong><i class="fas fa-exclamation-triangle"></i> {{ translate('There was an error with your upload:') }}</strong>
                <ul class="mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="close text-danger" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        <div class="card-body bg-light">
            <p  class="text-muted mb-4 text-center">
                <i class="fas fa-info-circle mr-1"></i>{{ translate('Please upload a valid file to import attributes.') }}
            </p>
            <form action="{{ route('attributes.import') }}" method="POST" enctype="multipart/form-data" class="p-3 border rounded bg-white shadow-sm">
                @csrf
                <div class="form-group mb-4">
                    <label for="file" class="font-weight-bold text-primary">
                        <i class="fas fa-upload mr-2"></i>{{ translate('Choose File') }}
                    </label>
                    <div class="custom-file">
                        <input type="file" name="file" id="file" class="custom-file-input" accept=".xls,.xlsx,.csv" required>
                        <label class="custom-file-label text-muted" for="file">{{ translate('Select your file...') }}</label>
                    </div>
                </div>
                <div class="form-group d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-cloud-upload-alt"></i> {{ translate('Upload and Import') }}
                    </button>
                </div>
            </form>
        </div>

        <div class="card-footer text-center bg-gradient-light border-top">
            <small class="text-muted">
                <i class="fas fa-question-circle mr-1"></i>{{ translate('Supported formats: .xls, .xlsx, .csv') }}
            </small>
        </div>
    </div>
</div>
<!-- End Import Section -->

  </div>
@endsection

@section('modal')
  @include('modals.delete_modal')
@endsection

@section('script')

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const fileInput = document.getElementById('file');
        const fileLabel = document.querySelector('.custom-file-label');

        fileInput.addEventListener('change', function (event) {
            const fileName = event.target.files[0]?.name || "{{ translate('Select your file...') }}";
            fileLabel.textContent = fileName;
        });
    });
</script>
@endsection


@extends('backend.layouts.app')

@section('content')
<div class="aiz-titlebar text-left mt-2 mb-3">
    <h1 class="h3">Import Purchase Orders from Excel</h1>
</div>

<div class="card">
    <div class="card-body">
        <!-- Display success or error messages -->
        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif

        <!-- Excel Import Form -->
        <form action="{{ route('import.excel') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label for="excel_file">Select Excel File:</label>
                <input type="file" class="form-control" id="excel_file" name="excel_file" >
                @error('excel_file')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <button type="submit" class="btn btn-primary">  <i class="las la-file-import"></i> Import</button>
        </form>
    </div>
</div>
@endsection

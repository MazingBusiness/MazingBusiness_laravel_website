@extends('backend.layouts.app')

@section('content')
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Create CI / PL Template</h5>
    </div>

    <div class="card-body">
        <form action="{{ route('import_templates.store') }}" method="POST">
            @csrf

            {{-- Template Name --}}
            <div class="form-group mb-3">
                <label class="form-label">Template Name <span class="text-danger">*</span></label>
                <input type="text"
                       name="name"
                       class="form-control"
                       value="{{ old('name') }}"
                       required>
            </div>

            {{-- CI View --}}
            <div class="form-group mb-3">
                <label class="form-label">Commercial Invoice View (CI)</label>
                <input type="text"
                       name="ci_view"
                       class="form-control"
                       value="{{ old('ci_view', 'backend.imports.ci.templates.commercial_invoice_pdf') }}"
                       placeholder="e.g. backend.imports.ci.templates.commercial_invoice_pdf">
                <small class="text-muted">
                    Yahan full Blade view name dalna hai jo CI ke liye use hoga.
                </small>
            </div>

            {{-- PL View --}}
            <div class="form-group mb-3">
                <label class="form-label">Packing List View (PL)</label>
                <input type="text"
                       name="pl_view"
                       class="form-control"
                       value="{{ old('pl_view', 'backend.imports.ci.templates.packing_list_pdf') }}"
                       placeholder="e.g. backend.imports.ci.templates.packing_list_pdf">
                <small class="text-muted">
                    Yahan full Blade view name dalna hai jo PL ke liye use hoga.
                </small>
            </div>

            {{-- Active / Inactive --}}
            <div class="form-group mb-3">
                <label class="form-label d-block">Status</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input"
                           type="radio"
                           name="is_active"
                           id="active_yes"
                           value="1"
                           {{ old('is_active', 1) == 1 ? 'checked' : '' }}>
                    <label class="form-check-label" for="active_yes">Active</label>
                </div>

                <div class="form-check form-check-inline">
                    <input class="form-check-input"
                           type="radio"
                           name="is_active"
                           id="active_no"
                           value="0"
                           {{ old('is_active', 1) == 0 ? 'checked' : '' }}>
                    <label class="form-check-label" for="active_no">Inactive</label>
                </div>
            </div>

            <div class="text-end">
                <button type="submit" class="btn btn-primary">
                    Save Template
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

{{-- resources/views/backend/order_logistics/manager41/add.blade.php --}}
@extends('backend.layouts.app')

@section('content')
{{-- =========================
 |  Facebook-style Look & Feel
 |========================= --}}
<style>
  :root{
    --fb-bg: #F0F2F5;
    --fb-card: #FFFFFF;
    --fb-text: #1c1e21;
    --fb-muted:#65676B;
    --fb-blue:#1877F2;
    --fb-blue-pressed:#166FE5;
    --fb-border:#E4E6EB;
    --fb-input-bg:#F5F6F7;
    --fb-input-border:#CCD0D5;
  }

  body, .page-content { background: var(--fb-bg) !important; }

  /* Titlebar */
  .fb-titlebar {
    background: var(--fb-card);
    border: 1px solid var(--fb-border);
    border-radius: 10px;
    padding: 14px 16px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
  }
  .fb-title {
    color: var(--fb-text);
    font-weight: 700;
    margin: 0;
  }
  .fb-submeta{ color: var(--fb-muted); font-size: .92rem; }

  /* Meta chips (wrap safely, no sidebar overflow) */
  .fb-meta{ display:flex; flex-wrap:wrap; gap:8px; margin-top:8px;}
  .fb-chip{
    display:inline-flex; align-items:center; gap:6px;
    background:#fff; border:1px solid var(--fb-border);
    border-radius: 999px; padding:6px 10px; max-width:100%;
  }
  .fb-chip i{ font-size: 1rem; color: var(--fb-muted); }
  .fb-chip .label{ color: var(--fb-muted); }
  .fb-chip .value{ color: var(--fb-text); overflow-wrap:anywhere; }

  /* Card */
  .fb-card{
    background: var(--fb-card);
    border:1px solid var(--fb-border);
    border-radius: 12px;
    box-shadow: 0 2px 6px rgba(0,0,0,.04);
  }

  /* Section headers */
  .fb-section{
    font-weight: 700; color: var(--fb-text);
    border-bottom:1px solid var(--fb-border);
    padding-bottom: .35rem; margin-bottom: 1rem;
    display:flex; align-items:center; gap:8px;
  }
  .fb-hint{ color: var(--fb-muted); font-size: .9rem; }

  /* Inputs */
  .form-control{
    background: var(--fb-input-bg);
    border:1px solid var(--fb-input-border);
    border-radius: 8px;
  }
  .form-control:focus{
    background: #fff;
    border-color: var(--fb-blue);
    box-shadow: 0 0 0 3px rgba(24, 119, 242, .15);
  }
  label.fw-600{ font-weight: 600; color: var(--fb-text); }

  /* Buttons */
  .btn-fb {
    background: var(--fb-blue); border-color: var(--fb-blue);
    color:#fff; border-radius: 8px; font-weight: 600;
  }
  .btn-fb:hover{ background: var(--fb-blue-pressed); border-color: var(--fb-blue-pressed); }
  .btn-light-fb{
    background:#fff; color: var(--fb-text);
    border:1px solid var(--fb-border); border-radius:8px; font-weight:600;
  }

  /* Drop area look */
  .fb-drop{
    border:1.5px dashed var(--fb-border);
    border-radius: 10px;
    padding: 14px;
    background: #fff;
  }

  /* File preview grid (client-side, small & neat) */
  .preview-grid{ display:grid; grid-template-columns: repeat(auto-fill, minmax(120px,1fr)); gap:10px; }
  .preview-card{
    border:1px solid var(--fb-border); border-radius:10px; background:#fff;
    padding:8px; display:flex; flex-direction:column; gap:6px;
  }
  .preview-thumb{
    width:100%; height:100px; object-fit:contain; background:#fff; border-radius:8px;
    border:1px solid var(--fb-border);
  }
  .preview-name{
    font-size:.8rem; color:var(--fb-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  }

  /* Alerts soft shadow */
  .alert{ box-shadow: 0 2px 6px rgba(0,0,0,.05); }
</style>

{{-- =========================
 |  Titlebar
 |========================= --}}
<div class="aiz-titlebar text-left mt-3 mb-4 fb-titlebar">
  <h1 class="h3 fb-title">Add Order Logistic <span class="text-muted">(Manager-41)</span></h1>
  <div class="fb-submeta">Fill LR details, transporter and upload LR/Invoice attachments.</div>

  <div class="fb-meta">
    <div class="fb-chip">
      <i class="las la-file-invoice"></i>
      <span class="label">Challan:</span>
      <span class="value">{{ $challanNo ?? ($challan->challan_no ?? '-') }}</span>
    </div>
    <div class="fb-chip">
      <i class="las la-user-tie"></i>
      <span class="label">Customer:</span>
      <span class="value">{{ $customerName ?? ($challan->user->company_name ?? $challan->user->name ?? 'Customer') }}</span>
    </div>
    <div class="fb-chip">
      <i class="las la-warehouse"></i>
      <span class="label">Warehouse:</span>
      <span class="value">{{ $warehouseName ?? ($challan->order_warehouse->name ?? '-') }}</span>
    </div>
    @if(!empty($invoiceNo))
      <div class="fb-chip">
        <i class="las la-hashtag"></i>
        <span class="label">Invoice:</span>
        <span class="value">{{ $invoiceNo }}</span>
      </div>
    @endif
  </div>
</div>

{{-- =========================
 |  Flash & Validation
 |========================= --}}
<div class="container px-0">
  @if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="las la-check-circle mr-2"></i>{!! session('success') !!}
      <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
  @endif

  @if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <i class="las la-exclamation-triangle mr-2"></i>{!! session('error') !!}
      <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
  @endif

  @if ($errors->any())
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
      <strong class="d-block mb-1"><i class="las la-info-circle mr-1"></i>Please fix the following:</strong>
      <ul class="mb-0 mt-1">
        @foreach ($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
      <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
  @endif
</div>

{{-- =========================
 |  Main Card
 |========================= --}}
<div class="card fb-card">
  <div class="card-body">
    <form method="POST"
          action="{{ route('manager41.order.logistics.store', $encryptedChallanId) }}"
          enctype="multipart/form-data">
      @csrf

      {{-- ===== Basic Information ===== --}}
      <div class="fb-section"><i class="las la-file-alt"></i> Basic Information</div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label for="invoice_no" class="fw-600">Invoice No (optional)</label>
          <input type="text"
                 id="invoice_no"
                 name="invoice_no"
                 class="form-control @error('invoice_no') is-invalid @enderror"
                 placeholder="e.g., DEL41/000045/25-26"
                 value="{{ old('invoice_no', $invoiceNo ?? '') }}">
          @error('invoice_no') <div class="invalid-feedback">{{ $message }}</div> @enderror
          <div class="fb-hint mt-1"><i class="las la-info-circle mr-1"></i>Add if invoice already created; else keep blank.</div>
        </div>

        <div class="col-md-6 mb-3">
          <label for="transport_name" class="fw-600">Transporter Name <span class="text-danger">*</span></label>
          <input type="text"
                 id="transport_name"
                 name="transport_name"
                 class="form-control @error('transport_name') is-invalid @enderror"
                 placeholder="e.g., VRL Logistics"
                 value="{{ old('transport_name') }}"
                 required>
          @error('transport_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
      </div>

      {{-- ===== LR Details ===== --}}
      <div class="fb-section"><i class="las la-truck"></i> LR Details</div>
      <div class="row">
        <div class="col-md-3 mb-3">
          <label for="lr_date" class="fw-600">LR Date <span class="text-danger">*</span></label>
          <input type="date"
                 id="lr_date"
                 name="lr_date"
                 class="form-control @error('lr_date') is-invalid @enderror"
                 value="{{ old('lr_date', now()->toDateString()) }}"
                 required>
          @error('lr_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-3 mb-3">
          <label for="lr_no" class="fw-600">LR Number <span class="text-danger">*</span></label>
          <input type="text"
                 id="lr_no"
                 name="lr_no"
                 class="form-control @error('lr_no') is-invalid @enderror"
                 placeholder="e.g., VRL/DEL/12345"
                 value="{{ old('lr_no') }}"
                 required>
          @error('lr_no') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-3 mb-3">
          <label for="no_of_boxes" class="fw-600">No. of Boxes <span class="text-danger">*</span></label>
          <input type="number"
                 id="no_of_boxes"
                 name="no_of_boxes"
                 class="form-control @error('no_of_boxes') is-invalid @enderror"
                 placeholder="e.g., 6"
                 value="{{ old('no_of_boxes') }}"
                 min="0"
                 required>
          @error('no_of_boxes') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-3 mb-3">
          <label for="lr_amount" class="fw-600">LR Amount <span class="text-danger">*</span></label>
          <input type="number"
                 step="0.01"
                 id="lr_amount"
                 name="lr_amount"
                 class="form-control @error('lr_amount') is-invalid @enderror"
                 placeholder="e.g., 850.00"
                 value="{{ old('lr_amount') }}"
                 required>
          @error('lr_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
      </div>

      {{-- ===== Attachments ===== --}}
      <div class="fb-section"><i class="las la-paperclip"></i> Attachments</div>

      <div class="mb-3">
        <label for="attachments" class="fw-600">LR / Transport Docs (Images/PDF) — Multiple</label>
        <div class="fb-drop">
          <input type="file"
                id="attachments"
                name="attachments[]"
                class="form-control @error('attachments.*') is-invalid @enderror"
                accept=".jpeg,.jpg,.png,.pdf"
                multiple>
          @error('attachments.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
          <div class="fb-hint mt-2">You can upload multiple files. They’ll be listed with this entry.</div>
        </div>

        {{-- client-side preview --}}
        <div id="attachments_preview" class="preview-grid mt-2" style="display:none;"></div>
      </div>

      <div class="mb-2">
        <label for="invoice_copy_upload" class="fw-600">Invoice Attachment (Image/PDF) — Single</label>
        <div class="fb-drop">
          <input type="file"
                id="invoice_copy_upload"
                name="invoice_copy_upload"
                class="form-control @error('invoice_copy_upload') is-invalid @enderror"
                accept=".jpeg,.jpg,.png,.pdf">
          @error('invoice_copy_upload') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
          <div class="fb-hint mt-2">Optional: upload a single invoice copy for this challan.</div>
        </div>

        {{-- client-side preview --}}
        <div id="invoice_preview" class="preview-grid mt-2" style="display:none;"></div>
      </div>

      {{-- ===== Actions ===== --}}
      <div class="d-flex justify-content-end mt-4 pt-2">
        <a href="{{ url()->previous() }}" class="btn btn-light-fb mr-2">
          <i class="las la-arrow-left mr-1"></i> Cancel
        </a>
        <button type="submit" class="btn btn-fb">
          <i class="las la-save mr-1"></i> Submit
        </button>
      </div>
    </form>
  </div>
</div>
@endsection

@section('script')
<script>
  // Auto-hide alerts for a cleaner feel
  setTimeout(function(){ $('.alert').alert('close'); }, 3000);
  @if(session('success')) window.scrollTo({ top: 0, behavior: 'smooth' }); @endif

  // Lightweight client-side previews
  function renderPreview(files, containerId){
    const wrap = document.getElementById(containerId);
    if (!wrap) return;
    wrap.innerHTML = '';
    if (!files || !files.length){ wrap.style.display='none'; return; }

    Array.from(files).forEach(file=>{
      const ext = (file.name.split('.').pop() || '').toLowerCase();
      const card = document.createElement('div');
      card.className = 'preview-card';

      if (['jpg','jpeg','png','webp','gif'].includes(ext)){
        const img = document.createElement('img');
        img.className = 'preview-thumb';
        img.alt = file.name;
        const fr = new FileReader();
        fr.onload = e => img.src = e.target.result;
        fr.readAsDataURL(file);
        card.appendChild(img);
      } else {
        const ph = document.createElement('div');
        ph.className = 'preview-thumb d-flex align-items-center justify-content-center';
        ph.innerHTML = `<span class="text-muted"><i class="las la-file-alt"></i> ${ext.toUpperCase() || 'FILE'}</span>`;
        card.appendChild(ph);
      }

      const name = document.createElement('div');
      name.className = 'preview-name';
      name.textContent = file.name;
      card.appendChild(name);

      wrap.appendChild(card);
    });

    wrap.style.display = 'grid';
  }

  document.getElementById('attachments')?.addEventListener('change', function(e){
    renderPreview(e.target.files, 'attachments_preview');
  });
  document.getElementById('invoice_copy_upload')?.addEventListener('change', function(e){
    renderPreview(e.target.files, 'invoice_preview');
  });
</script>
@endsection

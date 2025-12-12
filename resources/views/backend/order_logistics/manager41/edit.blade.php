{{-- resources/views/backend/order_logistics/manager41/edit.blade.php --}}
@extends('backend.layouts.app')

@section('content')
{{-- ======= Titlebar (wrap-safe) ======= --}}
<div class="aiz-titlebar text-left mt-3 mb-4">
  <h1 class="h3 fw-600 text-primary mb-2">Edit Order Logistic <span class="text-muted">(Manager-41)</span></h1>
  <div class="d-flex flex-wrap" style="gap:.5rem;">
    <span class="px-2 py-1 border rounded-pill bg-white">
      <i class="las la-file-invoice mr-1"></i> <span class="text-muted">Challan:</span>
      <span class="font-weight-600">{{ $challanNo ?? '-' }}</span>
    </span>
    <span class="px-2 py-1 border rounded-pill bg-white">
      <i class="las la-user mr-1"></i> <span class="font-weight-600">{{ $customer ?? 'Customer' }}</span>
    </span>
    <span class="px-2 py-1 border rounded-pill bg-white">
      <i class="las la-warehouse mr-1"></i> <span class="font-weight-600">{{ $warehouse ?? '-' }}</span>
    </span>
  </div>
</div>

{{-- ======= Flash & Validation ======= --}}
<div class="container px-0">
  @if (session('success'))
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
      <i class="las la-check-circle mr-2"></i> {!! session('success') !!}
      <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
  @endif
  @if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
      <i class="las la-exclamation-triangle mr-2"></i> {!! session('error') !!}
      <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
  @endif
  @if ($errors->any())
    <div class="alert alert-warning alert-dismissible fade show shadow-sm" role="alert">
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

{{-- ======= Styles ======= --}}
<style>
  .card-elev { border:1px solid #e5e7eb; border-radius:14px }
  .section-title{font-weight:600;color:#111827;border-bottom:1px solid #eef2f7;padding-bottom:.35rem;margin-bottom:.85rem;}
  .hint{color:#6b7280;font-size:.85rem}

  /* File tiles + small previews */
  .file-tile{border:1px solid #e5e7eb;border-radius:12px;padding:.6rem;height:100%;display:flex;flex-direction:column;background:#fff}
  .file-name{font-size:.8rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .file-preview{margin-top:.35rem;border-radius:.5rem;overflow:hidden;background:#f8fafc;display:flex;align-items:center;justify-content:center;position:relative}
  .file-preview img{width:100%;height:150px;object-fit:contain;background:#fff}
  .file-preview embed{width:100%;height:180px;border:0}
  .remove-chip{display:inline-flex;align-items:center;background:#fff3f3;border:1px solid #ffd6d6;color:#b91c1c;border-radius:999px;padding:.2rem .55rem;font-size:.78rem}
  .remove-chip input{margin-right:.35rem}

  /* Live preview tiles for new files */
  .preview-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));grid-gap:12px}
  .preview-item{border:1px dashed #d1d5db;border-radius:12px;padding:.6rem;background:#fff}
  .preview-item .name{font-size:.8rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .preview-item .box{margin-top:.35rem;border-radius:.5rem;background:#f8fafc;display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative}
  .preview-item .box img{width:100%;height:150px;object-fit:contain}
  .preview-item .box embed{width:100%;height:180px;border:0}

  /* Floating cut/remove button for previews */
  .cut-btn{
    position:absolute; top:8px; right:8px;
    border:none; border-radius:999px; padding:6px; line-height:1;
    background:#ffffff; box-shadow:0 2px 6px rgba(0,0,0,.15);
    cursor:pointer;
  }
  .cut-btn:hover{ background:#ffecec }
</style>

{{-- ======= Main Card ======= --}}
<div class="card card-elev shadow-sm">
  <div class="card-body">
    <form method="POST" action="{{ route('manager41.order.logistics.update', $encryptedId) }}" enctype="multipart/form-data" id="editLogisticForm">
      @csrf

      {{-- Invoice No (editable) --}}
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="fw-600">Invoice No (optional)</label>
          <input type="text" name="invoice_no"
                 class="form-control @error('invoice_no') is-invalid @enderror"
                 value="{{ old('invoice_no', $logistic->invoice_no) }}"
                 placeholder="e.g., DEL41/000045/25-26">
          @error('invoice_no') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
      </div>

      {{-- Logistics Details --}}
      <div class="row">
        <div class="col-12 mb-2"><div class="section-title"><i class="las la-truck mr-1"></i> Logistics Details</div></div>

        <div class="col-md-6 mb-3">
          <label class="fw-600">Transporter Name <span class="text-danger">*</span></label>
          <input type="text" name="transport_name" class="form-control @error('transport_name') is-invalid @enderror"
                 value="{{ old('transport_name', $logistic->transport_name) }}" placeholder="e.g., VRL Logistics" required>
          @error('transport_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
          <div class="hint mt-1"><i class="las la-info-circle mr-1"></i> Transporter’s registered name.</div>
        </div>

        <div class="col-md-3 mb-3">
          <label class="fw-600">LR Date <span class="text-danger">*</span></label>
          <input type="date" name="lr_date" class="form-control @error('lr_date') is-invalid @enderror"
                 value="{{ old('lr_date', $logistic->lr_date ? \Carbon\Carbon::parse($logistic->lr_date)->format('Y-m-d') : '') }}" required>
          @error('lr_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-3 mb-3">
          <label class="fw-600">LR Number <span class="text-danger">*</span></label>
          <input type="text" name="lr_no" class="form-control @error('lr_no') is-invalid @enderror"
                 value="{{ old('lr_no', $logistic->lr_no) }}" placeholder="e.g., VRL/DEL/12345" required>
          @error('lr_no') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-3 mb-3">
          <label class="fw-600">No. of Boxes <span class="text-danger">*</span></label>
          <input type="number" min="0" name="no_of_boxes" class="form-control @error('no_of_boxes') is-invalid @enderror"
                 value="{{ old('no_of_boxes', $logistic->no_of_boxes) }}" placeholder="e.g., 6" required>
          @error('no_of_boxes') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-3 mb-3">
          <label class="fw-600">LR Amount <span class="text-danger">*</span></label>
          <input type="number" step="0.01" min="0" name="lr_amount" class="form-control @error('lr_amount') is-invalid @enderror"
                 value="{{ old('lr_amount', $logistic->lr_amount) }}" placeholder="e.g., 850.00" required>
          @error('lr_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
      </div>

      <hr class="my-3">

      {{-- Current Attachments --}}
      @php $hasAttachments = !empty($attachments) && count($attachments) > 0; @endphp
      <div class="d-flex justify-content-between align-items-center">
        <div class="section-title mb-2"><i class="las la-paperclip mr-1"></i> Current Attachments
          @if($hasAttachments)
            <span class="ml-2 badge badge-pill badge-soft-secondary">{{ count($attachments) }}</span>
          @endif
        </div>
        <span class="hint mb-2"><i class="las la-lightbulb mr-1"></i>Tick “Remove” to drop a saved file; add more below.</span>
      </div>

      @if($hasAttachments)
        <div class="row">
          @foreach ($attachments as $i => $url)
            @php
              $path = parse_url($url, PHP_URL_PATH);
              $ext  = strtolower(pathinfo($path ?? '', PATHINFO_EXTENSION));
              $base = basename($path ?? '');
            @endphp
            <div class="col-lg-4 col-md-6 mb-3">
              <div class="file-tile">
                <div class="file-name" title="{{ $base }}">
                  <i class="las la-file mr-1 text-muted"></i>
                  <a href="{{ $url }}" target="_blank">{{ $base }}</a>
                </div>
                <div class="file-preview mt-2">
                  @if ($ext === 'pdf')
                    <embed src="{{ $url }}#toolbar=1&navpanes=0&scrollbar=1" type="application/pdf">
                  @elseif(in_array($ext, ['jpg','jpeg','png','webp','gif']))
                    <img src="{{ $url }}" alt="Attachment">
                  @else
                    <div class="text-muted py-5"><i class="las la-file-alt"></i> {{ strtoupper($ext ?: 'FILE') }} preview not supported.</div>
                  @endif
                </div>
                <div class="mt-2">
                  <label class="remove-chip mb-0">
                    <input class="remove-check" type="checkbox" name="remove_indexes[]" value="{{ $i }}"> Remove
                  </label>
                </div>
              </div>
            </div>
          @endforeach
        </div>
      @else
        <div class="text-muted mb-3"><i class="las la-info-circle mr-1"></i>No attachments yet.</div>
      @endif

      {{-- Add More Attachments --}}
      <div class="mt-2">
        <label class="fw-600">Add Attachments (Images/PDF)</label>
        <div class="input-group">
          <input type="file" id="attachments" name="attachments[]" class="form-control @error('attachments.*') is-invalid @enderror"
                 accept=".jpeg,.jpg,.png,.webp,.gif,.pdf" multiple>
          <div class="input-group-append"><span class="input-group-text"><i class="las la-cloud-upload-alt"></i></span></div>
        </div>
        @error('attachments.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        <div id="new-attachments-preview" class="preview-grid mt-2" style="display:none;"></div>
        <div class="hint mt-1"><i class="las la-scissors mr-1"></i> Tip: Click the ✂ (cut) button on a tile to remove an accidentally selected file.</div>
      </div>

      <hr class="my-3">

      {{-- Invoice Copy (single) --}}
      <div class="section-title"><i class="las la-file-invoice-dollar mr-1"></i> Invoice Attachment (Single)</div>

      @if($logistic->invoice_copy_upload)
        @php
          $ipath = parse_url($logistic->invoice_copy_upload, PHP_URL_PATH);
          $iext  = strtolower(pathinfo($ipath ?? '', PATHINFO_EXTENSION));
          $iname = basename($ipath ?? '');
        @endphp
        <div class="row">
          <div class="col-md-6 mb-3">
            <div class="file-tile">
              <div class="file-name" title="{{ $iname }}">
                <i class="las la-file mr-1 text-muted"></i>
                <a href="{{ $logistic->invoice_copy_upload }}" target="_blank">Current: {{ $iname }}</a>
              </div>
              <div class="file-preview mt-2">
                @if ($iext === 'pdf')
                  <embed src="{{ $logistic->invoice_copy_upload }}#toolbar=1&navpanes=0&scrollbar=1" type="application/pdf">
                @elseif(in_array($iext, ['jpg','jpeg','png','webp','gif']))
                  <img src="{{ $logistic->invoice_copy_upload }}" alt="Invoice Copy">
                @else
                  <div class="text-muted py-5"><i class="las la-file-alt"></i> {{ strtoupper($iext ?: 'FILE') }} preview not supported.</div>
                @endif
              </div>
            </div>
          </div>
        </div>
      @endif

      <div class="mt-1">
        <div class="input-group">
          <input type="file" id="invoice_copy_upload" name="invoice_copy_upload" class="form-control @error('invoice_copy_upload') is-invalid @enderror"
                 accept=".jpeg,.jpg,.png,.webp,.gif,.pdf">
          <div class="input-group-append"><span class="input-group-text"><i class="las la-cloud-upload-alt"></i></span></div>
        </div>
        @error('invoice_copy_upload') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror

        <div id="invoice-copy-preview" class="preview-grid mt-2" style="display:none;"></div>
        <div class="hint mt-1"><i class="las la-scissors mr-1"></i> Click ✂ to clear if a wrong file is chosen.</div>
      </div>

      {{-- Actions --}}
      <div class="d-flex justify-content-end mt-4 pt-2">
        <a href="{{ url()->previous() }}" class="btn btn-light mr-2"><i class="las la-arrow-left mr-1"></i> Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="las la-save mr-1"></i> Update</button>
      </div>
    </form>
  </div>
</div>
@endsection

@section('script')
<script>
  // Auto-hide alerts
  setTimeout(function(){ $('.alert').alert('close'); }, 3000);

  // ---------- Helpers ----------
  const byId = (id) => document.getElementById(id);
  const extOf = (name) => (name.split('.').pop() || '').toLowerCase();
  const fileKey = (f) => [f.name, f.size, f.lastModified].join('|');

  // ---------- New Attachments (multiple) with CUT/REMOVE ----------
  const attInput = byId('attachments');
  const attWrap  = byId('new-attachments-preview');
  // Persistent buffer of chosen files
  let attDT = new DataTransfer();

  function renderAttachmentPreviews() {
    attWrap.innerHTML = '';
    const files = Array.from(attDT.files);
    if (!files.length) { attWrap.style.display = 'none'; return; }

    files.forEach((f) => {
      const key = fileKey(f);
      const ext = extOf(f.name);

      const item = document.createElement('div');
      item.className = 'preview-item';
      item.innerHTML = `
        <div class="name"><i class="las la-file text-muted mr-1"></i>${f.name}</div>
        <div class="box mt-2">
          <button type="button" class="cut-btn" data-key="${key}" title="Cut / Remove">
            ✂
          </button>
        </div>`;

      const box = item.querySelector('.box');
      if (['jpg','jpeg','png','webp','gif'].includes(ext)) {
        const img = document.createElement('img');
        img.src = URL.createObjectURL(f);
        box.appendChild(img);
      } else if (ext === 'pdf') {
        const emb = document.createElement('embed');
        emb.type = 'application/pdf';
        emb.src  = URL.createObjectURL(f) + '#toolbar=0&navpanes=0&scrollbar=1';
        box.appendChild(emb);
      } else {
        box.insertAdjacentHTML('beforeend', '<div class="text-muted p-4"><i class="las la-file-alt"></i> Preview not supported</div>');
      }

      attWrap.appendChild(item);
    });

    // Bind all cut buttons
    attWrap.querySelectorAll('.cut-btn').forEach(btn => {
      btn.addEventListener('click', function(){
        const keyToRemove = this.getAttribute('data-key');

        // Rebuild DataTransfer without the removed file
        const nextDT = new DataTransfer();
        Array.from(attDT.files).forEach(ff => {
          if (fileKey(ff) !== keyToRemove) nextDT.items.add(ff);
        });
        attDT = nextDT;

        // Sync back to input and re-render
        attInput.files = attDT.files;
        renderAttachmentPreviews();
      });
    });

    attWrap.style.display = 'grid';
  }

  if (attInput) {
    attInput.addEventListener('change', function(){
      // Add newly picked files into persistent buffer
      Array.from(this.files || []).forEach(f => attDT.items.add(f));
      // Reflect back to the input
      attInput.files = attDT.files;
      renderAttachmentPreviews();
    });
  }

  // ---------- Invoice Copy (single) with CUT/REMOVE ----------
  const invInput = byId('invoice_copy_upload');
  const invWrap  = byId('invoice-copy-preview');

  function renderInvoicePreview(file) {
    invWrap.innerHTML = '';
    if (!file) { invWrap.style.display = 'none'; return; }

    const ext = extOf(file.name);
    const item = document.createElement('div');
    item.className = 'preview-item';
    item.innerHTML = `
      <div class="name"><i class="las la-file text-muted mr-1"></i>${file.name}</div>
      <div class="box mt-2">
        <button type="button" class="cut-btn" id="cut-invoice" title="Cut / Remove">✂</button>
      </div>`;

    const box = item.querySelector('.box');
    if (['jpg','jpeg','png','webp','gif'].includes(ext)) {
      const img = document.createElement('img');
      img.src = URL.createObjectURL(file);
      box.appendChild(img);
    } else if (ext === 'pdf') {
      const emb = document.createElement('embed');
      emb.type = 'application/pdf';
      emb.src  = URL.createObjectURL(file) + '#toolbar=0&navpanes=0&scrollbar=1';
      box.appendChild(emb);
    } else {
      box.insertAdjacentHTML('beforeend', '<div class="text-muted p-4"><i class="las la-file-alt"></i> Preview not supported</div>');
    }

    invWrap.appendChild(item);
    invWrap.style.display = 'grid';

    // Cut handler for single
    byId('cut-invoice').addEventListener('click', function(){
      invInput.value = '';
      invWrap.innerHTML = '';
      invWrap.style.display = 'none';
    });
  }

  if (invInput) {
    invInput.addEventListener('change', function(){
      const f = this.files && this.files[0];
      renderInvoicePreview(f || null);
    });
  }
</script>
@endsection

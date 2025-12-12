{{-- resources/views/backend/marketing/whatsapp_carousel/index_meta_business_like.blade.php
     Meta Business–style Templates list with drawer + confirm delete + robust toasts (Create only)
--}}
@extends('backend.layouts.app')

@section('content')
@php
  use Illuminate\Support\Str;
  $tpls = collect($templates ?? []);
  $countTotal    = $tpls->count();
  $countApproved = $tpls->where('status', 'APPROVED')->count();
  $countPaused   = $tpls->where('status', 'PAUSED')->count();
  $countRejected = $tpls->where('status', 'REJECTED')->count();
  $allCategories = $tpls->pluck('category')->filter()->unique()->values();
  $allLanguages  = $tpls->pluck('language')->filter()->unique()->values();
@endphp

<style>
  :root{
    /* Meta-ish palette */
    --bg:#F0F2F5;                 /* surface */
    --card:#FFFFFF;               /* cards */
    --text:#101214;               /* primary text */
    --muted:#606770;              /* secondary text */
    --border:#E4E6EB;             /* borders */
    --border-2:#DADDE1;           /* subtle borders */

    --blue:#0866FF;               /* Meta brand */
    --blue-600:#0753D6;           /* hover */
    --danger:#DC2626;             /* red */
    --danger-600:#B91C1C;         /* red hover */

    --chip:#F3F4F6;               /* neutral chip */
    --chip-blue:#E7F0FF;          /* info chip */
    --chip-green:#E8FFF3;         /* success chip */

    --shadow:0 10px 30px rgba(0,0,0,.08);
    --soft:0 2px 12px rgba(0,0,0,.06);
    --mono: ui-monospace, Menlo, Consolas, monospace;
    --radius:12px;
  }
  body{background:var(--bg)}
  .wrap{max-width:1248px;margin:0 auto}

  /* ===== Page header ===== */
  .hdr{display:flex;align-items:center;justify-content:space-between;margin:18px 0}
  .title{display:flex;align-items:center;gap:10px}
  .glyph{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#e9f0ff,#f0f5ff);display:flex;align-items:center;justify-content:center;color:var(--blue);font-weight:900}
  .title h1{font-size:20px;margin:0;color:var(--text);font-weight:800}
  .actions{display:flex;gap:8px}

  /* ===== Buttons (Meta-like) ===== */
  .btn{border:1px solid var(--border);background:var(--card);color:var(--text);border-radius:10px;padding:9px 14px;font-weight:700;line-height:1;cursor:pointer;display:inline-flex;gap:8px;align-items:center;box-shadow:var(--soft)}
  .btn:hover{background:#f7f8fa}
  .btn:disabled{opacity:.6;cursor:not-allowed}
  .btn .ic{font-weight:900;opacity:.9}

  .btn-primary{background:var(--blue);border-color:var(--blue);color:#fff}
  .btn-primary:hover{background:var(--blue-600)}

  .btn-secondary{background:var(--card);border-color:var(--border);color:var(--text)}
  .btn-secondary:hover{background:#f7f8fa}

  .btn-danger{background:var(--danger);border-color:var(--danger);color:#fff}
  .btn-danger:hover{background:var(--danger-600);border-color:var(--danger-600)}

  /* Outline variant like Meta (transparent, colored text/border; fills on hover) */
  .btn-outline{background:transparent}
  .btn.btn-danger.btn-outline{color:var(--danger);border-color:var(--danger)}
  .btn.btn-danger.btn-outline:hover{background:var(--danger);color:#fff;border-color:var(--danger)}
  .btn.btn-primary.btn-outline{color:var(--blue);border-color:var(--blue)}
  .btn.btn-primary.btn-outline:hover{background:var(--blue);color:#fff;border-color:var(--blue)}

  /* Icon-only ghost button */
  .btn-ghost{background:transparent;border-color:transparent;box-shadow:none;color:var(--muted)}
  .btn-ghost:hover{background:#e9ecef;color:var(--text)}

  /* ===== Toolbar ===== */
  .toolbar{margin-top:12px;background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:12px;box-shadow:var(--soft);position:sticky;top:10px;z-index:10}
  .row{display:grid;grid-template-columns:1fr 170px 170px 170px 170px;gap:10px}
  @media (max-width: 1100px){ .row{grid-template-columns:1fr 1fr} }
  .input,.select{height:40px;border:1px solid var(--border);border-radius:10px;background:var(--card);color:var(--text);padding:0 12px;outline:0}
  .tabs{margin-top:10px;border-top:1px solid var(--border);padding-top:10px;display:flex;gap:6px;flex-wrap:wrap}
  .tab{padding:8px 12px;border:1px solid var(--border);border-radius:999px;background:var(--card);color:var(--text);font-weight:700;font-size:12px;cursor:pointer}
  .tab.active{background:var(--blue);color:#fff;border-color:var(--blue)}

  /* ===== Table ===== */
  .table-wrap{margin-top:12px;overflow:auto;max-height:70vh}
  table{width:100%;border-collapse:separate;border-spacing:0 8px}
  thead th{position:sticky;top:0;background:var(--bg);padding:8px 10px;font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.02em;border-bottom:1px solid var(--border)}
  tbody tr{transition:transform .08s ease}
  .roww{background:var(--card);border:1px solid var(--border);box-shadow:0 1px 0 rgba(0,0,0,.04)}
  .roww td{padding:10px;border-top:1px solid var(--border);border-bottom:1px solid var(--border)}
  .roww td:first-child{border-left:1px solid var(--border);border-top-left-radius:10px;border-bottom-left-radius:10px}
  .roww td:last-child{border-right:1px solid var(--border);border-top-right-radius:10px;border-bottom-right-radius:10px}
  .name{font-weight:800;color:var(--text)}
  .id{font-size:12px;color:var(--muted)}
  .badge{font-size:11px;padding:4px 8px;border-radius:999px;display:inline-flex;align-items:center;gap:6px;border:1px solid var(--border-2);background:var(--chip)}
  .status-approved{background:var(--chip-blue);color:#1e55b6;border-color:#c7dbff}
  .status-rejected{background:#feeaea;color:#a33b3b;border-color:#ffc5c5}
  .status-paused{background:#fff7e6;color:#6f4500;border-color:#ffe0a6}
  .status-other{background:#f1f3f5;color:#495057;border-color:#dfe4ea}
  .mini{border:1px dashed var(--border);padding:6px 8px;border-radius:8px;display:inline-flex;gap:6px;align-items:center}
  .copy{cursor:pointer;color:var(--blue)}

  /* ===== Drawer ===== */
  .drawer{position:fixed;top:0;right:-640px;width:640px;height:100vh;background:var(--card);border-left:1px solid var(--border);box-shadow:var(--shadow);z-index:1050;display:flex;flex-direction:column;transition:right .25s ease}
  .drawer.open{right:0}
  .drawer-head{padding:14px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center}
  .drawer-title{font-weight:800}
  .drawer-body{padding:14px;overflow:auto}
  .sec{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:12px;margin-bottom:12px}
  .sec-title{font-size:12px;color:var(--muted);font-weight:800;text-transform:uppercase;margin-bottom:8px}
  .kv{display:inline-flex;font-size:11px;background:var(--chip);border:1px solid var(--border);border-radius:999px;padding:3px 8px;margin:2px}
  .kv.price{background:var(--chip-blue);border-color:#c7dbff;color:#1e55b6;font-weight:800}
  .mono{font-family:var(--mono)}

  /* ===== Confirm Modal ===== */
  .modal{position:fixed;inset:0;background:rgba(16,18,20,.45);display:none;align-items:center;justify-content:center;z-index:1100}
  .modal.show{display:flex}
  .modal-card{width:520px;max-width:92vw;background:var(--card);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow)}
  .modal-h{padding:14px 16px;border-bottom:1px solid var(--border);font-weight:800}
  .modal-b{padding:16px;color:var(--text)}
  .modal-f{padding:12px 16px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end}

  /* ===== Toasts (robust) ===== */
  .toasts{
    position:fixed;
    top:16px; right:16px;
    display:flex;flex-direction:column;gap:8px;
    z-index:2147483647; /* always on top */
    pointer-events:none; /* clicks pass through */
  }
  .toast{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:10px;
    box-shadow:var(--soft);
    padding:10px 12px;
    min-width:280px;
    display:flex;gap:8px;align-items:flex-start;
    opacity:0; transform:translateY(-8px);
    transition:opacity .25s ease, transform .25s ease;
    pointer-events:auto; /* allow hover/copy if needed */
  }
  .toast.show{ opacity:1; transform:translateY(0); }
  .toast.hide{ opacity:0; transform:translateY(-8px); }
  .toast.ok{border-color:#C7DBFF}
  .toast.err{border-color:#ffc5c5}
  .toast-title{font-weight:800;margin-bottom:2px}
</style>

<div class="wrap" id="metaUltraRoot">
  <div class="hdr">
    <div class="title">
      <span class="glyph">f</span>
      <h1>Templates</h1>
    </div>
    <div class="actions">
      <a href="{{ route('wa.carousel.create-template.form') }}" class="btn btn-primary"><span class="ic">＋</span>Create</a>
       <a href="{{ route('wa.carousel.send.form') }}" class="btn btn-primary"><span class="ic">▶</span>Send Campaign</a>
    </div>
  </div>

  <div class="toolbar">
    <div class="row">
      <input id="q" class="input" type="search" placeholder="Search name or ID…" autocomplete="off">
      <select id="fStatus" class="select">
        <option value="">All Status</option>
        <option value="APPROVED">Approved</option>
        <option value="PAUSED">Paused</option>
        <option value="REJECTED">Rejected</option>
      </select>
      <select id="fCategory" class="select">
        <option value="">All Categories</option>
        @foreach($allCategories as $cat)
          <option value="{{ $cat }}">{{ $cat }}</option>
        @endforeach
      </select>
      <select id="fLang" class="select">
        <option value="">All Languages</option>
        @foreach($allLanguages as $lng)
          <option value="{{ $lng }}">{{ strtoupper($lng) }}</option>
        @endforeach
      </select>
      <select id="fSort" class="select">
        <option value="">Sort: Name</option>
        <option value="status">Sort: Status</option>
        <option value="cards">Sort: Cards</option>
      </select>
    </div>
    <div class="tabs" id="tabs">
      <button class="tab active" data-tab="">All</button>
      <button class="tab" data-tab="APPROVED">Approved</button>
      <button class="tab" data-tab="PAUSED">Paused</button>
      <button class="tab" data-tab="REJECTED">Rejected</button>
      <div style="margin-left:auto;display:flex;gap:10px;align-items:center">
        <span style="color:var(--muted);font-size:12px">Total: <b>{{ $countTotal }}</b></span>
        <span class="kv">Approved: {{ $countApproved }}</span>
        <span class="kv">Paused: {{ $countPaused }}</span>
        <span class="kv">Rejected: {{ $countRejected }}</span>
      </div>
    </div>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:34px"><input type="checkbox" id="chkAll"></th>
          <th>Name</th>
          <th>Status</th>
          <th>Category</th>
          <th>Language</th>
          <th>Cards</th>
          <th>Header</th>
          <th>Param</th>
          <th style="width:180px">Options</th>
        </tr>
      </thead>
      <tbody id="tbody">
      @forelse($templates as $t)
        @php
          $components = (array) ($t['components'] ?? []);
          $status     = strtoupper((string) ($t['status'] ?? ''));
          $language   = (string) ($t['language'] ?? '');
          $category   = (string) ($t['category'] ?? '');
          $paramFmt   = (string) ($t['parameter_format'] ?? '—');
          $tname      = (string) ($t['name'] ?? '-');
          $tid        = (string) ($t['id'] ?? '');

          $bodyComp   = collect($components)->first(fn($c) => strtoupper((string)($c['type']??''))==='BODY') ?? [];
          $bodyText   = (string) ($bodyComp['text'] ?? '');
          $topExamples= (array) data_get($bodyComp, 'example.body_text.0', []);

          $carousel   = collect($components)->first(fn($c) => strtoupper((string)($c['type']??''))==='CAROUSEL') ?? [];
          $cards      = (array) ($carousel['cards'] ?? []);
          $cardsCount = is_array($cards) ? count($cards) : 0;

          $headerFormat = '-';
          if ($cardsCount) {
            $firstComps = (array) ($cards[0]['components'] ?? []);
            $hdr = collect($firstComps)->first(fn($x) => strtoupper((string)($x['type']??''))==='HEADER') ?? [];
            $headerFormat = strtoupper((string) ($hdr['format'] ?? '-'));
          }

          $rid = 'r-'.($loop->index);
        @endphp

        <tr class="roww"
            data-name="{{ Str::lower($tname) }}"
            data-id="{{ Str::lower($tid) }}"
            data-status="{{ $status }}"
            data-category="{{ $category }}"
            data-language="{{ Str::lower($language) }}"
            data-cards="{{ $cardsCount }}"
            data-template-name="{{ $tname }}"
            data-template-lang="{{ $language }}">
          <td><input type="checkbox" class="chk"></td>
          <td>
            <div class="name">{{ $tname }}</div>
            <div class="id">ID: <span class="mono">{{ $tid ?: '—' }}</span> <button type="button" class="btn-ghost copy" data-copy="{{ $tid }}">Copy</button></div>
          </td>
          <td>
            <span style="width:auto" class="badge {{ $status==='APPROVED'?'status-approved':($status==='PAUSED'?'status-paused':($status==='REJECTED'?'status-rejected':'status-other')) }}">{{ $status ?: '—' }}</span>
          </td>
          <td><span class="mini mono">{{ $category ?: '—' }}</span></td>
          <td><span class="mini mono">{{ strtoupper($language ?: '—') }}</span></td>
          <td><span class="mini mono">{{ $cardsCount }}</span></td>
          <td><span class="mini mono">{{ $headerFormat }}</span></td>
          <td><span class="mini mono">{{ $paramFmt }}</span></td>
          <td style="display:flex;gap:8px">
            <button class="btn" data-open="{{ $rid }}"><span class="ic">👁</span>View</button>
            {{-- EDIT: goes to edit form with template name & language --}}
          <a  class="btn btn-primary btn-outline"
             href="{{ route('wa.carousel.edit-template.form', ['name' => $tname, 'language' => $language]) }}">
            <span class="ic">✏️</span>Edit
          </a>
            <button class="btn btn-danger btn-outline"
                    data-del
                    data-name="{{ $tname }}"
                    data-id="{{ $tid }}"
                    data-lang="{{ $language }}">
              <span class="ic">🗑</span>Delete
            </button>
          </td>
        </tr>

        {{-- Hidden detail row (for drawer content) --}}
        <tr id="{{ $rid }}-detail" style="display:none">
          <td colspan="9">
            <div class="drawer-content">
              <div class="sec">
                <div class="sec-title">Template</div>
                <div class="mono" style="white-space:pre-wrap"><b>Name:</b> {{ $tname }}\n<b>ID:</b> {{ $tid ?: '—' }}</div>
                <div style="margin-top:6px" class="mono"><b>Status:</b> {{ $status ?: '—' }}  •  <b>Category:</b> {{ $category ?: '—' }}  •  <b>Lang:</b> {{ strtoupper($language ?: '—') }}</div>
              </div>
              <div class="sec">
                <div class="sec-title">Top Body</div>
                <div class="mono" style="white-space:pre-wrap">{{ $bodyText ?: '—' }}</div>
                @if(!empty($topExamples))
                  <div style="margin-top:6px">
                    @foreach($topExamples as $i => $ex)
                      <span class="kv">{{ '{'.($i+1).'}' }} = {{ $ex }}</span>
                    @endforeach
                  </div>
                @endif
              </div>
              <div class="sec">
                <div class="sec-title">Cards</div>
                @foreach($cards as $ci => $card)
                  @php
                    $cardComps = collect($card['components'] ?? []);
                    $hdr   = $cardComps->first(fn($x) => strtoupper((string)($x['type']??''))==='HEADER') ?? [];
                    $hdrFmt= strtoupper((string) ($hdr['format'] ?? '-'));
                    $hdrEx = (string) data_get($hdr, 'example.header_handle.0', '');

                    $cBody        = $cardComps->first(fn($x) => strtoupper((string)($x['type']??''))==='BODY') ?? [];
                    $cBodyTxt     = (string) ($cBody['text'] ?? '');
                    $cBodyExs     = (array) data_get($cBody, 'example.body_text.0', []);

                    $btns    = $cardComps->first(fn($x) => strtoupper((string)($x['type']??''))==='BUTTONS') ?? [];
                    $buttons = (array) ($btns['buttons'] ?? []);
                    $urlBtn  = collect($buttons)->first(fn($b) => strtoupper((string)($b['type']??''))==='URL') ?? [];
                    $urlText = (string) ($urlBtn['text'] ?? '');
                    $urlPtrn = (string) ($urlBtn['url'] ?? '');
                    $urlEx   = (string) data_get($urlBtn, 'example.0', '');
                    $qrBtn   = collect($buttons)->first(fn($b) => strtoupper((string)($b['type']??''))==='QUICK_REPLY') ?? [];
                    $qrText  = (string) ($qrBtn['text'] ?? '');
                  @endphp

                  <div class="sec" style="margin-top:10px">
                    <div class="mono" style="font-weight:800">Card #{{ $ci+1 }}</div>
                    <div class="mono" style="margin-top:4px"><b>HEADER:</b> {{ $hdrFmt }} @if($hdrEx) <span class="kv mono">handle: {{ Str::limit($hdrEx,48) }}</span> @endif</div>
                    <div class="mono" style="margin-top:8px"><b>Body</b></div>
                    <div class="mono" style="white-space:pre-wrap">{{ $cBodyTxt ?: '—' }}</div>
                    @if(!empty($cBodyExs))
                      <div style="margin-top:6px">
                        @foreach($cBodyExs as $i => $ex)
                          @php $isPrice = is_string($ex) && preg_match('/(₹|Rs\.?|INR)\s*\d|\b\d{3,}(?:\.\d{2})?\b/u', $ex); @endphp
                          <span class="kv {{ $isPrice ? 'price' : '' }}">{{ '{'.($i+1).'}' }} = {{ $ex }}</span>
                        @endforeach
                      </div>
                    @endif

                    @if(!empty($buttons))
                      <div class="mono" style="margin-top:8px"><b>Buttons</b></div>
                      <div>
                        @if(!empty($urlBtn))
                          <div class="mono">URL: <b>{{ $urlText ?: 'View' }}</b> <span class="kv mono">Pattern: {{ $urlPtrn ?: '—' }}</span> <span class="kv mono">Example: {{ $urlEx ?: '—' }}</span></div>
                        @endif
                        @if(!empty($qrBtn))
                          <div class="mono">Quick Reply: <b>{{ $qrText ?: '—' }}</b></div>
                        @endif
                      </div>
                    @endif
                  </div>
                @endforeach
              </div>
            </div>
          </td>
        </tr>
      @empty
        <tr class="roww"><td colspan="9" style="text-align:center;color:var(--muted)">No templates found.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- Drawer --}}
<div id="drawer" class="drawer" aria-hidden="true">
  <div class="drawer-head">
    <div class="drawer-title">Template Details</div>
    <button class="btn" id="drawerClose">Close</button>
  </div>
  <div class="drawer-body" id="drawerBody"></div>
</div>

{{-- Confirm Delete Modal --}}
<div id="confirmModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
  <div class="modal-card">
    <div class="modal-h" id="confirmTitle">Delete template?</div>
    <div class="modal-b">
      <div id="confirmText" style="white-space:pre-wrap">Are you sure you want to delete this template?
This action will remove the template from Meta Business (not just hide it) if your API credentials allow deletion.</div>
    </div>
    <div class="modal-f">
      <button type="button" class="btn" id="btnCancel">Cancel</button>
      <button type="button" class="btn btn-danger" id="btnConfirm"><span id="confirmSpin" class="ic" style="display:none">⏳</span> Delete</button>
    </div>
  </div>
</div>

{{-- Toasts (container with ARIA live) --}}
<div class="toasts" id="toasts" role="status" aria-live="polite"></div>

<script>
(function(){
  const $tbody = document.getElementById('tbody');
  const $drawer = document.getElementById('drawer');
  const $drawerBody = document.getElementById('drawerBody');
  const $drawerClose = document.getElementById('drawerClose');

  const $q = document.getElementById('q');
  const $fStatus = document.getElementById('fStatus');
  const $fCategory = document.getElementById('fCategory');
  const $fLang = document.getElementById('fLang');
  const $fSort = document.getElementById('fSort');
  const $tabs = document.getElementById('tabs');
  const $chkAll = document.getElementById('chkAll');
  let tabStatus = '';
  const norm = v => (v||'').toString().trim().toLowerCase();

  /* ===== Robust toast helper ===== */
  function ensureToastHost(){
    let host = document.getElementById('toasts');
    if(!host){
      host = document.createElement('div');
      host.id = 'toasts';
      host.className = 'toasts';
      host.setAttribute('role','status');
      host.setAttribute('aria-live','polite');
      document.body.appendChild(host);
    }
    return host;
  }
  const $toasts = ensureToastHost();

  function toast(message, type='ok', ttl=3200){
    try{
      const el = document.createElement('div');
      el.className = 'toast ' + (type === 'err' ? 'err' : 'ok');
      el.innerHTML = `
        <div>
          <div class="toast-title">${type === 'err' ? 'Error' : 'Notice'}</div>
          <div>${message}</div>
        </div>
      `;
      $toasts.appendChild(el);

      // Show (next frame for CSS transition)
      requestAnimationFrame(()=> el.classList.add('show'));

      // Hide before removal
      const fade = Math.max(250, Math.min(800, ttl * 0.25));
      setTimeout(()=> el.classList.add('hide'), Math.max(800, ttl - fade));
      setTimeout(()=> el.remove(), Math.max(1000, ttl));
    }catch(e){
      console && console.warn && console.warn('Toast error:', e);
    }
  }

  // Expose globally (optional)
  window.metaToast = toast;

  /* ===== Search/Filter/Sort ===== */
  function haystack(row){
    const name = row.querySelector('.name')?.textContent || '';
    const id   = row.querySelector('.id .mono')?.textContent || '';
    return (name + ' ' + id).toLowerCase();
  }

  function sortRows(){
    const mode = $fSort.value;
    if(!mode) return;
    const rows = Array.from($tbody.querySelectorAll('tr.roww')).filter(r=>r.style.display !== 'none');
    rows.sort((a,b)=>{
      if(mode==='status') return (a.getAttribute('data-status')||'').localeCompare(b.getAttribute('data-status')||'');
      if(mode==='cards') return (+a.getAttribute('data-cards')||0) - (+b.getAttribute('data-cards')||0);
      return (a.getAttribute('data-name')||'').localeCompare(b.getAttribute('data-name')||'');
    });
    const frag = document.createDocumentFragment();
    rows.forEach(r=>{
      const id = r.querySelector('[data-open]')?.getAttribute('data-open');
      const det = id ? document.getElementById(id+'-detail') : null;
      frag.appendChild(r);
      if(det) frag.appendChild(det);
    });
    $tbody.appendChild(frag);
  }

  function apply(){
    const q = norm($q.value);
    const st = $fStatus.value || tabStatus;
    const cat = norm($fCategory.value);
    const lang = norm($fLang.value);
    const rows = Array.from($tbody.querySelectorAll('tr.roww'));
    rows.forEach(row => {
      const text = haystack(row);
      const rowSt = row.getAttribute('data-status') || '';
      const rowC  = norm(row.getAttribute('data-category'));
      const rowL  = norm(row.getAttribute('data-language'));
      let ok = true;
      if(q && !text.includes(q)) ok = false;
      if(st && rowSt !== st) ok = false;
      if(cat && rowC !== cat) ok = false;
      if(lang && rowL !== lang) ok = false;
      row.style.display = ok ? '' : 'none';
      const id = row.querySelector('[data-open]')?.getAttribute('data-open');
      const det = id ? document.getElementById(id+'-detail') : null;
      if(det) det.style.display = 'none';
    });
    sortRows();
  }

  /* ===== Drawer ===== */
  function openDrawer(rowId){
    const detailRow = document.getElementById(rowId+'-detail');
    if(!detailRow) return;
    const content = detailRow.querySelector('.drawer-content');
    if(!content) return;
    $drawerBody.innerHTML = content.innerHTML;
    $drawer.classList.add('open');
    $drawer.setAttribute('aria-hidden','false');
  }
  function closeDrawer(){
    $drawer.classList.remove('open');
    $drawer.setAttribute('aria-hidden','true');
    $drawerBody.innerHTML = '';
  }

  /* ===== Copy ID / Open drawer bindings ===== */
  $tbody.addEventListener('click',(e)=>{
    const btn = e.target.closest('[data-open]');
    if(btn){ openDrawer(btn.getAttribute('data-open')); }
    const cpy = e.target.closest('.copy');
    if(cpy){
      const txt = cpy.getAttribute('data-copy')||'';
      if(txt){
        navigator.clipboard.writeText(txt).then(()=>{
          toast('Copied ID to clipboard');
        }).catch(()=> toast('Copy failed', 'err'));
      }
    }
  });
  $drawerClose.addEventListener('click', closeDrawer);
  document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') closeDrawer(); });

  [$q,$fStatus,$fCategory,$fLang,$fSort].forEach(el=> el && el.addEventListener('input', apply));
  [$fStatus,$fCategory,$fLang,$fSort].forEach(el=> el && el.addEventListener('change', apply));

  $tabs.addEventListener('click', (e)=>{
    const tab = e.target.closest('.tab');
    if(!tab) return;
    $tabs.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
    tab.classList.add('active');
    tabStatus = tab.getAttribute('data-tab')||'';
    $fStatus.value = '';
    apply();
  });

  if($chkAll){ $chkAll.addEventListener('change', ()=>{ document.querySelectorAll('.chk').forEach(c=> c.checked = $chkAll.checked); }); }

  apply();

  /* ===== Delete Flow (Meta-like confirm) ===== */
  const $modal = document.getElementById('confirmModal');
  const $confirmText = document.getElementById('confirmText');
  const $btnCancel = document.getElementById('btnCancel');
  const $btnConfirm = document.getElementById('btnConfirm');
  const $spin = document.getElementById('confirmSpin');
  let delCtx = { name:'', lang:'', rowEl:null, btnEl:null };

  function showConfirm(name, lang, row, btn){
    delCtx = { name:name, lang:lang, rowEl:row, btnEl:btn };
    $confirmText.textContent = `Are you sure you want to delete template "${name}" (${lang || '—'})?\nThis cannot be undone.`;
    $modal.classList.add('show');
  }
  function hideConfirm(){
    $modal.classList.remove('show');
    delCtx = { name:'', lang:'', rowEl:null, btnEl:null };
    $spin.style.display='none';
    $btnConfirm.disabled = false;
  }
  $btnCancel.addEventListener('click', hideConfirm);
  $modal.addEventListener('click', (e)=>{ if(e.target === $modal) hideConfirm(); });

  // Bind delete button
  $tbody.addEventListener('click', (e)=>{
    const del = e.target.closest('[data-del]');
    if(!del) return;
    const row = e.target.closest('tr.roww');
    const name = del.getAttribute('data-name')||row?.getAttribute('data-template-name')||'';
    const lang = del.getAttribute('data-lang')||row?.getAttribute('data-template-lang')||'';
    showConfirm(name, lang, row, del);
  });

  // Confirm: call backend
  $btnConfirm.addEventListener('click', async ()=>{
    try{
      $btnConfirm.disabled = true; $spin.style.display='inline-block';
      const name = delCtx.name; const language = delCtx.lang || 'en';
      if(!name){ toast('Missing template name', 'err'); hideConfirm(); return; }

      const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
      const resp = await fetch("{{ route('wa.carousel.template.delete') }}", {
        method:'POST', headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ name, language })
      });
      const json = await resp.json().catch(()=>({ ok:false, message:'Invalid server response' }));

      if(resp.ok && json.ok){
        // remove row + hidden detail
        if(delCtx.rowEl){
          const id = delCtx.rowEl.querySelector('[data-open]')?.getAttribute('data-open');
          delCtx.rowEl.remove();
          if(id){ const det = document.getElementById(id+'-detail'); if(det) det.remove(); }
        }
        toast('Template deleted successfully');
      } else {
        const msg = json.message || (json.error && (json.error.error_user_msg || json.error.message)) || 'Delete failed';
        toast(msg, 'err');
      }
    }catch(err){
      toast(err?.message || 'Network error', 'err');
    }finally{ hideConfirm(); }
  });
})();
</script>
@endsection

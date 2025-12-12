@extends('backend.layouts.app')

@section('content')
<style>
  :root{
    --bg:#f6f7f8; --card:#fff; --text:#0f172a; --muted:#6b7280; --line:#e5e7eb;
    --brand:#0866ff; --brand-600:#0757db; --ok:#10b981; --warn:#f59e0b; --danger:#ef4444;
    --radius:14px; --shadow:0 1px 2px rgba(16,24,40,.06),0 1px 3px rgba(16,24,40,.1);
  }
  body{background:var(--bg);}
  .page-wrap{display:grid; grid-template-columns: 1fr 360px; gap:20px;}
  @media (max-width:1200px){ .page-wrap{grid-template-columns:1fr;} .preview-pane{position:static; top:auto;} }

  .meta-card{background:var(--card); border:1px solid var(--line); border-radius:var(--radius); box-shadow:var(--shadow);}
  .meta-card .card-header{padding:16px 18px; border-bottom:1px solid var(--line); background:#fff; border-top-left-radius:var(--radius); border-top-right-radius:var(--radius);}
  .meta-card .card-body{padding:18px;}
  .h6{font-size:16px; font-weight:600; color:var(--text); margin:0;}
  .subtle{color:var(--muted); font-size:12px;}
  .btn{border-radius:10px; padding:.5rem .9rem; border:1px solid var(--line); background:#fff; color:#111;}
  .btn-primary{background:var(--brand); border-color:var(--brand); color:#fff;}
  .btn-primary:hover{background:var(--brand-600); border-color:var(--brand-600);}
  .btn-ghost{background:transparent; border-color:transparent; color:#6b7280;}
  .btn-danger{background:#fff; color:#ef4444; border-color:#fecaca;}
  .divider{height:1px; background:var(--line); margin:12px 0;}
  .group{display:grid; grid-template-columns:200px 1fr; gap:16px; align-items:flex-start; margin-bottom:16px;}
  .group label{font-weight:600; padding-top:9px;}
  .counter-badge{background:#f3f4f6;border:1px solid var(--line);border-radius:8px;padding:3px 8px;font-size:12px;}

  /* Preview */
  .preview-pane{position:sticky; top:80px; height:fit-content;}
  .phone{width:100%; background:#0b141a; border-radius:24px; padding:14px; color:#e9edef; box-shadow:var(--shadow); overflow:hidden;}
  .wh-chat{background:#202c33; border-radius:14px; padding:12px; margin-bottom:12px; overflow:hidden;}
  .wh-bubble{background:#005c4b; padding:10px 12px; border-radius:12px; line-height:1.4; font-size:13px;}
  .carousel-mini{display:grid; gap:10px; margin-top:10px;}
  .mini-card{display:grid; grid-template-columns:56px 1fr; gap:10px; background:#111b21; border:1px solid #233138; border-radius:12px; padding:8px;}
  .mini-card img{width:56px; height:56px; object-fit:cover; border-radius:8px; background:#334155;}
  .mini-title{font-size:12px; color:#e9edef; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
  .mini-sub{font-size:11px; color:#9ca3af; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
  .mini-actions{display:flex; gap:6px; margin-top:6px; flex-wrap:wrap;}
  .btn-mini{display:inline-block; font-size:11px; padding:6px 10px; border-radius:8px; background:#0d3a5a; border:1px solid #1f4f78; color:#cfe7ff; text-decoration:none;}
  .btn-mini.ghost{background:#1f2c34; border-color:#2b3943; color:#cbd5e1;}
  .quality li{margin:6px 0;}
  .warn{color:var(--warn)}
  .success{color:var(--ok)}
</style>

<div class="page-wrap">
  {{-- =============== LEFT: BUILDER =============== --}}
  <div>
    {{-- SUCCESS --}}
    @if (session('wa_create_success'))
      @php($s = session('wa_create_success'))
      <div class="alert alert-success d-flex align-items-start" role="alert">
        <div>
          <div class="font-weight-600 mb-1">Template created successfully 🎉</div>
          <div>Name: <code>{{ $s['template_name'] }}</code></div>
          <div>ID: <code>{{ $s['id'] }}</code></div>
          <div>Status: <span class="badge badge-success" style="width:auto">{{ $s['status'] }}</span></div>
          <div>Category: {{ $s['category'] }}</div>
        </div>
      </div>
    @endif

    {{-- ERROR --}}
    @if (session('wa_create_error'))
      @php($e = session('wa_create_error'))
      <div class="alert alert-danger" role="alert">
        <div class="font-weight-600 mb-1">Template creation failed</div>
        <div>{{ $e['message'] ?? 'Something went wrong.' }}</div>
        @if(!empty($e['debug']))
          <details class="mt-2">
            <summary>Details</summary>
            <pre class="mb-0" style="white-space: pre-wrap;">{{ json_encode($e['debug'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
          </details>
        @endif
      </div>
    @endif

    <div class="meta-card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <div>
          <div class="h6">Create WhatsApp Media-Card Carousel</div>
          <div class="subtle">Define template meta, body and up to 10 cards.</div>
        </div>
        <a href="{{ route('wa.carousel.create-template.form') }}" class="btn btn-ghost">Reset</a>
      </div>

      <div class="card-body">
        <form action="{{ route('wa.carousel.create-template') }}" method="POST" id="tplForm" novalidate>
          @csrf

          {{-- Meta --}}
          <div class="group">
            <label>Template name</label>
            <div>
              <input type="text" name="name" class="form-control" placeholder="mb_media_carousel_v{{ date('Ymd_His') }}" value="{{ old('name') }}">
              <div class="subtle mt-1">Lowercase, numbers & underscores only.</div>
            </div>
          </div>

          <div class="group">
            <label>Language & Header</label>
            <div class="d-grid" style="grid-template-columns:1fr 1fr; gap:12px;">
              <select name="language" class="form-control">
                <option value="en" selected>English (en)</option>
                <option value="hi">Hindi (hi)</option>
              </select>
              <select name="header_format" class="form-control" id="headerFormat">
                <option value="IMAGE" selected>Header: IMAGE</option>
                <option value="VIDEO">Header: VIDEO</option>
              </select>
            </div>
          </div>

          <div class="divider"></div>

          {{-- Top Body --}}
          <div class="group">
            <label>Top Body</label>
            <div>
              <textarea name="body" id="topBody" rows="3" class="form-control"
                placeholder="Hi @{{1}} @{{2}}, explore today's featured tools and offers curated just for you.">{{ old('body') }}</textarea>
              <div class="subtle mt-1">
                Use variables like <code>@{{1}}</code>, <code>@{{2}}</code>. (The <code>@</code> only escapes Blade.)
              </div>
            </div>
          </div>

          <div class="group">
            <label>Top Examples</label>
            <div id="top-body-examples">
              <div class="input-group mb-2 example-row">
                <div class="input-group-prepend"><span class="input-group-text">Example {1}</span></div>
                <input type="text" name="body_example[]" class="form-control" placeholder="e.g. Burhan" value="{{ old('body_example.0') }}">
              </div>
              <div class="input-group mb-2 example-row">
                <div class="input-group-prepend"><span class="input-group-text">Example {2}</span></div>
                <input type="text" name="body_example[]" class="form-control" placeholder="e.g. Immani" value="{{ old('body_example.1') }}">
              </div>
              <button type="button" class="btn btn-ghost" id="add-top-example">+ Add another</button>
              <div class="subtle mt-1">Labels increment automatically: {3}, {4}, …</div>
            </div>
          </div>

          <div class="divider"></div>

          {{-- Cards header --}}
          <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="h6 mb-0">Cards</div>
            <div class="d-flex align-items-center gap-2">
              <span class="counter-badge" id="card-count">0 / 10</span>
              <button type="button" class="btn btn-primary btn-sm" id="add-card">+ Add card</button>
            </div>
          </div>

          <div id="cards-wrapper"></div>

          <div class="divider"></div>

          <div class="d-flex align-items-center justify-content-end">
            <button type="submit" class="btn btn-primary">Submit for approval</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  {{-- =============== RIGHT: PREVIEW =============== --}}
  <aside class="preview-pane">
    <div class="meta-card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <div class="h6">Live Preview</div>
        <span class="subtle">Simulated</span>
      </div>
      <div class="card-body">
        <div class="phone">
          <div class="wh-chat">
            <div class="wh-bubble" id="preview-top">Hi @{{1}} @{{2}}, …</div>
            <div class="carousel-mini" id="preview-cards"></div>
          </div>
          <div class="subtle">Images load from your links. Buttons are a visual preview only.</div>
        </div>
      </div>
    </div>

    <div class="meta-card" style="margin-top:14px;">
      <div class="card-header"><div class="h6">Quality checks</div></div>
      <div class="card-body">
        <ul class="quality subtle" id="quality-panel" style="margin:0; padding-left:18px;">
          <li>We’ll highlight common pitfalls here.</li>
        </ul>
      </div>
    </div>
  </aside>
</div>

{{-- Card template --}}
<template id="card-template">
  <div class="meta-card card-item" data-index="__INDEX__">
    <div class="card-header d-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-2">
        <span style="cursor:grab; color:#9ca3af;">☰</span>
        <strong>Card #<span class="card-number">__HUMAN__</span></strong>
      </div>
      <div>
        <button type="button" class="btn btn-ghost btn-sm move-up">↑</button>
        <button type="button" class="btn btn-ghost btn-sm move-down">↓</button>
        <button type="button" class="btn btn-danger btn-sm remove-card">Remove</button>
      </div>
    </div>
    <div class="card-body">
      <div class="group">
        <label>Header media link</label>
        <div>
          <input type="url" class="form-control" name="cards[__INDEX__][header_link]" placeholder="https://example.com/image.jpg">
          <div class="subtle mt-1">Public URL. We upload to a 4:: handle in submission.</div>
        </div>
      </div>

      <div class="group">
        <label>Card body text</label>
        <div>
          <textarea class="form-control card-body-text" rows="2" name="cards[__INDEX__][body_text]" placeholder="Product highlight: @{{1}} — reliable performance. Price @{{2}} including GST."></textarea>
          <div class="subtle mt-1">Use variables like <code>@{{1}}</code>, <code>@{{2}}</code>. We’ll auto-add example fields.</div>
        </div>
      </div>

      <div class="group">
        <label>Card body example(s)</label>
        <div class="examples" data-card-examples>
          <div class="input-group mb-2 ex-row">
            <div class="input-group-prepend"><span class="input-group-text">Example {1}</span></div>
            <input type="text" class="form-control" name="cards[__INDEX__][body_example][]" placeholder="e.g. Precision finishing">
          </div>
          <div class="input-group mb-2 ex-row">
            <div class="input-group-prepend"><span class="input-group-text">Example {2}</span></div>
            <input type="text" class="form-control" name="cards[__INDEX__][body_example][]" placeholder="e.g. ₹1499">
          </div>
          <div class="subtle mt-1">We’ll ensure the count matches the variables present in your card body.</div>
        </div>
      </div>

      <div class="group">
        <label>URL button example</label>
        <div>
          <input type="url" class="form-control" name="cards[__INDEX__][url_btn_example]" placeholder="https://mazingbusiness.com/product/xtrive-angle-grinder-dw801">
          <div class="subtle mt-1">Example must be a full URL (with https). The template uses <code>https://.../product/@{{1}}</code>.</div>
        </div>
      </div>

      <div class="group" style="margin-bottom:0">
        <label>Quick reply text (optional)</label>
        <div>
          <input type="text" class="form-control" name="cards[__INDEX__][quick_reply_text]" placeholder="Interested" value="Interested">
        </div>
      </div>
    </div>
  </div>
</template>

@verbatim
<script>
(function(){
  const topBody = document.getElementById('topBody');
  const topExWrap = document.getElementById('top-body-examples');
  const addTopExBtn = document.getElementById('add-top-example');
  const headerFormat = document.getElementById('headerFormat');

  const wrapper = document.getElementById('cards-wrapper');
  const tplHtml = document.getElementById('card-template').innerHTML;
  const addCardBtn = document.getElementById('add-card');
  const cardCount = document.getElementById('card-count');

  const previewTop = document.getElementById('preview-top');
  const previewCards = document.getElementById('preview-cards');
  const qualityPanel = document.getElementById('quality-panel');

  let idx = 0, maxCards = 10;

  const debounce = (fn, ms)=>{ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn.apply(this,a), ms);}};

  function escapeRx(s){ return s.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'); }
  function rAll(str, find, rep){ return str.replace(new RegExp(escapeRx(find),'g'), rep); }

  function updateCardCount(){
    const n = wrapper ? wrapper.querySelectorAll('.card-item').length : 0;
    if (cardCount) cardCount.textContent = `${n} / ${maxCards}`;
  }

  // ==== helpers for variables ====
  function findVarNums(s){
    const nums = new Set();
    const re = /\{\{\s*(\d+)\s*\}\}|\{\s*(\d+)\s*\}/g;
    let m;
    while ((m = re.exec(s||'')) !== null){
      const n = parseInt(m[1] || m[2], 10);
      if (!isNaN(n)) nums.add(n);
    }
    return Array.from(nums).sort((a,b)=>a-b);
  }
  function replaceVar(text, idx, value){
    let out = (text || '');
    out = out.replace(new RegExp('\\{\\{\\s*' + idx + '\\s*\\}\\}', 'g'), value);
    out = out.replace(new RegExp('\\{\\s*'  + idx + '\\s*\\}', 'g'), value);
    return out;
  }

  function ensureExampleRows(exWrap, index, varNums){
    if (!exWrap) return;
    // current rows:
    const rows = exWrap.querySelectorAll('.ex-row');
    const need = (varNums.length ? Math.max.apply(null, varNums) : 0);
    const have = rows.length;

    // add missing
    for (let i = have+1; i <= need; i++){
      const row = document.createElement('div'); row.className = 'input-group mb-2 ex-row';
      row.innerHTML =
        '<div class="input-group-prepend"><span class="input-group-text">Example {'+i+'}</span></div>' +
        '<input type="text" class="form-control" name="cards['+index+'][body_example][]" placeholder="Value for {'+i+'}">';
      exWrap.insertBefore(row, exWrap.querySelector('.subtle'));
    }

    // relabel if count decreased
    const rows2 = exWrap.querySelectorAll('.ex-row');
    rows2.forEach((r, i)=>{
      const label = r.querySelector('.input-group-text');
      if (label) label.textContent = 'Example {'+(i+1)+'}';
      // if too many rows compared to need, remove extras at end
      if (i+1 > need) r.remove();
    });
  }

  function addCard(prefill={}){
    if (!wrapper || wrapper.children.length >= maxCards) return;
    let html = tplHtml;
    html = rAll(html, '__INDEX__', idx+'');
    html = rAll(html, '__HUMAN__', (idx+1)+'');

    const node = document.createElement('div'); node.innerHTML = html;
    const el = node.firstElementChild;

    const set = (sel,val)=>{ const x=el.querySelector(sel); if(x && val!=null) x.value=val; };
    set(`[name="cards[${idx}][header_link]"]`, prefill.header_link);
    set(`[name="cards[${idx}][body_text]"]`, prefill.body_text);
    const exWrap = el.querySelector('[data-card-examples]');
    if (exWrap && Array.isArray(prefill.body_examples)) {
      const inputs = exWrap.querySelectorAll('input[name="cards['+idx+'][body_example][]"]');
      inputs.forEach((inp,i)=>{ if(prefill.body_examples[i]!=null) inp.value=prefill.body_examples[i]; });
    }
    set(`[name="cards[${idx}][url_btn_example]"]`, prefill.url_btn_example);
    const qrSel = `[name="cards[${idx}][quick_reply_text]"]`;
    const qrInput = el.querySelector(qrSel);
    if (qrInput) qrInput.value = (prefill.quick_reply_text != null ? prefill.quick_reply_text : 'Interested');

    // actions
    el.querySelector('.remove-card').addEventListener('click', ()=>{ el.remove(); renumber(); updateCardCount(); renderPreview(); });
    el.querySelector('.move-up').addEventListener('click', ()=>{ const prev=el.previousElementSibling; if(prev){ wrapper.insertBefore(el, prev); renumber(); renderPreview(); }});
    el.querySelector('.move-down').addEventListener('click', ()=>{ const next=el.nextElementSibling; if(next){ wrapper.insertBefore(next, el); renumber(); renderPreview(); }});
    el.addEventListener('input', debounce(renderPreview, 120));

    // keep example rows in sync with var count
    const bodyTa = el.querySelector('.card-body-text');
    const syncExamples = ()=>{
      const nums = findVarNums(bodyTa.value);
      ensureExampleRows(exWrap, idx, nums);
    };
    if (bodyTa){ bodyTa.addEventListener('input', debounce(syncExamples, 120)); syncExamples(); }

    wrapper.appendChild(el);
    idx++; updateCardCount(); renderPreview();
  }

  function renumber(){
    if (!wrapper) return;
    wrapper.querySelectorAll('.card-item').forEach((el, i)=>{
      const no = el.querySelector('.card-number');
      if (no) no.textContent = (i+1);
    });
  }

  if (addTopExBtn && topExWrap) {
    addTopExBtn.addEventListener('click', ()=>{
      const rows = topExWrap.querySelectorAll('.example-row').length;
      const next = rows + 1;
      const group = document.createElement('div');
      group.className = 'input-group mb-2 example-row';
      group.innerHTML = `
        <div class="input-group-prepend"><span class="input-group-text">Example {${next}}</span></div>
        <input type="text" name="body_example[]" class="form-control" placeholder="Value for {${next}}">
      `;
      topExWrap.insertBefore(group, addTopExBtn);
      renderPreview();
    });
  }

  function renderPreview(){
    // Top body resolve
    const txt = (topBody && topBody.value) ? topBody.value : 'Hi {{1}} {{2}}, …';
    const exInputs = topExWrap ? topExWrap.querySelectorAll('input[name="body_example[]"]') : [];
    let resolved = txt;
    exInputs.forEach((inp, i)=>{
      const n = i+1;
      resolved = replaceVar(resolved, n, (inp.value || `{{${n}}}`));
    });
    if (previewTop) previewTop.textContent = resolved;

    // Cards
    if (previewCards) previewCards.innerHTML = '';
    const cards = wrapper ? Array.from(wrapper.querySelectorAll('.card-item')) : [];
    cards.forEach((card)=>{
      const link = card.querySelector('input[name*="[header_link]"]')?.value || '';
      const body = card.querySelector('textarea[name*="[body_text]"]')?.value || '';
      const url  = card.querySelector('input[name*="[url_btn_example]"]')?.value || '';
      const qr   = (card.querySelector('input[name*="[quick_reply_text]"]')?.value || 'Interested').trim();

      // collect examples in order
      const exInputs = card.querySelectorAll('input[name*="[body_example][]"]');
      let resolvedBody = body;
      exInputs.forEach((inp, i)=>{
        const n = i+1;
        resolvedBody = replaceVar(resolvedBody, n, (inp.value || `{{${n}}}`));
      });

      const mini = document.createElement('div'); mini.className = 'mini-card';
      const img = document.createElement('img'); img.src = link || 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2256%22 height=%2256%22/%3E';
      const text = document.createElement('div');

      const title = document.createElement('div'); title.className='mini-title'; title.textContent = resolvedBody || 'Card body…';
      const sub = document.createElement('div'); sub.className='mini-sub';
      sub.textContent = `${(headerFormat && headerFormat.value==='VIDEO')?'Video':'Image'} header`;
      const actions = document.createElement('div'); actions.className='mini-actions';

      const viewBtn = document.createElement('a');
      viewBtn.className = 'btn-mini';
      viewBtn.href = url || 'javascript:void(0)';
      viewBtn.target = '_blank';
      viewBtn.rel = 'noopener';
      viewBtn.textContent = 'View';
      actions.appendChild(viewBtn);

      const qrBtn = document.createElement('span');
      qrBtn.className = 'btn-mini ghost';
      qrBtn.textContent = qr || 'Interested';
      actions.appendChild(qrBtn);

      text.appendChild(title);
      text.appendChild(sub);
      text.appendChild(actions);

      mini.appendChild(img);
      mini.appendChild(text);
      if (previewCards) previewCards.appendChild(mini);
    });

    qualityScan();
  }

  function qualityScan(){
    if (!qualityPanel) return;
    const issues = [];
    const top = (topBody && topBody.value) ? topBody.value : '';
    const varCount = (top.match(/\{\{\s*\d+\s*\}\}/g)||[]).length;
    const wordCount = (top.replace(/\{\{\s*\d+\s*\}\}/g,'').trim().split(/\s+/).filter(Boolean).length);
    if (varCount>0 && wordCount < varCount*2) {
      issues.push('<span class="warn">Top body may hit “parameters words ratio” — add more static words.</span>');
    }
    const cards = wrapper ? Array.from(wrapper.querySelectorAll('.card-item')) : [];
    cards.forEach((el, i)=>{
      const body = el.querySelector('textarea[name*="[body_text]"]')?.value || '';
      const nums = findVarNums(body);
      const exInputs = el.querySelectorAll('input[name*="[body_example][]"]');
      if (nums.length && exInputs.length < Math.max.apply(null, nums)){
        issues.push(`Card #${i+1}: add examples for all variables {1..${Math.max.apply(null, nums)}}.`);
      }
      const url = el.querySelector('input[name*="[url_btn_example]"]')?.value || '';
      if (url && !/^https?:\/\//i.test(url)){
        issues.push(`Card #${i+1}: URL example should start with http(s).`);
      }
    });
    qualityPanel.innerHTML = issues.length
      ? `<ul style="margin:0; padding-left:18px; font-size:13px;">${issues.map(i=>`<li>${i}</li>`).join('')}</ul>`
      : `<div class="success">Looks good. You’re ready to submit for approval.</div>`;
  }

  // Add card btn
  if (addCardBtn) addCardBtn.addEventListener('click', ()=> addCard());

  // Seed 2 cards (now with two variables)
  addCard({
    header_link: 'https://mazingbusiness.com/public/uploads/all/fRDJWDaZZqUjETWXHtdejKAmf2voEAXvUIUUeA8u.jpg',
    body_text: 'Product highlight: {{1}} — reliable performance. Price {{2}} including GST.',
    body_examples: ['Precision finishing', '₹1499'],
    url_btn_example: 'https://mazingbusiness.com/product/xtrive-angle-grinder-dw801',
    quick_reply_text: 'Interested'
  });
  addCard({
    header_link: 'https://mazingbusiness.com/public/uploads/all/sAdom0t5BxZBEnzvwYJsm3D3XHgxkxTJ8rpPuwEj.jpg',
    body_text: 'Product highlight: {{1}} — reliable performance. Price {{2}} including GST.',
    body_examples: ['Heavy-duty use', '₹1799'],
    url_btn_example: 'https://mazingbusiness.com/product/xtrive-angle-grinder-dw802',
    quick_reply_text: 'Interested'
  });

  // Live hooks
  if (topBody) topBody.addEventListener('input', debounce(renderPreview, 120));
  if (headerFormat) headerFormat.addEventListener('change', renderPreview);

  updateCardCount();
  renderPreview();
})();
</script>
@endverbatim
@endsection

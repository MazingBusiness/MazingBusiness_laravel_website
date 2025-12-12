{{-- resources/views/backend/marketing/whatsapp_carousel/send.blade.php --}}
@extends('backend.layouts.app')

@section('content')
<style>
  /* ===================== PREMIUM WHATSAPP THEME (CSS-only) ===================== */
  :root{
    /* Brand & UI */
    --wa-green:#25d366;
    --wa-green-600:#1cbc5b;
    --wa-green-700:#16a34a;
    --ink:#0f172a;
    --ink-2:#1f2937;
    --muted:#6c757d;
    --line:#e7eef6;

    /* Dark preview (WhatsApp-like) */
    --wa-dark:#111b21;
    --wa-dark-2:#0b141a;
    --wa-dark-3:#1f2c34;
    --wa-ink:#e9edef;

    --radius:16px;
    --radius-md:12px;
    --radius-sm:10px;

    --shadow-lg:0 24px 60px rgba(7, 89, 133, .12);
    --shadow:0 14px 36px rgba(15, 23, 42, .12);
    --shadow-soft:0 8px 22px rgba(15, 23, 42, .08);
  }

  /* Page canvas */
  body{ background:linear-gradient(180deg, #f7fbff 0%, #f5faf7 100%); }
  .wa-page {max-width:1280px; margin:0 auto; padding-bottom:14px;}
  .wa-layout {display:grid; grid-template-columns: 1fr 360px; gap:24px;}
  @media (max-width: 1100px){ .wa-layout {grid-template-columns: 1fr;} }
  .wa-sticky {position: sticky; top:16px}

  /* ===== Sections / shells ===== */
  .card { border-radius: var(--radius); border:1px solid var(--line); box-shadow: var(--shadow-soft); overflow: hidden; }
  .card-header{ background:linear-gradient(180deg, #ffffff 0%, #f6fbff 100%); border-bottom:1px solid var(--line); padding:14px 18px !important; }
  .card-body{ padding:18px; }

  .wa-section{
    border:1px dashed var(--line);
    border-radius:var(--radius-md);
    padding:16px;
    background:#fff;
    margin-bottom:18px
  }
  .wa-label{font-weight:800;margin-bottom:6px;color:#0b1220}
  .wa-help{font-size:12px;color:var(--muted)}
  .wa-badge{
    display:inline-flex; align-items:center; gap:8px;
    padding:4px 10px; border-radius:999px; background:#f1f7ff; border:1px solid #e3edf9; color:#1f3a8a;
    font-size:12px; font-weight:800
  }

  /* ===== Form inputs ===== */
  .form-control{
    border-radius:12px !important;
    border:1px solid #e5edf6;
    transition: box-shadow .15s ease, border-color .15s ease;
  }
  .form-control:focus{
    border-color: #bfead1;
    box-shadow:0 0 0 .15rem rgba(37,211,102,.18);
  }
  .input-group-text{border-top-left-radius:12px;border-bottom-left-radius:12px}

  /* ===== Buttons ===== */
  .btn-primary{
    background:linear-gradient(180deg, var(--wa-green) 0%, var(--wa-green-600) 100%) !important;
    border:none !important;
    color:#052b18 !important;
    font-weight:900; letter-spacing:.2px;
    border-radius:12px; padding:.6rem 1rem;
    box-shadow:0 12px 26px rgba(37,211,102,.22);
  }
  .btn-primary:hover{ filter:brightness(.96); color:#052b18 !important }
  .btn-soft-secondary{
    background:#f4f6f9; border:1px solid var(--line); color:#111; border-radius:12px;
  }

  /* ===== Cards (per product) ===== */
  .wa-card-form{
    border:1px solid #e9edf2; border-radius:var(--radius-md); padding:14px; margin-bottom:14px;
    background:linear-gradient(180deg, #fbfcfd 0%, #ffffff 100%);
  }
  .wa-card-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
  .wa-card-title{font-weight:900; letter-spacing:.2px; color:#0b1220}
  .wa-grid{display:grid;grid-template-columns:1fr 1fr; gap:12px}
  @media (max-width: 992px){ .wa-grid{grid-template-columns:1fr} }

  /* ===== Phone preview card ===== */
  .wa-phone-card{border:1px solid var(--line); border-radius:var(--radius); background:#fff; box-shadow:var(--shadow)}
  .wa-phone-head{
    padding:12px 14px; border-bottom:1px solid var(--line);
    background:linear-gradient(180deg, #ffffff 0%, #f6fbff 100%);
    font-weight:800; color:#0b1220;
  }
  .wa-phone-pad{padding:14px}

  /* ===== Phone (WhatsApp-like) ===== */
  .wa-phone{
    width:100%;
    color:var(--wa-ink);
    border-radius:22px;
    padding:12px 12px 18px;
    background:
      radial-gradient(220px 180px at 100% -10%, rgba(37,211,102,.16), transparent 60%),
      radial-gradient(240px 220px at -10% 120%, rgba(16,185,129,.16), transparent 60%),
      linear-gradient(180deg, #0c141a 0%, #0b141a 100%);
    border:1px solid rgba(255,255,255,.06);
    box-shadow:0 30px 60px rgba(0,0,0,.2), inset 0 0 0 1px rgba(255,255,255,.04);
    overflow:hidden;
  }
  .wa-top-bubble{
    background:#075e54; /* WA-ish */
    border-radius:12px; padding:10px 12px; margin:8px 0 16px;
    font-weight:700; color:#d6ffee;
    box-shadow:inset 0 0 0 1px rgba(255,255,255,.05);
  }
  .wa-carousel-card{
    display:flex; gap:10px; align-items:flex-start;
    background:#0b1119; border:1px solid #1c2835;
    border-radius:12px; padding:10px; color:#d5dde6; margin-bottom:10px;
    box-shadow:inset 0 0 0 1px rgba(255,255,255,.03);
  }
  .wa-thumb{ width:54px; height:54px; border-radius:10px; background:#233041; flex:0 0 54px; object-fit:cover; border:1px solid #2f4050 }
  .wa-ctext{flex:1}
  .wa-ctitle{
    font-weight:900; line-height:1.25rem; max-height:2.5rem; overflow:hidden;
    display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; word-break:break-word;
  }
  .wa-cta-row{margin-top:6px}
  .wa-cta{
    display:inline-block; font-size:12px; padding:7px 12px; margin-right:8px; border-radius:999px;
    background:#16222a; border:1px solid #2b3b45; color:#cfe7ff; text-decoration:none; font-weight:800;
    box-shadow:inset 0 0 0 1px rgba(255,255,255,.03);
  }

  /* Keep long product names tidy in bootstrap-select */
  .bootstrap-select>.dropdown-toggle .filter-option-inner-inner{
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:block; max-width:100%;
  }
  .bootstrap-select .dropdown-menu li a span.text{ white-space:normal; line-height:1.3; }

  /* Status area */
  #wa-status.alert { margin-bottom: 16px; border-radius:12px }
  .part-status{ font-size:12px; margin-top:6px; }
  .part-status.ok{ color:#0a7a3d; }
  .part-status.err{ color:#b00020; }
</style>

<div class="wa-page">
  <div class="wa-layout">
    {{-- ================= LEFT: FORM ================= --}}
    <div>
      <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
          <h5 class="mb-0 h6">Send WhatsApp Media-Card Carousel (Total users: {{$totalCustomers}})</h5>
          <a href="{{ route('wa.carousel.send.form') }}" class="btn btn-soft-secondary btn-sm">Reset</a>
        </div>

        <div class="card-body">
          @if (session('wa_send_ok'))    <div class="alert alert-success">{{ session('wa_send_ok') }}</div> @endif
          @if (session('wa_send_error')) <div class="alert alert-danger">{{ session('wa_send_error') }}</div> @endif

          {{-- Status area for AJAX --}}
          <div id="wa-status" class="alert" style="display:none"></div>

          <form action="{{ route('wa.carousel.send.ajax') }}" method="POST" id="wa-send-form">
            @csrf
            <input type="hidden" name="language" id="tpl_language" value="en">
            <input type="hidden" name="header_format" value="image">

            {{-- ========= Filters: Branch → Manager → State ========= --}}
            <div class="wa-section">
              <div class="row">
                <div class="col-lg-4">
                  <div class="wa-label">Branch (Warehouse)</div>
                  <select id="branch-select" name="warehouse_id" class="form-control aiz-selectpicker" data-live-search="true">
                    <option value="">— Select Branch —</option>
                    @foreach ($warehouses ?? [] as $w)
                      <option value="{{ $w->id }}">{{ $w->name }}</option>
                    @endforeach
                  </select>
                  <div class="wa-help mt-1">Pick a branch to load its managers.</div>
                </div>

                <div class="col-lg-4">
                  <div class="wa-label">Manager</div>
                  <select id="manager-select" name="manager_id" class="form-control aiz-selectpicker" data-live-search="true">
                    <option value="">— Select Manager —</option>
                  </select>
                  <div class="wa-help mt-1">If blank, all users in branch will be targeted.</div>
                </div>

                <div class="col-lg-4">
                  <div class="wa-label">State</div>
                  <select id="state-select" name="state" class="form-control aiz-selectpicker" data-live-search="true" disabled>
                    <option value="">— Select State —</option>
                  </select>
                  <div class="wa-help mt-1">Distinct customer states under the selected manager (with counts).</div>
                </div>
              </div>
            </div>

            {{-- Template + optional single recipient --}}
            <div class="wa-section">
              <div class="row">
                <div class="col-lg-8">
                  <div class="wa-label">Template</div>
                  <select id="template_name" name="template_name" class="form-control aiz-selectpicker" data-live-search="true" required>
                    @forelse ($templates as $i => $t)
                      <option value="{{ $t['name'] }}" data-index="{{ $i }}">
                        {{ $t['name'] }} ({{ $t['language'] }}) — {{ (int)($t['card_count'] ?? 0) }} {{ (int)($t['card_count'] ?? 0) === 1 ? 'card' : 'cards' }}
                      </option>
                    @empty
                      <option value="">No APPROVED carousel templates available</option>
                    @endforelse
                  </select>
                  <div class="wa-help mt-1">Only APPROVED templates with at least 1 card are listed.</div>
                </div>
                <div class="col-lg-4">
                  <div class="wa-label">Send To (E.164)</div>
                  <input type="text" class="form-control" name="to" placeholder="+91XXXXXXXXXX">
                  <div class="wa-help mt-1">Optional if you are sending to filtered users.</div>
                </div>
              </div>
            </div>

            {{-- Top BODY Variables --}}
            <div class="wa-section">
              <div class="d-flex align-items-center justify-content-between">
                <div class="wa-label mb-0">Top Body Variables</div>
                <span id="top-body-var-count" class="wa-badge">0 vars</span>
              </div>
              <div id="top-vars" class="mt-2"></div>
              <div class="wa-help">
                Enter static text or DB fields like
                <code>users.name</code>, <code>addresses.company_name</code>,
                and <code>users.manager_id</code> <em>(this injects the manager’s phone)</em>.
                Values are resolved per recipient at send time.
              </div>
            </div>

            {{-- Cards (Part No → auto-fill Name, Slug, Image, Price) --}}
            <div class="wa-section">
              <div class="d-flex align-items-center justify-content-between">
                <div class="wa-label mb-0">Cards</div>
                <span id="cards-count" class="wa-badge">0 cards</span>
              </div>
              <div id="cards-wrapper" class="mt-2"></div>
              <div class="wa-help">
                Type a product <strong>Part No</strong> for each card. We’ll set:
                <ul class="mb-0 mt-1">
                  <li><em>BODY {{ '{' }}1{{ '}' }}</em> = product name</li>
                  <li><em>BODY {{ '{' }}2{{ '}' }}</em> = price (source field below). Use <code>products.mrp</code> to auto-compute <strong>MRP minus user discount%</strong>.</li>
                  <li><em>URL {{ '{' }}1{{ '}' }}</em> = product slug (e.g. <code>xtrive-angle-grinder-dw801</code>)</li>
                  <li><em>Media Link</em> = product thumbnail</li>
                </ul>
              </div>
            </div>

            <div class="text-right">
              <button type="submit" class="btn btn-primary" id="wa-submit-btn">
                <span class="btn-text">Send</span>
                <span class="spinner-border spinner-border-sm" id="wa-submit-spin" style="display:none"></span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    {{-- ================= RIGHT: STICKY PREVIEW ================= --}}
    <div class="wa-sticky">
      <div class="wa-phone-card">
        <div class="wa-phone-head">Live Preview</div>
        <div class="wa-phone-pad">
          <div class="wa-phone">
            <div id="preview-top-body" class="wa-top-bubble">—</div>
            <div id="preview-cards"></div>
          </div>
          <div class="wa-help mt-2">Visual aid only — final rendering is WhatsApp-side.</div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Templates JSON to JS --}}
<script id="wa-templates-json" type="application/json">
{!! json_encode($templates ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}
</script>

{{-- AJAX routes --}}
<script>
  window.WA_ROUTES = {
    managersByWarehouse: "{{ route('wa.getManagersByWarehouse') }}",
    statesByManager:     "{{ route('wa.getStatesByManager') }}",
    productByPartNo:     "{{ route('wa.find.product.by.partno') }}",
    sendAjax:            "{{ route('wa.carousel.send.ajax') }}",
    dispatchGroup:       "{{ route('wa.carousel.dispatch') }}"
  };
</script>

@verbatim
<script>
(function(){
  // ===== Utils =====
  function countVars(txt){
    if(!txt) return 0;
    var dbl = txt.match(/\{\{\s*\d+\s*\}\}/g); if (dbl && dbl.length) return dbl.length; // {{1}}
    var sgl = txt.match(/\{\s*\d+\s*\}/g);     return sgl ? sgl.length : 0;               // {1}
  }
  function replaceVar(text, idx, value){
    var out = (text || '');
    out = out.replace(new RegExp('\\{\\{\\s*' + idx + '\\s*\\}\\}', 'g'), value);
    out = out.replace(new RegExp('\\{\\s*'  + idx + '\\s*\\}', 'g'), value);
    return out;
  }
  function refreshPicker(el){
    if (typeof $ !== 'undefined' && $.fn.selectpicker) { $(el).selectpicker('refresh'); }
  }
  function formatINR(n){
    var x = parseFloat(n);
    if (isNaN(x)) return '';
    return '₹' + Math.round(x).toString();
  }

  // ===== Data =====
  var templates = [];
  try { templates = JSON.parse(document.getElementById('wa-templates-json').textContent || '[]'); }
  catch(e){ templates = []; }

  // ===== DOM =====
  var sel        = document.getElementById('template_name');
  var langInput  = document.getElementById('tpl_language');
  var topCon     = document.getElementById('top-vars');
  var topBadge   = document.getElementById('top-body-var-count');
  var cardsCon   = document.getElementById('cards-wrapper');
  var cardsBadge = document.getElementById('cards-count');
  var pvTop      = document.getElementById('preview-top-body');
  var pvCards    = document.getElementById('preview-cards');

  var branchSel  = document.getElementById('branch-select');
  var managerSel = document.getElementById('manager-select');
  var stateSel   = document.getElementById('state-select');

  // ===== Branch → Manager =====
  if (branchSel) {
    branchSel.addEventListener('change', function(){
      var warehouseId = this.value;

      // reset manager + state
      managerSel.innerHTML = '<option value="">— Select Manager —</option>';
      stateSel.innerHTML   = '<option value="">— Select State —</option>';
      stateSel.disabled    = true;
      refreshPicker(managerSel);
      refreshPicker(stateSel);

      if (!warehouseId) return;

      fetch(WA_ROUTES.managersByWarehouse + '?warehouse_id=' + encodeURIComponent(warehouseId), {
        method: 'GET', headers: {'X-Requested-With': 'XMLHttpRequest'}
      })
      .then(function(r){ return r.json(); })
      .then(function(list){
        if (!Array.isArray(list)) return;
        list.forEach(function(m){
          var opt = document.createElement('option');
          opt.value = m.id; opt.textContent = m.name;
          managerSel.appendChild(opt);
        });
        refreshPicker(managerSel);
      })
      .catch(function(){});
    });
  }

  // ===== Manager → State (with counts) =====
  if (managerSel) {
    managerSel.addEventListener('change', function(){
      var managerId = this.value;
      stateSel.innerHTML = '<option value="">— Select State —</option>';
      stateSel.disabled  = true;
      refreshPicker(stateSel);

      if (!managerId) return;

      fetch(WA_ROUTES.statesByManager + '?manager_id=' + encodeURIComponent(managerId), {
        method: 'GET', headers: {'X-Requested-With': 'XMLHttpRequest'}
      })
      .then(function(r){ return r.json(); })
      .then(function(states){
        if (!Array.isArray(states)) return;
        states.forEach(function(s){
          var stateName = (typeof s === 'string') ? s : (s.state || '');
          var count     = (typeof s === 'object' && s) ? (s.count || 0) : 0;
          if (!stateName) return;
          var opt = document.createElement('option');
          opt.value = stateName;
          opt.textContent = count ? (stateName + ' (' + count + ')') : stateName;
          stateSel.appendChild(opt);
        });
        stateSel.disabled = false;
        refreshPicker(stateSel);
      })
      .catch(function(){});
    });
  }

  // ===== Template shape parsing =====
  function extractTemplateShape(t){
    var comps = Array.isArray(t.components) ? t.components : [];
    var body  = comps.find(function(c){ return (c.type||'').toUpperCase()==='BODY'; }) || {};
    var car   = comps.find(function(c){ return (c.type||'').toUpperCase()==='CAROUSEL'; }) || {};
    var cards = Array.isArray(car.cards) ? car.cards : [];

    var shapeCards = cards.map(function(card){
      var cc = Array.isArray(card.components) ? card.components : [];
      var bodyC = cc.find(function(c){ return (c.type||'').toUpperCase()==='BODY'; }) || {};
      var btns  = cc.find(function(c){ return (c.type||'').toUpperCase()==='BUTTONS'; }) || {};
      var urlBtn = (Array.isArray(btns && btns.buttons) ? btns.buttons : [])
                    .find(function(b){ return (b.type||'').toUpperCase()==='URL'; });
      return {
        body_text: bodyC.text || '',
        url_text: urlBtn ? (urlBtn.url || '') : ''
      };
    });

    return {
      name: t.name,
      language: t.language || 'en',
      top_body_text: body.text || '',
      cards: shapeCards
    };
  }

  function renderForTemplateIndex(idx){
    var t = templates[idx];
    if (!t){ topCon.innerHTML=''; cardsCon.innerHTML=''; pvTop.textContent='—'; pvCards.innerHTML=''; return; }

    var shape = extractTemplateShape(t);
    langInput.value = (shape.language || 'en');

    // --- Top BODY vars ---
    var topText  = shape.top_body_text;
    var topCount = countVars(topText);
    topCon.innerHTML = '';
    topBadge.textContent = topCount + ' vars';
    for (var i=1;i<=topCount;i++){
      var row = document.createElement('div'); row.className = 'input-group mb-2';
      row.innerHTML =
        '<div class="input-group-prepend"><span class="input-group-text">Var {'+i+'}</span></div>' +
        '<input type="text" class="form-control" name="top_params[]" placeholder="Value or DB field (e.g. users.name, users.manager_id)">';
      topCon.appendChild(row);
    }
    topCon.addEventListener('input', function(){ updatePreview(shape); });

    // --- Cards (Part No → product lookup) ---
    cardsCon.innerHTML = '';
    cardsBadge.textContent = shape.cards.length + ' cards';

    shape.cards.forEach(function(c, i){
      var bodyText = c.body_text || '';
      var hasVar1  = /\{\{\s*1\s*\}\}|\{\s*1\s*\}/.test(bodyText);
      var hasVar2  = /\{\{\s*2\s*\}\}|\{\s*2\s*\}/.test(bodyText);
      var showUrlVar = /\{\{\s*1\s*\}\}|\{\s*1\s*\}/.test(c.url_text||'');

      var card = document.createElement('div');
      card.className = 'wa-card-form';

      var productBlock = '';
      if (hasVar1) {
        productBlock =
          '<div class="mb-2">' +
            '<div class="wa-label">Product Part No</div>' +
            '<input type="text" class="form-control partno-input" placeholder="Enter Part No and press Enter" data-card-index="'+i+'">' +
            '<div class="part-status" data-role="status"></div>' +
          '</div>' +
          '<input type="hidden" name="cards['+i+'][body_params][]" class="body-param-1" value="">' ;
      } else {
        productBlock = '<div class="wa-help">This card body has no {1} variable.</div>';
      }

      var priceBlock = '';
      if (hasVar2) {
        priceBlock =
          '<div class="mt-2">' +
            '<div class="wa-label">BODY {2} (price) source</div>' +
            '<input type="text" class="form-control price-source" name="cards['+i+'][price_source]" placeholder="products.mrp">' +
            '<div class="wa-help mt-1">Use <code>products.mrp</code> to apply MRP − user discount%. Or enter a fixed text like <code>₹1799</code>.</div>' +
          '</div>';
      }

      card.innerHTML =
        '<div class="wa-card-head"><div class="wa-card-title">Card #'+(i+1)+'</div></div>' +
        '<div class="wa-grid">' +
          '<div>' +
            '<div class="wa-label">Media Link (auto from part no)</div>' +
            '<input type="url" class="form-control media-link" name="cards['+i+'][media_link]" placeholder="https://example.com/image.jpg" readonly>' +
            '<div class="wa-help mt-1">Filled with product thumbnail.</div>' +
          '</div>' +
          '<div>' + productBlock +
             '<input type="hidden" class="mrp" name="cards['+i+'][mrp]" value="">' +
             '<input type="hidden" name="cards['+i+'][needs_var2]" value="'+(hasVar2 ? '1':'0')+'">' +
          '</div>' +
        '</div>' +
        priceBlock +
        '<div class="mt-2" style="'+(showUrlVar?'':'display:none')+'">' +
          '<div class="wa-label">URL {{1}} value (auto from slug)</div>' +
          '<input type="text" class="form-control url-param-1" name="cards['+i+'][url_button_param]" placeholder="slug" readonly>' +
        '</div>';

      attachPartNoHandlers(card, i, shape);
      cardsCon.appendChild(card);
    });

    updatePreview(shape);
  }

  function attachPartNoHandlers(cardEl, idx, shape){
    var input  = cardEl.querySelector('.partno-input');
    var status = cardEl.querySelector('[data-role="status"]');
    var media  = cardEl.querySelector('.media-link');
    var urlp   = cardEl.querySelector('.url-param-1');
    var body1  = cardEl.querySelector('.body-param-1');
    var mrp    = cardEl.querySelector('.mrp');

    function setStatusOK(msg){ if(status){ status.textContent=msg; status.className='part-status ok'; } }
    function setStatusERR(msg){ if(status){ status.textContent=msg; status.className='part-status err'; } }

    function lookup(part){
      part = (part || '').trim();
      if (!part){ setStatusERR('Enter a part number.'); return; }
      setStatusOK('Searching…');

      fetch(WA_ROUTES.productByPartNo + '?q=' + encodeURIComponent(part), {
        method:'GET', headers:{'X-Requested-With':'XMLHttpRequest'}
      })
      .then(function(r){ return r.json(); })
      .then(function(j){
        if (!j || j.ok !== true || !j.product){ throw new Error((j && j.message) || 'Not found'); }
        var p = j.product;
        if (media) media.value = p.image || '';
        if (urlp)  urlp.value  = p.slug  || '';
        if (body1) body1.value = p.name  || '';
        if (mrp)   mrp.value   = (p.mrp != null ? p.mrp : '');  // requires API to return mrp

        setStatusOK('Selected: ' + (p.name || '') + ' – ' + (p.slug || '') + (p.mrp ? (' · MRP '+formatINR(p.mrp)) : ''));
        updatePreview(shape);
      })
      .catch(function(err){
        if (media) media.value = '';
        if (urlp)  urlp.value  = '';
        if (body1) body1.value = '';
        if (mrp)   mrp.value   = '';
        setStatusERR(err.message || 'Product not found.');
        updatePreview(shape);
      });
    }

    if (input){
      input.addEventListener('keydown', function(e){
        if (e.key === 'Enter'){
          e.preventDefault();
          lookup(input.value);
        }
      });
      input.addEventListener('blur', function(){
        if (input.value.trim() !== ''){
          lookup(input.value);
        }
      });
    }
  }

  function updatePreview(shape){
    pvCards.innerHTML = '';
    if (!shape){ pvTop.textContent='—'; return; }

    // Top body
    var topText = shape.top_body_text || '';
    var topVals = Array.from(document.querySelectorAll('#top-vars input')).map(function(i){ return (i.value||'').trim(); });
    for (var i=0;i<topVals.length;i++){
      var display = topVals[i] || ('Var'+(i+1));
      topText = replaceVar(topText, i+1, display);
    }
    pvTop.textContent = topText || '—';

    // Cards
    var cardEls = Array.from(document.querySelectorAll('#cards-wrapper .wa-card-form'));
    cardEls.forEach(function(cEl, i){
      var mediaLink   = (cEl.querySelector('.media-link')||{}).value || '';
      var mrp         = parseFloat((cEl.querySelector('.mrp')||{}).value || '') || null;
      var priceSource = (cEl.querySelector('.price-source')||{}).value || '';
      var title = (shape.cards[i] ? shape.cards[i].body_text : '') || '';
      var var1  = (cEl.querySelector('.body-param-1')||{}).value || '';

      if (var1) title = replaceVar(title, 1, var1);

      // preview for {2}: if products.mrp, show MRP (discount is user-specific so preview uses MRP)
      if (/\{\{\s*2\s*\}\}|\{\s*2\s*\}/.test(title)) {
        var priceDisplay = '';
        if ((priceSource||'').trim().toLowerCase()==='products.mrp') {
          priceDisplay = (mrp!=null) ? formatINR(mrp) : '{{2}}';
        } else if (priceSource) {
          priceDisplay = priceSource;
        }
        title = replaceVar(title, 2, priceDisplay || '{{2}}');
      }

      // cleanup any leftover braces
      title = title.replace(/\{\{\s*\d+\s*\}\}/g, '').replace(/\{\s*\d+\s*\}/g, '');

      var imgSrc = mediaLink || 'https://via.placeholder.com/120x120?text=Header';

      var node = document.createElement('div');
      node.className = 'wa-carousel-card';
      node.innerHTML =
        '<img class="wa-thumb" src="'+imgSrc+'" alt="">' +
        '<div class="wa-ctext">' +
          '<div class="wa-ctitle">'+(title || 'Product…')+'</div>' +
          '<div class="wa-cta-row">' +
            '<a href="javascript:void(0)" class="wa-cta">View</a>' +
            '<a href="javascript:void(0)" class="wa-cta">Interested</a>' +
          '</div>' +
        '</div>';
      pvCards.appendChild(node);
    });
  }

  // Init
  var selEl = sel;
  if (selEl && selEl.options.length){
    selEl.addEventListener('change', function(){
      var opt = this.options[this.selectedIndex];
      var idx = parseInt((opt && opt.getAttribute('data-index')) || '0', 10) || 0;
      renderForTemplateIndex(idx);
    });
    var initIdx = parseInt((selEl.options[selEl.selectedIndex] && selEl.options[selEl.selectedIndex].getAttribute('data-index'))||'0',10) || 0;
    renderForTemplateIndex(initIdx);
  }

  // ===== AJAX SUBMIT =====
  var form   = document.getElementById('wa-send-form');
  var submit = document.getElementById('wa-submit-btn');
  var spin   = document.getElementById('wa-submit-spin');
  var status = document.getElementById('wa-status');

  function setBusy(b){
    if (!submit || !spin) return;
    if (b){ submit.disabled = true; spin.style.display='inline-block'; }
    else { submit.disabled = false; spin.style.display='none'; }
  }
  function showStatus(html, klass){
    if (!status) return;
    status.className = 'alert ' + (klass||'alert-info');
    status.innerHTML = html;
    status.style.display = '';
  }

  if (form){
    form.addEventListener('submit', function(e){
      e.preventDefault();
      setBusy(true);
      showStatus('Queuing messages…', 'alert-info');

      var fd = new FormData(form);

      // Step 1: queue inserts
      fetch(WA_ROUTES.sendAjax, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
      .then(function(r){ return r.json(); })
      .then(function(j){
        if (!j || !j.ok){
          throw new Error((j && j.message) || 'Failed to queue.');
        }
        showStatus('Queued <strong>'+j.recipients+'</strong> recipients. Dispatching…', 'alert-success');

        // Step 2: dispatch the group
        var fd2 = new FormData();
        fd2.append('_token', fd.get('_token'));
        fd2.append('group_id', j.group_id);

        return fetch(WA_ROUTES.dispatchGroup, { method:'POST', body: fd2, headers:{'X-Requested-With':'XMLHttpRequest'} });
      })
      .then(function(r){ return r.json(); })
      .then(function(j2){
        if (!j2 || !j2.ok){
          throw new Error((j2 && j2.message) || 'Failed to dispatch.');
        }
        showStatus('Dispatch started for Group <strong>'+ (j2.group_id||'') +'</strong>.', 'alert-success');
      })
      .catch(function(err){
        showStatus(err.message || 'Something went wrong.', 'alert-danger');
      })
      .finally(function(){
        setBusy(false);
      });
    });
  }

})();
</script>
@endverbatim
@endsection

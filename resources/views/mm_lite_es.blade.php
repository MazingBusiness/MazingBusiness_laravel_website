<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>MM Lite Embedded Signup</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;padding:24px;background:#f8fafc}
    .card{max-width:640px;margin:0 auto;background:#fff;border:1px solid #e6e8eb;border-radius:12px;padding:20px}
    .btn{display:inline-block;padding:12px 18px;border-radius:10px;border:0;background:#0866ff;color:#fff;font-weight:600;cursor:pointer;text-decoration:none}
    .muted{color:#596273;font-size:13px;margin-top:10px}
    .row{margin-top:14px}
    code{background:#f6f7f9;padding:1px 6px;border-radius:6px}
  </style>
</head>
<body>
  <div id="fb-root"></div>

  <div class="card">
    <h2>Marketing Messages Lite – Embedded Signup  </h2>
    <p class="muted">
      App: <code>545743998346520</code> • Business: <code>295006418829715</code> • WABA: <code>530229950165776</code>
    </p>

    <div class="row">
      <!-- 1) Direct link (opens business.facebook.com) -->
      <a id="openES" class="btn" href="#" rel="noopener">Open Embedded Signup</a>
      <!-- 2) Fallback link (older path) -->
      <a id="openES2" class="btn" style="background:#334155;margin-left:8px" href="#" rel="noopener">Fallback ES</a>
    </div>

    <div class="row">
      <button class="btn" id="btnStart" style="background:#10b981">Login then Open (JS)</button>
    </div>

    <p class="muted" id="log"></p>
  </div>

  <!-- FB JS SDK -->
  <script>
    // Static IDs
    const APP_ID      = '545743998346520';
    const BUSINESS_ID = '295006418829715';

    // Always force business.facebook.com host
    function buildESUrl(primary=true){
      const base = primary
        ? 'https://business.facebook.com/whatsapp_business/es'
        : 'https://business.facebook.com/whatsapp_business/embedded_signup/';
      const params = new URLSearchParams();
      params.set('app_id', APP_ID);
      params.set('business_id', BUSINESS_ID);
      // URL-encode [0] => features_enabled%5B0%5D
      params.append('features_enabled[0]', 'marketing_messages_lite');
      params.set('feature_type', 'whatsapp');
      return `${base}?${params.toString()}`;
    }

    function log(t){ document.getElementById('log').innerText = t; }

    // Direct anchor clicks (no controller)
    document.getElementById('openES').addEventListener('click', function(e){
      e.preventDefault();
      const url = buildESUrl(true);
      window.location.assign(url); // same tab; prevents browser rewriting
    });
    document.getElementById('openES2').addEventListener('click', function(e){
      e.preventDefault();
      const url = buildESUrl(false);
      window.location.assign(url);
    });

    // FB SDK init
    window.fbAsyncInit = function () {
      FB.init({ appId: APP_ID, autoLogAppEvents: true, xfbml: true, version: 'v24.0' });
    };
    (function(d, s, id){
      let js, fjs = d.getElementsByTagName(s)[0];
      if (d.getElementById(id)) return;
      js = d.createElement(s); js.id = id;
      js.src = "https://connect.facebook.net/en_US/sdk.js";
      fjs.parentNode.insertBefore(js, fjs);
    }(document,'script','facebook-jssdk'));

    // Login then open ES
    document.getElementById('btnStart').addEventListener('click', function(){
      log('Opening Facebook Login...');
      FB.login(function(resp){
        if (!resp || !resp.authResponse){ log('Login cancelled or failed.'); return; }
        log('Login OK. Redirecting to Embedded Signup...');
        // Open primary; if blocked, try fallback
        try { window.location.replace(buildESUrl(true)); }
        catch(e){ window.location.replace(buildESUrl(false)); }
      }, {
        scope: 'public_profile,email,business_management,whatsapp_business_management',
        return_scopes: true
      });
    });
  </script>
</body>
</html>

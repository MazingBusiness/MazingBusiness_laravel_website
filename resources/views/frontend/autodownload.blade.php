<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Preparing your download…</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <style>
    html, body {
      height: 100%;
      margin: 0;
    }
    /* Center container */
    .loader-wrap {
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100%;
      background: #0b0b0b0a; /* subtle tint */
      flex-direction: column;
      gap: 16px;
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    }
    .spinner {
      width: 56px;
      height: 56px;
      border: 4px solid rgba(0,0,0,.15);
      border-top-color: rgba(0,0,0,.6);
      border-radius: 50%;
      animation: spin 0.9s linear infinite;
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
    .loader-text {
      font-size: 14px;
      color: #333;
      opacity: .9;
    }
  </style>

  <script>
    (function () {
      const downloadUrl = @json($downloadUrl);
      const redirectUrl = @json($redirectUrl);
      const ticketId    = @json($ticketId);
      const cookieName  = 'warranty_dl_' + ticketId;

      function hasCookie(name) {
        return document.cookie.split('; ').some(c => c.startsWith(name + '='));
      }

      function start() {
        // fire download in hidden iframe
        var iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = downloadUrl + (downloadUrl.includes('?') ? '&' : '?') + 't=' + Date.now();
        document.body.appendChild(iframe);
        setTimeout(function(){ window.location = redirectUrl; }, 3000);
      }
      window.onload = start;
    })();
  </script>
</head>
<body>
  <div class="loader-wrap" role="status" aria-live="polite" aria-busy="true">
    <div class="spinner" aria-hidden="true"></div>
    <div class="loader-text">Process your download…</div>
  </div>
</body>
</html>
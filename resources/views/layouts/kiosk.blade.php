<!DOCTYPE html>
<html lang="en" data-theme="{{ \App\Models\Setting::current()->dark_mode ? 'dark' : 'light' }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Self-Checkout') — {{ $settings->store_name }}</title>
<link href="https://fonts.googleapis.com/css2?family=Figtree:ital,wght@0,300..900;1,300..900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css">
@vite(['resources/css/app.css', 'resources/js/app.js'])
@php
  $__currencyConfig = [
      'code' => $settings->currency,
      'symbol' => $settings->currency_symbol,
      'rate' => (float) $settings->exchange_rate,
      'decimals' => \App\Support\CurrencyDetector::decimalsFor($settings->currency),
  ];
@endphp
<script>
  (function () {
    var saved = localStorage.getItem('nexus-theme');
    if (saved) document.documentElement.dataset.theme = saved;
  })();
  window.currency = @json($__currencyConfig);
</script>
@stack('head')
</head>
<body>
<div class="bg-pattern"></div>
<div class="app">
  <div class="main">
    <header class="topbar">
      <div class="sidebar-logo" style="margin-right:10px;">{{ mb_substr($settings->store_name, 0, 1) }}</div>
      <div style="font-weight:700;font-family:'Figtree';font-size:16px;">{{ $settings->store_name }}</div>
      <div class="topbar-actions">
        <button class="topbar-btn" id="fullscreenToggle" aria-label="Toggle fullscreen" data-tooltip="Toggle fullscreen"><i class="bx bx-fullscreen"></i></button>
        <button class="topbar-btn" id="themeToggle" aria-label="Toggle theme" data-tooltip="Toggle theme"><i class="bx {{ $settings->dark_mode ? 'bxs-moon' : 'bxs-sun' }}"></i></button>
        <a href="{{ route('login') }}" class="topbar-btn" aria-label="Staff Login" data-tooltip="Staff Login"><i class="bx bx-log-in"></i></a>
      </div>
    </header>

    <div class="content" id="contentArea">
      @yield('content')
    </div>
  </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<div class="modal-overlay" id="confirmModal">
  <div class="modal" style="max-width:400px;">
    <div class="modal-header">
      <h3 id="confirmModalTitle">Please Confirm</h3>
      <button type="button" class="modal-close" id="confirmModalClose" aria-label="Close" data-tooltip="Close"><i class="bx bx-x"></i></button>
    </div>
    <div class="modal-body">
      <p id="confirmModalMessage" style="font-size:13px;color:var(--fg);line-height:1.5;"></p>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" id="confirmModalCancel">Cancel</button>
      <button type="button" class="btn btn-danger" id="confirmModalOk">Confirm</button>
    </div>
  </div>
</div>

@stack('modals')
@stack('scripts')
<script>
  // Kiosks are meant to run fullscreen on an unattended tablet/terminal, but
  // browsers only allow requestFullscreen() from inside a real user gesture —
  // so it can't fire on page load. Catch the customer's very first tap
  // anywhere and use that gesture to go fullscreen automatically; the topbar
  // button covers manually entering/exiting it afterward.
  function isFullscreen() {
    return !!document.fullscreenElement;
  }
  function enterFullscreen() {
    const el = document.documentElement;
    (el.requestFullscreen || el.webkitRequestFullscreen)?.call(el);
  }
  function exitFullscreen() {
    (document.exitFullscreen || document.webkitExitFullscreen)?.call(document);
  }
  function updateFullscreenIcon() {
    const btn = document.getElementById('fullscreenToggle');
    if (!btn) return;
    btn.innerHTML = isFullscreen() ? '<i class="bx bx-exit-fullscreen"></i>' : '<i class="bx bx-fullscreen"></i>';
  }
  document.getElementById('fullscreenToggle')?.addEventListener('click', () => {
    isFullscreen() ? exitFullscreen() : enterFullscreen();
  });
  document.addEventListener('fullscreenchange', updateFullscreenIcon);
  document.addEventListener('click', function autoFullscreen() {
    if (!isFullscreen()) enterFullscreen();
    document.removeEventListener('click', autoFullscreen);
  }, { once: true });
</script>
</body>
</html>

<!DOCTYPE html>
<html lang="en" data-theme="{{ \App\Models\Setting::current()->dark_mode ? 'dark' : 'light' }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Dashboard') — {{ config('app.name') }}</title>
<link href="https://fonts.googleapis.com/css2?family=Figtree:ital,wght@0,300..900;1,300..900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
@php
  $__settings = \App\Models\Setting::current();
  $__currencyConfig = [
      'code' => $__settings->currency,
      'symbol' => $__settings->currency_symbol,
      'rate' => (float) $__settings->exchange_rate,
      'decimals' => \App\Support\CurrencyDetector::decimalsFor($__settings->currency),
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
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-logo">N</div>
      <div class="sidebar-brand">{{ config('app.name') }}</div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section">
        <div class="nav-section-title">Main</div>
        <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}"><i class="bx bxs-dashboard"></i><span>Dashboard</span></a>
        <a href="{{ route('pos.index') }}" class="nav-item {{ request()->routeIs('pos.*') ? 'active' : '' }}"><i class="bx bxs-store-alt"></i><span>POS Terminal</span></a>
        <a href="{{ route('orders.index') }}" class="nav-item {{ request()->routeIs('orders.*') ? 'active' : '' }}"><i class="bx bxs-receipt"></i><span>Orders</span></a>
        <a href="{{ route('cash-register.index') }}" class="nav-item {{ request()->routeIs('cash-register.*') ? 'active' : '' }}"><i class="bx bxs-wallet"></i><span>Cash Register</span></a>
      </div>
      <div class="nav-section">
        <div class="nav-section-title">Management</div>
        <a href="{{ route('products.index') }}" class="nav-item {{ request()->routeIs('products.*') ? 'active' : '' }}"><i class="bx bxs-box"></i><span>Products</span></a>
        <a href="{{ route('customers.index') }}" class="nav-item {{ request()->routeIs('customers.*') ? 'active' : '' }}"><i class="bx bxs-group"></i><span>Customers</span></a>
        @if (auth()->user()->isManager())
        <a href="{{ route('purchases.index') }}" class="nav-item {{ request()->routeIs('purchases.*') ? 'active' : '' }}"><i class="bx bxs-truck"></i><span>Purchasing</span></a>
        <a href="{{ route('stock-adjustments.index') }}" class="nav-item {{ request()->routeIs('stock-adjustments.*') ? 'active' : '' }}"><i class="bx bxs-edit-alt"></i><span>Stock Adjustments</span></a>
        <a href="{{ route('modifiers.index') }}" class="nav-item {{ request()->routeIs('modifiers.*') ? 'active' : '' }}"><i class="bx bxs-slider-alt"></i><span>Modifiers</span></a>
        <a href="{{ route('staff.index') }}" class="nav-item {{ request()->routeIs('staff.*') ? 'active' : '' }}"><i class="bx bxs-briefcase"></i><span>Staff</span></a>
        @endif
      </div>
      <div class="nav-section">
        <div class="nav-section-title">Insights</div>
        <a href="{{ route('reports.index') }}" class="nav-item {{ request()->routeIs('reports.*') ? 'active' : '' }}"><i class="bx bx-line-chart"></i><span>Reports</span></a>
      </div>
      @if (auth()->user()->isManager())
      <div class="nav-section">
        <div class="nav-section-title">System</div>
        <a href="{{ route('settings.edit') }}" class="nav-item {{ request()->routeIs('settings.*') ? 'active' : '' }}"><i class="bx bxs-cog"></i><span>Settings</span></a>
      </div>
      @endif
    </nav>
    <div class="sidebar-footer">
      <img src="https://picsum.photos/seed/{{ auth()->user()->avatar_seed ?? 'user' }}/80/80.jpg" alt="avatar">
      <div class="sidebar-footer-info">
        <div class="sidebar-footer-name">{{ auth()->user()->name }}</div>
        <div class="sidebar-footer-role">{{ ucfirst(auth()->user()->role) }}</div>
      </div>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="topbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle sidebar"><i class="bx bx-menu"></i></button>
      <form class="topbar-search" action="{{ route('search') }}" method="GET">
        <i class="bx bxs-search"></i>
        <input type="text" name="q" placeholder="Search products, orders, customers..." aria-label="Global search" value="{{ request('q') }}">
      </form>
      <div class="topbar-actions">
        <div class="topbar-time" id="topbarTime"></div>
        <button class="topbar-btn" id="themeToggle" aria-label="Toggle theme" data-tooltip="Toggle theme"><i class="bx {{ \App\Models\Setting::current()->dark_mode ? 'bxs-moon' : 'bxs-sun' }}"></i></button>
        <button class="topbar-btn" aria-label="Notifications" data-tooltip="Notifications"><i class="bx bxs-bell"></i><span class="dot"></span></button>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit" class="topbar-btn" aria-label="Log out" data-tooltip="Log out"><i class="bx bxs-log-out"></i></button>
        </form>
      </div>
    </header>

    <div class="content" id="contentArea">
      @yield('content')
    </div>
  </div>
</div>

<div class="toast-container" id="toastContainer"></div>
<div id="flashData" data-success="{{ session('success') }}" data-error="{{ session('error') }}" style="display:none;"></div>

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
</body>
</html>

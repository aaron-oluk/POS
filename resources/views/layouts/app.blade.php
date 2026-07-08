<!DOCTYPE html>
<html lang="en" data-theme="{{ \App\Models\Setting::current()->dark_mode ? 'dark' : 'light' }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Dashboard') — {{ config('app.name') }}</title>
<link href="https://fonts.googleapis.com/css2?family=Figtree:ital,wght@0,300..900;1,300..900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
<script>
  (function () {
    var saved = localStorage.getItem('nexus-theme');
    if (saved) document.documentElement.dataset.theme = saved;
  })();
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
        <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}"><i class="fa-solid fa-grid-2"></i><span>Dashboard</span></a>
        <a href="{{ route('pos.index') }}" class="nav-item {{ request()->routeIs('pos.*') ? 'active' : '' }}"><i class="fa-solid fa-cash-register"></i><span>POS Terminal</span></a>
        <a href="{{ route('orders.index') }}" class="nav-item {{ request()->routeIs('orders.*') ? 'active' : '' }}"><i class="fa-solid fa-receipt"></i><span>Orders</span></a>
      </div>
      <div class="nav-section">
        <div class="nav-section-title">Management</div>
        <a href="{{ route('products.index') }}" class="nav-item {{ request()->routeIs('products.*') ? 'active' : '' }}"><i class="fa-solid fa-box"></i><span>Products</span></a>
        <a href="{{ route('customers.index') }}" class="nav-item {{ request()->routeIs('customers.*') ? 'active' : '' }}"><i class="fa-solid fa-users"></i><span>Customers</span></a>
        @if (auth()->user()->isManager())
        <a href="{{ route('staff.index') }}" class="nav-item {{ request()->routeIs('staff.*') ? 'active' : '' }}"><i class="fa-solid fa-user-tie"></i><span>Staff</span></a>
        @endif
      </div>
      <div class="nav-section">
        <div class="nav-section-title">Insights</div>
        <a href="{{ route('reports.index') }}" class="nav-item {{ request()->routeIs('reports.*') ? 'active' : '' }}"><i class="fa-solid fa-chart-line"></i><span>Reports</span></a>
      </div>
      @if (auth()->user()->isManager())
      <div class="nav-section">
        <div class="nav-section-title">System</div>
        <a href="{{ route('settings.edit') }}" class="nav-item {{ request()->routeIs('settings.*') ? 'active' : '' }}"><i class="fa-solid fa-gear"></i><span>Settings</span></a>
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
      <button class="topbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="fa-solid fa-bars"></i></button>
      <form class="topbar-search" action="{{ route('search') }}" method="GET">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" name="q" placeholder="Search products, orders, customers..." aria-label="Global search" value="{{ request('q') }}">
      </form>
      <div class="topbar-actions">
        <div class="topbar-time" id="topbarTime"></div>
        <button class="topbar-btn" id="themeToggle" aria-label="Toggle theme"><i class="fa-solid fa-{{ \App\Models\Setting::current()->dark_mode ? 'moon' : 'sun' }}"></i></button>
        <button class="topbar-btn" aria-label="Notifications"><i class="fa-solid fa-bell"></i><span class="dot"></span></button>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit" class="topbar-btn" aria-label="Log out" title="Log out"><i class="fa-solid fa-right-from-bracket"></i></button>
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
      <button type="button" class="modal-close" id="confirmModalClose"><i class="fa-solid fa-xmark"></i></button>
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

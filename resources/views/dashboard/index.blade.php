@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="page-header">
  <div>
    <h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle">Here's what's happening at your store today</p>
  </div>
  <div style="display:flex;gap:8px;">
    <a href="{{ route('pos.index') }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> New Sale</a>
  </div>
</div>

@php
  $fmtChange = function ($v) {
    $cls = $v >= 0 ? 'up' : 'down';
    $icon = $v >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
    return "<span class=\"stat-change $cls\"><i class=\"fa-solid $icon\"></i> ".number_format(abs($v), 1)."% vs yesterday</span>";
  };
@endphp

<div class="grid grid-4" style="margin-bottom:24px;">
  <div class="card stat-card">
    <div class="stat-icon" style="background:var(--accent-dim);color:var(--accent);"><i class="fa-solid fa-dollar-sign"></i></div>
    <div><div class="stat-label">Today's Revenue</div><div class="stat-value">${{ number_format($todayRevenue, 2) }}</div>{!! $fmtChange($revenueChange) !!}</div>
  </div>
  <div class="card stat-card">
    <div class="stat-icon" style="background:var(--success-dim);color:var(--success);"><i class="fa-solid fa-bag-shopping"></i></div>
    <div><div class="stat-label">Today's Orders</div><div class="stat-value">{{ $todayCount }}</div>{!! $fmtChange($ordersChange) !!}</div>
  </div>
  <div class="card stat-card">
    <div class="stat-icon" style="background:var(--info-dim);color:var(--info);"><i class="fa-solid fa-cart-shopping"></i></div>
    <div><div class="stat-label">Avg. Order Value</div><div class="stat-value">${{ number_format($todayAov, 2) }}</div>{!! $fmtChange($aovChange) !!}</div>
  </div>
  <div class="card stat-card">
    <div class="stat-icon" style="background:var(--warning-dim);color:var(--warning);"><i class="fa-solid fa-users"></i></div>
    <div><div class="stat-label">Customers Today</div><div class="stat-value">{{ $todayCustomers }}</div>{!! $fmtChange($customersChange) !!}</div>
  </div>
</div>

<div class="grid grid-2" style="margin-bottom:24px;">
  <div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
      <h3 style="font-size:15px;">Revenue — Last 7 Days</h3>
    </div>
    <div class="chart-container"><canvas id="revenueChart"></canvas></div>
  </div>
  <div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
      <h3 style="font-size:15px;">Sales by Category</h3>
    </div>
    <div class="chart-container"><canvas id="categoryChart"></canvas></div>
  </div>
</div>

<div class="grid grid-2">
  <div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
      <h3 style="font-size:15px;">Recent Transactions</h3>
      <a href="{{ route('orders.index') }}" class="btn btn-secondary btn-sm">View All</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Order ID</th><th>Customer</th><th>Amount</th><th>Status</th></tr></thead>
        <tbody>
          @forelse ($recentTransactions as $o)
          <tr>
            <td style="font-weight:600;font-family:'Figtree';">{{ $o->order_number }}</td>
            <td>{{ $o->customer?->full_name ?? 'Walk-in' }}</td>
            <td style="font-family:'Figtree';font-weight:600;">${{ number_format($o->total, 2) }}</td>
            <td><span class="badge badge-{{ ['completed'=>'success','pending'=>'warning','refunded'=>'danger','cancelled'=>'muted'][$o->status] }}">{{ ucfirst($o->status) }}</span></td>
          </tr>
          @empty
          <tr><td colspan="4" style="text-align:center;color:var(--fg-muted);">No transactions yet</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
      <h3 style="font-size:15px;">Top Products</h3>
    </div>
    <div>
      @php $maxQty = $topProducts->max('qty') ?: 1; @endphp
      @forelse ($topProducts as $i => $p)
      <div style="display:flex;align-items:center;gap:12px;padding:10px 0;{{ $i < $topProducts->count() - 1 ? 'border-bottom:1px solid var(--border);' : '' }}">
        <span style="font-size:20px;">{{ $p->product_icon }}</span>
        <div style="flex:1;min-width:0;">
          <div style="font-size:13px;font-weight:600;">{{ $p->product_name }}</div>
          <div class="progress-bar" style="margin-top:6px;"><div class="progress-fill" style="width:{{ round($p->qty / $maxQty * 100) }}%;background:var(--accent);"></div></div>
        </div>
        <div style="text-align:right;"><div style="font-family:'Figtree';font-weight:700;">{{ $p->qty }}</div><div style="font-size:11px;color:var(--fg-muted);">sold</div></div>
      </div>
      @empty
      <div style="text-align:center;color:var(--fg-muted);padding:16px;">No sales yet</div>
      @endforelse
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const isDark = document.documentElement.dataset.theme === 'dark';
  const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
  const textColor = isDark ? '#7c7f8a' : '#6b7280';

  new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
      labels: @json($last7Days->pluck('label')),
      datasets: [{
        label: 'Revenue',
        data: @json($last7Days->pluck('total')),
        borderColor: '#e8a838', backgroundColor: 'rgba(232,168,56,0.08)',
        fill: true, tension: 0.4, pointRadius: 4, pointBackgroundColor: '#e8a838',
        pointBorderColor: isDark ? '#1e2029' : '#ffffff', pointBorderWidth: 2,
      }],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { color: gridColor }, ticks: { color: textColor, font: { size: 11 } } },
        y: { grid: { color: gridColor }, ticks: { color: textColor, font: { size: 11 }, callback: (v) => '$' + v } },
      },
    },
  });

  const catLabels = @json($categoryTotals->pluck('category'));
  const catData = @json($categoryTotals->pluck('total'));
  const catColors = ['#e8a838', '#34d399', '#60a5fa', '#f87171', '#a78bfa', '#fb923c', '#2dd4bf', '#f472b6'];
  new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: { labels: catLabels, datasets: [{ data: catData, backgroundColor: catColors.slice(0, catLabels.length), borderWidth: 0, hoverOffset: 8 }] },
    options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { position: 'right', labels: { color: textColor, font: { size: 11 }, usePointStyle: true, pointStyle: 'circle', padding: 12 } } } },
  });
});
</script>
@endpush

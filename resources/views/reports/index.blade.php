@extends('layouts.app')

@section('title', 'Reports')

@section('content')
<div class="page-header">
  <div>
    <h1 class="page-title">Reports</h1>
    <p class="page-subtitle">Detailed analytics and business insights</p>
  </div>
  <form method="GET">
    <select class="input-field" style="width:auto;height:38px;" name="period" onchange="this.form.submit()">
      <option value="today" {{ $period === 'today' ? 'selected' : '' }}>Today</option>
      <option value="week" {{ $period === 'week' ? 'selected' : '' }}>This Week</option>
      <option value="month" {{ $period === 'month' ? 'selected' : '' }}>This Month</option>
      <option value="year" {{ $period === 'year' ? 'selected' : '' }}>This Year</option>
    </select>
  </form>
</div>

<div class="grid grid-4" style="margin-bottom:24px;">
  <div class="card stat-card"><div class="stat-icon" style="background:var(--accent-dim);color:var(--accent);"><i class="bx bxs-dollar-circle"></i></div><div><div class="stat-label">Total Revenue</div><div class="stat-value">@money($totalRevenue)</div></div></div>
  <div class="card stat-card"><div class="stat-icon" style="background:var(--success-dim);color:var(--success);"><i class="bx bxs-shopping-bag"></i></div><div><div class="stat-label">Total Orders</div><div class="stat-value">{{ $totalOrders }}</div></div></div>
  <div class="card stat-card"><div class="stat-icon" style="background:var(--info-dim);color:var(--info);"><i class="bx bxs-receipt"></i></div><div><div class="stat-label">Avg. Order</div><div class="stat-value">@money($avgOrder)</div></div></div>
  <div class="card stat-card"><div class="stat-icon" style="background:var(--warning-dim);color:var(--warning);"><i class="bx bxs-donate-heart"></i></div><div><div class="stat-label">Total Tips</div><div class="stat-value">@money($totalTips)</div></div></div>
</div>

<div class="grid grid-2" style="margin-bottom:24px;">
  <div class="card">
    <h3 style="font-size:15px;margin-bottom:16px;">Hourly Sales Breakdown</h3>
    <div class="chart-container"><canvas id="hourlyChart"></canvas></div>
  </div>
  <div class="card">
    <h3 style="font-size:15px;margin-bottom:16px;">Payment Method Distribution</h3>
    <div class="chart-container"><canvas id="paymentChart"></canvas></div>
  </div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3 style="font-size:15px;margin-bottom:16px;">Staff Performance</h3>
    <div>
      @forelse ($staffPerformance as $i => $s)
      <div style="display:flex;align-items:center;gap:12px;padding:10px 0;{{ $i < $staffPerformance->count() - 1 ? 'border-bottom:1px solid var(--border);' : '' }}">
        <div style="width:24px;font-family:'Figtree';font-weight:700;color:{{ $i === 0 ? 'var(--accent)' : ($i === 1 ? 'var(--fg-muted)' : ($i === 2 ? '#cd7f32' : 'var(--fg-dim)')) }};font-size:14px;">#{{ $i + 1 }}</div>
        <img src="https://picsum.photos/seed/{{ $s->avatar_seed }}/60/60.jpg" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">
        <div style="flex:1;"><div style="font-size:13px;font-weight:600;">{{ $s->name }}</div><div style="font-size:11px;color:var(--fg-muted);">{{ ucfirst($s->role) }}</div></div>
        <div style="text-align:right;"><div style="font-family:'Figtree';font-weight:700;">@money($s->orders_sum_total ?? 0)</div><div style="font-size:11px;color:var(--fg-muted);">{{ $s->orders_count }} orders</div></div>
      </div>
      @empty
      <div style="text-align:center;color:var(--fg-muted);padding:16px;">No staff data</div>
      @endforelse
    </div>
  </div>
  <div class="card">
    <h3 style="font-size:15px;margin-bottom:16px;">Low Stock Alerts</h3>
    <div>
      @forelse ($lowStock as $i => $p)
      <div style="display:flex;align-items:center;gap:12px;padding:10px 0;{{ $i < $lowStock->count() - 1 ? 'border-bottom:1px solid var(--border);' : '' }}">
        <span style="font-size:22px;">{{ $p->icon }}</span>
        <div style="flex:1;"><div style="font-size:13px;font-weight:600;">{{ $p->name }}</div><div style="font-size:11px;color:var(--fg-muted);">{{ $p->category->name }}</div></div>
        <span class="badge {{ $p->stock <= 0 ? 'badge-danger' : 'badge-warning' }}">{{ $p->stock <= 0 ? 'Out' : $p->stock.' left' }}</span>
      </div>
      @empty
      <div style="text-align:center;padding:24px;color:var(--fg-muted);"><i class="bx bxs-check-circle" style="font-size:32px;color:var(--success);display:block;margin-bottom:8px;"></i>All items are well stocked</div>
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

  new Chart(document.getElementById('hourlyChart'), {
    type: 'bar',
    data: {
      labels: @json($hourlyLabels),
      datasets: [{ label: 'Sales', data: @json($hourlyData), backgroundColor: 'rgba(232,168,56,0.7)', borderRadius: 6, borderSkipped: false }],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false }, ticks: { color: textColor, font: { size: 11 } } },
        y: { grid: { color: gridColor }, ticks: { color: textColor, font: { size: 11 }, callback: (v) => window.formatMoney(v) } },
      },
    },
  });

  const payLabels = @json($paymentCounts->keys()->map(fn($k) => ['cash'=>'Cash','card'=>'Card','mobile'=>'Mobile Pay'][$k] ?? $k));
  const payData = @json($paymentCounts->values());
  new Chart(document.getElementById('paymentChart'), {
    type: 'pie',
    data: { labels: payLabels, datasets: [{ data: payData, backgroundColor: ['#e8a838', '#34d399', '#60a5fa'], borderWidth: 0, hoverOffset: 8 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: textColor, font: { size: 12 }, usePointStyle: true, pointStyle: 'circle', padding: 16 } } } },
  });
});
</script>
@endpush

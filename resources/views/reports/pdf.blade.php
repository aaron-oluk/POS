<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  @page { margin: 28px 32px; }
  body { font-family: 'Helvetica', 'Arial', sans-serif; color: #111827; font-size: 11px; line-height: 1.5; }
  h1, h2, h3 { font-family: 'Helvetica', 'Arial', sans-serif; margin: 0; }
  .header { border-bottom: 2px solid #e8a838; padding-bottom: 12px; margin-bottom: 18px; }
  .header .store-name { font-size: 20px; font-weight: bold; color: #111827; }
  .header .meta { color: #6b7280; font-size: 10px; margin-top: 4px; }
  .header .period { display: inline-block; margin-top: 8px; padding: 3px 10px; background: #fef3e2; color: #b7791f; border-radius: 10px; font-size: 10px; font-weight: bold; }

  .stats { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
  .stats td { width: 25%; padding: 10px 12px; border: 1px solid #e5e7eb; }
  .stats .label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; }
  .stats .value { font-size: 16px; font-weight: bold; color: #111827; margin-top: 3px; }

  .section-title { font-size: 13px; font-weight: bold; color: #111827; margin: 18px 0 8px; padding-bottom: 4px; border-bottom: 1px solid #e5e7eb; }

  .insights { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; padding: 10px 14px; margin-bottom: 4px; }
  .insights ul { margin: 0; padding-left: 16px; }
  .insights li { margin-bottom: 6px; }

  table.data { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
  table.data th { text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; padding: 6px 8px; border-bottom: 1.5px solid #d1d5db; }
  table.data td { padding: 6px 8px; border-bottom: 1px solid #f0f1f3; font-size: 10.5px; }
  table.data tr:last-child td { border-bottom: none; }
  .num { text-align: right; }
  .rank { color: #9ca3af; font-weight: bold; }
  .badge-danger { color: #dc2626; font-weight: bold; }
  .badge-warning { color: #b45309; font-weight: bold; }

  .two-col { width: 100%; }
  .two-col td { vertical-align: top; width: 50%; padding-right: 10px; }

  .footer { position: fixed; bottom: -16px; left: 0; right: 0; text-align: center; font-size: 9px; color: #9ca3af; }
</style>
</head>
<body>

<div class="header">
  <div class="store-name">{{ $settings->store_name }}</div>
  <div class="meta">{{ $settings->address }} &middot; {{ $settings->phone }} &middot; {{ $settings->email }}</div>
  <div class="meta">Generated {{ $generatedAt->format('M j, Y \a\t g:i A') }}</div>
  <span class="period">{{ ['today' => 'TODAY', 'week' => 'THIS WEEK', 'month' => 'THIS MONTH', 'year' => 'THIS YEAR'][$period] ?? strtoupper($period) }} REPORT</span>
</div>

<table class="stats">
  <tr>
    <td>
      <div class="label">Total Revenue</div>
      <div class="value">{{ $settings->money($totalRevenue) }}</div>
    </td>
    <td>
      <div class="label">Total Orders</div>
      <div class="value">{{ $totalOrders }}</div>
    </td>
    <td>
      <div class="label">Avg. Order</div>
      <div class="value">{{ $settings->money($avgOrder) }}</div>
    </td>
    <td>
      <div class="label">Total Tips</div>
      <div class="value">{{ $settings->money($totalTips) }}</div>
    </td>
  </tr>
</table>

<div class="section-title">Analysis</div>
<div class="insights">
  <ul>
    @foreach ($insights as $line)
      <li>{{ $line }}</li>
    @endforeach
  </ul>
</div>

<table class="two-col">
  <tr>
    <td>
      <div class="section-title">Hourly Sales</div>
      <table class="data">
        <thead><tr><th>Hour</th><th class="num">Revenue</th></tr></thead>
        <tbody>
          @foreach ($hourlyLabels as $i => $label)
            @if (($hourlyData[$i] ?? 0) > 0)
            <tr><td>{{ $label }}</td><td class="num">{{ $settings->money($hourlyData[$i]) }}</td></tr>
            @endif
          @endforeach
        </tbody>
      </table>
    </td>
    <td>
      <div class="section-title">Payment Methods</div>
      <table class="data">
        <thead><tr><th>Method</th><th class="num">Collected</th></tr></thead>
        <tbody>
          @forelse ($paymentTotals as $method => $amount)
            <tr><td>{{ ['cash' => 'Cash', 'card' => 'Card', 'mobile' => 'Mobile Pay'][$method] ?? ucfirst($method) }}</td><td class="num">{{ $settings->money($amount) }}</td></tr>
          @empty
            <tr><td colspan="2">No data</td></tr>
          @endforelse
        </tbody>
      </table>
    </td>
  </tr>
</table>

<table class="two-col">
  <tr>
    <td>
      <div class="section-title">Top Products</div>
      <table class="data">
        <thead><tr><th>Product</th><th class="num">Qty</th><th class="num">Revenue</th></tr></thead>
        <tbody>
          @forelse ($topProducts as $p)
            <tr><td>{{ $p->product_name }}</td><td class="num">{{ $p->qty }}</td><td class="num">{{ $settings->money($p->revenue) }}</td></tr>
          @empty
            <tr><td colspan="3">No sales in this period</td></tr>
          @endforelse
        </tbody>
      </table>
    </td>
    <td>
      <div class="section-title">Category Breakdown</div>
      <table class="data">
        <thead><tr><th>Category</th><th class="num">Revenue</th></tr></thead>
        <tbody>
          @forelse ($categoryTotals as $c)
            <tr><td>{{ $c->category }}</td><td class="num">{{ $settings->money($c->total) }}</td></tr>
          @empty
            <tr><td colspan="2">No data</td></tr>
          @endforelse
        </tbody>
      </table>
    </td>
  </tr>
</table>

<div class="section-title">Staff Performance</div>
<table class="data">
  <thead><tr><th>#</th><th>Name</th><th>Role</th><th class="num">Sales</th><th class="num">Orders</th></tr></thead>
  <tbody>
    @forelse ($staffPerformance as $i => $s)
      <tr>
        <td class="rank">{{ $i + 1 }}</td>
        <td>{{ $s->name }}</td>
        <td>{{ ucfirst($s->role) }}</td>
        <td class="num">{{ $settings->money($s->orders_sum_total ?? 0) }}</td>
        <td class="num">{{ $s->orders_count }}</td>
      </tr>
    @empty
      <tr><td colspan="5">No staff data</td></tr>
    @endforelse
  </tbody>
</table>

<div class="section-title">Low Stock Alerts</div>
<table class="data">
  <thead><tr><th>Product</th><th>Category</th><th class="num">Stock</th></tr></thead>
  <tbody>
    @forelse ($lowStock as $p)
      <tr>
        <td>{{ $p->name }}</td>
        <td>{{ $p->category->name }}</td>
        <td class="num {{ $p->stock <= 0 ? 'badge-danger' : 'badge-warning' }}">{{ $p->stock <= 0 ? 'Out of stock' : $p->stock }}</td>
      </tr>
    @empty
      <tr><td colspan="3">All products are well stocked</td></tr>
    @endforelse
  </tbody>
</table>

<div class="footer">{{ $settings->store_name }} &middot; Confidential business report</div>

</body>
</html>

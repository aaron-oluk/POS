@extends('layouts.app')

@section('title', 'Stock Management')

@section('content')
<div class="page-header">
  <div>
    <h1 class="page-title">Stock Management</h1>
    <p class="page-subtitle">Track inventory levels and every stock movement</p>
  </div>
  <button class="btn btn-primary btn-sm" onclick="openAdjustmentModal()"><i class="bx bx-plus"></i> Add Stock</button>
</div>

<div class="grid grid-4" style="margin-bottom:20px;">
  <div class="card" style="display:flex;align-items:center;gap:12px;"><div class="stat-icon" style="background:var(--accent-dim);color:var(--accent);width:40px;height:40px;font-size:15px;"><i class="bx bxs-archive"></i></div><div><div class="stat-label">Total Products</div><div style="font-family:'Figtree';font-size:22px;font-weight:700;">{{ $totalProducts }}</div></div></div>
  <div class="card" style="display:flex;align-items:center;gap:12px;"><div class="stat-icon" style="background:var(--warning-dim);color:var(--warning);width:40px;height:40px;font-size:15px;"><i class="bx bxs-error"></i></div><div><div class="stat-label">Low Stock</div><div style="font-family:'Figtree';font-size:22px;font-weight:700;">{{ $lowStockCount }}</div></div></div>
  <div class="card" style="display:flex;align-items:center;gap:12px;"><div class="stat-icon" style="background:var(--danger-dim);color:var(--danger);width:40px;height:40px;font-size:15px;"><i class="bx bx-block"></i></div><div><div class="stat-label">Out of Stock</div><div style="font-family:'Figtree';font-size:22px;font-weight:700;">{{ $outOfStockCount }}</div></div></div>
  <div class="card" style="display:flex;align-items:center;gap:12px;"><div class="stat-icon" style="background:var(--success-dim);color:var(--success);width:40px;height:40px;font-size:15px;"><i class="bx bxs-dollar-circle"></i></div><div><div class="stat-label">Inventory Value</div><div style="font-family:'Figtree';font-size:22px;font-weight:700;">@money($inventoryValue)</div></div></div>
</div>

<div class="card" style="margin-bottom:20px;">
  <h3 style="margin-bottom:12px;">Current Inventory</h3>
  <form method="GET" style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
    <div class="topbar-search" style="max-width:280px;flex:1;">
      <i class="bx bxs-search"></i>
      <input type="text" name="q" placeholder="Search products..." value="{{ $search }}">
    </div>
    <select class="input-field" style="width:auto;height:38px;" name="category" onchange="this.form.submit()">
      <option value="">All Categories</option>
      @foreach ($categories as $c)
        <option value="{{ $c->name }}" {{ $categoryFilter === $c->name ? 'selected' : '' }}>{{ $c->name }}</option>
      @endforeach
    </select>
    <select class="input-field" style="width:auto;height:38px;" name="stock" onchange="this.form.submit()">
      <option value="">All Stock</option>
      <option value="low" {{ $stockFilter === 'low' ? 'selected' : '' }}>Low Stock</option>
      <option value="out" {{ $stockFilter === 'out' ? 'selected' : '' }}>Out of Stock</option>
      <option value="ok" {{ $stockFilter === 'ok' ? 'selected' : '' }}>In Stock</option>
    </select>
    <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
    @if ($search || $categoryFilter || $stockFilter)
      <a href="{{ route('stock-management.index') }}" class="btn btn-secondary btn-sm">Clear</a>
    @endif
  </form>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Product</th><th>Category</th><th>Cost</th><th>Price</th><th>Stock</th><th>Status</th><th>Value</th><th>Actions</th></tr></thead>
      <tbody>
        @forelse ($products as $p)
        <tr>
          <td><div style="display:flex;align-items:center;gap:10px;"><span style="font-size:20px;">{{ $p->icon }}</span><div><div style="font-weight:600;">{{ $p->name }}</div><div style="font-size:11px;color:var(--fg-dim);">{{ $p->sku }}</div></div></div></td>
          <td><span class="badge badge-muted">{{ $p->category->name }}</span></td>
          <td>@money($p->cost)</td>
          <td>@money($p->price)</td>
          <td style="font-family:'Figtree';font-weight:600;">{{ $p->stock }}</td>
          <td>
            @if ($p->stock_status === 'out')<span class="badge badge-danger">Out of Stock</span>
            @elseif ($p->stock_status === 'low')<span class="badge badge-warning">Low Stock</span>
            @else<span class="badge badge-success">In Stock</span>@endif
          </td>
          <td style="color:var(--fg-muted);">@money($p->stock * $p->cost)</td>
          <td>
            <button class="btn btn-secondary btn-sm btn-icon" onclick="openAdjustmentModal({{ $p->id }})" aria-label="Adjust stock" data-tooltip="Adjust stock"><i class="bx bx-slider-alt" style="font-size:11px;"></i></button>
          </td>
        </tr>
        @empty
        <tr><td colspan="8" style="text-align:center;color:var(--fg-muted);">No products found</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  {{ $products->links('pagination.custom') }}
</div>

<div class="card">
  <h3 style="margin-bottom:12px;">Stock Movement History</h3>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Product</th><th>Type</th><th>Reason</th><th>Qty</th><th>Before → After</th><th>By</th><th>Date</th><th>Notes</th></tr></thead>
      <tbody>
        @forelse ($adjustments as $a)
        <tr>
          <td><div style="display:flex;align-items:center;gap:8px;"><span>{{ $a->product->icon }}</span><span style="font-weight:600;">{{ $a->product->name }}</span></div></td>
          <td><span class="badge {{ $a->type === 'increase' ? 'badge-success' : 'badge-danger' }}">{{ ucfirst($a->type) }}</span></td>
          <td><span class="badge badge-muted">{{ \App\Models\StockAdjustment::reasonLabel($a->reason) }}</span></td>
          <td style="font-family:'Figtree';font-weight:600;">{{ $a->type === 'increase' ? '+' : '-' }}{{ $a->quantity }}</td>
          <td style="color:var(--fg-muted);">{{ $a->stock_before }} → {{ $a->stock_after }}</td>
          <td>{{ $a->user->name }}</td>
          <td style="color:var(--fg-muted);">@localTime($a->created_at, 'M j, Y g:i A')</td>
          <td style="color:var(--fg-muted);font-size:11px;">{{ $a->notes ?? '—' }}</td>
        </tr>
        @empty
        <tr><td colspan="8" style="text-align:center;color:var(--fg-muted);">No stock movement recorded yet</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  {{ $adjustments->links('pagination.custom') }}
</div>
@endsection

@push('modals')
<div class="modal-overlay" id="adjustmentModal">
  <div class="modal" style="max-width:480px;">
    <div class="modal-header">
      <h3>Add / Adjust Stock</h3>
      <button class="modal-close" onclick="closeModal('adjustmentModal')" aria-label="Close" data-tooltip="Close"><i class="bx bx-x"></i></button>
    </div>
    <form id="adjustmentForm" method="POST" action="{{ route('stock-management.store') }}">
      @csrf
      <div class="modal-body">
        <div class="input-group" style="margin-bottom:16px;"><label>Product</label>
          <select class="input-field" name="product_id" id="adProduct" required>
            <option value="">Select product...</option>
            @foreach ($allProducts as $p)
              <option value="{{ $p->id }}" data-stock="{{ $p->stock }}">{{ $p->icon }} {{ $p->name }} ({{ $p->stock }} in stock)</option>
            @endforeach
          </select>
        </div>
        <div class="tabs" style="margin-bottom:16px;">
          <button class="tab active" data-type="increase" type="button">Increase Stock</button>
          <button class="tab" data-type="decrease" type="button">Decrease Stock</button>
        </div>
        <input type="hidden" name="type" id="adType" value="increase">
        <div class="grid grid-2" style="gap:16px;margin-bottom:16px;">
          <div class="input-group"><label>Quantity</label><input type="number" class="input-field" name="quantity" id="adQty" min="1" required></div>
          <div class="input-group"><label>Reason</label>
            <select class="input-field" name="reason" id="adReason" required>
              <option value="recount">Recount</option>
              <option value="waste">Waste</option>
              <option value="damage">Damage</option>
              <option value="theft">Theft</option>
              <option value="other">Other</option>
            </select>
          </div>
        </div>
        <div class="input-group"><label>Notes <span style="font-weight:400;color:var(--fg-dim);">(optional)</span></label><textarea class="input-field" name="notes" rows="2"></textarea></div>
        <div style="font-size:11px;color:var(--fg-dim);margin-top:8px;" id="adCurrentStock"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('adjustmentModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Save</button>
      </div>
    </form>
  </div>
</div>
@endpush

@push('scripts')
<script>
document.querySelectorAll('#adjustmentModal .tab').forEach((tab) => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('#adjustmentModal .tab').forEach((t) => t.classList.remove('active'));
    tab.classList.add('active');
    document.getElementById('adType').value = tab.dataset.type;
  });
});

document.getElementById('adProduct').addEventListener('change', (e) => {
  const opt = e.target.selectedOptions[0];
  const stockEl = document.getElementById('adCurrentStock');
  stockEl.textContent = opt && opt.value ? `Current stock on hand: ${opt.dataset.stock} units` : '';
});

function openAdjustmentModal(productId) {
  document.getElementById('adjustmentForm').reset();
  document.getElementById('adType').value = 'increase';
  document.querySelectorAll('#adjustmentModal .tab').forEach((t) => t.classList.toggle('active', t.dataset.type === 'increase'));
  document.getElementById('adCurrentStock').textContent = '';
  if (productId) {
    const select = document.getElementById('adProduct');
    select.value = productId;
    select.dispatchEvent(new Event('change'));
  }
  openModal('adjustmentModal');
}
</script>
@endpush

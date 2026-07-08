@extends('layouts.app')

@section('title', 'Products')

@section('content')
@php $canManage = auth()->user()->isManager(); @endphp
<div class="page-header">
  <div>
    <h1 class="page-title">Products</h1>
    <p class="page-subtitle">Manage your product catalog and inventory</p>
  </div>
  @if ($canManage)
  <button class="btn btn-primary btn-sm" onclick="openProductModal()"><i class="fa-solid fa-plus"></i> Add Product</button>
  @endif
</div>

<div class="grid grid-4" style="margin-bottom:20px;">
  <div class="card" style="display:flex;align-items:center;gap:12px;"><div class="stat-icon" style="background:var(--accent-dim);color:var(--accent);width:40px;height:40px;font-size:15px;"><i class="fa-solid fa-box"></i></div><div><div class="stat-label">Total Products</div><div style="font-family:'Figtree';font-size:22px;font-weight:700;">{{ $totalProducts }}</div></div></div>
  <div class="card" style="display:flex;align-items:center;gap:12px;"><div class="stat-icon" style="background:var(--warning-dim);color:var(--warning);width:40px;height:40px;font-size:15px;"><i class="fa-solid fa-triangle-exclamation"></i></div><div><div class="stat-label">Low Stock</div><div style="font-family:'Figtree';font-size:22px;font-weight:700;">{{ $lowStock }}</div></div></div>
  <div class="card" style="display:flex;align-items:center;gap:12px;"><div class="stat-icon" style="background:var(--danger-dim);color:var(--danger);width:40px;height:40px;font-size:15px;"><i class="fa-solid fa-ban"></i></div><div><div class="stat-label">Out of Stock</div><div style="font-family:'Figtree';font-size:22px;font-weight:700;">{{ $outOfStock }}</div></div></div>
  <div class="card" style="display:flex;align-items:center;gap:12px;"><div class="stat-icon" style="background:var(--success-dim);color:var(--success);width:40px;height:40px;font-size:15px;"><i class="fa-solid fa-dollar-sign"></i></div><div><div class="stat-label">Inventory Value</div><div style="font-family:'Figtree';font-size:22px;font-weight:700;">${{ number_format($inventoryValue, 0) }}</div></div></div>
</div>

<div class="card">
  <form method="GET" style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
    <div class="topbar-search" style="max-width:280px;flex:1;">
      <i class="fa-solid fa-magnifying-glass"></i>
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
  </form>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th>@if($canManage)<th>Actions</th>@endif</tr></thead>
      <tbody>
        @forelse ($products as $p)
        <tr>
          <td><div style="display:flex;align-items:center;gap:10px;"><span style="font-size:22px;">{{ $p->icon }}</span><div><div style="font-weight:600;">{{ $p->name }}</div><div style="font-size:11px;color:var(--fg-dim);">{{ $p->sku }}</div></div></div></td>
          <td><span class="badge badge-muted">{{ $p->category->name }}</span></td>
          <td style="font-family:'Figtree';font-weight:600;">${{ number_format($p->price, 2) }}</td>
          <td><span style="font-family:'Figtree';font-weight:600;{{ $p->stock <= 15 ? 'color:var(--danger);' : '' }}">{{ $p->stock }}</span></td>
          <td>
            @if ($p->stock <= 0)<span class="badge badge-danger">Out of Stock</span>
            @elseif ($p->stock <= 15)<span class="badge badge-warning">Low Stock</span>
            @else<span class="badge badge-success">In Stock</span>@endif
          </td>
          @if ($canManage)
          <td>
            <div style="display:flex;gap:4px;">
              <button class="btn btn-secondary btn-sm btn-icon" title="Edit" onclick='openProductModal(@json($p))'><i class="fa-solid fa-pen" style="font-size:11px;"></i></button>
              <form method="POST" action="{{ route('products.destroy', $p) }}" data-confirm="Delete {{ $p->name }}? This cannot be undone." data-confirm-title="Delete Product" data-confirm-label="Delete">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Delete"><i class="fa-solid fa-trash" style="font-size:11px;"></i></button>
              </form>
            </div>
          </td>
          @endif
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center;color:var(--fg-muted);">No products found</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  {{ $products->links('pagination.custom') }}
</div>
@endsection

@if ($canManage)
@push('modals')
<div class="modal-overlay" id="productModal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="productModalTitle">Add Product</h3>
      <button class="modal-close" onclick="closeModal('productModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form id="productForm" method="POST" action="{{ route('products.store') }}">
      @csrf
      <input type="hidden" name="_method" id="productMethod" value="POST">
      <div class="modal-body">
        <div class="grid grid-2" style="gap:16px;">
          <div class="input-group"><label>Product Name</label><input type="text" class="input-field" name="name" id="pName" placeholder="e.g. Cappuccino" required></div>
          <div class="input-group"><label>Category</label>
            <select class="input-field" name="category_id" id="pCategory" required>
              @foreach ($categories as $c)
                <option value="{{ $c->id }}">{{ $c->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="input-group"><label>Price ($)</label><input type="number" class="input-field" name="price" id="pPrice" placeholder="0.00" step="0.01" required></div>
          <div class="input-group"><label>Cost ($)</label><input type="number" class="input-field" name="cost" id="pCost" placeholder="0.00" step="0.01"></div>
          <div class="input-group"><label>Stock Quantity</label><input type="number" class="input-field" name="stock" id="pStock" placeholder="0"></div>
          <div class="input-group"><label>SKU</label><input type="text" class="input-field" name="sku" id="pSku" placeholder="e.g. COF-025" required></div>
        </div>
        <div class="input-group" style="margin-top:16px;"><label>Description</label><textarea class="input-field" name="description" id="pDesc" rows="2" placeholder="Brief description..."></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('productModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save Product</button>
      </div>
    </form>
  </div>
</div>
@endpush

@push('scripts')
<script>
function openProductModal(product) {
  const form = document.getElementById('productForm');
  document.getElementById('productModalTitle').textContent = product ? 'Edit Product' : 'Add Product';
  form.action = product ? `/products/${product.id}` : @json(route('products.store'));
  document.getElementById('productMethod').value = product ? 'PUT' : 'POST';
  document.getElementById('pName').value = product ? product.name : '';
  document.getElementById('pCategory').value = product ? product.category_id : '';
  document.getElementById('pPrice').value = product ? product.price : '';
  document.getElementById('pCost').value = product ? product.cost : '';
  document.getElementById('pStock').value = product ? product.stock : '';
  document.getElementById('pSku').value = product ? product.sku : '';
  document.getElementById('pDesc').value = product ? (product.description ?? '') : '';
  openModal('productModal');
}
</script>
@endpush
@endif

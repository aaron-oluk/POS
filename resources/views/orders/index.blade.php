@extends('layouts.app')

@section('title', 'Orders')

@section('content')
<div class="page-header">
  <div>
    <h1 class="page-title">Orders</h1>
    <p class="page-subtitle">Manage and review all transactions</p>
  </div>
  <div style="display:flex;gap:8px;">
    <a href="{{ route('pos.index') }}" class="btn btn-primary btn-sm"><i class="bx bx-plus"></i> New Order</a>
  </div>
</div>
<div class="card" style="margin-bottom:16px;">
  <div class="filter-bar">
    @foreach (['all' => 'All', 'completed' => 'Completed', 'pending' => 'Pending', 'refunded' => 'Refunded', 'cancelled' => 'Cancelled'] as $key => $label)
      <a href="{{ route('orders.index', ['status' => $key]) }}" class="filter-chip {{ $status === $key ? 'active' : '' }}">{{ $label }}</a>
    @endforeach
  </div>
</div>
<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Order ID</th><th>Customer</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
        @forelse ($orders as $o)
        <tr>
          <td style="font-weight:600;font-family:'Figtree';cursor:pointer;color:var(--accent);" onclick="showOrderDetail({{ $o->id }})">{{ $o->order_number }}</td>
          <td>{{ $o->customer?->full_name ?? 'Walk-in' }}</td>
          <td>{{ $o->items()->count() }} item{{ $o->items()->count() > 1 ? 's' : '' }}</td>
          <td style="font-family:'Figtree';font-weight:600;">@money($o->total)</td>
          <td><span class="badge badge-muted">{{ \App\Models\Order::paymentLabel($o->payment_method) }}</span></td>
          <td><span class="badge badge-{{ ['completed'=>'success','pending'=>'warning','refunded'=>'danger','cancelled'=>'muted'][$o->status] }}">{{ ucfirst($o->status) }}</span></td>
          <td style="color:var(--fg-muted);font-size:12px;">{{ $o->created_at->format('M j, g:i A') }}</td>
          <td>
            <div style="display:flex;gap:4px;">
              <button class="btn btn-secondary btn-sm btn-icon" onclick="showOrderDetail({{ $o->id }})" title="View"><i class="bx bxs-show" style="font-size:11px;"></i></button>
              @if ($o->status === 'completed')
              <form method="POST" action="{{ route('orders.refund', $o) }}" data-confirm="Refund {{ $o->order_number }}? This will mark it as refunded." data-confirm-title="Refund Order" data-confirm-label="Refund">
                @csrf @method('PATCH')
                <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Refund"><i class="bx bx-rotate-left" style="font-size:11px;"></i></button>
              </form>
              @endif
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="8" style="text-align:center;color:var(--fg-muted);">No orders found</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  {{ $orders->links('pagination.custom') }}
</div>
@endsection

@push('modals')
<div class="modal-overlay" id="orderDetailModal">
  <div class="modal">
    <div class="modal-header">
      <h3>Order Details</h3>
      <button class="modal-close" onclick="closeModal('orderDetailModal')"><i class="bx bx-x"></i></button>
    </div>
    <div class="modal-body" id="orderDetailContent"></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('orderDetailModal')">Close</button>
    </div>
  </div>
</div>
@endpush

@push('scripts')
<script>
async function showOrderDetail(id) {
  try {
    const res = await fetch(`/orders/${id}`, { headers: { Accept: 'application/json' } });
    const o = await res.json();
    const statusBadge = { completed: 'badge-success', pending: 'badge-warning', refunded: 'badge-danger', cancelled: 'badge-muted' }[o.status];
    const dateStr = new Date(o.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    document.getElementById('orderDetailContent').innerHTML = `
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <div><div style="font-family:'Figtree';font-size:20px;font-weight:700;">${o.order_number}</div><div style="font-size:12px;color:var(--fg-muted);">${dateStr}</div></div>
        <span class="badge ${statusBadge}" style="font-size:13px;padding:5px 14px;">${o.status.charAt(0).toUpperCase() + o.status.slice(1)}</span>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
        <div class="card" style="padding:12px;"><div style="font-size:11px;color:var(--fg-muted);">Customer</div><div style="font-weight:600;margin-top:2px;">${o.customer}</div></div>
        <div class="card" style="padding:12px;"><div style="font-size:11px;color:var(--fg-muted);">Payment</div><div style="font-weight:600;margin-top:2px;">${o.payment_method}</div></div>
      </div>
      <h4 style="font-size:13px;margin-bottom:8px;">Items</h4>
      <div class="table-wrap"><table>
        <thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
        <tbody>${o.items.map((it) => `<tr><td>${it.icon} ${it.name}</td><td>${it.qty}</td><td>${window.formatMoney(it.unit_price)}</td><td style="font-weight:600;">${window.formatMoney(it.total)}</td></tr>`).join('')}</tbody>
      </table></div>
      <div style="margin-top:12px;text-align:right;">
        <div style="font-size:13px;color:var(--fg-muted);">Subtotal: ${window.formatMoney(o.subtotal)}</div>
        ${o.discount_amount > 0 ? `<div style="font-size:13px;color:var(--fg-muted);">Discount: -${window.formatMoney(o.discount_amount)}</div>` : ''}
        <div style="font-size:13px;color:var(--fg-muted);">Tax: ${window.formatMoney(o.tax)}</div>
        ${o.tip > 0 ? `<div style="font-size:13px;color:var(--fg-muted);">Tip: ${window.formatMoney(o.tip)}</div>` : ''}
        <div style="font-family:'Figtree';font-size:22px;font-weight:700;margin-top:4px;">${window.formatMoney(o.total)}</div>
      </div>
    `;
    openModal('orderDetailModal');
  } catch (e) {
    showToast('Could not load order', 'error');
  }
}

const params = new URLSearchParams(window.location.search);
if (params.get('open')) {
  showOrderDetail(params.get('open'));
}
</script>
@endpush

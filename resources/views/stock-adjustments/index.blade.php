@extends('layouts.app')

@section('title', 'Stock Adjustments')

@section('content')
<div class="page-header">
  <div>
    <h1 class="page-title">Stock Adjustments</h1>
    <p class="page-subtitle">Correct inventory counts with a reason and audit trail</p>
  </div>
  <button class="btn btn-primary btn-sm" onclick="openAdjustmentModal()"><i class="bx bx-plus"></i> New Adjustment</button>
</div>

<div class="card">
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
          <td style="color:var(--fg-muted);">{{ $a->created_at->format('M j, Y g:i A') }}</td>
          <td style="color:var(--fg-muted);font-size:11px;">{{ $a->notes ?? '—' }}</td>
        </tr>
        @empty
        <tr><td colspan="8" style="text-align:center;color:var(--fg-muted);">No stock adjustments recorded yet</td></tr>
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
      <h3>New Stock Adjustment</h3>
      <button class="modal-close" onclick="closeModal('adjustmentModal')" aria-label="Close" data-tooltip="Close"><i class="bx bx-x"></i></button>
    </div>
    <form id="adjustmentForm" method="POST" action="{{ route('stock-adjustments.store') }}">
      @csrf
      <div class="modal-body">
        <div class="input-group" style="margin-bottom:16px;"><label>Product</label>
          <select class="input-field" name="product_id" id="adProduct" required>
            <option value="">Select product...</option>
            @foreach ($products as $p)
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
        <button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Save Adjustment</button>
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

function openAdjustmentModal() {
  document.getElementById('adjustmentForm').reset();
  document.getElementById('adType').value = 'increase';
  document.querySelectorAll('#adjustmentModal .tab').forEach((t) => t.classList.toggle('active', t.dataset.type === 'increase'));
  document.getElementById('adCurrentStock').textContent = '';
  openModal('adjustmentModal');
}
</script>
@endpush

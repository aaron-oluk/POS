@extends('layouts.app')

@section('title', 'Purchasing')

@section('content')
<div class="page-header">
  <div>
    <h1 class="page-title">Purchasing</h1>
    <p class="page-subtitle">Record supplier restocks and manage suppliers</p>
  </div>
  <div style="display:flex;gap:8px;">
    <button class="btn btn-secondary btn-sm" onclick="openSupplierModal()"><i class="bx bx-plus"></i> Add Supplier</button>
    <button class="btn btn-primary btn-sm" onclick="openPurchaseModal()"><i class="bx bx-plus"></i> New Purchase</button>
  </div>
</div>

@php $totalOutstanding = max(0, $totalSpend - $totalPaid); @endphp
<div class="grid grid-4" style="margin-bottom:20px;">
  <div class="card" style="display:flex;align-items:center;gap:12px;"><div class="stat-icon" style="background:var(--accent-dim);color:var(--accent);width:40px;height:40px;font-size:15px;"><i class="bx bxs-truck"></i></div><div><div class="stat-label">Total Purchases</div><div style="font-family:'Figtree';font-size:22px;font-weight:700;">{{ $purchaseCount }}</div></div></div>
  <div class="card" style="display:flex;align-items:center;gap:12px;"><div class="stat-icon" style="background:var(--warning-dim);color:var(--warning);width:40px;height:40px;font-size:15px;"><i class="bx bxs-calendar"></i></div><div><div class="stat-label">Spend This Month</div><div style="font-family:'Figtree';font-size:22px;font-weight:700;">@money($thisMonthSpend)</div></div></div>
  <div class="card" style="display:flex;align-items:center;gap:12px;"><div class="stat-icon" style="background:var(--success-dim);color:var(--success);width:40px;height:40px;font-size:15px;"><i class="bx bxs-dollar-circle"></i></div><div><div class="stat-label">Total Paid to Suppliers</div><div style="font-family:'Figtree';font-size:22px;font-weight:700;">@money($totalPaid)</div></div></div>
  <div class="card" style="display:flex;align-items:center;gap:12px;"><div class="stat-icon" style="background:var(--danger-dim);color:var(--danger);width:40px;height:40px;font-size:15px;"><i class="bx bxs-error-circle"></i></div><div><div class="stat-label">Outstanding Balance</div><div style="font-family:'Figtree';font-size:22px;font-weight:700;{{ $totalOutstanding > 0 ? 'color:var(--danger);' : '' }}">@money($totalOutstanding)</div></div></div>
</div>

<div class="tabs">
  <button class="tab active" data-tab="history" type="button">Purchase History</button>
  <button class="tab" data-tab="suppliers" type="button">Suppliers</button>
</div>

<div id="purchases-history" class="settings-section">
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Reference</th><th>Supplier</th><th>Items</th><th>Total</th><th>Balance Owed</th><th>Status</th><th>Supply Date</th><th>Recorded By</th><th>Actions</th></tr></thead>
        <tbody>
          @forelse ($purchases as $p)
          @php $poNumber = $p->reference_no ?? 'PO-'.str_pad($p->id, 4, '0', STR_PAD_LEFT); @endphp
          <tr>
            <td style="font-weight:600;">{{ $poNumber }}</td>
            <td>{{ $p->supplier->name }}</td>
            <td>{{ $p->items->sum('quantity') }} units ({{ $p->items->count() }} products)</td>
            <td style="font-family:'Figtree';font-weight:600;">@money($p->total)</td>
            <td style="font-family:'Figtree';font-weight:600;{{ $p->balance_due > 0 ? 'color:var(--danger);' : '' }}">@money($p->balance_due)</td>
            <td>
              @if ($p->payment_status === 'paid')<span class="badge badge-success">Paid</span>
              @elseif ($p->payment_status === 'partial')<span class="badge badge-warning">Partial</span>
              @else<span class="badge badge-danger">Unpaid</span>@endif
            </td>
            <td style="color:var(--fg-muted);">{{ $p->supply_date?->format('M j, Y') ?? '—' }}</td>
            <td>{{ $p->user->name }}</td>
            <td>
              @if ($p->balance_due > 0)
              <button class="btn btn-secondary btn-sm" data-pay-id="{{ $p->id }}" data-pay-ref="{{ $poNumber }}" data-pay-balance="{{ (float) $p->balance_due }}" onclick="openPayModal(this.dataset)"><i class="bx bx-money"></i> Record Payment</button>
              @endif
            </td>
          </tr>
          @empty
          <tr><td colspan="9" style="text-align:center;color:var(--fg-muted);">No purchases recorded yet</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    {{ $purchases->links('pagination.custom') }}
  </div>
</div>

<div id="purchases-suppliers" class="settings-section" style="display:none;">
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Supplier</th><th>Contact</th><th>Phone</th><th>Email</th><th>Actions</th></tr></thead>
        <tbody>
          @forelse ($suppliers as $s)
          <tr>
            <td style="font-weight:600;">{{ $s->name }}</td>
            <td>{{ $s->contact_name ?? '—' }}</td>
            <td>{{ $s->phone ?? '—' }}</td>
            <td>{{ $s->email ?? '—' }}</td>
            <td>
              <div style="display:flex;gap:4px;">
                <button class="btn btn-secondary btn-sm btn-icon" aria-label="Edit" data-tooltip="Edit" onclick='openSupplierModal(@json($s))'><i class="bx bxs-pencil" style="font-size:11px;"></i></button>
                <form method="POST" action="{{ route('suppliers.destroy', $s) }}" data-confirm="Delete {{ $s->name }}?" data-confirm-title="Delete Supplier" data-confirm-label="Delete">
                  @csrf @method('DELETE')
                  <button type="submit" class="btn btn-danger btn-sm btn-icon" aria-label="Delete" data-tooltip="Delete"><i class="bx bxs-trash" style="font-size:11px;"></i></button>
                </form>
              </div>
            </td>
          </tr>
          @empty
          <tr><td colspan="5" style="text-align:center;color:var(--fg-muted);">No suppliers yet</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@push('modals')
<div class="modal-overlay" id="purchaseModal">
  <div class="modal" style="max-width:640px;">
    <div class="modal-header">
      <h3>New Purchase</h3>
      <button class="modal-close" onclick="closeModal('purchaseModal')" aria-label="Close" data-tooltip="Close"><i class="bx bx-x"></i></button>
    </div>
    <form id="purchaseForm" method="POST" action="{{ route('purchases.store') }}">
      @csrf
      <div class="modal-body">
        <div class="grid grid-2" style="gap:16px;margin-bottom:12px;">
          <div class="input-group"><label>Supplier</label>
            <select class="input-field" name="supplier_id" id="puSupplier" required>
              <option value="">Select supplier...</option>
              @foreach ($suppliers as $s)
                <option value="{{ $s->id }}">{{ $s->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="input-group"><label>Reference No. <span style="font-weight:400;color:var(--fg-dim);">(optional)</span></label><input type="text" class="input-field" name="reference_no" placeholder="e.g. INV-1042"></div>
        </div>
        <div class="grid grid-2" style="gap:16px;margin-bottom:12px;">
          <div class="input-group"><label>Date Supplied</label><input type="date" class="input-field" name="supply_date" id="puSupplyDate" max="{{ now()->format('Y-m-d') }}" required></div>
          <div class="input-group"><label>Amount Paid Now <span style="font-weight:400;color:var(--fg-dim);">(optional)</span></label><input type="number" class="input-field" name="amount_paid" id="puAmountPaid" step="0.01" min="0" placeholder="Defaults to full total"></div>
        </div>
        <div style="font-size:11px;color:var(--fg-dim);margin:-8px 0 12px;">Leave "Amount Paid" blank to record the purchase as paid in full. Enter a smaller amount (or 0) if the supplier is extending credit — the remaining balance can be settled later from the purchase history table.</div>
        <div class="input-group" style="margin-bottom:12px;"><label>Notes</label><textarea class="input-field" name="notes" rows="2"></textarea></div>

        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
          <label style="font-size:12px;font-weight:600;color:var(--fg-muted);">Line Items</label>
          <button type="button" class="btn btn-secondary btn-sm" id="addPurchaseItemBtn"><i class="bx bx-plus"></i> Add Item</button>
        </div>
        <div id="purchaseItemRows"></div>

        <div class="card" style="text-align:right;margin-top:8px;">
          <span style="font-size:12px;color:var(--fg-muted);">Purchase Total: </span>
          <span style="font-family:'Figtree';font-size:18px;font-weight:700;" id="purchaseTotalDisplay">{{ '$0.00' }}</span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('purchaseModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Save Purchase</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="supplierModal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="supplierModalTitle">Add Supplier</h3>
      <button class="modal-close" onclick="closeModal('supplierModal')" aria-label="Close" data-tooltip="Close"><i class="bx bx-x"></i></button>
    </div>
    <form id="supplierForm" method="POST" action="{{ route('suppliers.store') }}">
      @csrf
      <input type="hidden" name="_method" id="supplierMethod" value="POST">
      <div class="modal-body">
        <div class="grid grid-2" style="gap:16px;">
          <div class="input-group"><label>Supplier Name</label><input type="text" class="input-field" name="name" id="suName" required></div>
          <div class="input-group"><label>Contact Name</label><input type="text" class="input-field" name="contact_name" id="suContact"></div>
          <div class="input-group"><label>Phone</label><input type="tel" class="input-field" name="phone" id="suPhone"></div>
          <div class="input-group"><label>Email</label><input type="email" class="input-field" name="email" id="suEmail"></div>
        </div>
        <div class="input-group" style="margin-top:16px;"><label>Address</label><input type="text" class="input-field" name="address" id="suAddress"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('supplierModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Save Supplier</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="payModal">
  <div class="modal" style="max-width:400px;">
    <div class="modal-header">
      <h3 id="payModalTitle">Record Payment</h3>
      <button class="modal-close" onclick="closeModal('payModal')" aria-label="Close" data-tooltip="Close"><i class="bx bx-x"></i></button>
    </div>
    <form id="payForm" method="POST">
      @csrf @method('PUT')
      <div class="modal-body">
        <div class="card" style="text-align:center;margin-bottom:16px;">
          <div style="font-size:12px;color:var(--fg-muted);margin-bottom:4px;">Balance Owed</div>
          <div style="font-family:'Figtree';font-size:22px;font-weight:700;color:var(--danger);" id="payBalanceDisplay">$0.00</div>
        </div>
        <div class="input-group"><label>Amount to Pay</label><input type="number" class="input-field" name="amount" id="payAmountInput" step="0.01" min="0.01" required autofocus></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('payModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Record Payment</button>
      </div>
    </form>
  </div>
</div>
@endpush

@php
  $puProducts = $products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'icon' => $p->icon, 'cost' => (float) $p->cost])->values();
@endphp
@push('scripts')
<script>
document.querySelectorAll('.tabs .tab').forEach((tab) => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.tabs .tab').forEach((t) => t.classList.remove('active'));
    tab.classList.add('active');
    ['history', 'suppliers'].forEach((t) => {
      const sec = document.getElementById('purchases-' + t);
      if (sec) sec.style.display = t === tab.dataset.tab ? 'block' : 'none';
    });
  });
});

const puProducts = @json($puProducts);
let purchaseRowCount = 0;

function addPurchaseItemRow() {
  const idx = purchaseRowCount++;
  const container = document.getElementById('purchaseItemRows');
  const row = document.createElement('div');
  row.className = 'grid grid-2';
  row.style.cssText = 'gap:8px;margin-bottom:8px;align-items:end;';
  row.dataset.row = idx;
  row.innerHTML = `
    <div class="input-group" style="grid-column:span 1;">
      <select class="input-field pu-product" name="items[${idx}][product_id]" required>
        <option value="">Select product...</option>
        ${puProducts.map((p) => `<option value="${p.id}" data-cost="${p.cost}">${p.icon} ${p.name}</option>`).join('')}
      </select>
    </div>
    <div style="display:flex;gap:8px;">
      <input type="number" class="input-field pu-qty" name="items[${idx}][quantity]" placeholder="Qty" min="1" style="width:80px;" required>
      <input type="number" class="input-field pu-cost" name="items[${idx}][unit_cost]" placeholder="Unit cost" step="0.01" min="0" style="width:110px;" required>
      <button type="button" class="btn btn-danger btn-sm btn-icon" aria-label="Remove item" data-tooltip="Remove item" onclick="removePurchaseItemRow(${idx})"><i class="bx bx-x"></i></button>
    </div>
  `;
  container.appendChild(row);

  const productSelect = row.querySelector('.pu-product');
  const costInput = row.querySelector('.pu-cost');
  productSelect.addEventListener('change', () => {
    const opt = productSelect.selectedOptions[0];
    if (opt && opt.dataset.cost) costInput.value = opt.dataset.cost;
    recalcPurchaseTotal();
  });
  row.querySelectorAll('.pu-qty, .pu-cost').forEach((el) => el.addEventListener('input', recalcPurchaseTotal));
}

function removePurchaseItemRow(idx) {
  document.querySelector(`#purchaseItemRows [data-row="${idx}"]`)?.remove();
  recalcPurchaseTotal();
}

function recalcPurchaseTotal() {
  let total = 0;
  document.querySelectorAll('#purchaseItemRows [data-row]').forEach((row) => {
    const qty = parseFloat(row.querySelector('.pu-qty').value) || 0;
    const cost = parseFloat(row.querySelector('.pu-cost').value) || 0;
    total += qty * cost;
  });
  document.getElementById('purchaseTotalDisplay').textContent = window.formatMoney(total);
}

document.getElementById('addPurchaseItemBtn').addEventListener('click', addPurchaseItemRow);

function openPurchaseModal() {
  document.getElementById('purchaseForm').reset();
  document.getElementById('puSupplyDate').value = new Date().toISOString().slice(0, 10);
  document.getElementById('purchaseItemRows').innerHTML = '';
  purchaseRowCount = 0;
  addPurchaseItemRow();
  recalcPurchaseTotal();
  openModal('purchaseModal');
}

function openPayModal(dataset) {
  const balance = parseFloat(dataset.payBalance);
  document.getElementById('payModalTitle').textContent = `Record Payment — ${dataset.payRef}`;
  document.getElementById('payForm').action = `/purchases/${dataset.payId}/pay`;
  document.getElementById('payBalanceDisplay').textContent = window.formatMoney(balance);
  const amountInput = document.getElementById('payAmountInput');
  amountInput.max = balance;
  amountInput.value = balance;
  openModal('payModal');
}

function openSupplierModal(supplier) {
  const form = document.getElementById('supplierForm');
  document.getElementById('supplierModalTitle').textContent = supplier ? 'Edit Supplier' : 'Add Supplier';
  form.action = supplier ? `/suppliers/${supplier.id}` : @json(route('suppliers.store'));
  document.getElementById('supplierMethod').value = supplier ? 'PUT' : 'POST';
  document.getElementById('suName').value = supplier ? supplier.name : '';
  document.getElementById('suContact').value = supplier ? (supplier.contact_name ?? '') : '';
  document.getElementById('suPhone').value = supplier ? (supplier.phone ?? '') : '';
  document.getElementById('suEmail').value = supplier ? (supplier.email ?? '') : '';
  document.getElementById('suAddress').value = supplier ? (supplier.address ?? '') : '';
  openModal('supplierModal');
}
</script>
@endpush

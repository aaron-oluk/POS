@extends('layouts.app')

@section('title', 'POS Terminal')

@section('content')
<div class="pos-layout">
  <div class="pos-products">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
      <div class="pos-categories" id="posCategories"></div>
      <div class="topbar-search" style="max-width:240px;">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" placeholder="Search items..." id="posSearch" aria-label="Search POS items">
      </div>
    </div>
    <div class="pos-grid" id="posGrid"></div>
  </div>
  <div class="pos-cart" id="posCart">
    <div class="pos-cart-header">
      <h3>Current Order</h3>
      <div style="display:flex;gap:6px;">
        <button class="btn-icon btn-secondary" id="holdOrderBtn" title="Hold Order"><i class="fa-solid fa-pause"></i></button>
        <button class="btn-icon btn-danger" id="clearCartBtn" title="Clear Cart"><i class="fa-solid fa-trash"></i></button>
      </div>
    </div>
    <div class="pos-cart-items" id="posCartItems">
      <div class="pos-cart-empty"><i class="fa-solid fa-basket-shopping"></i><div>Cart is empty</div><div style="font-size:11px;">Tap a product to add it</div></div>
    </div>
    <div class="pos-cart-footer">
      <div class="input-group" style="margin-bottom:10px;">
        <label for="posCustomer">Customer (optional)</label>
        <select class="input-field" id="posCustomer">
          <option value="">Walk-in Customer</option>
          @foreach ($customers as $c)
            <option value="{{ $c->id }}">{{ $c->first_name }} {{ $c->last_name }}</option>
          @endforeach
        </select>
      </div>
      <div class="pos-cart-row"><span style="color:var(--fg-muted)">Subtotal</span><span id="cartSubtotal">$0.00</span></div>
      <div class="pos-cart-row"><span style="color:var(--fg-muted)">{{ $settings->tax_name }} ({{ rtrim(rtrim(number_format($settings->tax_rate, 2), '0'), '.') }}%)</span><span id="cartTax">$0.00</span></div>
      <div class="pos-cart-row"><span style="color:var(--fg-muted)">Discount</span><span id="cartDiscount" style="color:var(--success);">-$0.00</span></div>
      <div class="pos-cart-row total"><span>Total</span><span id="cartTotal">$0.00</span></div>
      <div class="pos-cart-actions">
        <button class="btn btn-secondary" id="showDiscountBtn"><i class="fa-solid fa-percent"></i> Discount</button>
        <button class="btn btn-primary btn-lg" id="showPaymentBtn"><i class="fa-solid fa-credit-card"></i> Pay</button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('modals')
<div class="modal-overlay" id="paymentModal">
  <div class="modal">
    <div class="modal-header">
      <h3>Process Payment</h3>
      <button class="modal-close" onclick="closeModal('paymentModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <div class="pay-amount-display"><div class="label">Amount Due</div><div class="amount" id="payAmount">$0.00</div></div>
      <div class="payment-methods" id="payMethods">
        @if ($settings->cash_enabled)
        <div class="pay-method selected" data-method="cash"><i class="fa-solid fa-money-bill-wave"></i><span>Cash</span></div>
        @endif
        @if ($settings->card_enabled)
        <div class="pay-method" data-method="card"><i class="fa-solid fa-credit-card"></i><span>Card</span></div>
        @endif
        @if ($settings->mobile_enabled)
        <div class="pay-method" data-method="mobile"><i class="fa-solid fa-mobile-screen"></i><span>Mobile</span></div>
        @endif
      </div>
      <div id="cashSection">
        <div class="input-group" style="margin-bottom:12px;">
          <label>Amount Received</label>
          <input type="number" class="input-field" id="cashReceived" placeholder="0.00" style="font-size:18px;font-family:'Space Grotesk';font-weight:700;">
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px;">
          <button class="btn btn-secondary btn-sm quick-cash" data-amt="5">$5</button>
          <button class="btn btn-secondary btn-sm quick-cash" data-amt="10">$10</button>
          <button class="btn btn-secondary btn-sm quick-cash" data-amt="20">$20</button>
          <button class="btn btn-secondary btn-sm quick-cash" data-amt="50">$50</button>
          <button class="btn btn-secondary btn-sm quick-cash" data-amt="100">$100</button>
          <button class="btn btn-secondary btn-sm quick-cash" data-amt="exact">Exact</button>
        </div>
        <div class="card" style="text-align:center;">
          <div style="font-size:12px;color:var(--fg-muted);margin-bottom:4px;">Change Due</div>
          <div style="font-family:'Space Grotesk';font-size:28px;font-weight:700;color:var(--success);" id="changeDue">$0.00</div>
        </div>
      </div>
      @if ($settings->prompt_tips)
      <div id="tipSection" style="margin-top:16px;">
        <label style="font-size:12px;font-weight:600;color:var(--fg-muted);display:block;margin-bottom:8px;">Add Tip</label>
        <div style="display:flex;gap:8px;">
          <button class="btn btn-secondary btn-sm tip-btn" data-pct="0" style="flex:1;">No Tip</button>
          @foreach (explode(',', $settings->tip_options) as $opt)
            <button class="btn btn-secondary btn-sm tip-btn" data-pct="{{ trim($opt) }}" style="flex:1;">{{ trim($opt) }}%</button>
          @endforeach
        </div>
      </div>
      @endif
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('paymentModal')">Cancel</button>
      <button class="btn btn-primary btn-lg" id="processPayBtn"><i class="fa-solid fa-check"></i> Complete Payment</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="discountModal">
  <div class="modal" style="max-width:400px;">
    <div class="modal-header">
      <h3>Apply Discount</h3>
      <button class="modal-close" onclick="closeModal('discountModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <div class="tabs" style="margin-bottom:16px;">
        <button class="tab active" data-type="percent" type="button">Percentage</button>
        <button class="tab" data-type="fixed" type="button">Fixed Amount</button>
      </div>
      <div class="input-group">
        <label id="discountLabel">Discount Percentage</label>
        <input type="number" class="input-field" id="discountValue" placeholder="0" min="0">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" id="removeDiscountBtn">Remove</button>
      <button class="btn btn-primary" id="applyDiscountBtn"><i class="fa-solid fa-check"></i> Apply</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="receiptModal">
  <div class="modal" style="max-width:400px;">
    <div class="modal-header">
      <h3>Receipt</h3>
      <button class="modal-close" onclick="closeModal('receiptModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body" id="receiptContent"></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('receiptModal')">Close</button>
      <button class="btn btn-primary" onclick="showToast('Receipt printed','success');closeModal('receiptModal')"><i class="fa-solid fa-print"></i> Print</button>
    </div>
  </div>
</div>
@endpush

@php
  $posProducts = $products->map(fn ($p) => [
      'id' => $p->id, 'name' => $p->name, 'category' => $p->category->name,
      'price' => (float) $p->price, 'stock' => $p->stock, 'sku' => $p->sku,
      'icon' => $p->icon, 'desc' => $p->description,
  ])->values();
  $posCategories = collect(['All'])->concat($categories);
@endphp
@push('scripts')
<script>
  window.posData = {
    products: @json($posProducts),
    categories: @json($posCategories),
    taxRate: {{ (float) $settings->tax_rate }},
    checkoutUrl: @json(route('pos.checkout')),
  };
</script>
@vite(['resources/js/pages/pos.js'])
@endpush

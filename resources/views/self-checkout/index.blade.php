@extends('layouts.kiosk')

@section('title', 'Self-Checkout')

@section('content')
<div style="display:flex;flex-direction:column;height:calc(100vh - 4rem - 3rem);margin:-1.5rem;background:var(--bg);position:relative;z-index:1;">
  <div class="page-header" style="flex-shrink:0;margin:0;padding:1.5rem 1.5rem 12px;background:var(--bg);">
    <div>
      <h1 class="page-title">Self-Checkout</h1>
      <p class="page-subtitle">Tap items to add them, then pay by card or mobile — no cashier needed</p>
    </div>
    <span class="badge badge-info"><i class="bx bx-scan"></i> Self-Checkout</span>
  </div>
  <div class="pos-layout" style="flex:1;height:auto;min-height:0;margin:0;">
    <div class="pos-products" style="padding:0 1.25rem 1.25rem;">
      <div style="position:sticky;top:0;z-index:2;background:var(--bg);display:flex;align-items:center;justify-content:space-between;padding:1.25rem 0 12px;flex-wrap:wrap;gap:8px;">
        <div class="pos-categories" id="posCategories"></div>
        <div class="topbar-search" style="max-width:200px;">
          <i class="bx bx-barcode"></i>
          <input type="text" placeholder="Scan barcode..." id="posBarcode" aria-label="Scan barcode">
        </div>
        <div class="topbar-search" style="max-width:240px;">
          <i class="bx bxs-search"></i>
          <input type="text" placeholder="Search items..." id="posSearch" aria-label="Search items">
        </div>
      </div>
      <div class="pos-grid" id="posGrid"></div>
    </div>
    <div class="pos-cart" id="posCart">
      <div class="pos-cart-header">
        <h3>Your Order</h3>
        <div style="display:flex;gap:6px;">
          <button class="btn-icon btn-danger" id="clearCartBtn" aria-label="Clear Cart" data-tooltip="Clear Cart"><i class="bx bxs-trash"></i></button>
        </div>
      </div>
      <div class="pos-cart-items" id="posCartItems">
        <div class="pos-cart-empty"><i class="bx bxs-basket"></i><div>Cart is empty</div><div style="font-size:11px;">Tap a product to add it</div></div>
      </div>
      <div class="pos-cart-footer">
        <div class="pos-cart-row"><span style="color:var(--fg-muted)">Subtotal</span><span id="cartSubtotal">$0.00</span></div>
        <div class="pos-cart-row"><span style="color:var(--fg-muted)">{{ $settings->tax_name }} ({{ rtrim(rtrim(number_format($settings->tax_rate, 2), '0'), '.') }}%)</span><span id="cartTax">$0.00</span></div>
        <div class="pos-cart-row total"><span>Total</span><span id="cartTotal">$0.00</span></div>
        <div class="pos-cart-actions">
          <button class="btn btn-primary btn-lg" id="showPaymentBtn" style="width:100%;"><i class="bx bxs-credit-card"></i> Pay</button>
        </div>
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
      <button class="modal-close" onclick="closeModal('paymentModal')" aria-label="Close" data-tooltip="Close"><i class="bx bx-x"></i></button>
    </div>
    <div class="modal-body">
      <div class="pay-amount-display"><div class="label">Amount Due</div><div class="amount" id="payAmount">$0.00</div></div>
      <div class="payment-methods" id="payMethods">
        @if ($settings->card_enabled)
        <button type="button" class="pay-method" data-method="card"><i class="bx bxs-credit-card"></i><span>Card</span></button>
        @endif
        @if ($settings->mobile_enabled)
        <button type="button" class="pay-method" data-method="mobile"><i class="bx bxs-mobile"></i><span>Mobile</span></button>
        @endif
      </div>
      <div id="splitSection" style="display:none;">
        <div id="splitRows"></div>
        <div class="card" style="text-align:center;">
          <div style="font-size:12px;color:var(--fg-muted);margin-bottom:4px;" id="splitStatusLabel">Remaining</div>
          <div style="font-family:'Figtree';font-size:28px;font-weight:700;" id="splitStatusAmount">$0.00</div>
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
      <button class="btn btn-primary btn-lg" id="processPayBtn"><i class="bx bx-check"></i> Complete Payment</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="customizeModal">
  <div class="modal" style="max-width:420px;">
    <div class="modal-header">
      <h3 id="customizeModalTitle">Customize Item</h3>
      <button class="modal-close" onclick="closeModal('customizeModal')" aria-label="Close" data-tooltip="Close"><i class="bx bx-x"></i></button>
    </div>
    <div class="modal-body" id="customizeModalBody"></div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeModal('customizeModal')">Cancel</button>
      <button type="button" class="btn btn-primary" id="customizeAddBtn"><i class="bx bx-cart-add"></i> Add to Cart</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="receiptModal">
  <div class="modal" style="max-width:400px;">
    <div class="modal-header">
      <h3>Receipt</h3>
      <button class="modal-close" onclick="closeModal('receiptModal')" aria-label="Close" data-tooltip="Close"><i class="bx bx-x"></i></button>
    </div>
    <div class="modal-body" id="receiptContent"></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="window.print()"><i class="bx bxs-printer"></i> Print</button>
      <button class="btn btn-primary" style="flex:1;" onclick="closeModal('receiptModal');window.location.reload();"><i class="bx bx-check"></i> Done</button>
    </div>
  </div>
</div>
@endpush

@php
  $posProducts = $products->map(fn ($p) => [
      'id' => $p->id, 'name' => $p->name, 'category' => $p->category->name,
      'price' => (float) $p->price, 'stock' => $p->stock, 'sku' => $p->sku,
      'barcode' => $p->barcode, 'icon' => $p->icon, 'desc' => $p->description,
      'modifierGroups' => $p->modifierGroups->map(fn ($g) => [
          'id' => $g->id, 'name' => $g->name, 'multiple' => (bool) $g->multiple,
          'options' => $g->options->map(fn ($o) => ['id' => $o->id, 'name' => $o->name, 'price_delta' => (float) $o->price_delta]),
      ]),
  ])->values();
  $posCategories = collect(['All'])->concat($categories);
@endphp
@push('scripts')
<script>
  window.posData = {
    products: @json($posProducts),
    categories: @json($posCategories),
    taxRate: {{ (float) $settings->tax_rate }},
    checkoutUrl: @json(route('self-checkout.checkout')),
    storeName: @json($settings->store_name),
  };
</script>
@vite(['resources/js/pages/self-checkout.js'])
@endpush

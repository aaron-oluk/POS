@extends('layouts.app')

@section('title', 'Settings')

@section('content')
<div class="page-header">
  <div>
    <h1 class="page-title">Settings</h1>
    <p class="page-subtitle">Configure your POS system preferences</p>
  </div>
</div>

<div class="tabs">
  <button class="tab active" data-tab="general" type="button">General</button>
  <button class="tab" data-tab="receipt" type="button">Receipt</button>
  <button class="tab" data-tab="payment" type="button">Payment</button>
  <button class="tab" data-tab="tax" type="button">Tax</button>
</div>

<div id="settings-general" class="settings-section">
  <form method="POST" action="{{ route('settings.general') }}">
    @csrf @method('PUT')
    <h3>Store Information</h3>
    <div class="grid grid-2" style="gap:16px;">
      <div class="input-group"><label>Store Name</label><input type="text" class="input-field" name="store_name" value="{{ $settings->store_name }}"></div>
      <div class="input-group"><label>Phone Number</label><input type="text" class="input-field" name="phone" value="{{ $settings->phone }}"></div>
      <div class="input-group"><label>Email Address</label><input type="email" class="input-field" name="email" value="{{ $settings->email }}"></div>
      <div class="input-group"><label>Address</label><input type="text" class="input-field" name="address" value="{{ $settings->address }}"></div>
      <div class="input-group"><label>Currency</label>
        <select class="input-field" name="currency">
          @if (! array_key_exists($settings->currency, $currencies))
            <option value="{{ $settings->currency }}" selected>{{ $settings->currency }} ({{ $settings->currency_symbol }}) — detected</option>
          @endif
          @foreach ($currencies as $code => $c)
            <option value="{{ $code }}" {{ $settings->currency === $code ? 'selected' : '' }}>{{ $code }} ({{ $c['symbol'] }}) — {{ $c['name'] }}</option>
          @endforeach
        </select>
      </div>
      <div class="input-group"><label>Timezone</label>
        <select class="input-field" name="timezone">
          @foreach ($timezoneGroups as $group => $zones)
            <optgroup label="{{ $group }}">
              @foreach ($zones as $tz => $label)
                <option value="{{ $tz }}" {{ $settings->timezone === $tz ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </optgroup>
          @endforeach
        </select>
      </div>
    </div>
    <div style="margin-top:20px;">
      <h3>Appearance</h3>
      <div class="settings-row">
        <div><div class="settings-row-label">Dark Mode</div><div class="settings-row-desc">Use dark theme across the application</div></div>
        <label class="toggle"><input type="checkbox" name="dark_mode" {{ $settings->dark_mode ? 'checked' : '' }}><span class="toggle-slider"></span></label>
      </div>
      <div class="settings-row">
        <div><div class="settings-row-label">Compact Mode</div><div class="settings-row-desc">Reduce spacing for more screen real estate</div></div>
        <label class="toggle"><input type="checkbox" name="compact_mode" {{ $settings->compact_mode ? 'checked' : '' }}><span class="toggle-slider"></span></label>
      </div>
      <div class="settings-row">
        <div><div class="settings-row-label">Sound Effects</div><div class="settings-row-desc">Play sounds for cart actions and payments</div></div>
        <label class="toggle"><input type="checkbox" name="sound_effects" {{ $settings->sound_effects ? 'checked' : '' }}><span class="toggle-slider"></span></label>
      </div>
    </div>
    <div style="margin-top:16px;"><button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Save Changes</button></div>
  </form>
</div>

<div id="settings-receipt" class="settings-section" style="display:none;">
  <form method="POST" action="{{ route('settings.receipt') }}">
    @csrf @method('PUT')
    <h3>Receipt Configuration</h3>
    <div class="grid grid-2" style="gap:16px;">
      <div class="input-group"><label>Receipt Header</label><textarea class="input-field" name="receipt_header" rows="3">{{ $settings->receipt_header }}</textarea></div>
      <div class="input-group"><label>Receipt Footer</label><textarea class="input-field" name="receipt_footer" rows="3">{{ $settings->receipt_footer }}</textarea></div>
      <div class="input-group"><label>Paper Size</label>
        <select class="input-field" name="paper_size">
          @foreach (['80mm (Thermal)', '58mm (Thermal)', 'A4'] as $size)
            <option {{ $settings->paper_size === $size ? 'selected' : '' }}>{{ $size }}</option>
          @endforeach
        </select>
      </div>
      <div class="input-group"><label>Font Size</label>
        <select class="input-field" name="font_size">
          @foreach (['Small', 'Medium', 'Large'] as $size)
            <option {{ $settings->font_size === $size ? 'selected' : '' }}>{{ $size }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div style="margin-top:16px;">
      <div class="settings-row"><div><div class="settings-row-label">Show QR Code</div><div class="settings-row-desc">Display a QR code linking to digital receipt</div></div><label class="toggle"><input type="checkbox" name="show_qr" {{ $settings->show_qr ? 'checked' : '' }}><span class="toggle-slider"></span></label></div>
      <div class="settings-row"><div><div class="settings-row-label">Auto-Print</div><div class="settings-row-desc">Automatically print receipt after payment</div></div><label class="toggle"><input type="checkbox" name="auto_print" {{ $settings->auto_print ? 'checked' : '' }}><span class="toggle-slider"></span></label></div>
    </div>
    <div style="margin-top:16px;"><button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Save Changes</button></div>
  </form>
</div>

<div id="settings-payment" class="settings-section" style="display:none;">
  <form method="POST" action="{{ route('settings.payment') }}">
    @csrf @method('PUT')
    <h3>Checkout Mode</h3>
    <div class="settings-row">
      <div>
        <div class="settings-row-label">Self-Checkout</div>
        <div class="settings-row-desc">Customers complete their own order at the terminal without a cashier. Cash is never offered as a payment option in this mode — regardless of the Cash toggle below — since there's no attendant to handle cash or give change.</div>
      </div>
      <label class="toggle"><input type="checkbox" name="self_checkout_enabled" {{ $settings->self_checkout_enabled ? 'checked' : '' }}><span class="toggle-slider"></span></label>
    </div>
    <h3 style="margin-top:24px;">Payment Methods</h3>
    @foreach ([
      'cash_enabled' => ['Cash', 'Accept cash payments with change calculation'],
      'card_enabled' => ['Credit/Debit Card', 'Process card payments via terminal integration'],
      'mobile_enabled' => ['Mobile Pay (Apple Pay / Google Pay)', 'Accept contactless mobile payments'],
      'gift_cards_enabled' => ['Gift Cards', 'Redeem and sell store gift cards'],
      'split_payment_enabled' => ['Split Payment', 'Allow splitting bills between multiple methods'],
    ] as $field => [$label, $desc])
    <div class="settings-row"><div><div class="settings-row-label">{{ $label }}</div><div class="settings-row-desc">{{ $desc }}</div></div><label class="toggle"><input type="checkbox" name="{{ $field }}" {{ $settings->$field ? 'checked' : '' }}><span class="toggle-slider"></span></label></div>
    @endforeach
    <h3 style="margin-top:24px;">Tip Configuration</h3>
    <div class="grid grid-2" style="gap:16px;">
      <div class="input-group"><label>Tip Options (%)</label><input type="text" class="input-field" name="tip_options" value="{{ $settings->tip_options }}"></div>
      <div class="input-group"><label>Default Tip</label>
        <select class="input-field" name="default_tip">
          @foreach (['None', '18%', '20%'] as $opt)
            <option {{ $settings->default_tip === $opt ? 'selected' : '' }}>{{ $opt }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="settings-row"><div><div class="settings-row-label">Prompt for Tips</div><div class="settings-row-desc">Show tip selection on payment screen</div></div><label class="toggle"><input type="checkbox" name="prompt_tips" {{ $settings->prompt_tips ? 'checked' : '' }}><span class="toggle-slider"></span></label></div>
    <div style="margin-top:16px;"><button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Save Changes</button></div>
  </form>
</div>

<div id="settings-tax" class="settings-section" style="display:none;">
  <form method="POST" action="{{ route('settings.tax') }}">
    @csrf @method('PUT')
    <h3>Tax Configuration</h3>
    <div class="grid grid-2" style="gap:16px;">
      <div class="input-group"><label>Default Tax Rate (%)</label><input type="number" class="input-field" name="tax_rate" value="{{ $settings->tax_rate }}" step="0.1"></div>
      <div class="input-group"><label>Tax Name</label><input type="text" class="input-field" name="tax_name" value="{{ $settings->tax_name }}"></div>
    </div>
    <div class="settings-row"><div><div class="settings-row-label">Tax Included in Prices</div><div class="settings-row-desc">Prices already include tax — show tax as breakdown</div></div><label class="toggle"><input type="checkbox" name="tax_included" {{ $settings->tax_included ? 'checked' : '' }}><span class="toggle-slider"></span></label></div>
    <div class="settings-row"><div><div class="settings-row-label">Round Tax to Nearest Cent</div><div class="settings-row-desc">Round calculated tax to 2 decimal places</div></div><label class="toggle"><input type="checkbox" name="round_tax" {{ $settings->round_tax ? 'checked' : '' }}><span class="toggle-slider"></span></label></div>
    <h3 style="margin-top:24px;">Category Tax Overrides</h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Category</th><th>Tax Rate (%)</th><th>Exempt</th></tr></thead>
        <tbody>
          @foreach ($categories as $c)
          <tr>
            <td>{{ $c->name }}</td>
            <td><input type="number" step="0.1" class="input-field" style="width:120px;" name="categories[{{ $c->id }}][tax_rate]" value="{{ $c->tax_rate }}" placeholder="{{ $settings->tax_rate }} (default)"></td>
            <td><label class="toggle"><input type="checkbox" name="categories[{{ $c->id }}][tax_exempt]" {{ $c->tax_exempt ? 'checked' : '' }}><span class="toggle-slider"></span></label></td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div style="margin-top:16px;"><button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Save Changes</button></div>
  </form>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.tabs .tab').forEach((tab) => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.tabs .tab').forEach((t) => t.classList.remove('active'));
    tab.classList.add('active');
    ['general', 'receipt', 'payment', 'tax'].forEach((t) => {
      const sec = document.getElementById('settings-' + t);
      if (sec) sec.style.display = t === tab.dataset.tab ? 'block' : 'none';
    });
  });
});
</script>
@endpush

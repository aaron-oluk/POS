@extends('layouts.app')

@section('title', 'Cash Register')

@section('content')
<div class="page-header">
  <div>
    <h1 class="page-title">Cash Register</h1>
    <p class="page-subtitle">Open and close your register drawer with a cash count</p>
  </div>
</div>

@if ($openSession)
<div class="card" style="margin-bottom:20px;">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
    <div>
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
        <span class="badge badge-success">Register Open</span>
        <span style="color:var(--fg-muted);font-size:12px;">since @localTime($openSession->opened_at, 'M j, Y g:i A')</span>
      </div>
      <div class="grid grid-3" style="gap:24px;margin-top:12px;">
        <div><div class="stat-label">Opening Float</div><div style="font-family:'Figtree';font-size:20px;font-weight:700;">@money($openSession->opening_float)</div></div>
        <div><div class="stat-label">Cash Sales So Far</div><div style="font-family:'Figtree';font-size:20px;font-weight:700;">@money($liveCashSales)</div></div>
        <div><div class="stat-label">Expected in Drawer</div><div style="font-family:'Figtree';font-size:20px;font-weight:700;color:var(--accent);">@money($openSession->opening_float + $liveCashSales)</div></div>
      </div>
    </div>
    <button class="btn btn-primary" onclick="openCloseRegisterModal()"><i class="bx bx-lock-alt"></i> Close Register</button>
  </div>
</div>
@else
<div class="card" style="margin-bottom:20px;max-width:420px;">
  <h3 style="margin-bottom:12px;">Open Register</h3>
  <form method="POST" action="{{ route('cash-register.open') }}">
    @csrf
    <div class="input-group" style="margin-bottom:12px;">
      <label>Opening Float ({{ \App\Models\Setting::current()->currency_symbol }})</label>
      <input type="number" class="input-field" name="opening_float" step="0.01" min="0" placeholder="0.00" required autofocus>
    </div>
    <button type="submit" class="btn btn-primary"><i class="bx bx-lock-open-alt"></i> Open Register</button>
  </form>
</div>
@endif

<div class="card">
  <h3 style="margin-bottom:12px;">Session History</h3>
  <div class="table-wrap">
    <table>
      <thead><tr>@if(auth()->user()->isManager())<th>Cashier</th>@endif<th>Opened</th><th>Closed</th><th>Opening Float</th><th>Expected</th><th>Counted</th><th>Discrepancy</th></tr></thead>
      <tbody>
        @forelse ($history as $s)
        <tr>
          @if(auth()->user()->isManager())<td style="font-weight:600;">{{ $s->user->name }}</td>@endif
          <td style="color:var(--fg-muted);">@localTime($s->opened_at, 'M j, g:i A')</td>
          <td style="color:var(--fg-muted);">{{ $s->closed_at ? \App\Models\Setting::current()->localTime($s->closed_at, 'M j, g:i A') : '—' }}</td>
          <td>@money($s->opening_float)</td>
          <td>@money($s->expected_cash)</td>
          <td>@money($s->counted_cash)</td>
          <td>
            @php $d = (float) $s->discrepancy; @endphp
            @if (abs($d) < 0.01)
              <span class="badge badge-success">Balanced</span>
            @else
              <span class="badge {{ $d > 0 ? 'badge-info' : 'badge-danger' }}">{{ $d > 0 ? '+' : '' }}@money($d)</span>
            @endif
          </td>
        </tr>
        @empty
        <tr><td colspan="{{ auth()->user()->isManager() ? 7 : 6 }}" style="text-align:center;color:var(--fg-muted);">No closed sessions yet</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  {{ $history->links('pagination.custom') }}
</div>
@endsection

@if ($openSession)
@push('modals')
<div class="modal-overlay" id="closeRegisterModal">
  <div class="modal" style="max-width:420px;">
    <div class="modal-header">
      <h3>Close Register</h3>
      <button class="modal-close" onclick="closeModal('closeRegisterModal')" aria-label="Close" data-tooltip="Close"><i class="bx bx-x"></i></button>
    </div>
    <form method="POST" action="{{ route('cash-register.close', $openSession) }}">
      @csrf @method('PUT')
      <div class="modal-body">
        <div class="input-group" style="margin-bottom:12px;">
          <label>Counted Cash in Drawer</label>
          <input type="number" class="input-field" name="counted_cash" id="crCountedCash" step="0.01" min="0" placeholder="0.00" required autofocus>
        </div>
        <div class="card" style="text-align:center;margin-bottom:12px;">
          <div style="font-size:12px;color:var(--fg-muted);margin-bottom:4px;">Expected in Drawer</div>
          <div style="font-family:'Figtree';font-size:22px;font-weight:700;">@money($openSession->opening_float + $liveCashSales)</div>
        </div>
        <div class="card" style="text-align:center;">
          <div style="font-size:12px;color:var(--fg-muted);margin-bottom:4px;">Discrepancy</div>
          <div style="font-family:'Figtree';font-size:22px;font-weight:700;" id="crDiscrepancy">$0.00</div>
        </div>
        <div class="input-group" style="margin-top:12px;"><label>Notes <span style="font-weight:400;color:var(--fg-dim);">(optional)</span></label><textarea class="input-field" name="notes" rows="2"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('closeRegisterModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Confirm Close</button>
      </div>
    </form>
  </div>
</div>
@endpush

@push('scripts')
<script>
const crExpected = {{ (float) $openSession->opening_float + $liveCashSales }};
function openCloseRegisterModal() {
  document.getElementById('crCountedCash').value = '';
  document.getElementById('crDiscrepancy').textContent = window.formatMoney(-crExpected);
  openModal('closeRegisterModal');
}
document.getElementById('crCountedCash')?.addEventListener('input', (e) => {
  const counted = parseFloat(e.target.value) || 0;
  const diff = counted - crExpected;
  const el = document.getElementById('crDiscrepancy');
  el.textContent = window.formatMoney(diff);
  el.style.color = Math.abs(diff) < 0.01 ? 'var(--success)' : (diff > 0 ? 'var(--info)' : 'var(--danger)');
});
</script>
@endpush
@endif

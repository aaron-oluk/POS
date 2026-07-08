@extends('layouts.app')

@section('title', 'Staff')

@section('content')
<div class="page-header">
  <div>
    <h1 class="page-title">Staff</h1>
    <p class="page-subtitle">Manage employees and their access levels</p>
  </div>
  <button class="btn btn-primary btn-sm" onclick="openStaffModal()"><i class="fa-solid fa-user-plus"></i> Add Staff</button>
</div>
<div class="grid grid-3">
  @foreach ($staff as $s)
  <div class="card" style="text-align:center;">
    <img src="https://picsum.photos/seed/{{ $s->avatar_seed }}/120/120.jpg" alt="{{ $s->name }}" style="width:72px;height:72px;border-radius:50%;object-fit:cover;margin-bottom:12px;border:3px solid {{ $s->color }};">
    <div style="font-weight:700;font-size:15px;">{{ $s->name }}</div>
    <div style="font-size:12px;color:var(--fg-muted);margin:4px 0 12px;">{{ ucfirst($s->role) }}</div>
    <div style="display:flex;justify-content:center;gap:4px;margin-bottom:12px;">
      <span class="badge {{ $s->active ? 'badge-success' : 'badge-muted' }}">{{ $s->active ? 'Active' : 'Inactive' }}</span>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px;">
      <div class="card" style="padding:10px;background:var(--bg-hover);"><div style="font-size:10px;color:var(--fg-dim);">Sales</div><div style="font-family:'Figtree';font-weight:700;font-size:14px;">${{ number_format(($s->orders_sum_total ?? 0) / 1000, 1) }}k</div></div>
      <div class="card" style="padding:10px;background:var(--bg-hover);"><div style="font-size:10px;color:var(--fg-dim);">Orders</div><div style="font-family:'Figtree';font-weight:700;font-size:14px;">{{ $s->orders_count }}</div></div>
    </div>
    <div style="display:flex;gap:6px;justify-content:center;">
      <button class="btn btn-secondary btn-sm" onclick='openStaffModal(@json($s))'><i class="fa-solid fa-pen"></i> Edit</button>
    </div>
  </div>
  @endforeach
</div>
@endsection

@push('modals')
<div class="modal-overlay" id="staffModal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="staffModalTitle">Add Staff Member</h3>
      <button class="modal-close" onclick="closeModal('staffModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form id="staffForm" method="POST" action="{{ route('staff.store') }}">
      @csrf
      <input type="hidden" name="_method" id="staffMethod" value="POST">
      <div class="modal-body">
        <div class="grid grid-2" style="gap:16px;">
          <div class="input-group"><label>Full Name</label><input type="text" class="input-field" name="name" id="sName" placeholder="Jane Smith" required></div>
          <div class="input-group"><label>Email</label><input type="email" class="input-field" name="email" id="sEmail" placeholder="jane@nexuscoffee.com" required></div>
          <div class="input-group"><label>Role</label>
            <select class="input-field" name="role" id="sRole">
              <option value="cashier">Cashier</option>
              <option value="barista">Barista</option>
              <option value="manager">Manager</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div class="input-group"><label>Phone</label><input type="tel" class="input-field" name="phone" id="sPhone" placeholder="+1 (555) 000-0000"></div>
          <div class="input-group"><label>Password <span style="font-weight:400;color:var(--fg-dim);">(leave blank to keep current)</span></label><input type="password" class="input-field" name="password" id="sPassword" placeholder="••••••••"></div>
        </div>
        <div class="settings-row" style="margin-top:12px;">
          <div><div class="settings-row-label">Active</div><div class="settings-row-desc">Employee can log in and process transactions</div></div>
          <label class="toggle"><input type="checkbox" name="active" id="sActive" checked><span class="toggle-slider"></span></label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('staffModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save Staff</button>
      </div>
    </form>
  </div>
</div>
@endpush

@push('scripts')
<script>
function openStaffModal(staff) {
  const form = document.getElementById('staffForm');
  document.getElementById('staffModalTitle').textContent = staff ? 'Edit Staff Member' : 'Add Staff Member';
  form.action = staff ? `/staff/${staff.id}` : @json(route('staff.store'));
  document.getElementById('staffMethod').value = staff ? 'PUT' : 'POST';
  document.getElementById('sName').value = staff ? staff.name : '';
  document.getElementById('sEmail').value = staff ? staff.email : '';
  document.getElementById('sRole').value = staff ? staff.role : 'cashier';
  document.getElementById('sPhone').value = staff ? (staff.phone ?? '') : '';
  document.getElementById('sPassword').value = '';
  document.getElementById('sActive').checked = staff ? !!staff.active : true;
  openModal('staffModal');
}
</script>
@endpush

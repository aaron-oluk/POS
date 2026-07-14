@extends('layouts.app')

@section('title', 'Staff')

@section('content')
<div class="page-header">
  <div>
    <h1 class="page-title">Staff</h1>
    <p class="page-subtitle">Manage employees and their access levels</p>
  </div>
  <button class="btn btn-primary btn-sm" onclick="openStaffModal()"><i class="bx bxs-user-plus"></i> Add Staff</button>
</div>
<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Staff</th><th>Role</th><th>Status</th><th>Sales</th><th>Orders</th><th>Actions</th></tr></thead>
      <tbody>
        @forelse ($staff as $s)
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px;">
              <img src="https://picsum.photos/seed/{{ $s->avatar_seed }}/60/60.jpg" alt="{{ $s->name }}" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid {{ $s->color }};">
              <div>
                <div style="font-weight:600;">{{ $s->name }}</div>
                <div style="font-size:11px;color:var(--fg-dim);">{{ $s->email }}</div>
              </div>
            </div>
          </td>
          <td><span class="badge badge-muted">{{ ucfirst($s->role) }}</span></td>
          <td><span class="badge {{ $s->active ? 'badge-success' : 'badge-muted' }}">{{ $s->active ? 'Active' : 'Inactive' }}</span></td>
          <td style="font-family:'Figtree';font-weight:600;">@money($s->orders_sum_total ?? 0)</td>
          <td style="font-family:'Figtree';font-weight:600;">{{ $s->orders_count }}</td>
          <td>
            <button class="btn btn-secondary btn-sm btn-icon" aria-label="Edit" data-tooltip="Edit" onclick='openStaffModal(@json($s))'><i class="bx bxs-pencil" style="font-size:11px;"></i></button>
          </td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center;color:var(--fg-muted);">No staff found</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection

@push('modals')
<div class="modal-overlay" id="staffModal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="staffModalTitle">Add Staff Member</h3>
      <button class="modal-close" onclick="closeModal('staffModal')" aria-label="Close" data-tooltip="Close"><i class="bx bx-x"></i></button>
    </div>
    <form id="staffForm" method="POST" action="{{ route('staff.store') }}">
      @csrf
      <input type="hidden" name="_method" id="staffMethod" value="POST">
      <div class="modal-body">
        <div class="grid grid-2" style="gap:16px;">
          <div class="input-group"><label>Full Name</label><input type="text" class="input-field" name="name" id="sName" placeholder="Jane Smith" required></div>
          <div class="input-group"><label>Email</label><input type="email" class="input-field" name="email" id="sEmail" placeholder="jane@pos.com" required></div>
          <div class="input-group"><label>Role</label>
            <select class="input-field" name="role" id="sRole">
              <option value="cashier">Cashier</option>
              <option value="barista">Barista</option>
              <option value="manager">Manager</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div class="input-group"><label>Phone</label><input type="tel" class="input-field" name="phone" id="sPhone" placeholder="+1 (555) 000-0000"></div>
          <div class="input-group"><label>Password <span style="font-weight:400;color:var(--fg-dim);">(leave blank to keep current)</span></label>
            <div class="password-field">
              <input type="password" class="input-field" name="password" id="sPassword" placeholder="••••••••">
              <button type="button" class="password-toggle" onclick="togglePasswordVisibility(this)" aria-label="Show password" data-tooltip="Show password"><i class="bx bx-hide"></i></button>
            </div>
          </div>
        </div>
        <div class="settings-row" style="margin-top:12px;">
          <div><div class="settings-row-label">Active</div><div class="settings-row-desc">Employee can log in and process transactions</div></div>
          <label class="toggle"><input type="checkbox" name="active" id="sActive" checked><span class="toggle-slider"></span></label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('staffModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Save Staff</button>
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

@extends('layouts.app')

@section('title', 'Customers')

@php
  $tierColors = ['gold' => 'var(--warning)', 'silver' => 'var(--fg-muted)', 'bronze' => '#cd7f32'];
@endphp

@section('content')
<div class="page-header">
  <div>
    <h1 class="page-title">Customers</h1>
    <p class="page-subtitle">View and manage your customer database</p>
  </div>
  <button class="btn btn-primary btn-sm" onclick="openCustomerModal()"><i class="fa-solid fa-user-plus"></i> Add Customer</button>
</div>

<div class="grid grid-3" style="margin-bottom:20px;">
  <div class="card" style="display:flex;align-items:center;gap:12px;"><div class="stat-icon" style="background:var(--accent-dim);color:var(--accent);width:40px;height:40px;font-size:15px;"><i class="fa-solid fa-users"></i></div><div><div class="stat-label">Total Customers</div><div style="font-family:'Figtree';font-size:22px;font-weight:700;">{{ $totalCustomers }}</div></div></div>
  <div class="card" style="display:flex;align-items:center;gap:12px;"><div class="stat-icon" style="background:var(--warning-dim);color:var(--warning);width:40px;height:40px;font-size:15px;"><i class="fa-solid fa-crown"></i></div><div><div class="stat-label">Gold Members</div><div style="font-family:'Figtree';font-size:22px;font-weight:700;">{{ $goldCount }}</div></div></div>
  <div class="card" style="display:flex;align-items:center;gap:12px;"><div class="stat-icon" style="background:var(--success-dim);color:var(--success);width:40px;height:40px;font-size:15px;"><i class="fa-solid fa-chart-simple"></i></div><div><div class="stat-label">Avg. Spend</div><div style="font-family:'Figtree';font-size:22px;font-weight:700;">${{ number_format($avgSpend, 0) }}</div></div></div>
</div>

<div class="card">
  <form method="GET" style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
    <div class="topbar-search" style="max-width:280px;flex:1;">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input type="text" name="q" placeholder="Search customers..." value="{{ $search }}">
    </div>
    <select class="input-field" style="width:auto;height:38px;" name="tier" onchange="this.form.submit()">
      <option value="">All Tiers</option>
      <option value="gold" {{ $tierFilter === 'gold' ? 'selected' : '' }}>Gold</option>
      <option value="silver" {{ $tierFilter === 'silver' ? 'selected' : '' }}>Silver</option>
      <option value="bronze" {{ $tierFilter === 'bronze' ? 'selected' : '' }}>Bronze</option>
    </select>
    <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
  </form>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Customer</th><th>Email</th><th>Phone</th><th>Orders</th><th>Total Spent</th><th>Tier</th><th>Actions</th></tr></thead>
      <tbody>
        @forelse ($customers as $c)
        <tr>
          <td><div style="display:flex;align-items:center;gap:10px;">
            <div class="avatar" style="background:{{ $c->color }};color:#0f1117;">{{ $c->initials }}</div>
            <div><div style="font-weight:600;">{{ $c->full_name }}</div><div style="font-size:11px;color:var(--fg-dim);">Since {{ $c->created_at->format('Y-m-d') }}</div></div>
          </div></td>
          <td style="color:var(--fg-muted);">{{ $c->email }}</td>
          <td style="color:var(--fg-muted);">{{ $c->phone }}</td>
          <td style="font-family:'Figtree';font-weight:600;">{{ $c->orders_count }}</td>
          <td style="font-family:'Figtree';font-weight:600;">${{ number_format($c->total_spent, 2) }}</td>
          <td><span class="badge" style="background:{{ $tierColors[$c->tier] }}22;color:{{ $tierColors[$c->tier] }};"><i class="fa-solid fa-crown" style="font-size:9px;"></i> {{ ucfirst($c->tier) }}</span></td>
          <td>
            <div style="display:flex;gap:4px;">
              <button class="btn btn-secondary btn-sm btn-icon" title="Edit" onclick='openCustomerModal(@json($c))'><i class="fa-solid fa-pen" style="font-size:11px;"></i></button>
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center;color:var(--fg-muted);">No customers found</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  {{ $customers->links('pagination.custom') }}
</div>
@endsection

@push('modals')
<div class="modal-overlay" id="customerModal">
  <div class="modal" style="max-width:460px;">
    <div class="modal-header">
      <h3 id="customerModalTitle">Add Customer</h3>
      <button class="modal-close" onclick="closeModal('customerModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form id="customerForm" method="POST" action="{{ route('customers.store') }}">
      @csrf
      <input type="hidden" name="_method" id="customerMethod" value="POST">
      <div class="modal-body">
        <div class="grid grid-2" style="gap:16px;">
          <div class="input-group"><label>First Name</label><input type="text" class="input-field" name="first_name" id="cFirst" placeholder="John" required></div>
          <div class="input-group"><label>Last Name</label><input type="text" class="input-field" name="last_name" id="cLast" placeholder="Doe" required></div>
          <div class="input-group"><label>Email</label><input type="email" class="input-field" name="email" id="cEmail" placeholder="john@example.com"></div>
          <div class="input-group"><label>Phone</label><input type="tel" class="input-field" name="phone" id="cPhone" placeholder="+1 (555) 000-0000"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('customerModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save Customer</button>
      </div>
    </form>
  </div>
</div>
@endpush

@push('scripts')
<script>
function openCustomerModal(customer) {
  const form = document.getElementById('customerForm');
  document.getElementById('customerModalTitle').textContent = customer ? 'Edit Customer' : 'Add Customer';
  form.action = customer ? `/customers/${customer.id}` : @json(route('customers.store'));
  document.getElementById('customerMethod').value = customer ? 'PUT' : 'POST';
  document.getElementById('cFirst').value = customer ? customer.first_name : '';
  document.getElementById('cLast').value = customer ? customer.last_name : '';
  document.getElementById('cEmail').value = customer ? (customer.email ?? '') : '';
  document.getElementById('cPhone').value = customer ? (customer.phone ?? '') : '';
  openModal('customerModal');
}
</script>
@endpush

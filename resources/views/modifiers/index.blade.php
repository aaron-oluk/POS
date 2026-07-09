@extends('layouts.app')

@section('title', 'Modifiers')

@section('content')
<div class="page-header">
  <div>
    <h1 class="page-title">Product Modifiers</h1>
    <p class="page-subtitle">Add-on options like size or milk type, priced per selection and attached to products</p>
  </div>
  <button class="btn btn-primary btn-sm" onclick="openModifierGroupModal()"><i class="bx bx-plus"></i> Add Modifier Group</button>
</div>

<div class="grid grid-2" style="gap:16px;">
  @forelse ($groups as $g)
  <div class="card">
    <div style="display:flex;align-items:start;justify-content:space-between;margin-bottom:8px;">
      <div>
        <h3 style="margin-bottom:2px;">{{ $g->name }}</h3>
        <span class="badge badge-muted">{{ $g->multiple ? 'Multiple choice' : 'Single choice' }}</span>
      </div>
      <div style="display:flex;gap:4px;">
        <button class="btn btn-secondary btn-sm btn-icon" aria-label="Edit" data-tooltip="Edit" onclick='openModifierGroupModal(@json($g->load("options","products")))'><i class="bx bxs-pencil" style="font-size:11px;"></i></button>
        <form method="POST" action="{{ route('modifiers.destroy', $g) }}" data-confirm="Delete the {{ $g->name }} modifier group?" data-confirm-title="Delete Modifier Group" data-confirm-label="Delete">
          @csrf @method('DELETE')
          <button type="submit" class="btn btn-danger btn-sm btn-icon" aria-label="Delete" data-tooltip="Delete"><i class="bx bxs-trash" style="font-size:11px;"></i></button>
        </form>
      </div>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;">
      @foreach ($g->options as $o)
        <span class="badge badge-info">{{ $o->name }} {{ $o->price_delta > 0 ? '+'.number_format($o->price_delta, 2) : ($o->price_delta < 0 ? number_format($o->price_delta, 2) : '') }}</span>
      @endforeach
    </div>
    <div style="font-size:11px;color:var(--fg-muted);">
      Applies to: {{ $g->products->pluck('name')->join(', ') ?: 'No products yet' }}
    </div>
  </div>
  @empty
  <div class="card" style="grid-column:1/-1;text-align:center;color:var(--fg-muted);">No modifier groups yet — add one to offer options like size or milk type at checkout.</div>
  @endforelse
</div>
@endsection

@push('modals')
<div class="modal-overlay" id="modifierGroupModal">
  <div class="modal" style="max-width:600px;">
    <div class="modal-header">
      <h3 id="modifierGroupModalTitle">Add Modifier Group</h3>
      <button class="modal-close" onclick="closeModal('modifierGroupModal')" aria-label="Close" data-tooltip="Close"><i class="bx bx-x"></i></button>
    </div>
    <form id="modifierGroupForm" method="POST" action="{{ route('modifiers.store') }}">
      @csrf
      <input type="hidden" name="_method" id="mgMethod" value="POST">
      <div class="modal-body">
        <div class="grid grid-2" style="gap:16px;margin-bottom:12px;">
          <div class="input-group"><label>Group Name</label><input type="text" class="input-field" name="name" id="mgName" placeholder="e.g. Milk Type" required></div>
          <div class="settings-row" style="border:none;padding-top:22px;">
            <div><div class="settings-row-label">Allow multiple selections</div></div>
            <label class="toggle"><input type="checkbox" name="multiple" id="mgMultiple"><span class="toggle-slider"></span></label>
          </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
          <label style="font-size:12px;font-weight:600;color:var(--fg-muted);">Options</label>
          <button type="button" class="btn btn-secondary btn-sm" id="addModifierOptionBtn"><i class="bx bx-plus"></i> Add Option</button>
        </div>
        <div id="modifierOptionRows"></div>

        <div style="margin-top:16px;">
          <label style="font-size:12px;font-weight:600;color:var(--fg-muted);display:block;margin-bottom:8px;">Applies To Products</label>
          <div style="max-height:160px;overflow-y:auto;border:1px solid var(--border);border-radius:var(--radius-sm);padding:10px;display:grid;grid-template-columns:1fr 1fr;gap:6px;">
            @foreach ($products as $p)
            <label style="display:flex;align-items:center;gap:6px;font-size:12px;">
              <input type="checkbox" name="product_ids[]" value="{{ $p->id }}" class="mg-product-checkbox">
              {{ $p->icon }} {{ $p->name }}
            </label>
            @endforeach
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modifierGroupModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Save Modifier Group</button>
      </div>
    </form>
  </div>
</div>
@endpush

@push('scripts')
<script>
let modifierOptionRowCount = 0;

function addModifierOptionRow(name = '', priceDelta = '') {
  const idx = modifierOptionRowCount++;
  const container = document.getElementById('modifierOptionRows');
  const row = document.createElement('div');
  row.style.cssText = 'display:flex;gap:8px;margin-bottom:8px;';
  row.dataset.row = idx;
  row.innerHTML = `
    <input type="text" class="input-field" name="options[${idx}][name]" placeholder="Option name (e.g. Oat Milk)" value="${name}" required>
    <input type="number" class="input-field" name="options[${idx}][price_delta]" placeholder="+0.00" step="0.01" value="${priceDelta}" style="width:110px;" required>
    <button type="button" class="btn btn-danger btn-sm btn-icon" aria-label="Remove option" data-tooltip="Remove option" onclick="this.closest('[data-row]').remove()"><i class="bx bx-x"></i></button>
  `;
  container.appendChild(row);
}

document.getElementById('addModifierOptionBtn').addEventListener('click', () => addModifierOptionRow());

function openModifierGroupModal(group) {
  const form = document.getElementById('modifierGroupForm');
  document.getElementById('modifierGroupModalTitle').textContent = group ? 'Edit Modifier Group' : 'Add Modifier Group';
  form.action = group ? `/modifiers/${group.id}` : @json(route('modifiers.store'));
  document.getElementById('mgMethod').value = group ? 'PUT' : 'POST';
  document.getElementById('mgName').value = group ? group.name : '';
  document.getElementById('mgMultiple').checked = group ? !!group.multiple : false;

  document.getElementById('modifierOptionRows').innerHTML = '';
  modifierOptionRowCount = 0;
  if (group && group.options && group.options.length) {
    group.options.forEach((o) => addModifierOptionRow(o.name, o.price_delta));
  } else {
    addModifierOptionRow();
  }

  const productIds = group ? (group.products || []).map((p) => p.id) : [];
  document.querySelectorAll('.mg-product-checkbox').forEach((cb) => {
    cb.checked = productIds.includes(parseInt(cb.value, 10));
  });

  openModal('modifierGroupModal');
}
</script>
@endpush

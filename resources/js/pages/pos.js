const { products, categories, checkoutUrl } = window.posData;

const CART_STORAGE_KEY = 'nexus-pos-cart-v1';

let cart = [];
let discountType = 'percent';
let discountValue = 0;
let currentTip = 0;
let currentPosCategory = 'All';
let currentCustomizeProduct = null;

// A cart line is keyed by product + the sorted set of chosen modifier option
// ids, so e.g. "Latte, Oat Milk" and "Latte, Whole Milk" stay separate lines
// even though they're the same product.
function cartKey(productId, modifiers) {
    const ids = modifiers.map((m) => m.id).sort((a, b) => a - b).join(',');
    return `${productId}:${ids}`;
}

// ===== CART PERSISTENCE =====
// The cart is plain in-memory JS state, which would normally be wiped out
// the moment the cashier navigates to another page (this is a regular
// server-rendered app, not an SPA). Mirror it to localStorage so a held
// selection survives navigating away and back, and only actually disappears
// when the cashier clears it (or completes checkout).
function saveCartState() {
    try {
        localStorage.setItem(CART_STORAGE_KEY, JSON.stringify({
            items: cart.map((c) => ({ productId: c.product.id, qty: c.qty, modifierOptionIds: c.modifiers.map((m) => m.id) })),
            discountType,
            discountValue,
            customerId: document.getElementById('posCustomer')?.value || '',
        }));
    } catch (e) {
        // localStorage unavailable (e.g. private browsing quota) — cart just won't persist.
    }
}

function clearCartState() {
    try {
        localStorage.removeItem(CART_STORAGE_KEY);
    } catch (e) {
        // ignore
    }
}

function restoreCartState() {
    let saved;
    try {
        saved = JSON.parse(localStorage.getItem(CART_STORAGE_KEY) || 'null');
    } catch (e) {
        saved = null;
    }
    if (!saved) return;

    const restoredItems = [];
    (saved.items || []).forEach((item) => {
        const product = products.find((p) => p.id === item.productId);
        if (!product || product.stock <= 0) return;
        const allOptions = (product.modifierGroups || []).flatMap((g) => g.options);
        const modifiers = (item.modifierOptionIds || []).map((id) => allOptions.find((o) => o.id === id)).filter(Boolean);
        const unitPrice = product.price + modifiers.reduce((s, m) => s + m.price_delta, 0);
        restoredItems.push({
            key: cartKey(product.id, modifiers),
            product,
            qty: Math.min(item.qty, product.stock),
            modifiers,
            unitPrice,
        });
    });
    cart = restoredItems;

    if (saved.discountType === 'percent' || saved.discountType === 'fixed') {
        discountType = saved.discountType;
    }
    discountValue = Number(saved.discountValue) || 0;

    const discountTab = document.querySelector(`#discountModal .tab[data-type="${discountType}"]`);
    if (discountTab) {
        document.querySelectorAll('#discountModal .tab').forEach((t) => t.classList.remove('active'));
        discountTab.classList.add('active');
        document.getElementById('discountLabel').textContent = discountType === 'percent' ? 'Discount Percentage' : `Discount Amount (${window.currency?.symbol || '$'})`;
    }
    document.getElementById('discountValue').value = discountValue || '';

    const customerSelect = document.getElementById('posCustomer');
    if (customerSelect && saved.customerId && [...customerSelect.options].some((o) => o.value === saved.customerId)) {
        customerSelect.value = saved.customerId;
    }
}

function renderPosCategories() {
    document.getElementById('posCategories').innerHTML = categories.map((c) =>
        `<span class="pos-cat ${c === currentPosCategory ? 'active' : ''}" data-cat="${c}">${c}</span>`
    ).join('');
    document.querySelectorAll('.pos-cat').forEach((el) => {
        el.addEventListener('click', () => {
            currentPosCategory = el.dataset.cat;
            renderPosCategories();
            renderPosGrid();
        });
    });
}

function renderPosGrid() {
    const search = (document.getElementById('posSearch')?.value || '').toLowerCase();
    const filtered = products.filter((p) => {
        if (p.stock <= 0) return false;
        if (currentPosCategory !== 'All' && p.category !== currentPosCategory) return false;
        if (search && !p.name.toLowerCase().includes(search) && !p.sku.toLowerCase().includes(search)) return false;
        return true;
    });
    document.getElementById('posGrid').innerHTML = filtered.map((p) => `
        <div class="pos-item" data-id="${p.id}" title="${p.desc ?? ''}">
            <div class="pos-item-img">${p.icon}</div>
            <div class="pos-item-name">${p.name}${p.modifierGroups && p.modifierGroups.length ? ' <i class="bx bx-slider-alt" style="font-size:10px;color:var(--fg-dim);" data-tooltip="Customizable"></i>' : ''}</div>
            <div class="pos-item-price">${window.formatMoney(p.price)}</div>
            <div class="pos-item-stock">${p.stock} in stock</div>
        </div>
    `).join('');
    document.querySelectorAll('.pos-item').forEach((el) => {
        el.addEventListener('click', () => addToCart(parseInt(el.dataset.id, 10)));
    });
}

function addToCart(productId) {
    const p = products.find((x) => x.id === productId);
    if (!p) return;
    if (p.stock <= 0) {
        showToast(`${p.name} is out of stock`, 'warning');
        return;
    }
    if (p.modifierGroups && p.modifierGroups.length) {
        openCustomizeModal(p);
    } else {
        addToCartWithModifiers(p, []);
    }
}

function addToCartWithModifiers(product, modifiers) {
    const key = cartKey(product.id, modifiers);
    const existingProductQty = cart.filter((c) => c.product.id === product.id).reduce((s, c) => s + c.qty, 0);
    if (existingProductQty >= product.stock) {
        showToast(`${product.name} is out of stock`, 'warning');
        return;
    }
    const existingLine = cart.find((c) => c.key === key);
    if (existingLine) {
        existingLine.qty++;
    } else {
        const unitPrice = product.price + modifiers.reduce((s, m) => s + m.price_delta, 0);
        cart.push({ key, product, qty: 1, modifiers, unitPrice });
    }
    renderCart();
    showToast(`Added ${product.name}`, 'success');
}

function openCustomizeModal(product) {
    currentCustomizeProduct = product;
    document.getElementById('customizeModalTitle').textContent = `Customize ${product.icon} ${product.name}`;
    document.getElementById('customizeModalBody').innerHTML = product.modifierGroups.map((g) => `
        <div style="margin-bottom:14px;">
            <label style="font-size:12px;font-weight:600;color:var(--fg-muted);display:block;margin-bottom:6px;">${g.name}${g.multiple ? '' : ' (choose one)'}</label>
            ${g.options.map((o, i) => `
                <label style="display:flex;align-items:center;justify-content:space-between;padding:6px 0;font-size:13px;cursor:pointer;">
                    <span style="display:flex;align-items:center;gap:8px;">
                        <input type="${g.multiple ? 'checkbox' : 'radio'}" name="mg-${g.id}" value="${o.id}" ${(!g.multiple && i === 0) ? 'checked' : ''}>
                        ${o.name}
                    </span>
                    <span style="color:var(--fg-muted);">${o.price_delta !== 0 ? (o.price_delta > 0 ? '+' : '') + window.formatMoney(o.price_delta) : ''}</span>
                </label>
            `).join('')}
        </div>
    `).join('');
    openModal('customizeModal');
}

document.getElementById('customizeAddBtn').addEventListener('click', () => {
    if (!currentCustomizeProduct) return;
    const modifiers = [];
    currentCustomizeProduct.modifierGroups.forEach((g) => {
        document.querySelectorAll(`input[name="mg-${g.id}"]:checked`).forEach((input) => {
            const opt = g.options.find((o) => o.id === parseInt(input.value, 10));
            if (opt) modifiers.push(opt);
        });
    });
    addToCartWithModifiers(currentCustomizeProduct, modifiers);
    closeModal('customizeModal');
});

function updateCartQty(key, delta) {
    const item = cart.find((c) => c.key === key);
    if (!item) return;
    const otherQty = cart.filter((c) => c.product.id === item.product.id && c.key !== key).reduce((s, c) => s + c.qty, 0);
    item.qty += delta;
    if (item.qty <= 0) {
        cart = cart.filter((c) => c.key !== key);
    } else if (item.qty + otherQty > item.product.stock) {
        item.qty = Math.max(1, item.product.stock - otherQty);
        showToast('Max stock reached', 'warning');
    }
    renderCart();
}

function removeFromCart(key) {
    cart = cart.filter((c) => c.key !== key);
    renderCart();
}

function clearCart() {
    if (cart.length === 0) return;
    cart = [];
    discountValue = 0;
    currentTip = 0;
    clearCartState();
    renderCart();
    showToast('Cart cleared', 'info');
}

function getCartTotals() {
    const subtotal = cart.reduce((s, c) => s + c.unitPrice * c.qty, 0);
    let disc = discountType === 'percent' ? subtotal * (discountValue / 100) : discountValue;
    disc = Math.min(disc, subtotal);
    const afterDisc = subtotal - disc;
    const tax = afterDisc * (window.posData.taxRate / 100);
    const tip = afterDisc * (currentTip / 100);
    const total = afterDisc + tax + tip;
    return { subtotal, disc, tax, tip, total };
}

function renderCart() {
    const container = document.getElementById('posCartItems');
    if (cart.length === 0) {
        container.innerHTML = '<div class="pos-cart-empty"><i class="bx bxs-basket"></i><div>Cart is empty</div><div style="font-size:11px;">Tap a product to add it</div></div>';
    } else {
        container.innerHTML = cart.map((c) => `
            <div class="pos-cart-item">
                <div class="pos-cart-item-info">
                    <div class="pos-cart-item-name">${c.product.icon} ${c.product.name}</div>
                    ${c.modifiers.length ? `<div style="font-size:10px;color:var(--fg-dim);">${c.modifiers.map((m) => m.name).join(', ')}</div>` : ''}
                    <div class="pos-cart-item-price">${window.formatMoney(c.unitPrice)} each</div>
                </div>
                <div class="pos-cart-qty">
                    <button data-key="${c.key}" data-delta="-1" aria-label="Decrease quantity" data-tooltip="Decrease quantity"><i class="bx bx-minus" style="font-size:10px;"></i></button>
                    <span>${c.qty}</span>
                    <button data-key="${c.key}" data-delta="1" aria-label="Increase quantity" data-tooltip="Increase quantity"><i class="bx bx-plus" style="font-size:10px;"></i></button>
                </div>
                <div class="pos-cart-item-total">${window.formatMoney(c.unitPrice * c.qty)}</div>
                <button class="pos-cart-item-remove" data-remove-key="${c.key}" aria-label="Remove from cart" data-tooltip="Remove from cart"><i class="bx bx-x"></i></button>
            </div>
        `).join('');
        container.querySelectorAll('[data-delta]').forEach((btn) => {
            btn.addEventListener('click', () => updateCartQty(btn.dataset.key, parseInt(btn.dataset.delta, 10)));
        });
        container.querySelectorAll('[data-remove-key]').forEach((btn) => {
            btn.addEventListener('click', () => removeFromCart(btn.dataset.removeKey));
        });
    }
    const t = getCartTotals();
    document.getElementById('cartSubtotal').textContent = window.formatMoney(t.subtotal);
    document.getElementById('cartTax').textContent = window.formatMoney(t.tax);
    document.getElementById('cartDiscount').textContent = '-' + window.formatMoney(t.disc);
    document.getElementById('cartTotal').textContent = window.formatMoney(t.total);
    renderPosGrid();
    saveCartState();
}

document.getElementById('posSearch').addEventListener('input', renderPosGrid);

// Barcode scanners behave like a fast keyboard typing the code followed by Enter.
document.getElementById('posBarcode')?.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const code = e.target.value.trim();
    e.target.value = '';
    if (!code) return;
    const product = products.find((p) => p.barcode && p.barcode === code);
    if (!product) {
        showToast(`No product found for barcode ${code}`, 'error');
        return;
    }
    addToCart(product.id);
});
document.getElementById('holdOrderBtn').addEventListener('click', () => {
    if (cart.length === 0) { showToast('Cart is empty', 'warning'); return; }
    showToast('Order held successfully. You can start a new order.', 'info');
    cart = []; discountValue = 0; currentTip = 0; renderCart();
});
document.getElementById('clearCartBtn').addEventListener('click', clearCart);

// ===== Discount modal =====
document.getElementById('showDiscountBtn').addEventListener('click', () => openModal('discountModal'));
document.querySelectorAll('#discountModal .tab').forEach((tab) => {
    tab.addEventListener('click', () => {
        discountType = tab.dataset.type;
        document.querySelectorAll('#discountModal .tab').forEach((t) => t.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById('discountLabel').textContent = discountType === 'percent' ? 'Discount Percentage' : `Discount Amount (${window.currency?.symbol || '$'})`;
    });
});
document.getElementById('applyDiscountBtn').addEventListener('click', () => {
    const v = parseFloat(document.getElementById('discountValue').value) || 0;
    if (discountType === 'percent' && (v < 0 || v > 100)) { showToast('Percentage must be 0-100', 'error'); return; }
    if (discountType === 'fixed' && v < 0) { showToast('Amount must be positive', 'error'); return; }
    discountValue = v;
    renderCart();
    closeModal('discountModal');
    showToast('Discount applied', 'success');
});
document.getElementById('removeDiscountBtn').addEventListener('click', () => {
    discountValue = 0;
    renderCart();
    closeModal('discountModal');
});

// ===== Payment modal =====
// A single method (the common case) behaves exactly as before: pick one,
// pay the full total through it. Selecting more than one method switches to
// "split" mode — a per-method amount input appears for each selected method,
// and a running Remaining/Change Due indicator tracks whether they add up.

const METHOD_LABELS = { cash: 'Cash', card: 'Card', mobile: 'Mobile' };
const METHOD_ORDER = ['cash', 'card', 'mobile'];

// Cash may not be rendered at all in self-checkout mode, so the default
// selection is whichever method button actually exists first (in
// METHOD_ORDER), not a hardcoded 'cash'.
function firstAvailableMethod() {
    const available = [...document.querySelectorAll('.pay-method')].map((el) => el.dataset.method);
    return METHOD_ORDER.find((m) => available.includes(m)) || available[0];
}

let selectedPayMethods = new Set();
let splitAmounts = {};

function round2(n) {
    return Math.round((n + Number.EPSILON) * 100) / 100;
}

function isSplitMode() {
    return selectedPayMethods.size > 1;
}

// Rounds up to a "nice" 1/2/5-style step at the value's own order of magnitude,
// so quick-cash suggestions look like real bills/notes in whatever currency is active.
function niceRoundUp(value) {
    if (!isFinite(value) || value <= 0) return 0;
    const magnitude = Math.pow(10, Math.floor(Math.log10(value)));
    const normalized = value / magnitude;
    let nice;
    if (normalized <= 1) nice = 1;
    else if (normalized <= 2) nice = 2;
    else if (normalized <= 5) nice = 5;
    else nice = 10;
    return nice * magnitude;
}

function quickCashAmounts(base) {
    const safeBase = base > 0 ? base : 1;
    return [...new Set([1, 1.5, 2, 3, 5].map((m) => niceRoundUp(safeBase * m)))];
}

function renderQuickCashButtons(total) {
    const container = document.getElementById('quickCashButtons');
    if (!container) return;
    const amounts = quickCashAmounts(total);

    container.innerHTML = amounts.map((amt) =>
        `<button type="button" class="btn btn-secondary btn-sm quick-cash" data-amt="${amt}">${window.formatMoney(amt)}</button>`
    ).join('') + `<button type="button" class="btn btn-secondary btn-sm quick-cash" data-amt="exact">Exact</button>`;

    container.querySelectorAll('.quick-cash').forEach((btn) => {
        btn.addEventListener('click', () => {
            const amt = btn.dataset.amt;
            document.getElementById('cashReceived').value = amt === 'exact' ? getCartTotals().total.toFixed(2) : amt;
            calcChange();
        });
    });
}

function renderSplitRows() {
    const container = document.getElementById('splitRows');
    if (!container) return;
    const methods = METHOD_ORDER.filter((m) => selectedPayMethods.has(m));

    container.innerHTML = methods.map((method) => `
        <div class="input-group" style="margin-bottom:10px;">
            <label>${METHOD_LABELS[method]} amount</label>
            <input type="number" class="input-field split-amount-input" data-method="${method}" placeholder="0.00" value="${splitAmounts[method] || ''}">
        </div>
        ${method === 'cash' ? '<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px;" id="splitQuickCash"></div>' : ''}
    `).join('');

    container.querySelectorAll('.split-amount-input').forEach((input) => {
        input.addEventListener('input', () => {
            splitAmounts[input.dataset.method] = parseFloat(input.value) || 0;
            updateSplitStatus();
        });
    });

    renderSplitQuickCash();
}

function renderSplitQuickCash() {
    const container = document.getElementById('splitQuickCash');
    if (!container) return;
    const t = getCartTotals();
    const enteredForOthers = [...selectedPayMethods]
        .filter((m) => m !== 'cash')
        .reduce((s, m) => s + (splitAmounts[m] || 0), 0);
    const remaining = Math.max(0, round2(t.total - enteredForOthers));
    const amounts = quickCashAmounts(remaining);

    container.innerHTML = amounts.map((amt) =>
        `<button type="button" class="btn btn-secondary btn-sm quick-cash" data-amt="${amt}">${window.formatMoney(amt)}</button>`
    ).join('') + `<button type="button" class="btn btn-secondary btn-sm quick-cash" data-amt="exact">Exact</button>`;

    container.querySelectorAll('.quick-cash').forEach((btn) => {
        btn.addEventListener('click', () => {
            const amt = btn.dataset.amt === 'exact' ? remaining : parseFloat(btn.dataset.amt);
            splitAmounts.cash = amt;
            const input = document.querySelector('.split-amount-input[data-method="cash"]');
            if (input) input.value = amt ? amt.toFixed(2) : '';
            updateSplitStatus();
        });
    });
}

function updateSplitStatus() {
    const t = getCartTotals();
    const entered = [...selectedPayMethods].reduce((s, m) => s + (splitAmounts[m] || 0), 0);
    const diff = round2(t.total - entered);
    const label = document.getElementById('splitStatusLabel');
    const amount = document.getElementById('splitStatusAmount');
    if (diff > 0.001) {
        label.textContent = 'Remaining';
        amount.textContent = window.formatMoney(diff);
        amount.style.color = 'var(--danger)';
    } else {
        label.textContent = selectedPayMethods.has('cash') ? 'Change Due' : 'Fully Covered';
        amount.textContent = window.formatMoney(Math.abs(diff));
        amount.style.color = 'var(--success)';
    }
}

function renderPaymentModalBody() {
    const t = getCartTotals();
    const cashSection = document.getElementById('cashSection');
    const splitSection = document.getElementById('splitSection');

    if (isSplitMode()) {
        cashSection.style.display = 'none';
        splitSection.style.display = 'block';
        renderSplitRows();
        updateSplitStatus();
    } else {
        splitSection.style.display = 'none';
        const onlyMethod = [...selectedPayMethods][0];
        cashSection.style.display = onlyMethod === 'cash' ? 'block' : 'none';
        if (onlyMethod === 'cash') renderQuickCashButtons(t.total);
    }
}

document.getElementById('showPaymentBtn').addEventListener('click', () => {
    if (cart.length === 0) { showToast('Cart is empty', 'warning'); return; }
    const t = getCartTotals();
    document.getElementById('payAmount').textContent = window.formatMoney(t.total);
    document.getElementById('cashReceived').value = '';
    document.getElementById('changeDue').textContent = window.formatMoney(0);
    currentTip = 0;
    document.querySelectorAll('.tip-btn').forEach((b) => (b.style.borderColor = 'var(--border)'));

    // Reset to a single default method every time the modal opens.
    const defaultMethod = firstAvailableMethod();
    selectedPayMethods = new Set([defaultMethod]);
    splitAmounts = {};
    document.querySelectorAll('.pay-method').forEach((m) => m.classList.toggle('selected', m.dataset.method === defaultMethod));
    renderPaymentModalBody();

    openModal('paymentModal');
});
document.querySelectorAll('.pay-method').forEach((el) => {
    el.addEventListener('click', () => {
        const method = el.dataset.method;
        if (selectedPayMethods.has(method)) {
            if (selectedPayMethods.size === 1) return; // at least one method must stay selected
            selectedPayMethods.delete(method);
            delete splitAmounts[method];
        } else {
            selectedPayMethods.add(method);
        }
        el.classList.toggle('selected', selectedPayMethods.has(method));
        renderPaymentModalBody();
    });
});
function calcChange() {
    const t = getCartTotals();
    const received = parseFloat(document.getElementById('cashReceived').value) || 0;
    const change = Math.max(0, received - t.total);
    const el = document.getElementById('changeDue');
    el.textContent = window.formatMoney(change);
    el.style.color = received >= t.total ? 'var(--success)' : 'var(--danger)';
}
document.getElementById('cashReceived')?.addEventListener('input', calcChange);
document.querySelectorAll('.tip-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
        currentTip = parseFloat(btn.dataset.pct);
        const t = getCartTotals();
        document.getElementById('payAmount').textContent = window.formatMoney(t.total);
        document.querySelectorAll('.tip-btn').forEach((b) => {
            b.style.borderColor = b === btn ? 'var(--accent)' : 'var(--border)';
        });
        renderPaymentModalBody();
        calcChange();
    });
});

document.getElementById('processPayBtn').addEventListener('click', async () => {
    const t = getCartTotals();
    const btn = document.getElementById('processPayBtn');

    let payments;
    if (isSplitMode()) {
        payments = [...selectedPayMethods]
            .map((method) => ({ method, amount: splitAmounts[method] || 0 }))
            .filter((p) => p.amount > 0);
        const nonCash = payments.filter((p) => p.method !== 'cash').reduce((s, p) => s + p.amount, 0);
        const cashAmt = payments.filter((p) => p.method === 'cash').reduce((s, p) => s + p.amount, 0);
        if (nonCash > t.total + 0.01) { showToast('Card/mobile amounts exceed the total', 'error'); return; }
        if (nonCash + cashAmt < t.total - 0.01) { showToast('Amounts entered do not cover the total', 'error'); return; }
    } else {
        const method = [...selectedPayMethods][0];
        if (method === 'cash') {
            const received = parseFloat(document.getElementById('cashReceived').value) || 0;
            if (received < t.total) { showToast('Insufficient amount received', 'error'); return; }
            payments = [{ method: 'cash', amount: received }];
        } else {
            payments = [{ method, amount: t.total }];
        }
    }

    btn.disabled = true;
    try {
        const payload = {
            items: cart.map((c) => ({ product_id: c.product.id, qty: c.qty, modifier_option_ids: c.modifiers.map((m) => m.id) })),
            discount_type: discountValue > 0 ? discountType : null,
            discount_value: discountValue,
            tip_percent: currentTip,
            payments,
            customer_id: document.getElementById('posCustomer').value || null,
        };
        const result = await window.postJson(checkoutUrl, payload);

        // reflect stock decrement locally
        Object.entries(result.remaining_stock || {}).forEach(([id, stock]) => {
            const p = products.find((x) => x.id === parseInt(id, 10));
            if (p) p.stock = stock;
        });

        showReceipt(result.order);
        closeModal('paymentModal');
        cart = []; discountValue = 0; currentTip = 0;
        clearCartState();
        renderCart();
        showToast('Payment completed successfully!', 'success');
    } catch (e) {
        showToast(e.message || 'Payment failed', 'error');
    } finally {
        btn.disabled = false;
    }
});

function showReceipt(order) {
    const dateStr = new Date(order.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: window.storeTimezone });
    const itemsHtml = order.items.map((it) => `
        <div class="receipt-row"><span>${it.name}${it.modifiers && it.modifiers.length ? ' ('+it.modifiers.join(', ')+')' : ''} x${it.qty}</span><span>${window.formatMoney(it.total)}</span></div>
    `).join('');
    const paymentHtml = order.payments.length > 1
        ? order.payments.map((p) => `<div class="receipt-row"><span>${p.method}</span><span>${window.formatMoney(p.amount)}</span></div>`).join('')
        : `<div class="receipt-row"><span>Payment</span><span>${order.payment_method}</span></div>`;
    document.getElementById('receiptContent').innerHTML = `
        <div class="receipt">
            <div class="receipt-center"><strong style="font-size:16px;">${window.storeName ?? 'Nexus Coffee & Co.'}</strong></div>
            <div class="receipt-divider"></div>
            <div class="receipt-row"><span>Order</span><span>${order.order_number}</span></div>
            <div class="receipt-row"><span>Date</span><span>${dateStr}</span></div>
            <div class="receipt-row"><span>Cashier</span><span>${order.cashier}</span></div>
            <div class="receipt-row"><span>Customer</span><span>${order.customer}</span></div>
            <div class="receipt-divider"></div>
            ${itemsHtml}
            <div class="receipt-divider"></div>
            <div class="receipt-row"><span>Subtotal</span><span>${window.formatMoney(order.subtotal)}</span></div>
            ${order.discount_amount > 0 ? `<div class="receipt-row"><span>Discount</span><span>-${window.formatMoney(order.discount_amount)}</span></div>` : ''}
            <div class="receipt-row"><span>${order.tax_name}</span><span>${window.formatMoney(order.tax)}</span></div>
            ${order.tip > 0 ? `<div class="receipt-row"><span>Tip</span><span>${window.formatMoney(order.tip)}</span></div>` : ''}
            <div class="receipt-divider"></div>
            <div class="receipt-row receipt-total"><span>TOTAL</span><span>${window.formatMoney(order.total)}</span></div>
            ${paymentHtml}
            ${order.change_due > 0.005 ? `<div class="receipt-row"><span>Change</span><span>${window.formatMoney(order.change_due)}</span></div>` : ''}
            <div class="receipt-divider"></div>
            <div class="receipt-center" style="font-size:11px;">Thank you for visiting!</div>
        </div>
    `;
    openModal('receiptModal');
    if (window.autoPrint) setTimeout(() => window.print(), 300);
}

document.getElementById('posCustomer')?.addEventListener('change', saveCartState);

renderPosCategories();
restoreCartState();
renderCart();

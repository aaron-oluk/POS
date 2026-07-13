const { products, categories, checkoutUrl } = window.posData;

const CART_STORAGE_KEY = 'nexus-self-checkout-cart-v1';

let cart = [];
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
// Mirrors the cart to localStorage so a customer's in-progress selection
// survives an accidental page reload, and only disappears when they clear
// it (or complete checkout).
function saveCartState() {
    try {
        localStorage.setItem(CART_STORAGE_KEY, JSON.stringify({
            items: cart.map((c) => ({ productId: c.product.id, qty: c.qty, modifierOptionIds: c.modifiers.map((m) => m.id) })),
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
    currentTip = 0;
    clearCartState();
    renderCart();
    showToast('Cart cleared', 'info');
}

function getCartTotals() {
    const subtotal = cart.reduce((s, c) => s + c.unitPrice * c.qty, 0);
    const tax = subtotal * (window.posData.taxRate / 100);
    const tip = subtotal * (currentTip / 100);
    const total = subtotal + tax + tip;
    return { subtotal, tax, tip, total };
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
document.getElementById('clearCartBtn').addEventListener('click', clearCart);

// ===== Payment modal =====
// Self-checkout never offers cash (no attendant to handle it or give
// change), so every method here charges exactly its allocated amount —
// there's no "change due" concept to account for.

const METHOD_LABELS = { card: 'Card', mobile: 'Mobile' };
const METHOD_ORDER = ['card', 'mobile'];

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

function renderSplitRows() {
    const container = document.getElementById('splitRows');
    if (!container) return;
    const methods = METHOD_ORDER.filter((m) => selectedPayMethods.has(m));

    container.innerHTML = methods.map((method) => `
        <div class="input-group" style="margin-bottom:10px;">
            <label>${METHOD_LABELS[method]} amount</label>
            <input type="number" class="input-field split-amount-input" data-method="${method}" placeholder="0.00" value="${splitAmounts[method] || ''}">
        </div>
    `).join('');

    container.querySelectorAll('.split-amount-input').forEach((input) => {
        input.addEventListener('input', () => {
            splitAmounts[input.dataset.method] = parseFloat(input.value) || 0;
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
        label.textContent = 'Fully Covered';
        amount.textContent = window.formatMoney(Math.abs(diff));
        amount.style.color = 'var(--success)';
    }
}

function renderPaymentModalBody() {
    const splitSection = document.getElementById('splitSection');
    if (isSplitMode()) {
        splitSection.style.display = 'block';
        renderSplitRows();
        updateSplitStatus();
    } else {
        splitSection.style.display = 'none';
    }
}

document.getElementById('showPaymentBtn').addEventListener('click', () => {
    if (cart.length === 0) { showToast('Cart is empty', 'warning'); return; }
    const t = getCartTotals();
    document.getElementById('payAmount').textContent = window.formatMoney(t.total);
    currentTip = 0;
    document.querySelectorAll('.tip-btn').forEach((b) => (b.style.borderColor = 'var(--border)'));

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
document.querySelectorAll('.tip-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
        currentTip = parseFloat(btn.dataset.pct);
        const t = getCartTotals();
        document.getElementById('payAmount').textContent = window.formatMoney(t.total);
        document.querySelectorAll('.tip-btn').forEach((b) => {
            b.style.borderColor = b === btn ? 'var(--accent)' : 'var(--border)';
        });
        renderPaymentModalBody();
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
        const entered = payments.reduce((s, p) => s + p.amount, 0);
        if (entered > t.total + 0.01) { showToast('Amounts exceed the total', 'error'); return; }
        if (entered < t.total - 0.01) { showToast('Amounts entered do not cover the total', 'error'); return; }
    } else {
        const method = [...selectedPayMethods][0];
        payments = [{ method, amount: t.total }];
    }

    btn.disabled = true;
    try {
        const payload = {
            items: cart.map((c) => ({ product_id: c.product.id, qty: c.qty, modifier_option_ids: c.modifiers.map((m) => m.id) })),
            tip_percent: currentTip,
            payments,
        };
        const result = await window.postJson(checkoutUrl, payload);

        // reflect stock decrement locally
        Object.entries(result.remaining_stock || {}).forEach(([id, stock]) => {
            const p = products.find((x) => x.id === parseInt(id, 10));
            if (p) p.stock = stock;
        });

        showReceipt(result.order);
        closeModal('paymentModal');
        cart = []; currentTip = 0;
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
    const dateStr = new Date(order.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    const itemsHtml = order.items.map((it) => `
        <div class="receipt-row"><span>${it.name}${it.modifiers && it.modifiers.length ? ' ('+it.modifiers.join(', ')+')' : ''} x${it.qty}</span><span>${window.formatMoney(it.total)}</span></div>
    `).join('');
    const paymentHtml = order.payments.length > 1
        ? order.payments.map((p) => `<div class="receipt-row"><span>${p.method}</span><span>${window.formatMoney(p.amount)}</span></div>`).join('')
        : `<div class="receipt-row"><span>Payment</span><span>${order.payment_method}</span></div>`;
    document.getElementById('receiptContent').innerHTML = `
        <div class="receipt">
            <div class="receipt-center"><strong style="font-size:16px;">${window.posData.storeName ?? ''}</strong></div>
            <div class="receipt-divider"></div>
            <div class="receipt-row"><span>Order</span><span>${order.order_number}</span></div>
            <div class="receipt-row"><span>Date</span><span>${dateStr}</span></div>
            <div class="receipt-divider"></div>
            ${itemsHtml}
            <div class="receipt-divider"></div>
            <div class="receipt-row"><span>Subtotal</span><span>${window.formatMoney(order.subtotal)}</span></div>
            <div class="receipt-row"><span>${order.tax_name}</span><span>${window.formatMoney(order.tax)}</span></div>
            ${order.tip > 0 ? `<div class="receipt-row"><span>Tip</span><span>${window.formatMoney(order.tip)}</span></div>` : ''}
            <div class="receipt-divider"></div>
            <div class="receipt-row receipt-total"><span>TOTAL</span><span>${window.formatMoney(order.total)}</span></div>
            ${paymentHtml}
            <div class="receipt-divider"></div>
            <div class="receipt-center" style="font-size:11px;">Thank you for visiting!</div>
        </div>
    `;
    openModal('receiptModal');
}

renderPosCategories();
restoreCartState();
renderCart();

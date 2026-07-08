const { products, categories, checkoutUrl } = window.posData;

let cart = [];
let discountType = 'percent';
let discountValue = 0;
let selectedPayMethod = 'cash';
let currentTip = 0;
let currentPosCategory = 'All';

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
            <div class="pos-item-name">${p.name}</div>
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
    const existing = cart.find((c) => c.product.id === productId);
    if (existing) {
        if (existing.qty >= p.stock) {
            showToast(`${p.name} is out of stock`, 'warning');
            return;
        }
        existing.qty++;
    } else {
        cart.push({ product: p, qty: 1 });
    }
    renderCart();
    showToast(`Added ${p.name}`, 'success');
}

function updateCartQty(productId, delta) {
    const item = cart.find((c) => c.product.id === productId);
    if (!item) return;
    item.qty += delta;
    if (item.qty <= 0) {
        cart = cart.filter((c) => c.product.id !== productId);
    } else if (item.qty > item.product.stock) {
        item.qty = item.product.stock;
        showToast('Max stock reached', 'warning');
    }
    renderCart();
}

function removeFromCart(productId) {
    cart = cart.filter((c) => c.product.id !== productId);
    renderCart();
}

function clearCart() {
    if (cart.length === 0) return;
    cart = [];
    discountValue = 0;
    currentTip = 0;
    renderCart();
    showToast('Cart cleared', 'info');
}

function getCartTotals() {
    const subtotal = cart.reduce((s, c) => s + c.product.price * c.qty, 0);
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
                    <div class="pos-cart-item-price">${window.formatMoney(c.product.price)} each</div>
                </div>
                <div class="pos-cart-qty">
                    <button data-id="${c.product.id}" data-delta="-1"><i class="bx bx-minus" style="font-size:10px;"></i></button>
                    <span>${c.qty}</span>
                    <button data-id="${c.product.id}" data-delta="1"><i class="bx bx-plus" style="font-size:10px;"></i></button>
                </div>
                <div class="pos-cart-item-total">${window.formatMoney(c.product.price * c.qty)}</div>
                <button class="pos-cart-item-remove" data-remove="${c.product.id}"><i class="bx bx-x"></i></button>
            </div>
        `).join('');
        container.querySelectorAll('[data-delta]').forEach((btn) => {
            btn.addEventListener('click', () => updateCartQty(parseInt(btn.dataset.id, 10), parseInt(btn.dataset.delta, 10)));
        });
        container.querySelectorAll('[data-remove]').forEach((btn) => {
            btn.addEventListener('click', () => removeFromCart(parseInt(btn.dataset.remove, 10)));
        });
    }
    const t = getCartTotals();
    document.getElementById('cartSubtotal').textContent = window.formatMoney(t.subtotal);
    document.getElementById('cartTax').textContent = window.formatMoney(t.tax);
    document.getElementById('cartDiscount').textContent = '-' + window.formatMoney(t.disc);
    document.getElementById('cartTotal').textContent = window.formatMoney(t.total);
    renderPosGrid();
}

document.getElementById('posSearch').addEventListener('input', renderPosGrid);
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

function renderQuickCashButtons(total) {
    const container = document.getElementById('quickCashButtons');
    if (!container) return;
    const base = total > 0 ? total : 1;
    const amounts = [...new Set([1, 1.5, 2, 3, 5].map((m) => niceRoundUp(base * m)))];

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

document.getElementById('showPaymentBtn').addEventListener('click', () => {
    if (cart.length === 0) { showToast('Cart is empty', 'warning'); return; }
    const t = getCartTotals();
    document.getElementById('payAmount').textContent = window.formatMoney(t.total);
    document.getElementById('cashReceived').value = '';
    document.getElementById('changeDue').textContent = window.formatMoney(0);
    currentTip = 0;
    document.querySelectorAll('.tip-btn').forEach((b) => (b.style.borderColor = 'var(--border)'));
    renderQuickCashButtons(t.total);
    openModal('paymentModal');
});
document.querySelectorAll('.pay-method').forEach((el) => {
    el.addEventListener('click', () => {
        document.querySelectorAll('.pay-method').forEach((m) => m.classList.remove('selected'));
        el.classList.add('selected');
        selectedPayMethod = el.dataset.method;
        const cashSection = document.getElementById('cashSection');
        if (cashSection) cashSection.style.display = selectedPayMethod === 'cash' ? 'block' : 'none';
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
        renderQuickCashButtons(t.total);
        calcChange();
    });
});

document.getElementById('processPayBtn').addEventListener('click', async () => {
    const t = getCartTotals();
    const btn = document.getElementById('processPayBtn');

    if (selectedPayMethod === 'cash') {
        const received = parseFloat(document.getElementById('cashReceived').value) || 0;
        if (received < t.total) { showToast('Insufficient amount received', 'error'); return; }
    }

    btn.disabled = true;
    try {
        const payload = {
            items: cart.map((c) => ({ product_id: c.product.id, qty: c.qty })),
            discount_type: discountValue > 0 ? discountType : null,
            discount_value: discountValue,
            tip_percent: currentTip,
            payment_method: selectedPayMethod,
            cash_received: selectedPayMethod === 'cash' ? (parseFloat(document.getElementById('cashReceived').value) || 0) : null,
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
        cart = []; discountValue = 0; currentTip = 0; renderCart();
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
        <div class="receipt-row"><span>${it.name} x${it.qty}</span><span>${window.formatMoney(it.total)}</span></div>
    `).join('');
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
            <div class="receipt-row"><span>Payment</span><span>${order.payment_method}</span></div>
            <div class="receipt-divider"></div>
            <div class="receipt-center" style="font-size:11px;">Thank you for visiting!</div>
        </div>
    `;
    openModal('receiptModal');
}

renderPosCategories();
renderCart();

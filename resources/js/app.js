import './bootstrap';

// ===== CURRENCY (window.currency injected by the layout from Setting::current()) =====
// Prices/totals are stored natively in the active currency (converted server-side
// by App\Support\CurrencyConverter whenever the currency setting changes), so this
// is purely presentational: decimal precision + symbol placement.
window.formatMoney = function (amount) {
    const currency = window.currency || { symbol: '$', decimals: 2 };
    const formatted = (Number(amount) || 0).toLocaleString('en-US', {
        minimumFractionDigits: currency.decimals,
        maximumFractionDigits: currency.decimals,
    });
    return currency.symbol.length > 1 ? `${currency.symbol} ${formatted}` : `${currency.symbol}${formatted}`;
};

// ===== CSRF for fetch() calls =====
window.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

window.postJson = async function (url, data = {}, method = 'POST') {
    const response = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': window.csrfToken,
        },
        body: JSON.stringify(data),
    });
    const json = await response.json().catch(() => ({}));
    if (!response.ok) {
        const error = new Error(json.message || 'Request failed');
        error.status = response.status;
        error.errors = json.errors || {};
        throw error;
    }
    return json;
};

// ===== SIDEBAR TOGGLE =====
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', () => {
            if (window.innerWidth <= 900) {
                sidebar.classList.toggle('mobile-open');
            } else {
                sidebar.classList.toggle('collapsed');
            }
        });
    }

    // ===== THEME =====
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', toggleTheme);
    }

    // ===== TIME =====
    const timeEl = document.getElementById('topbarTime');
    if (timeEl) {
        const updateTime = () => {
            timeEl.textContent = new Date().toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            });
        };
        updateTime();
        setInterval(updateTime, 1000);
    }

    // ===== MODAL OVERLAY DISMISS =====
    document.querySelectorAll('.modal-overlay').forEach((m) => {
        m.addEventListener('click', (e) => {
            if (e.target !== m) return;
            m.classList.remove('show');
            if (m.id === 'confirmModal') resolvePendingConfirm(false);
        });
    });

    // ===== CONFIRM MODAL BUTTONS =====
    document.getElementById('confirmModalOk')?.addEventListener('click', () => resolvePendingConfirm(true));
    document.getElementById('confirmModalCancel')?.addEventListener('click', () => resolvePendingConfirm(false));
    document.getElementById('confirmModalClose')?.addEventListener('click', () => resolvePendingConfirm(false));

    // ===== KEYBOARD SHORTCUTS =====
    document.addEventListener('keydown', (e) => {
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) return;
        if (e.key === 'F2') {
            e.preventDefault();
            window.location.href = window.posUrl || '/pos';
        }
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.show').forEach((m) => {
                m.classList.remove('show');
                if (m.id === 'confirmModal') resolvePendingConfirm(false);
            });
        }
    });
});

function toggleTheme() {
    const html = document.documentElement;
    const isDark = html.dataset.theme === 'dark';
    const next = isDark ? 'light' : 'dark';
    html.dataset.theme = next;
    localStorage.setItem('nexus-theme', next);
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.innerHTML = isDark ? '<i class="fa-solid fa-sun"></i>' : '<i class="fa-solid fa-moon"></i>';
    }
    document.dispatchEvent(new CustomEvent('theme-changed', { detail: { theme: next } }));
}
window.toggleTheme = toggleTheme;

// Apply saved theme before paint-adjacent script (also inlined in <head> to avoid flash, see layout).

// ===== TOAST =====
window.showToast = function (msg, type = 'info') {
    const icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
    const colors = { success: 'var(--success)', error: 'var(--danger)', warning: 'var(--warning)', info: 'var(--info)' };
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const t = document.createElement('div');
    t.className = 'toast';
    t.innerHTML = `<i class="fa-solid ${icons[type]}" style="color:${colors[type]};font-size:16px;"></i><span></span>`;
    t.querySelector('span').textContent = msg;
    container.appendChild(t);
    setTimeout(() => {
        t.classList.add('removing');
        setTimeout(() => t.remove(), 300);
    }, 3000);
};

// ===== MODAL HELPERS =====
window.openModal = function (id) {
    document.getElementById(id)?.classList.add('show');
};
window.closeModal = function (id) {
    document.getElementById(id)?.classList.remove('show');
};

// ===== CONFIRM DIALOG (replaces native confirm()) =====
let pendingConfirmResolve = null;

function resolvePendingConfirm(result) {
    if (!pendingConfirmResolve) return;
    const resolve = pendingConfirmResolve;
    pendingConfirmResolve = null;
    resolve(result);
}

window.confirmDialog = function (message, options = {}) {
    const { title = 'Please Confirm', confirmLabel = 'Confirm', danger = true } = options;
    const modal = document.getElementById('confirmModal');
    if (!modal) return Promise.resolve(window.confirm(message));

    // Resolve any dialog left hanging (shouldn't normally happen) before opening a new one.
    resolvePendingConfirm(false);

    document.getElementById('confirmModalTitle').textContent = title;
    document.getElementById('confirmModalMessage').textContent = message;
    const okBtn = document.getElementById('confirmModalOk');
    okBtn.textContent = confirmLabel;
    okBtn.className = 'btn ' + (danger ? 'btn-danger' : 'btn-primary');

    return new Promise((resolve) => {
        pendingConfirmResolve = resolve;
        modal.classList.add('show');
    }).then((result) => {
        modal.classList.remove('show');
        return result;
    });
};

// ===== GENERIC "CONFIRM BEFORE SUBMIT" FORMS =====
// Add data-confirm="message" (optionally data-confirm-title / data-confirm-label)
// to any <form> for a styled confirmation instead of the browser's native dialog.
document.addEventListener('submit', async (e) => {
    const form = e.target.closest('form[data-confirm]');
    if (!form || form.dataset.confirmed === 'true') return;
    e.preventDefault();

    const ok = await window.confirmDialog(form.dataset.confirm, {
        title: form.dataset.confirmTitle || 'Please Confirm',
        confirmLabel: form.dataset.confirmLabel || 'Confirm',
    });

    if (ok) {
        form.dataset.confirmed = 'true';
        form.submit();
    }
});

// ===== FLASH MESSAGES FROM SESSION (rendered as data attrs in layout) =====
document.addEventListener('DOMContentLoaded', () => {
    const flash = document.getElementById('flashData');
    if (flash) {
        const success = flash.dataset.success;
        const error = flash.dataset.error;
        if (success) window.showToast(success, 'success');
        if (error) window.showToast(error, 'error');
    }
});

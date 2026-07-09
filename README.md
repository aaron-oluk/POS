# Nexus POS

A full-stack point-of-sale system for a coffee shop, built with Laravel 12 and a hand-rolled vanilla CSS/JS frontend (no Tailwind, no frontend framework). Covers the whole flow: a POS terminal for taking orders, inventory/product management, customers, staff with role-based access, order history with refunds, and reporting.

## Stack

- **Backend**: Laravel 12, SQLite
- **Auth**: session-based (`Auth` facade), no public registration — staff accounts are created by an Admin/Manager from the Staff page
- **Frontend**: Blade views sharing one layout, vanilla CSS (`resources/css/app.css`, sized in `rem`/`em` rather than fixed `px` — only border-widths, box-shadows, and one decorative background dot stay in `px`) and JS (`resources/js/app.js` + `resources/js/pages/pos.js`), bundled with Vite. Chart.js, Boxicons, and Google Fonts are loaded from CDN.
- **Roles**: `admin`, `manager`, `cashier`, `barista`. Only `admin`/`manager` can manage Products, Staff, Settings, and process refunds.

## Getting started

```bash
composer install
npm install
cp .env.example .env   # already present in this repo
php artisan key:generate
php artisan migrate --seed
npm run build           # or `npm run dev` for hot-reloading during development
php artisan serve
```

Visit `http://127.0.0.1:8000` and log in with one of the seeded accounts below.

## Demo accounts

All seeded users share the password `password`.

| Email           | Role    | Notes                                     |
| --------------- | ------- | ----------------------------------------- |
| `admin@pos.com` | admin   | Generic admin login                       |
| `sarah@pos.com` | admin   | Full access                               |
| `jake@pos.com`  | barista |                                            |
| `maria@pos.com` | cashier |                                            |
| `david@pos.com` | barista |                                            |
| `emily@pos.com` | cashier | Seeded as **inactive** — login is blocked  |
| `ryan@pos.com`  | barista |                                            |

## Feature map

- **Dashboard** (`/dashboard`) — today's revenue/orders/AOV/customers vs. yesterday, 7-day revenue trend, sales-by-category breakdown, recent transactions, top products. All computed live from real orders.
- **POS Terminal** (`/pos`) — category/search filtering, live cart with quantity controls, percentage/fixed discounts, tip presets, cash/card/mobile payment with change calculation, printable receipt. Checkout posts to `POST /pos/checkout`, which re-validates stock and prices server-side, applies tax (global rate or per-category override), creates the `Order`/`OrderItem` rows, and decrements stock — all inside one DB transaction. The cart (items, quantities, discount, selected customer) is mirrored to `localStorage` (`resources/js/pages/pos.js`, `nexus-pos-cart-v1`) so it survives navigating to another page — since this is a normal server-rendered app, not an SPA, plain in-memory JS state would otherwise vanish on every page load. It's only cleared by hitting "Clear Cart" or completing checkout; on restore, items are re-matched against the current product list so price/stock are never stale.
  - **Split payment**: tapping more than one payment method (Cash/Card/Mobile) switches the modal into split mode — a per-method amount input appears for each selected method, with a live Remaining/Change Due indicator. The checkout payload always sends a `payments: [{method, amount}, ...]` array (even for the common single-method case); the backend validates card/mobile amounts never exceed the total, cash covers whatever's left (with any excess returned as change), and stores one `order_payments` row per method used. `orders.payment_method` is set to the single method, or `'split'` if more than one was used — `Order::$payment_summary` renders that as e.g. "Cash + Card". Reports' payment-method breakdown sums from `order_payments` (not `orders.payment_method`) so a split order's revenue is correctly attributed across both methods rather than lumped under "split".
  - **Barcode scan**: a dedicated "Scan barcode" field (`#posBarcode`) matches fast scanner keystrokes ending in Enter against each product's `barcode`, then adds it to the cart the same way a click would.
  - **Product modifiers**: products with attached modifier groups (e.g. Milk Type, Size, Extras — see Modifiers below) open a "Customize" modal before adding to the cart. Each distinct product + modifier-selection combination is its own cart line (`cartKey()` in `pos.js`), priced at `product.price + Σ selected price_delta`. Checkout persists selections as `order_item_modifiers` rows.
- **Orders** (`/orders`) — status filter, paginated list, order detail modal, refund action (completed orders only).
- **Products** (`/products`) — search/category/stock filters, add/edit/delete (Admin/Manager only), low-stock and inventory-value stats, optional barcode field.
- **Customers** (`/customers`) — search/tier filters, add/edit. Tier (bronze/silver/gold) and lifetime spend are computed from completed orders, not stored.
- **Cash Register** (`/cash-register`) — every cashier opens a session with a starting cash float before their shift and closes it by counting the physical drawer. `CashRegisterSession::cashSales()` sums `order_payments` (method `cash`, completed orders, that cashier) between open and close to compute the expected amount; closing records `counted_cash` and the resulting `discrepancy` for a permanent audit trail. Admin/Manager see every cashier's history; everyone else sees only their own.
- **Purchasing** (`/purchases`, Admin/Manager only) — record a supplier delivery (line items of product/qty/unit cost, a required **Date Supplied**, and an optional **Amount Paid Now**); on save, `PurchaseController::store()` increments each product's stock and updates its `cost` to the latest purchase price, all inside one DB transaction. A "Suppliers" tab manages the supplier directory used by the purchase form.
  - **Supplier payment tracking**: `purchases.amount_paid` defaults to the full total when left blank (paid in full on delivery), or can be set lower to record a delivery bought on credit — `Purchase::$balance_due`/`$payment_status` (paid/partial/unpaid) are computed from `total - amount_paid`. Outstanding balances get a "Record Payment" action (`PUT /purchases/{purchase}/pay`) to log later installments against the same purchase, capped so a payment can never exceed what's still owed. `supply_date` is stored separately from `created_at` since a purchase can be entered into the system after the goods actually arrived. Together, `total` (cost of goods received), `amount_paid` (actual cash paid out) and `supply_date` give the numbers needed to assess supplier spend and margins over any period — the Purchasing page's stat cards surface Spend This Month, Total Paid to Suppliers, and Outstanding Balance.
- **Stock Adjustments** (`/stock-adjustments`, Admin/Manager only) — correct inventory counts (waste, damage, theft, recount, other) outside of a sale or purchase, always with a required reason and optional notes. Every adjustment is stored as a `StockAdjustment` row recording `stock_before`/`stock_after`, so — unlike editing a product's stock field directly — there's a permanent audit trail of who changed what and why. A decrease can never take stock below zero.
  - **Non-negative stock invariant**: `Product::booted()` hooks both the `saving` and `updating` model events to reject any write that would leave `stock < 0` — `saving` covers `create()`/`update()`/`save()`, and `updating` additionally covers `increment()`/`decrement()` (used by POS checkout and purchasing), which bypass `saving` entirely. Checkout and stock-adjustment flows already validate this with a friendly error before touching the database; this model-level guard is the last line of defense for any other code path, present or future.
- **Modifiers** (`/modifiers`, Admin/Manager only) — define reusable modifier groups (e.g. "Milk Type" = single-choice, "Extras" = multi-choice) with priced options, and attach each group to the products it applies to. Powers the POS "Customize" flow above.
- **Staff** (`/staff`, Admin/Manager only) — table of employees with role/status/sales/orders, add/edit via modal.
- **Reports** (`/reports`) — today/week/month/year period selector, hourly sales, payment-method mix, staff leaderboard, low-stock alerts. **Download** exports a formatted PDF (`barryvdh/laravel-dompdf`) for the selected period with the same figures plus a plain-English analysis section (busiest hour, top seller, leading category, top staff member, restock warnings) — see `ReportController::download()`/`buildInsights()` and `resources/views/reports/pdf.blade.php`.
- **Settings** (`/settings`, Admin/Manager only) — General/Receipt/Payment/Tax tabs, including per-category tax rate/exemption overrides that feed directly into POS checkout tax calculations.

### Currency

The store's currency is auto-detected on first run from the host machine's real system timezone (`App\Support\CurrencyDetector`, via `/etc/localtime`/`/etc/timezone` — not Laravel's `config('app.timezone')`, which is fixed to UTC) and resolved to one of 36 curated world currencies with native symbols and approximate USD exchange rates. Override detection with the `SYSTEM_TIMEZONE` env var if the host isn't representative (e.g. in a container), or change it any time on Settings > General.

Prices and totals are stored **natively in the active currency**, not live-converted at display time: `App\Support\CurrencyConverter` rescales every `products.price`/`cost`, `orders.*`, `order_items.*`, `modifier_options.price_delta`, `purchases.total`/`purchase_items.*`, and `cash_register_sessions.*` money column whenever the currency changes (seed data is converted once at install too — `ModifierGroupSeeder` rescales its USD-authored price deltas the same way `SettingSeeder` does for product prices, right after creating them), so editing a product always shows/saves a plain native-currency number and historical records stay internally consistent. The `@money($amount)` Blade directive and `window.formatMoney()` JS helper handle display formatting (decimal precision + symbol placement) everywhere amounts are shown, including the POS terminal's quick-cash buttons, which round to sensible note-sized denominations in whatever currency is active rather than fixed $5/$10/$20.

The store's **timezone** is auto-detected the same way (`App\Support\CurrencyDetector::detectTimezone()`) and the Settings > General dropdown lists every IANA timezone PHP knows about, grouped by region, rather than a fixed shortlist.

### Tooltips

Icon-only controls (cart quantity +/-, remove item, hold/clear cart, table row actions, modal close buttons, topbar icons) carry a `data-tooltip="Label"` attribute plus a matching `aria-label`. A CSS-only tooltip (`[data-tooltip]::after` in `app.css`) shows the label just *below* the control on hover/focus — anchored below rather than above because most of these controls sit at the top edge of a scrolling container (topbar, cart header, modal header), where an above-anchored tooltip gets clipped by that ancestor's `overflow`.

## Data model

`categories` → `products` → `order_items` → `orders` → `customers` / `users` (cashier). A single-row `settings` table holds store info, receipt/payment/tax configuration. Products can attach `modifier_groups` (many-to-many via `modifier_group_product`), each with `modifier_options`; selections on a sale are snapshotted into `order_item_modifiers`. `suppliers` → `purchases` → `purchase_items` record restocks and increment product stock; each `purchases` row carries its own `supply_date` and `amount_paid` for supplier payment/cash-flow tracking (see Purchasing above). `stock_adjustments` log manual inventory corrections with a reason and before/after count — `products.stock` itself is guarded at the model level (`Product::booted()`) and can never go negative, however it's modified. `cash_register_sessions` track each cashier's opening float through to a counted-cash close and discrepancy. See `database/migrations` for exact columns and `database/seeders` for the demo data (24 products, 12 customers, 6 staff, ~50 historical orders, 4 suppliers, 3 modifier groups).

## Tests

```bash
php artisan test
```

Covers the login flow (including the inactive-account block and role-gated routes), the POS checkout flow (order creation, stock decrement, insufficient-stock rejection, split payment, modifier price deltas), purchasing (stock increment + cost update, credit/partial-payment tracking and settling a balance, overpayment rejection, role gate), stock adjustments (audit trail, decrease-below-zero rejection), the product non-negative-stock invariant (create/update/decrement all blocked), and cash register sessions (open/close with discrepancy, one-open-session-per-cashier, ownership check on close).

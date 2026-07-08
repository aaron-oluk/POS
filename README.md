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
- **POS Terminal** (`/pos`) — category/search filtering, live cart with quantity controls, percentage/fixed discounts, tip presets, cash/card/mobile payment with change calculation, printable receipt. Checkout posts to `POST /pos/checkout`, which re-validates stock and prices server-side, applies tax (global rate or per-category override), creates the `Order`/`OrderItem` rows, and decrements stock — all inside one DB transaction.
- **Orders** (`/orders`) — status filter, paginated list, order detail modal, refund action (completed orders only).
- **Products** (`/products`) — search/category/stock filters, add/edit/delete (Admin/Manager only), low-stock and inventory-value stats.
- **Customers** (`/customers`) — search/tier filters, add/edit. Tier (bronze/silver/gold) and lifetime spend are computed from completed orders, not stored.
- **Staff** (`/staff`, Admin/Manager only) — table of employees with role/status/sales/orders, add/edit via modal.
- **Reports** (`/reports`) — today/week/month/year period selector, hourly sales, payment-method mix, staff leaderboard, low-stock alerts.
- **Settings** (`/settings`, Admin/Manager only) — General/Receipt/Payment/Tax tabs, including per-category tax rate/exemption overrides that feed directly into POS checkout tax calculations.

### Currency

The store's currency is auto-detected on first run from the host machine's real system timezone (`App\Support\CurrencyDetector`, via `/etc/localtime`/`/etc/timezone` — not Laravel's `config('app.timezone')`, which is fixed to UTC) and resolved to one of 36 curated world currencies with native symbols and approximate USD exchange rates. Override detection with the `SYSTEM_TIMEZONE` env var if the host isn't representative (e.g. in a container), or change it any time on Settings > General.

Prices and totals are stored **natively in the active currency**, not live-converted at display time: `App\Support\CurrencyConverter` rescales every `products.price`/`cost`, `orders.*`, and `order_items.*` value whenever the currency changes (seed data is converted once at install too), so editing a product always shows/saves a plain native-currency number and historical order totals stay internally consistent. The `@money($amount)` Blade directive and `window.formatMoney()` JS helper handle display formatting (decimal precision + symbol placement) everywhere amounts are shown, including the POS terminal's quick-cash buttons, which round to sensible note-sized denominations in whatever currency is active rather than fixed $5/$10/$20.

The store's **timezone** is auto-detected the same way (`App\Support\CurrencyDetector::detectTimezone()`) and the Settings > General dropdown lists every IANA timezone PHP knows about, grouped by region, rather than a fixed shortlist.

## Data model

`categories` → `products` → `order_items` → `orders` → `customers` / `users` (cashier). A single-row `settings` table holds store info, receipt/payment/tax configuration. See `database/migrations` for exact columns and `database/seeders` for the demo data (24 products, 12 customers, 6 staff, ~50 historical orders).

## Tests

```bash
php artisan test
```

Covers the login flow (including the inactive-account block and role-gated routes) and the POS checkout flow (order creation, stock decrement, insufficient-stock rejection).

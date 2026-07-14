<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $settings = Setting::current();
        [$todayStart, $todayEnd] = $settings->localDayRange(0);
        [$yesterdayStart, $yesterdayEnd] = $settings->localDayRange(1);

        $todayOrders = Order::whereBetween('created_at', [$todayStart, $todayEnd])->where('status', '!=', 'cancelled');
        $yesterdayOrders = Order::whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])->where('status', '!=', 'cancelled');

        $todayRevenue = (clone $todayOrders)->sum('total');
        $yesterdayRevenue = (clone $yesterdayOrders)->sum('total');
        $todayCount = (clone $todayOrders)->count();
        $yesterdayCount = (clone $yesterdayOrders)->count();

        // COUNT(DISTINCT customer_id) silently ignores NULLs, so walk-in orders
        // (no linked customer — every self-checkout order, plus any POS sale
        // rung up without picking a customer) would never move this number.
        // Each walk-in order counts as one customer visit instead.
        $countCustomers = fn ($query) => (clone $query)->whereNotNull('customer_id')->distinct('customer_id')->count('customer_id')
            + (clone $query)->whereNull('customer_id')->count();
        $todayCustomers = $countCustomers($todayOrders);
        $yesterdayCustomers = $countCustomers($yesterdayOrders);
        $todayAov = $todayCount > 0 ? $todayRevenue / $todayCount : 0;
        $yesterdayAov = $yesterdayCount > 0 ? $yesterdayRevenue / $yesterdayCount : 0;

        $pctChange = fn ($current, $previous) => $previous > 0 ? (($current - $previous) / $previous) * 100 : ($current > 0 ? 100 : 0);

        $last7Days = collect(range(6, 0))->map(function ($daysAgo) use ($settings) {
            // localDayRange's bounds are UTC (needed for the query below) —
            // the label needs the store-local date, which can be a different
            // calendar day than those UTC instants near midnight.
            $localDay = $settings->localToday()->subDays($daysAgo);
            [$dayStart, $dayEnd] = $settings->localDayRange($daysAgo);

            return [
                'label' => $localDay->format('D'),
                'total' => (float) Order::whereBetween('created_at', [$dayStart, $dayEnd])->where('status', '!=', 'cancelled')->sum('total'),
            ];
        });

        $categoryTotals = OrderItem::query()
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw('categories.name as category, SUM(order_items.total) as total')
            ->groupBy('categories.name')
            ->orderByDesc('total')
            ->get();

        $recentTransactions = Order::with('customer')->latest()->take(6)->get();

        $topProducts = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw('order_items.product_id, order_items.product_name, order_items.product_icon, SUM(order_items.quantity) as qty')
            ->groupBy('order_items.product_id', 'order_items.product_name', 'order_items.product_icon')
            ->orderByDesc('qty')
            ->take(5)
            ->get();

        return view('dashboard.index', [
            'todayRevenue' => $todayRevenue,
            'revenueChange' => $pctChange($todayRevenue, $yesterdayRevenue),
            'todayCount' => $todayCount,
            'ordersChange' => $pctChange($todayCount, $yesterdayCount),
            'todayAov' => $todayAov,
            'aovChange' => $pctChange($todayAov, $yesterdayAov),
            'todayCustomers' => $todayCustomers,
            'customersChange' => $pctChange($todayCustomers, $yesterdayCustomers),
            'last7Days' => $last7Days,
            'categoryTotals' => $categoryTotals,
            'recentTransactions' => $recentTransactions,
            'topProducts' => $topProducts,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $today = Carbon::today();
        $yesterday = $today->copy()->subDay();

        $todayOrders = Order::whereDate('created_at', $today)->where('status', '!=', 'cancelled');
        $yesterdayOrders = Order::whereDate('created_at', $yesterday)->where('status', '!=', 'cancelled');

        $todayRevenue = (clone $todayOrders)->sum('total');
        $yesterdayRevenue = (clone $yesterdayOrders)->sum('total');
        $todayCount = (clone $todayOrders)->count();
        $yesterdayCount = (clone $yesterdayOrders)->count();
        $todayCustomers = (clone $todayOrders)->distinct('customer_id')->count('customer_id');
        $yesterdayCustomers = (clone $yesterdayOrders)->distinct('customer_id')->count('customer_id');
        $todayAov = $todayCount > 0 ? $todayRevenue / $todayCount : 0;
        $yesterdayAov = $yesterdayCount > 0 ? $yesterdayRevenue / $yesterdayCount : 0;

        $pctChange = fn ($current, $previous) => $previous > 0 ? (($current - $previous) / $previous) * 100 : ($current > 0 ? 100 : 0);

        $last7Days = collect(range(6, 0))->map(function ($daysAgo) {
            $date = Carbon::today()->subDays($daysAgo);

            return [
                'label' => $date->format('D'),
                'total' => (float) Order::whereDate('created_at', $date)->where('status', '!=', 'cancelled')->sum('total'),
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

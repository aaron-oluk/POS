<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $period = $request->get('period', 'week');

        $from = match ($period) {
            'today' => Carbon::today(),
            'month' => Carbon::today()->startOfMonth(),
            'year' => Carbon::today()->startOfYear(),
            default => Carbon::today()->subDays(6),
        };

        $orders = Order::with('items')
            ->where('created_at', '>=', $from)
            ->where('status', '!=', 'cancelled');

        $totalRevenue = (clone $orders)->sum('total');
        $totalOrders = (clone $orders)->count();
        $avgOrder = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
        $totalTips = (clone $orders)->sum('tip');

        $hourlyTotals = (clone $orders)->get()
            ->groupBy(fn ($o) => (int) $o->created_at->format('G'))
            ->map(fn ($group) => (float) $group->sum('total'));

        $hourRange = collect(range(8, 19));
        $hourlyLabels = $hourRange->map(fn ($h) => Carbon::createFromTime($h)->format('gA'));
        $hourlyData = $hourRange->map(fn ($h) => round($hourlyTotals[$h] ?? 0, 2));

        $paymentCounts = (clone $orders)->get()->groupBy('payment_method')->map->count();

        $staffPerformance = User::withSum(['orders' => fn ($q) => $q->where('created_at', '>=', $from)->where('status', '!=', 'cancelled')], 'total')
            ->withCount(['orders' => fn ($q) => $q->where('created_at', '>=', $from)->where('status', '!=', 'cancelled')])
            ->orderByDesc('orders_sum_total')
            ->get();

        $lowStock = Product::with('category')->where('stock', '<=', 15)->orderBy('stock')->get();

        return view('reports.index', [
            'period' => $period,
            'totalRevenue' => $totalRevenue,
            'totalOrders' => $totalOrders,
            'avgOrder' => $avgOrder,
            'totalTips' => $totalTips,
            'hourlyLabels' => $hourlyLabels,
            'hourlyData' => $hourlyData,
            'paymentCounts' => $paymentCounts,
            'staffPerformance' => $staffPerformance,
            'lowStock' => $lowStock,
        ]);
    }
}

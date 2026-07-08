<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $data = $this->gather($request->get('period', 'week'));

        return view('reports.index', $data);
    }

    public function download(Request $request)
    {
        $period = $request->get('period', 'week');
        $data = $this->gather($period);
        $data['settings'] = Setting::current();
        $data['generatedAt'] = now();
        $data['insights'] = $this->buildInsights($data, $period);

        $pdf = Pdf::loadView('reports.pdf', $data)->setPaper('a4');

        $filename = 'report-'.$period.'-'.now()->format('Y-m-d').'.pdf';

        return $pdf->download($filename);
    }

    private function gather(string $period): array
    {
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

        $topProducts = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.created_at', '>=', $from)
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw('order_items.product_name, order_items.product_icon, SUM(order_items.quantity) as qty, SUM(order_items.total) as revenue')
            ->groupBy('order_items.product_name', 'order_items.product_icon')
            ->orderByDesc('revenue')
            ->take(5)
            ->get();

        $categoryTotals = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('orders.created_at', '>=', $from)
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw('categories.name as category, SUM(order_items.total) as total')
            ->groupBy('categories.name')
            ->orderByDesc('total')
            ->get();

        return [
            'period' => $period,
            'periodFrom' => $from,
            'totalRevenue' => $totalRevenue,
            'totalOrders' => $totalOrders,
            'avgOrder' => $avgOrder,
            'totalTips' => $totalTips,
            'hourlyLabels' => $hourlyLabels,
            'hourlyData' => $hourlyData,
            'paymentCounts' => $paymentCounts,
            'staffPerformance' => $staffPerformance,
            'lowStock' => $lowStock,
            'topProducts' => $topProducts,
            'categoryTotals' => $categoryTotals,
        ];
    }

    /**
     * A short plain-English narrative summarizing the numbers above, so the
     * PDF reads as an analysis rather than a raw data dump.
     */
    private function buildInsights(array $data, string $period): array
    {
        $insights = [];
        $periodLabel = ['today' => 'today', 'week' => 'this week', 'month' => 'this month', 'year' => 'this year'][$period] ?? $period;

        if ($data['totalOrders'] === 0) {
            return ["No completed orders were recorded {$periodLabel}."];
        }

        $insights[] = sprintf(
            'The store processed %d orders %s, generating %s in revenue at an average of %s per order.',
            $data['totalOrders'],
            $periodLabel,
            $data['settings']->money($data['totalRevenue']),
            $data['settings']->money($data['avgOrder'])
        );

        if ($data['totalTips'] > 0) {
            $tipRate = $data['totalRevenue'] > 0 ? ($data['totalTips'] / $data['totalRevenue']) * 100 : 0;
            $insights[] = sprintf(
                'Customers left %s in tips, about %s%% of total revenue.',
                $data['settings']->money($data['totalTips']),
                number_format($tipRate, 1)
            );
        }

        $peakHour = collect($data['hourlyLabels'])
            ->zip($data['hourlyData'])
            ->sortByDesc(fn ($pair) => $pair[1])
            ->first();
        if ($peakHour && $peakHour[1] > 0) {
            $insights[] = "The busiest hour was {$peakHour[0]}, bringing in {$data['settings']->money($peakHour[1])}.";
        }

        if ($data['topProducts']->isNotEmpty()) {
            $top = $data['topProducts']->first();
            $topRevenue = $data['settings']->money($top->revenue);
            $insights[] = "{$top->product_name} was the top seller, with {$top->qty} sold ({$topRevenue} in revenue).";
        }

        if ($data['categoryTotals']->isNotEmpty()) {
            $topCategory = $data['categoryTotals']->first();
            $share = $data['totalRevenue'] > 0 ? ($topCategory->total / $data['totalRevenue']) * 100 : 0;
            $shareLabel = number_format($share, 0);
            $insights[] = "{$topCategory->category} was the leading category, accounting for {$shareLabel}% of revenue.";
        }

        $topStaff = $data['staffPerformance']->first();
        if ($topStaff && ($topStaff->orders_sum_total ?? 0) > 0) {
            $insights[] = "{$topStaff->name} led the team with {$data['settings']->money($topStaff->orders_sum_total)} in sales across {$topStaff->orders_count} orders.";
        }

        if ($data['lowStock']->isNotEmpty()) {
            $outOfStock = $data['lowStock']->where('stock', '<=', 0)->count();
            $lowCount = $data['lowStock']->count();
            $insights[] = $outOfStock > 0
                ? "{$lowCount} products are low on stock, including {$outOfStock} completely out of stock — restocking is recommended."
                : "{$lowCount} products are running low on stock and should be reordered soon.";
        } else {
            $insights[] = 'All products are adequately stocked.';
        }

        return $insights;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->get('status', 'all');
        $search = $request->get('q');

        $orders = Order::with('customer')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->whereHas('customer', function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%");
                    });
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('orders.index', [
            'orders' => $orders,
            'status' => $status,
        ]);
    }

    public function show(Order $order): JsonResponse
    {
        $order->load('items', 'customer', 'cashier');

        return response()->json([
            'order_number' => $order->order_number,
            'status' => $order->status,
            'created_at' => $order->created_at->toIso8601String(),
            'customer' => $order->customer?->full_name ?? 'Walk-in Customer',
            'cashier' => $order->cashier->name,
            'payment_method' => Order::paymentLabel($order->payment_method),
            'items' => $order->items->map(fn ($i) => [
                'name' => $i->product_name, 'icon' => $i->product_icon,
                'qty' => $i->quantity, 'unit_price' => (float) $i->unit_price, 'total' => (float) $i->total,
            ]),
            'subtotal' => (float) $order->subtotal,
            'discount_amount' => (float) $order->discount_amount,
            'tax' => (float) $order->tax,
            'tip' => (float) $order->tip,
            'total' => (float) $order->total,
        ]);
    }

    public function refund(Order $order): RedirectResponse
    {
        abort_unless($order->status === 'completed', 422, 'Only completed orders can be refunded.');

        $order->update(['status' => 'refunded']);

        return back()->with('success', "{$order->order_number} has been refunded.");
    }
}

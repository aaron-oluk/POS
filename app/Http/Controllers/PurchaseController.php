<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function index(): View
    {
        $purchases = Purchase::with('supplier', 'user', 'items')
            ->latest('supply_date')
            ->paginate(15);

        return view('purchases.index', [
            'purchases' => $purchases,
            'suppliers' => Supplier::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(['id', 'name', 'icon', 'cost']),
            'purchaseCount' => Purchase::count(),
            'totalSpend' => Purchase::sum('total'),
            'totalPaid' => Purchase::sum('amount_paid'),
            'thisMonthSpend' => Purchase::whereMonth('supply_date', now()->month)->whereYear('supply_date', now()->year)->sum('total'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'supply_date' => ['required', 'date', 'before_or_equal:today'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'in:cash,bank,mobile_money'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $error = null;

        DB::transaction(function () use ($data, $request, &$error) {
            $products = Product::whereIn('id', collect($data['items'])->pluck('product_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $total = 0;
            foreach ($data['items'] as $item) {
                $total += $item['quantity'] * $item['unit_cost'];
            }
            $total = round($total, 2);

            // Defaulting the amount paid to the full total preserves the
            // "paid in full at delivery" behavior when the field is left
            // blank; explicitly entering a smaller figure records the rest
            // as owed to the supplier (bought on credit).
            $amountPaid = array_key_exists('amount_paid', $data) && $data['amount_paid'] !== null
                ? round((float) $data['amount_paid'], 2)
                : $total;

            if ($amountPaid > $total + 0.01) {
                $error = 'Amount paid cannot exceed the purchase total.';

                return;
            }

            $purchase = Purchase::create([
                'supplier_id' => $data['supplier_id'],
                'user_id' => $request->user()->id,
                'reference_no' => $data['reference_no'] ?? null,
                'supply_date' => $data['supply_date'],
                'notes' => $data['notes'] ?? null,
                'total' => $total,
                'amount_paid' => $amountPaid,
            ]);

            // Only log a payment event if money actually changed hands now —
            // a purchase entered with $0 paid is bought entirely on credit.
            if ($amountPaid > 0.009) {
                PurchasePayment::create([
                    'purchase_id' => $purchase->id,
                    'user_id' => $request->user()->id,
                    'method' => $data['payment_method'] ?? 'cash',
                    'amount' => $amountPaid,
                    'paid_on' => $data['supply_date'],
                ]);
            }

            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'total' => round($item['quantity'] * $item['unit_cost'], 2),
                ]);

                $product->increment('stock', $item['quantity']);
                $product->update(['cost' => $item['unit_cost']]);
            }
        });

        if ($error) {
            return back()->with('error', $error);
        }

        return back()->with('success', 'Purchase recorded and stock updated.');
    }

    public function pay(Request $request, Purchase $purchase): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:cash,bank,mobile_money'],
            'paid_on' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($data['amount'] > $purchase->balance_due + 0.01) {
            return back()->with('error', 'Payment exceeds the outstanding balance owed to this supplier.');
        }

        PurchasePayment::create([
            'purchase_id' => $purchase->id,
            'user_id' => $request->user()->id,
            'method' => $data['method'],
            'amount' => $data['amount'],
            'paid_on' => $data['paid_on'] ?? now()->toDateString(),
            'notes' => $data['notes'] ?? null,
        ]);

        $purchase->update(['amount_paid' => round((float) $purchase->amount_paid + $data['amount'], 2)]);

        return back()->with('success', 'Supplier payment recorded.');
    }

    public function payments(Purchase $purchase): JsonResponse
    {
        return response()->json([
            'payments' => $purchase->payments()->with('user')->latest('paid_on')->get()->map(fn (PurchasePayment $p) => [
                'method' => PurchasePayment::methodLabel($p->method),
                'amount' => (float) $p->amount,
                'paid_on' => $p->paid_on->format('M j, Y'),
                'recorded_by' => $p->user->name,
                'notes' => $p->notes,
            ]),
        ]);
    }
}

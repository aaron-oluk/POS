<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockAdjustment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StockAdjustmentController extends Controller
{
    public function index(): View
    {
        $adjustments = StockAdjustment::with('product', 'user')
            ->latest()
            ->paginate(15);

        return view('stock-adjustments.index', [
            'adjustments' => $adjustments,
            'products' => Product::orderBy('name')->get(['id', 'name', 'icon', 'stock']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'type' => ['required', 'in:increase,decrease'],
            'reason' => ['required', 'in:waste,damage,theft,recount,other'],
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);

        $error = null;

        DB::transaction(function () use ($data, &$error) {
            $product = Product::lockForUpdate()->findOrFail($data['product_id']);
            $before = $product->stock;

            if ($data['type'] === 'decrease' && $data['quantity'] > $before) {
                $error = "Cannot decrease stock by more than the current {$before} units on hand.";

                return;
            }

            $after = $data['type'] === 'increase' ? $before + $data['quantity'] : $before - $data['quantity'];
            $product->update(['stock' => $after]);

            StockAdjustment::create([
                'product_id' => $product->id,
                'user_id' => request()->user()->id,
                'type' => $data['type'],
                'reason' => $data['reason'],
                'quantity' => $data['quantity'],
                'stock_before' => $before,
                'stock_after' => $after,
                'notes' => $data['notes'] ?? null,
            ]);
        });

        if ($error) {
            return back()->with('error', $error);
        }

        return back()->with('success', 'Stock adjustment recorded.');
    }
}

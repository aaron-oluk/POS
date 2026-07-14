<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockAdjustment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StockManagementController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->get('q');
        $categoryFilter = $request->get('category');
        $stockFilter = $request->get('stock');

        // Stats always reflect the full inventory, independent of the table's
        // search/filter — same convention as the Products page.
        $allProducts = Product::orderBy('name')->get();

        $products = Product::with('category')
            ->when($search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")))
            ->when($categoryFilter, fn ($q) => $q->whereHas('category', fn ($q) => $q->where('name', $categoryFilter)))
            ->when($stockFilter === 'low', fn ($q) => $q->where('stock', '>', 0)->where('stock', '<=', 15))
            ->when($stockFilter === 'out', fn ($q) => $q->where('stock', '<=', 0))
            ->when($stockFilter === 'ok', fn ($q) => $q->where('stock', '>', 15))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $adjustments = StockAdjustment::with('product', 'user')
            ->latest()
            ->paginate(15, ['*'], 'adjustments_page');

        return view('stock-management.index', [
            'products' => $products,
            'allProducts' => $allProducts,
            'categories' => Category::orderBy('name')->get(),
            'adjustments' => $adjustments,
            'search' => $search,
            'categoryFilter' => $categoryFilter,
            'stockFilter' => $stockFilter,
            'totalProducts' => $allProducts->count(),
            'lowStockCount' => $allProducts->filter(fn ($p) => $p->stock_status === 'low')->count(),
            'outOfStockCount' => $allProducts->filter(fn ($p) => $p->stock_status === 'out')->count(),
            'inventoryValue' => $allProducts->sum(fn ($p) => $p->stock * (float) $p->cost),
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

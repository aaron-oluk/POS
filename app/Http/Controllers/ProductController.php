<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->get('q');
        $categoryFilter = $request->get('category');
        $stockFilter = $request->get('stock');

        $products = Product::with('category')
            ->when($search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")))
            ->when($categoryFilter, fn ($q) => $q->whereHas('category', fn ($q) => $q->where('name', $categoryFilter)))
            ->when($stockFilter === 'low', fn ($q) => $q->where('stock', '>', 0)->where('stock', '<=', 15))
            ->when($stockFilter === 'out', fn ($q) => $q->where('stock', '<=', 0))
            ->when($stockFilter === 'ok', fn ($q) => $q->where('stock', '>', 15))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(),
            'totalProducts' => Product::count(),
            'lowStock' => Product::where('stock', '>', 0)->where('stock', '<=', 15)->count(),
            'outOfStock' => Product::where('stock', '<=', 0)->count(),
            'inventoryValue' => Product::sum(DB::raw('price * stock')),
            'search' => $search,
            'categoryFilter' => $categoryFilter,
            'stockFilter' => $stockFilter,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['cost'] = $data['cost'] ?? 0;
        $data['stock'] = $data['stock'] ?? 0;
        Product::create($data);

        return back()->with('success', 'Product added.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request, $product->id);
        $data['cost'] = $data['cost'] ?? 0;
        $data['stock'] = $data['stock'] ?? 0;
        $product->update($data);

        return back()->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return back()->with('success', 'Product deleted.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'sku' => ['required', 'string', 'max:50', 'unique:products,sku,'.$ignoreId],
            'barcode' => ['nullable', 'string', 'max:50', 'unique:products,barcode,'.$ignoreId],
            'description' => ['nullable', 'string'],
        ]);
    }
}

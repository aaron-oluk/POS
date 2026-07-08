<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $q = trim((string) $request->get('q'));

        if ($q === '') {
            return redirect()->route('dashboard');
        }

        if ($product = Product::where('name', 'like', "%{$q}%")->first()) {
            return redirect()->route('products.index', ['q' => $product->name]);
        }

        if ($customer = Customer::where(fn ($query) => $query->where('first_name', 'like', "%{$q}%")->orWhere('last_name', 'like', "%{$q}%"))->first()) {
            return redirect()->route('customers.index', ['q' => $customer->first_name]);
        }

        if (preg_match('/(\d+)/', $q, $m) && ($order = Order::find(((int) $m[1]) - 999))) {
            return redirect()->route('orders.index', ['open' => $order->id]);
        }

        return redirect()->back()->with('error', "No results found for \"{$q}\"");
    }
}

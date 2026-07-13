<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SelfCheckoutController extends Controller
{
    public function index(): View
    {
        $settings = Setting::current();
        abort_unless($settings->self_checkout_enabled, 404);

        return view('self-checkout.index', [
            'categories' => Category::orderBy('name')->pluck('name'),
            'products' => Product::with('category', 'modifierGroups.options')->orderBy('name')->get(),
            'settings' => $settings,
        ]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $settings = Setting::current();
        abort_unless($settings->self_checkout_enabled, 404);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.modifier_option_ids' => ['nullable', 'array'],
            'items.*.modifier_option_ids.*' => ['integer', 'exists:modifier_options,id'],
            'tip_percent' => ['nullable', 'numeric', 'min:0'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'in:card,mobile'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        return response()->json(CheckoutService::process($data, $settings, User::kiosk()->id));
    }
}

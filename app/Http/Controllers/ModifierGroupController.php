<?php

namespace App\Http\Controllers;

use App\Models\ModifierGroup;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ModifierGroupController extends Controller
{
    public function index(): View
    {
        return view('modifiers.index', [
            'groups' => ModifierGroup::with('options', 'products')->orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(['id', 'name', 'icon']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $request) {
            $group = ModifierGroup::create([
                'name' => $data['name'],
                'multiple' => $request->boolean('multiple'),
            ]);

            $group->options()->createMany($data['options']);
            $group->products()->sync($data['product_ids'] ?? []);
        });

        return back()->with('success', 'Modifier group created.');
    }

    public function update(Request $request, ModifierGroup $modifier): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $modifier, $request) {
            $modifier->update([
                'name' => $data['name'],
                'multiple' => $request->boolean('multiple'),
            ]);

            $modifier->options()->delete();
            $modifier->options()->createMany($data['options']);
            $modifier->products()->sync($data['product_ids'] ?? []);
        });

        return back()->with('success', 'Modifier group updated.');
    }

    public function destroy(ModifierGroup $modifier): RedirectResponse
    {
        $modifier->delete();

        return back()->with('success', 'Modifier group deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Not validated here: a real HTML checkbox without a `value`
            // attribute submits "on" when checked, which Laravel's strict
            // `boolean` rule rejects. $request->boolean() reads it directly.
            'options' => ['required', 'array', 'min:1'],
            'options.*.name' => ['required', 'string', 'max:255'],
            'options.*.price_delta' => ['required', 'numeric'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['exists:products,id'],
        ]);
    }
}

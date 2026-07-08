<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Setting;
use App\Support\CurrencyConverter;
use App\Support\CurrencyDetector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        return view('settings.edit', [
            'settings' => Setting::current(),
            'categories' => Category::orderBy('name')->get(),
            'currencies' => CurrencyDetector::supportedCurrencies(),
            'timezoneGroups' => CurrencyDetector::groupedTimezones(),
        ]);
    }

    public function updateGeneral(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email'],
            'address' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'max:8'],
            'timezone' => ['required', 'string', 'max:64'],
        ]);
        $data['dark_mode'] = $request->boolean('dark_mode');
        $data['compact_mode'] = $request->boolean('compact_mode');
        $data['sound_effects'] = $request->boolean('sound_effects');

        $settings = Setting::current();
        $oldCurrency = $settings->currency;
        $oldRate = (float) $settings->exchange_rate;
        $newRate = CurrencyDetector::rateFor($data['currency']);
        $data['currency_symbol'] = CurrencyDetector::symbolFor($data['currency']);
        $data['exchange_rate'] = $newRate;

        $settings->update($data);

        if ($data['currency'] !== $oldCurrency) {
            CurrencyConverter::convertAll($oldRate, $newRate, CurrencyDetector::decimalsFor($data['currency']));
        }

        return back()->with('success', 'Settings saved successfully.');
    }

    public function updateReceipt(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'receipt_header' => ['nullable', 'string'],
            'receipt_footer' => ['nullable', 'string'],
            'paper_size' => ['required', 'string'],
            'font_size' => ['required', 'string'],
        ]);
        $data['show_qr'] = $request->boolean('show_qr');
        $data['auto_print'] = $request->boolean('auto_print');

        Setting::current()->update($data);

        return back()->with('success', 'Receipt settings saved.');
    }

    public function updatePayment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tip_options' => ['required', 'string'],
            'default_tip' => ['required', 'string'],
        ]);
        foreach (['cash_enabled', 'card_enabled', 'mobile_enabled', 'gift_cards_enabled', 'split_payment_enabled', 'prompt_tips'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        Setting::current()->update($data);

        return back()->with('success', 'Payment settings saved.');
    }

    public function updateTax(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'tax_name' => ['required', 'string', 'max:255'],
        ]);
        $data['tax_included'] = $request->boolean('tax_included');
        $data['round_tax'] = $request->boolean('round_tax');

        Setting::current()->update($data);

        if ($request->has('categories')) {
            foreach ($request->input('categories', []) as $categoryId => $fields) {
                Category::whereKey($categoryId)->update([
                    'tax_rate' => $fields['tax_rate'] !== '' ? $fields['tax_rate'] : null,
                    'tax_exempt' => isset($fields['tax_exempt']),
                ]);
            }
        }

        return back()->with('success', 'Tax settings saved.');
    }
}

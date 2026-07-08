<?php

namespace App\Models;

use App\Support\CurrencyDetector;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'dark_mode' => 'boolean',
            'compact_mode' => 'boolean',
            'sound_effects' => 'boolean',
            'show_qr' => 'boolean',
            'auto_print' => 'boolean',
            'cash_enabled' => 'boolean',
            'card_enabled' => 'boolean',
            'mobile_enabled' => 'boolean',
            'gift_cards_enabled' => 'boolean',
            'split_payment_enabled' => 'boolean',
            'prompt_tips' => 'boolean',
            'tax_included' => 'boolean',
            'round_tax' => 'boolean',
            'tax_rate' => 'decimal:2',
        ];
    }

    public static function current(): self
    {
        return once(function () {
            $detected = CurrencyDetector::detect();

            return static::query()->firstOrCreate(['id' => 1], [
                'currency' => $detected['code'],
                'currency_symbol' => $detected['symbol'],
            ]);
        });
    }
}

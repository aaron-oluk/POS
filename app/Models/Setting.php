<?php

namespace App\Models;

use App\Support\CurrencyDetector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
            'self_checkout_enabled' => 'boolean',
            'prompt_tips' => 'boolean',
            'tax_included' => 'boolean',
            'round_tax' => 'boolean',
            'tax_rate' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
        ];
    }

    public static function current(): self
    {
        return once(function () {
            $detected = CurrencyDetector::detect();

            return static::query()->firstOrCreate(['id' => 1], [
                'currency' => $detected['code'],
                'currency_symbol' => $detected['symbol'],
                'exchange_rate' => $detected['rate'],
                'timezone' => CurrencyDetector::detectTimezone(),
            ]);
        });
    }

    /**
     * Format an amount for display. Prices/totals are stored natively in
     * the active currency (see App\Support\CurrencyConverter, which rescales
     * every stored value whenever the currency setting changes), so this is
     * purely presentational: decimal precision + symbol placement.
     */
    public function money(float|int|string $amount): string
    {
        $decimals = CurrencyDetector::decimalsFor($this->currency);
        $formatted = number_format((float) $amount, $decimals);
        $symbol = (string) $this->currency_symbol;

        return mb_strlen($symbol) > 1 ? "{$symbol} {$formatted}" : "{$symbol}{$formatted}";
    }

    /**
     * Timestamps are stored and cast in the app's fixed UTC timezone
     * (config('app.timezone')) for internal consistency; this converts one
     * to the store's actual location for display, since that's the wall-clock
     * time staff and customers there actually experience.
     */
    public function toLocal(\DateTimeInterface $date): Carbon
    {
        return Carbon::parse($date)->setTimezone($this->timezone);
    }

    public function localTime(\DateTimeInterface $date, string $format = 'M j, Y g:i A'): string
    {
        return $this->toLocal($date)->format($format);
    }

    /**
     * The current moment in the store's timezone — use instead of now()/
     * Carbon::today() when computing "today"/"this week" boundaries, so they
     * line up with the store's actual calendar day rather than UTC's.
     */
    public function localNow(): Carbon
    {
        return Carbon::now($this->timezone);
    }

    public function localToday(): Carbon
    {
        return Carbon::today($this->timezone);
    }

    /**
     * [start, end) UTC instants bounding a calendar day in the store's
     * timezone, for whereBetween('created_at', ...) queries against the
     * UTC-stored column — whereDate()/direct-equality comparisons operate on
     * the raw stored string, so they silently use UTC's day boundary instead
     * of the store's unless converted like this first.
     * $daysAgo=0 -> today, 1 -> yesterday, etc.
     */
    public function localDayRange(int $daysAgo = 0): array
    {
        $start = $this->localToday()->subDays($daysAgo);

        return [$start->copy()->utc(), $start->copy()->addDay()->utc()];
    }

    /**
     * UTC instant marking the start of a trailing N-day window ending today
     * (inclusive) in the store's timezone, e.g. localDaysAgo(6) for "last 7
     * days" used alongside where('created_at', '>=', ...).
     */
    public function localDaysAgo(int $days): Carbon
    {
        return $this->localToday()->subDays($days)->utc();
    }

    /**
     * Parse a naive datetime string (e.g. from a <input type="datetime-local">
     * with no timezone info) as the store's local time, converted to UTC for
     * comparison against the UTC-stored column.
     */
    public function parseLocal(string $dateString): Carbon
    {
        return Carbon::parse($dateString, $this->timezone)->utc();
    }
}

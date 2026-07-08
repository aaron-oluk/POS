<?php

namespace App\Support;

use IntlTimeZone;
use Locale;
use NumberFormatter;

class CurrencyDetector
{
    /**
     * A representative locale per currency. Used to look up a nice native
     * symbol (e.g. UGX -> "USh", SAR -> "ر.س.‏") for the curated list below,
     * since generic "en_<region>" locales don't carry every country's own
     * currency formatting conventions.
     */
    protected const CURRENCY_LOCALES = [
        'USD' => 'en_US', 'EUR' => 'de_DE', 'GBP' => 'en_GB', 'JPY' => 'ja_JP',
        'CAD' => 'en_CA', 'AUD' => 'en_AU', 'NZD' => 'en_NZ', 'CHF' => 'de_CH',
        'CNY' => 'zh_CN', 'HKD' => 'zh_HK', 'SGD' => 'en_SG', 'INR' => 'en_IN',
        'PKR' => 'en_PK', 'BDT' => 'en_BD', 'PHP' => 'en_PH', 'THB' => 'th_TH',
        'VND' => 'vi_VN', 'IDR' => 'id_ID', 'KRW' => 'ko_KR',
        'KES' => 'en_KE', 'UGX' => 'en_UG', 'TZS' => 'en_TZ', 'NGN' => 'en_NG',
        'GHS' => 'en_GH', 'ZAR' => 'en_ZA', 'EGP' => 'ar_EG',
        'SAR' => 'ar_SA', 'AED' => 'ar_AE',
        'BRL' => 'pt_BR', 'MXN' => 'es_MX',
        'TRY' => 'tr_TR', 'RUB' => 'ru_RU', 'PLN' => 'pl_PL',
        'SEK' => 'sv_SE', 'NOK' => 'nb_NO', 'DKK' => 'da_DK',
    ];

    protected const CURRENCY_NAMES = [
        'USD' => 'US Dollar', 'EUR' => 'Euro', 'GBP' => 'British Pound', 'JPY' => 'Japanese Yen',
        'CAD' => 'Canadian Dollar', 'AUD' => 'Australian Dollar', 'NZD' => 'New Zealand Dollar', 'CHF' => 'Swiss Franc',
        'CNY' => 'Chinese Yuan', 'HKD' => 'Hong Kong Dollar', 'SGD' => 'Singapore Dollar', 'INR' => 'Indian Rupee',
        'PKR' => 'Pakistani Rupee', 'BDT' => 'Bangladeshi Taka', 'PHP' => 'Philippine Peso', 'THB' => 'Thai Baht',
        'VND' => 'Vietnamese Dong', 'IDR' => 'Indonesian Rupiah', 'KRW' => 'South Korean Won',
        'KES' => 'Kenyan Shilling', 'UGX' => 'Ugandan Shilling', 'TZS' => 'Tanzanian Shilling', 'NGN' => 'Nigerian Naira',
        'GHS' => 'Ghanaian Cedi', 'ZAR' => 'South African Rand', 'EGP' => 'Egyptian Pound',
        'SAR' => 'Saudi Riyal', 'AED' => 'UAE Dirham',
        'BRL' => 'Brazilian Real', 'MXN' => 'Mexican Peso',
        'TRY' => 'Turkish Lira', 'RUB' => 'Russian Ruble', 'PLN' => 'Polish Zloty',
        'SEK' => 'Swedish Krona', 'NOK' => 'Norwegian Krone', 'DKK' => 'Danish Krone',
    ];

    /**
     * Approximate units-per-1-USD used to convert the app's USD-denominated
     * seed prices into whatever currency is active. These are static
     * snapshots, not a live feed — good enough for a self-hosted POS demo,
     * but a real deployment should refresh them from a live rates API.
     */
    protected const EXCHANGE_RATES = [
        'USD' => 1, 'EUR' => 0.92, 'GBP' => 0.79, 'JPY' => 149,
        'CAD' => 1.36, 'AUD' => 1.51, 'NZD' => 1.64, 'CHF' => 0.88,
        'CNY' => 7.24, 'HKD' => 7.82, 'SGD' => 1.34, 'INR' => 83.3,
        'PKR' => 278, 'BDT' => 110, 'PHP' => 56.5, 'THB' => 35.8,
        'VND' => 24500, 'IDR' => 15600, 'KRW' => 1330,
        'KES' => 129, 'UGX' => 3700, 'TZS' => 2500, 'NGN' => 1550,
        'GHS' => 15.2, 'ZAR' => 18.7, 'EGP' => 48.5,
        'SAR' => 3.75, 'AED' => 3.67,
        'BRL' => 5.05, 'MXN' => 17.0,
        'TRY' => 32.1, 'RUB' => 92.5, 'PLN' => 4.02,
        'SEK' => 10.5, 'NOK' => 10.6, 'DKK' => 6.87,
    ];

    /**
     * Currencies not commonly displayed with sub-unit (cent) precision.
     */
    protected const ZERO_DECIMAL = ['JPY', 'KRW', 'VND', 'IDR', 'UGX', 'TZS'];

    /**
     * Detect ['code', 'symbol', 'rate'] from the host machine's real-world
     * location (system timezone, falling back to the OS locale) rather than
     * Laravel's app timezone, which is usually forced to UTC.
     */
    public static function detect(): array
    {
        $country = static::systemCountry();

        if ($country && $currency = static::currencyForCountry($country)) {
            return $currency;
        }

        return ['code' => 'USD', 'symbol' => '$', 'rate' => 1.0];
    }

    /**
     * Curated list of world currencies for the Settings dropdown:
     * [code => ['name' => ..., 'symbol' => ..., 'rate' => ...]].
     */
    public static function supportedCurrencies(): array
    {
        $list = [];

        foreach (static::CURRENCY_LOCALES as $code => $locale) {
            $list[$code] = [
                'name' => static::CURRENCY_NAMES[$code] ?? $code,
                'symbol' => static::symbolForLocale($locale),
                'rate' => static::rateFor($code),
            ];
        }

        return $list;
    }

    /**
     * Resolve the display symbol for any ISO currency code, preferring the
     * curated native-locale list and falling back to the code itself.
     */
    public static function symbolFor(string $code): string
    {
        $locale = static::CURRENCY_LOCALES[$code] ?? null;

        return $locale ? static::symbolForLocale($locale) : $code;
    }

    public static function rateFor(string $code): float
    {
        return (float) (static::EXCHANGE_RATES[$code] ?? 1.0);
    }

    public static function decimalsFor(string $code): int
    {
        return in_array($code, static::ZERO_DECIMAL, true) ? 0 : 2;
    }

    protected static function currencyForCountry(string $country): ?array
    {
        $fmt = new NumberFormatter('en_'.$country, NumberFormatter::CURRENCY);
        $code = $fmt->getTextAttribute(NumberFormatter::CURRENCY_CODE);

        if (! $code) {
            return null;
        }

        return ['code' => $code, 'symbol' => static::symbolFor($code), 'rate' => static::rateFor($code)];
    }

    protected static function symbolForLocale(string $locale): string
    {
        return (new NumberFormatter($locale, NumberFormatter::CURRENCY))->getSymbol(NumberFormatter::CURRENCY_SYMBOL);
    }

    protected static function systemCountry(): ?string
    {
        if ($timezoneId = static::systemTimezoneId()) {
            if (class_exists(IntlTimeZone::class)) {
                $region = IntlTimeZone::getRegion($timezoneId);
                if ($region) {
                    return $region;
                }
            }
        }

        if (class_exists(Locale::class)) {
            $region = Locale::getRegion(Locale::getDefault());
            if ($region) {
                return $region;
            }
        }

        return null;
    }

    protected static function systemTimezoneId(): ?string
    {
        if ($override = env('SYSTEM_TIMEZONE')) {
            return $override;
        }

        if (is_link('/etc/localtime')) {
            $target = readlink('/etc/localtime');
            if ($target && preg_match('#zoneinfo/(.+)$#', $target, $matches)) {
                return $matches[1];
            }
        }

        if (is_readable('/etc/timezone')) {
            $contents = trim((string) file_get_contents('/etc/timezone'));
            if ($contents !== '') {
                return $contents;
            }
        }

        return null;
    }
}

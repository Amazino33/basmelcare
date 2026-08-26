<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\AppSetting;
use App\Models\Customer;

/**
 * What a consultation costs, and whether this one is free.
 *
 * Both are configurable, so both live here rather than being spread through
 * the screens that need them. A price read in two places drifts.
 */
class ConsultationPricing
{
    /** How the consultation happens. The system records it; staff arrange it. */
    public const MODES = [
        'physical' => 'In person',
        'video'    => 'Video call',
        'text'     => 'Text / chat',
    ];

    /** Only pharmacists today; the field exists so a doctor is an addition. */
    public const PROVIDERS = [
        'pharmacist' => 'Pharmacist',
    ];

    /** Free allowance periods. 'none' switches the allowance off entirely. */
    public const FREE_PERIODS = [
        'none'  => 'No free consultations',
        'ever'  => 'Per customer, ever',
        'year'  => 'Per customer, each year',
        'month' => 'Per customer, each month',
    ];

    public static function price(string $provider, string $mode): float
    {
        return (float) AppSetting::get(static::priceKey($provider, $mode), 0);
    }

    public static function priceKey(string $provider, string $mode): string
    {
        return 'consult_price_' . $provider . '_' . $mode;
    }

    public static function freeAllowance(): int
    {
        return (int) AppSetting::get('consult_free_count', 1);
    }

    public static function freePeriod(): string
    {
        $period = (string) AppSetting::get('consult_free_period', 'ever');

        return array_key_exists($period, static::FREE_PERIODS) ? $period : 'ever';
    }

    /**
     * How many free consultations this customer has left.
     *
     * Counts what was actually recorded as free rather than recalculating
     * against today's settings: the allowance changes, and a consultation
     * given free last year was still given free.
     */
    public static function freeRemainingFor(?Customer $customer): int
    {
        $period = static::freePeriod();

        if ($period === 'none' || ! $customer) {
            return 0;
        }

        $used = Appointment::where('customer_id', $customer->id)
            ->where('was_free', true)
            ->where('status', '!=', 'cancelled')
            ->when($period === 'year', fn ($q) => $q->where('created_at', '>=', now()->startOfYear()))
            ->when($period === 'month', fn ($q) => $q->where('created_at', '>=', now()->startOfMonth()))
            ->count();

        return max(0, static::freeAllowance() - $used);
    }

    public static function isFreeFor(?Customer $customer): bool
    {
        return static::freeRemainingFor($customer) > 0;
    }

    /** What this customer would actually be charged. */
    public static function chargeFor(?Customer $customer, string $provider, string $mode): float
    {
        return static::isFreeFor($customer) ? 0.0 : static::price($provider, $mode);
    }
}

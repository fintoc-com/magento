<?php
declare(strict_types=1);

namespace Fintoc\Payment\Utils;

/**
 * Utility methods for amount handling.
 */
class AmountUtils
{
    public const CURRENCY_DECIMALS = [
        'CLP' => 0,
        'MXN' => 2,
    ];

    /**
     * Round a decimal amount to an integer using PHP_ROUND_HALF_UP strategy.
     *
     * @param float|int $amount
     * @return int
     */
    public static function roundToIntHalfUp($amount): int
    {
        return (int)\round((float)$amount, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * Convert an amount in major units to an integer in the currency's minor units.
     *
     * @param float|int|string $amount
     * @param string|null $currency
     * @return int
     */
    public static function toMinorUnits($amount, ?string $currency): int
    {
        $decimals = self::getCurrencyDecimals($currency);
        $rounded = \round((float)$amount, $decimals, PHP_ROUND_HALF_UP);
        return self::roundToIntHalfUp($rounded * (10 ** $decimals));
    }

    /**
     * Convert an integer amount in the currency's minor units to major units.
     *
     * @param int|float|string $amount
     * @param string|null $currency
     * @return float
     */
    public static function fromMinorUnits($amount, ?string $currency): float
    {
        return ((float)$amount) / (10 ** self::getCurrencyDecimals($currency));
    }

    /**
     * Number of decimals Fintoc uses for the currency's minor unit.
     *
     * @param string|null $currency
     * @return int
     */
    private static function getCurrencyDecimals(?string $currency): int
    {
        return self::CURRENCY_DECIMALS[\strtoupper((string)$currency)] ?? 0;
    }
}

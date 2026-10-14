<?php
declare(strict_types=1);

namespace Fintoc\Payment\Test\Unit\Utils;

use Fintoc\Payment\Utils\AmountUtils;
use PHPUnit\Framework\TestCase;

class AmountUtilsTest extends TestCase
{
    /**
     * @dataProvider toMinorUnitsProvider
     */
    public function testToMinorUnits($amount, ?string $currency, int $expected): void
    {
        $this->assertSame($expected, AmountUtils::toMinorUnits($amount, $currency));
    }

    public static function toMinorUnitsProvider(): array
    {
        return [
            'CLP integer' => [1000, 'CLP', 1000],
            'MXN with cents' => [100.50, 'MXN', 10050],
            'MXN float drift' => [0.29, 'MXN', 29],
            'unknown currency keeps integer' => [1000.4, 'USD', 1000],
        ];
    }

    /**
     * @dataProvider fromMinorUnitsProvider
     */
    public function testFromMinorUnits($amount, ?string $currency, float $expected): void
    {
        $this->assertSame($expected, AmountUtils::fromMinorUnits($amount, $currency));
    }

    public static function fromMinorUnitsProvider(): array
    {
        return [
            'CLP' => [1000, 'CLP', 1000.0],
            'MXN' => [10050, 'MXN', 100.5],
        ];
    }

    public function testRoundTripKeepsMxnCents(): void
    {
        $this->assertSame(20.25, AmountUtils::fromMinorUnits(AmountUtils::toMinorUnits(20.25, 'MXN'), 'MXN'));
    }
}

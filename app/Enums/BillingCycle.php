<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use Carbon\CarbonImmutable;

enum BillingCycle: string
{
    use HasOptions;

    case OneTime = 'one_time';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case HalfYearly = 'half_yearly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::OneTime => 'One-time',
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
            self::HalfYearly => 'Half-yearly',
            self::Yearly => 'Yearly',
        };
    }

    /**
     * Length of one period in months, or null for a one-time charge.
     */
    public function months(): ?int
    {
        return match ($this) {
            self::OneTime => null,
            self::Monthly => 1,
            self::Quarterly => 3,
            self::HalfYearly => 6,
            self::Yearly => 12,
        };
    }

    /**
     * The last day of a period starting on the given date (inclusive), e.g. 27 Oct 2026 → 26 Oct 2027.
     * Month ends are clamped, so a monthly period from 31 Jan ends on 28 Feb (or 29 Feb).
     */
    public function periodEnd(CarbonImmutable $start): ?CarbonImmutable
    {
        $months = $this->months();

        return $months === null ? null : $start->addMonthsNoOverflow($months)->subDay();
    }

    /**
     * The first day of the period that ends on the given date.
     */
    public function periodStartFor(CarbonImmutable $end): ?CarbonImmutable
    {
        $months = $this->months();

        return $months === null ? null : $end->addDay()->subMonthsNoOverflow($months);
    }

    /**
     * The period that contains the given day, counting whole periods from the original start date.
     * A subscription that started on 27 Oct 2023 (yearly) is, on 20 Oct 2026, in 27 Oct 2025 – 26 Oct 2026.
     *
     * @return array{CarbonImmutable, CarbonImmutable}|null
     */
    public function periodContaining(CarbonImmutable $start, CarbonImmutable $day): ?array
    {
        $months = $this->months();

        if ($months === null) {
            return null;
        }

        $periods = 0;

        while ($start->addMonthsNoOverflow($months * ($periods + 1))->lte($day)) {
            $periods++;
        }

        $periodStart = $start->addMonthsNoOverflow($months * $periods);

        return [$periodStart, $start->addMonthsNoOverflow($months * ($periods + 1))->subDay()];
    }

    /**
     * How many times this cycle is billed in a year (0 for one-time).
     */
    public function perYear(): int
    {
        $months = $this->months();

        return $months === null ? 0 : intdiv(12, $months);
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::OneTime => 'one-time',
            self::Monthly => '/mo',
            self::Quarterly => '/qtr',
            self::HalfYearly => '/half-yr',
            self::Yearly => '/yr',
        };
    }
}

<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The Indian financial year, April to March, labelled like "2026-27".
 */
final class FinancialYear
{
    private function __construct(public readonly int $startYear) {}

    public static function for(CarbonInterface $date): self
    {
        return new self($date->month >= 4 ? $date->year : $date->year - 1);
    }

    public static function current(): self
    {
        return self::for(CarbonImmutable::today());
    }

    public function label(): string
    {
        return sprintf('%d-%02d', $this->startYear, ($this->startYear + 1) % 100);
    }

    public function start(): CarbonImmutable
    {
        return CarbonImmutable::create($this->startYear, 4, 1)->startOfDay();
    }

    public function end(): CarbonImmutable
    {
        return CarbonImmutable::create($this->startYear + 1, 3, 31)->startOfDay();
    }
}

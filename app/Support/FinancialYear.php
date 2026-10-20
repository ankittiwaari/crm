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

    /**
     * Parse a label like "2026-27"; null when it is not a financial year label.
     */
    public static function fromLabel(?string $label): ?self
    {
        if ($label === null || preg_match('/^(\d{4})-(\d{2})$/', $label, $matches) !== 1) {
            return null;
        }

        $year = new self((int) $matches[1]);

        return $year->label() === $label ? $year : null;
    }

    public function previous(): self
    {
        return new self($this->startYear - 1);
    }

    /**
     * The twelve months of the year, April first, as the first day of each month.
     *
     * @return list<CarbonImmutable>
     */
    public function months(): array
    {
        return array_map(fn (int $offset): CarbonImmutable => $this->start()->addMonths($offset), range(0, 11));
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

<?php

namespace App\Services\Invoices;

use App\Enums\TaxType;

/**
 * GST arithmetic for invoices. All amounts are integer paise; tax is rounded half-up per line,
 * split equally into CGST and SGST within the state, and the grand total is rounded to the rupee.
 */
final class InvoiceCalculator
{
    /**
     * @param  list<array{quantity: int, unit_price: int, discount?: int|null, tax_rate: string|int|float}>  $lines
     * @return array{
     *     lines: list<array{amount: int, discount: int, taxable_amount: int, cgst: int, sgst: int, igst: int, line_total: int}>,
     *     subtotal: int, discount_total: int, taxable_total: int, cgst_total: int, sgst_total: int, igst_total: int,
     *     round_off: int, total: int
     * }
     */
    public function calculate(array $lines, TaxType $taxType): array
    {
        $results = [];

        foreach ($lines as $line) {
            $amount = $line['quantity'] * $line['unit_price'];
            $discount = min($amount, max(0, (int) ($line['discount'] ?? 0)));
            $taxable = $amount - $discount;
            $basisPoints = (int) round((float) $line['tax_rate'] * 100);

            [$cgst, $sgst, $igst] = $taxType === TaxType::IntraState
                ? [self::percent($taxable, $basisPoints, 2), self::percent($taxable, $basisPoints, 2), 0]
                : [0, 0, self::percent($taxable, $basisPoints, 1)];

            $results[] = [
                'amount' => $amount,
                'discount' => $discount,
                'taxable_amount' => $taxable,
                'cgst' => $cgst,
                'sgst' => $sgst,
                'igst' => $igst,
                'line_total' => $taxable + $cgst + $sgst + $igst,
            ];
        }

        $sum = fn (string $key): int => array_sum(array_column($results, $key));
        $gross = $sum('line_total');
        $total = intdiv($gross + 50, 100) * 100;

        return [
            'lines' => $results,
            'subtotal' => $sum('amount'),
            'discount_total' => $sum('discount'),
            'taxable_total' => $sum('taxable_amount'),
            'cgst_total' => $sum('cgst'),
            'sgst_total' => $sum('sgst'),
            'igst_total' => $sum('igst'),
            'round_off' => $total - $gross,
            'total' => $total,
        ];
    }

    /**
     * $amount × (basis points / 10000) ÷ $parts, rounded half-up.
     */
    private static function percent(int $amount, int $basisPoints, int $parts): int
    {
        $denominator = 10_000 * $parts;

        return intdiv($amount * $basisPoints + intdiv($denominator, 2), $denominator);
    }
}

<?php

namespace App\Services\Invoices;

use App\Models\InvoiceSequence;
use App\Support\FinancialYear;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Hands out gapless invoice numbers per financial year, e.g. INV/2026-27/0001.
 */
class InvoiceNumberGenerator
{
    /**
     * Reserve the next number. Must run inside the transaction that saves the invoice, so a
     * failed save releases the number and two finalisations never get the same one.
     *
     * @return array{number: string, financial_year: string, sequence: int}
     */
    public function next(CarbonInterface $issueDate, string $prefix): array
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Invoice numbers must be reserved inside a database transaction.');
        }

        $year = FinancialYear::for($issueDate)->label();

        InvoiceSequence::query()->firstOrCreate(['financial_year' => $year]);

        $sequence = InvoiceSequence::query()->where('financial_year', $year)->lockForUpdate()->firstOrFail();
        $sequence->increment('last_number');

        return [
            'number' => sprintf('%s/%s/%04d', $prefix, $year, $sequence->last_number),
            'financial_year' => $year,
            'sequence' => $sequence->last_number,
        ];
    }
}

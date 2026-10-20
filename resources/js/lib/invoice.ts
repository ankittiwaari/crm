import { rupeesToPaise } from '@/lib/billing';
import type { InvoiceLine } from '@/types';

export type InvoiceTotals = {
    lines: { taxable: number; tax: number }[];
    taxable: number;
    cgst: number;
    sgst: number;
    igst: number;
    roundOff: number;
    total: number;
};

function percent(amount: number, basisPoints: number, parts: number): number {
    const denominator = 10_000 * parts;

    return Math.floor((amount * basisPoints + denominator / 2) / denominator);
}

/**
 * Mirrors App\Services\Invoices\InvoiceCalculator for live totals while editing.
 * The server recalculates and is the authority.
 */
export function calculateTotals(
    lines: InvoiceLine[],
    intraState: boolean,
): InvoiceTotals {
    const results = lines.map((line) => {
        const quantity = Math.max(0, Math.trunc(Number(line.quantity) || 0));
        const amount = quantity * (rupeesToPaise(line.unit_price) ?? 0);
        const discount = Math.min(amount, rupeesToPaise(line.discount) ?? 0);
        const taxable = amount - discount;
        const basisPoints = Math.round(Number(line.tax_rate) * 100);
        const cgst = intraState ? percent(taxable, basisPoints, 2) : 0;
        const igst = intraState ? 0 : percent(taxable, basisPoints, 1);

        return { taxable, cgst, sgst: cgst, igst };
    });

    const sum = (key: 'taxable' | 'cgst' | 'sgst' | 'igst') =>
        results.reduce((total, line) => total + line[key], 0);
    const gross = sum('taxable') + sum('cgst') + sum('sgst') + sum('igst');
    const total = Math.floor((gross + 50) / 100) * 100;

    return {
        lines: results.map((line) => ({
            taxable: line.taxable,
            tax: line.cgst + line.sgst + line.igst,
        })),
        taxable: sum('taxable'),
        cgst: sum('cgst'),
        sgst: sum('sgst'),
        igst: sum('igst'),
        roundOff: total - gross,
        total,
    };
}

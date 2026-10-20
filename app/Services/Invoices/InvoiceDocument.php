<?php

namespace App\Services\Invoices;

use App\Enums\TaxType;
use App\Models\Client;
use App\Models\CompanyProfile;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Support\AmountInWords;
use App\Support\UpiLink;
use Illuminate\Support\Facades\Storage;

/**
 * Everything needed to show or print an invoice. Issued invoices use their frozen snapshots;
 * drafts use the current business profile and client details.
 */
class InvoiceDocument
{
    public function __construct(
        private InvoiceParties $parties,
        private UpiQrCode $qrCode,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function data(Invoice $invoice, bool $withImages = true): array
    {
        $invoice->loadMissing('items');

        $seller = $invoice->seller_snapshot ?? $this->parties->seller(CompanyProfile::current());
        $client = $invoice->client_snapshot ?? $this->parties->client(Client::withTrashed()->findOrFail($invoice->client_id));
        $balance = $invoice->isDraft() ? $invoice->total : $invoice->balanceDue();
        $upiLink = filled($seller['upi_id'] ?? null) && $balance > 0
            ? UpiLink::make($seller['upi_id'], $seller['legal_name'] ?? null, $balance, $invoice->number)
            : null;

        return [
            'number' => $invoice->number,
            'display_number' => $invoice->displayNumber(),
            'is_draft' => $invoice->isDraft(),
            'title' => filled($seller['gstin'] ?? null) ? 'Tax Invoice' : 'Invoice',
            'issue_date' => $invoice->issue_date,
            'due_date' => $invoice->due_date,
            'place_of_supply' => $invoice->place_of_supply,
            'place_of_supply_name' => $client['state_code'] === $invoice->place_of_supply
                ? $client['state_name']
                : ($seller['state_code'] === $invoice->place_of_supply ? $seller['state_name'] : null),
            'is_intra_state' => $invoice->tax_type === TaxType::IntraState,
            'seller' => $seller,
            'client' => $client,
            'items' => $invoice->items->map(fn (InvoiceItem $item): array => [
                'description' => $item->description,
                'sac_code' => $item->sac_code,
                'period_start' => $item->period_start,
                'period_end' => $item->period_end,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount' => $item->discount,
                'tax_rate' => $item->tax_rate,
                'taxable_amount' => $item->taxable_amount,
                'line_total' => $item->line_total,
            ])->all(),
            'tax_summary' => $this->taxSummary($invoice),
            'subtotal' => $invoice->subtotal,
            'discount_total' => $invoice->discount_total,
            'taxable_total' => $invoice->taxable_total,
            'cgst_total' => $invoice->cgst_total,
            'sgst_total' => $invoice->sgst_total,
            'igst_total' => $invoice->igst_total,
            'round_off' => $invoice->round_off,
            'total' => $invoice->total,
            'amount_settled' => $invoice->amount_settled,
            'balance_due' => $balance,
            'amount_in_words' => AmountInWords::rupees($invoice->total),
            'notes' => $invoice->notes,
            'terms' => $this->terms($invoice, $seller),
            'upi_link' => $upiLink,
            'upi_qr' => $withImages && $upiLink ? $this->qrCode->dataUri($upiLink) : null,
            'logo' => $withImages ? $this->logo($seller['logo_path'] ?? null) : null,
            'status' => $invoice->status->value,
        ];
    }

    /**
     * Tax by SAC code and rate, as GST rules require on the invoice.
     *
     * @return list<array{sac_code: string, tax_rate: string, taxable: int, cgst: int, sgst: int, igst: int}>
     */
    private function taxSummary(Invoice $invoice): array
    {
        return array_values($invoice->items
            ->groupBy(fn (InvoiceItem $item): string => $item->sac_code.'|'.$item->tax_rate)
            ->map(fn ($items): array => [
                'sac_code' => $items->first()->sac_code,
                'tax_rate' => $items->first()->tax_rate,
                'taxable' => (int) $items->sum('taxable_amount'),
                'cgst' => (int) $items->sum('cgst'),
                'sgst' => (int) $items->sum('sgst'),
                'igst' => (int) $items->sum('igst'),
            ])
            ->all());
    }

    /**
     * Payment terms, the invoice's own terms, and the MSME clause when it is switched on.
     *
     * @param  array<string, mixed>  $seller
     * @return list<string>
     */
    private function terms(Invoice $invoice, array $seller): array
    {
        $days = (int) $invoice->issue_date->diffInDays($invoice->due_date);
        $terms = [$days === 0 ? 'Payment due on receipt.' : "Payment due within {$days} days of the invoice date."];

        foreach (preg_split('/\R/', (string) $invoice->terms) ?: [] as $line) {
            if (trim($line) !== '') {
                $terms[] = trim($line);
            }
        }

        if (($seller['show_msme_clause'] ?? false) && filled($seller['udyam_number'] ?? null)) {
            $terms[] = "Supplier is registered under the MSMED Act, 2006 (Udyam Reg. No. {$seller['udyam_number']}). "
                .'Payment beyond 45 days attracts interest under Section 16 of the Act.';
        }

        return $terms;
    }

    private function logo(?string $path): ?string
    {
        if ($path === null || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) Storage::disk('public')->get($path));
    }
}

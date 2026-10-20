<?php

namespace App\Actions\Invoices;

use App\Models\Client;
use App\Models\CompanyProfile;
use App\Models\Invoice;
use App\Services\Invoices\InvoiceCalculator;
use App\Services\Invoices\InvoiceParties;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use LogicException;

class SaveDraftInvoice
{
    public function __construct(
        private InvoiceCalculator $calculator,
        private InvoiceParties $parties,
    ) {}

    /**
     * Create or update a draft invoice and its lines, working out GST from the client's state.
     *
     * @param  array<string, mixed>  $data  Validated invoice fields.
     * @param  list<array<string, mixed>>  $items  Validated lines with amounts in paise.
     */
    public function handle(array $data, array $items, ?Invoice $invoice = null): Invoice
    {
        $invoice ??= new Invoice;

        if (! $invoice->isDraft()) {
            throw new LogicException('Only draft invoices can be edited.');
        }

        return DB::transaction(function () use ($invoice, $data, $items): Invoice {
            $invoice->fill($data);
            $this->calculate($invoice, $items);
            $invoice->save();

            $invoice->items()->delete();

            foreach ($items as $position => $item) {
                $invoice->items()->create([
                    ...Arr::only($item, [
                        'subscription_id', 'product_id', 'description', 'sac_code', 'period_start',
                        'period_end', 'quantity', 'unit_price', 'discount', 'tax_rate',
                    ]),
                    ...$item['calculated'],
                    'position' => $position,
                ]);
            }

            return $invoice;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $items  Calculated amounts are added to each line.
     */
    private function calculate(Invoice $invoice, array &$items): void
    {
        $profile = CompanyProfile::current();
        $client = Client::withTrashed()->findOrFail($invoice->client_id);

        $invoice->place_of_supply = $this->parties->placeOfSupply($client, $profile);
        $invoice->tax_type = $this->parties->taxType($invoice->place_of_supply, $profile);

        $result = $this->calculator->calculate(array_map(fn (array $item): array => [
            'quantity' => (int) $item['quantity'],
            'unit_price' => (int) $item['unit_price'],
            'discount' => (int) ($item['discount'] ?? 0),
            'tax_rate' => $item['tax_rate'],
        ], $items), $invoice->tax_type);

        foreach ($result['lines'] as $index => $line) {
            $items[$index]['discount'] = $line['discount'];
            $items[$index]['calculated'] = Arr::except($line, ['amount', 'discount']);
        }

        $invoice->forceFill(Arr::except($result, ['lines']));
    }
}

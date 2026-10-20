<?php

namespace App\Http\Requests\Subscriptions;

use App\Support\Money;

/**
 * Accepts rupee amounts like "48,000.50" and hands paise to the application.
 */
trait ConvertsRupees
{
    protected function rupeesRule(): string
    {
        return 'regex:/^\d{1,9}(\.\d{1,2})?$/';
    }

    /**
     * @param  list<string>  $keys
     */
    protected function stripRupeeFormatting(array $keys): void
    {
        foreach ($keys as $key) {
            if ($this->filled($key)) {
                $this->merge([$key => str_replace([',', ' ', '₹'], '', (string) $this->input($key))]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    protected function rupeesToPaise(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = filled($data[$key]) ? Money::toPaise($data[$key]) : null;
            }
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    protected function rupeeMessages(): array
    {
        return [
            'rate.regex' => 'Enter the amount in rupees, e.g. 48000 or 48000.50.',
            'setup_fee.regex' => 'Enter the amount in rupees, e.g. 15000.',
        ];
    }
}

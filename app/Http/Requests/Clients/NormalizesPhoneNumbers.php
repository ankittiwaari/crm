<?php

namespace App\Http\Requests\Clients;

use App\Support\PhoneNumber;

/**
 * Stores phone numbers in E.164 so WhatsApp links and search work consistently.
 */
trait NormalizesPhoneNumbers
{
    /**
     * @param  list<string>  $keys  Dot-notation input keys.
     */
    protected function normalizePhoneNumbers(array $keys): void
    {
        $input = $this->all();

        foreach ($keys as $key) {
            if (filled(data_get($input, $key))) {
                data_set($input, $key, PhoneNumber::normalize((string) data_get($input, $key)));
            }
        }

        $this->replace($input);
    }

    protected function phoneRule(): string
    {
        return 'regex:/^\+[1-9]\d{7,14}$/';
    }
}

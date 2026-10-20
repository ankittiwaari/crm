<?php

namespace App\Actions\Clients;

use App\Models\Client;
use Illuminate\Support\Facades\DB;

class CreateClient
{
    public function __construct(private SaveContact $saveContact) {}

    /**
     * Create a client, optionally with its first contact, who becomes the primary contact.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $contact
     */
    public function handle(array $attributes, ?array $contact = null): Client
    {
        return DB::transaction(function () use ($attributes, $contact): Client {
            $client = Client::create($attributes);

            if ($contact !== null) {
                $this->saveContact->handle($client, [
                    ...$contact,
                    'is_primary' => true,
                    'receives_invoices' => true,
                ]);
            }

            return $client;
        });
    }
}

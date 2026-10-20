<?php

namespace App\Actions\Clients;

use App\Models\Client;
use App\Models\Contact;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveContact
{
    /**
     * Create or update a client's contact. A client always has exactly one primary contact
     * once it has any, and the WhatsApp opt-in records when consent was given.
     *
     * @param  array<string, mixed>  $data  Validated input.
     */
    public function handle(Client $client, array $data, ?Contact $contact = null): Contact
    {
        return DB::transaction(function () use ($client, $data, $contact): Contact {
            $contact ??= $client->contacts()->make();

            $contact->fill(Arr::only($data, ['name', 'designation', 'email', 'phone', 'whatsapp_number', 'receives_invoices']));

            if (array_key_exists('whatsapp_opt_in', $data)) {
                $contact->whatsapp_opted_in_at = $data['whatsapp_opt_in']
                    ? ($contact->whatsapp_opted_in_at ?? now())
                    : null;
            }

            $makePrimary = (bool) ($data['is_primary'] ?? false)
                || ! $client->contacts()->whereKeyNot($contact->getKey())->where('is_primary', true)->exists();

            if ($makePrimary) {
                $client->contacts()->whereKeyNot($contact->getKey())->update(['is_primary' => false]);
            }

            $contact->is_primary = $makePrimary;
            $contact->save();

            return $contact;
        });
    }

    /**
     * Delete a contact, promoting another contact to primary when needed.
     */
    public function delete(Contact $contact): void
    {
        DB::transaction(function () use ($contact): void {
            $contact->delete();

            if ($contact->is_primary) {
                Contact::query()
                    ->whereBelongsTo($contact->client)
                    ->oldest('id')
                    ->first()
                    ?->update(['is_primary' => true]);
            }
        });
    }
}

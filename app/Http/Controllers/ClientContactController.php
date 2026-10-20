<?php

namespace App\Http\Controllers;

use App\Actions\Clients\SaveContact;
use App\Http\Requests\Clients\ContactRequest;
use App\Models\Client;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ClientContactController extends Controller
{
    public function store(ContactRequest $request, Client $client, SaveContact $saveContact): RedirectResponse
    {
        $contact = $saveContact->handle($client, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name added.', ['name' => $contact->name])]);

        return to_route('clients.show', $client);
    }

    public function update(ContactRequest $request, Contact $contact, SaveContact $saveContact): RedirectResponse
    {
        $saveContact->handle($contact->client, $request->validated(), $contact);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact updated.')]);

        return to_route('clients.show', $contact->client_id);
    }

    public function destroy(Contact $contact, SaveContact $saveContact): RedirectResponse
    {
        $saveContact->delete($contact);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name removed.', ['name' => $contact->name])]);

        return to_route('clients.show', $contact->client_id);
    }
}

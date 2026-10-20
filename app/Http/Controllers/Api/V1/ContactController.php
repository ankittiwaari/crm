<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Clients\SaveContact;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\ContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Client;
use App\Models\Contact;
use Illuminate\Http\Response;

class ContactController extends Controller
{
    public function store(ContactRequest $request, Client $client, SaveContact $saveContact): ContactResource
    {
        return ContactResource::make($saveContact->handle($client, $request->validated()));
    }

    public function update(ContactRequest $request, Contact $contact, SaveContact $saveContact): ContactResource
    {
        return ContactResource::make($saveContact->handle($contact->client, $request->validated(), $contact));
    }

    public function destroy(Contact $contact, SaveContact $saveContact): Response
    {
        $saveContact->delete($contact);

        return response()->noContent();
    }
}

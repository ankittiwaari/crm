<?php

use App\Enums\MessageChannel;
use App\Mail\InvoiceMail;
use App\Models\Client;
use App\Models\CompanyProfile;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->travelTo('2026-10-20 10:00:00');
    CompanyProfile::factory()->create();
});

function issuedInvoice(): Invoice
{
    $client = Client::factory()->create(['email' => 'office@sunrise.test']);
    $draft = test()->actingAs(User::factory()->create());
    $draft->post(route('invoices.store'), [
        'client_id' => $client->id,
        'issue_date' => today()->toDateString(),
        'due_date' => today()->addDays(30)->toDateString(),
        'items' => [['description' => 'School ERP', 'sac_code' => '998314', 'quantity' => 1, 'unit_price' => '48000', 'tax_rate' => '18']],
    ]);
    $invoice = Invoice::query()->latest('id')->firstOrFail();
    $draft->post(route('invoices.finalise', $invoice));

    return $invoice->refresh();
}

test('the PDF can be viewed and downloaded', function () {
    $user = User::factory()->create();
    $invoice = issuedInvoice();

    $this->actingAs($user)
        ->get(route('invoices.pdf', $invoice))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="INV-2026-27-0001.pdf"');

    $this->actingAs($user)
        ->get(route('invoices.pdf', ['invoice' => $invoice, 'download' => 1]))
        ->assertHeader('Content-Disposition', 'attachment; filename="INV-2026-27-0001.pdf"');
});

test('clients can open the PDF through a signed link without logging in', function () {
    $invoice = issuedInvoice();
    auth()->logout();

    $this->get(URL::temporarySignedRoute('invoices.public-pdf', now()->addDay(), ['invoice' => $invoice->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->get(route('invoices.public-pdf', $invoice))->assertForbidden();
    $this->get(URL::temporarySignedRoute('invoices.public-pdf', now()->subMinute(), ['invoice' => $invoice->id]))->assertForbidden();
});

test('drafts are never shared through the public link', function () {
    $draft = Invoice::factory()->create(['status' => 'draft', 'number' => null]);

    $this->get(URL::temporarySignedRoute('invoices.public-pdf', now()->addDay(), ['invoice' => $draft->id]))
        ->assertNotFound();
});

test('sending on WhatsApp logs the message and returns a prefilled chat link', function () {
    $user = User::factory()->create();
    $invoice = issuedInvoice();
    $contact = Contact::factory()->for($invoice->client)->create(['name' => 'R. Sharma', 'phone' => '+919845012345']);

    $response = $this->actingAs($user)
        ->postJson(route('invoices.whatsapp', $invoice), ['contact' => $contact->id])
        ->assertOk();

    expect($response->json('url'))->toStartWith('https://wa.me/919845012345?text=Dear%20R.%20Sharma')
        ->and(urldecode($response->json('url')))->toContain($invoice->number)->toContain('/i/'.$invoice->id);

    $this->assertDatabaseHas('messages', [
        'messageable_id' => $invoice->id,
        'contact_id' => $contact->id,
        'channel' => MessageChannel::WhatsAppLink->value,
        'recipient' => '+919845012345',
    ]);
});

test('WhatsApp can only be sent to the invoice client\'s own contacts', function () {
    $user = User::factory()->create();
    $invoice = issuedInvoice();
    $stranger = Contact::factory()->create(['phone' => '+919845099999']);

    $this->actingAs($user)
        ->postJson(route('invoices.whatsapp', $invoice), ['contact' => $stranger->id])
        ->assertNotFound();
});

test('emailing queues the invoice with the PDF and logs it', function () {
    Mail::fake();
    $user = User::factory()->create();
    $invoice = issuedInvoice();

    $this->actingAs($user)
        ->post(route('invoices.email', $invoice))
        ->assertRedirect(route('invoices.show', $invoice));

    Mail::assertQueued(InvoiceMail::class, fn (InvoiceMail $mail) => $mail->hasTo('office@sunrise.test') && $mail->invoice->is($invoice));
    $this->assertDatabaseHas('messages', ['messageable_id' => $invoice->id, 'channel' => 'email', 'recipient' => 'office@sunrise.test']);
});

test('drafts cannot be sent', function () {
    $user = User::factory()->create();
    $draft = Invoice::factory()->create(['status' => 'draft', 'number' => null]);

    $this->actingAs($user)->post(route('invoices.email', $draft))->assertStatus(409);
});

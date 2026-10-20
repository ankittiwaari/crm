<?php

use App\Enums\ActivityType;
use App\Enums\ReminderType;
use App\Models\Activity;
use App\Models\Client;
use App\Models\CompanyProfile;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Message;
use App\Models\Reminder;
use App\Models\Subscription;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppMessenger;
use App\Services\WhatsApp\WhatsAppTemplates;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo('2026-10-20 10:00:00');
    $this->profile = CompanyProfile::factory()->create(['legal_name' => 'Acme Software']);
    $this->profile->forceFill([
        'whatsapp_enabled' => true,
        'whatsapp_waba_id' => '1029384756',
        'whatsapp_phone_number_id' => '5647382910',
        'whatsapp_access_token' => 'secret-token',
        'whatsapp_app_secret' => 'app-secret',
        'whatsapp_verify_token' => 'verify-me',
        'whatsapp_templates' => collect(WhatsAppTemplates::definitions())
            ->mapWithKeys(fn ($definition, string $type) => [$type => ['name' => $type, 'language' => 'en']])
            ->all(),
    ])->save();
    $this->user = User::factory()->create();
});

function optedInContact(?Client $client = null, array $attributes = []): Contact
{
    return Contact::factory()->primary()->for($client ?? Client::factory()->create())->create([
        'name' => 'R. Sharma',
        'phone' => '+919845012345',
        'whatsapp_opted_in_at' => now(),
        'receives_invoices' => true,
        ...$attributes,
    ]);
}

function fakeMeta(string $messageId = 'wamid.ABC'): void
{
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => $messageId]]])]);
}

function signedWebhook(array $payload): TestResponse
{
    $body = json_encode($payload);

    return test()->call('POST', route('webhooks.whatsapp'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'app-secret'),
    ], $body);
}

test('an invoice is sent as a template with the PDF attached and logged as sent', function () {
    fakeMeta();
    $contact = optedInContact();
    $invoice = Invoice::factory()->for($contact->client)->create(['number' => 'INV/2026-27/0001', 'seller_snapshot' => ['legal_name' => 'Acme Software']]);

    $this->actingAs($this->user)
        ->post(route('invoices.whatsapp-api', $invoice), ['contact' => $contact->id])
        ->assertSessionHasNoErrors();

    Http::assertSent(function (Request $request) {
        $template = $request['template'];

        return $request->url() === 'https://graph.facebook.com/v26.0/5647382910/messages'
            && $request->hasHeader('Authorization', 'Bearer secret-token')
            && $request['to'] === '919845012345'
            && $template['name'] === 'invoice_issued'
            && str_contains($template['components'][0]['parameters'][0]['document']['link'], '/i/')
            && $template['components'][0]['parameters'][0]['document']['filename'] === 'INV-2026-27-0001.pdf'
            && array_column($template['components'][1]['parameters'], 'text') === ['R. Sharma', 'INV/2026-27/0001', '₹63,720.00', '19 Nov 2026', 'Acme Software'];
    });

    $message = Message::sole();

    expect($message->status)->toBe('sent')
        ->and($message->provider_message_id)->toBe('wamid.ABC')
        ->and($message->body)->toBe('Dear R. Sharma, please find attached invoice INV/2026-27/0001 for ₹63,720.00, due by 19 Nov 2026. Thank you, Acme Software');
});

test('contacts who have not agreed to WhatsApp messages are never messaged', function () {
    Http::fake();
    $contact = optedInContact(attributes: ['whatsapp_opted_in_at' => null]);
    $invoice = Invoice::factory()->for($contact->client)->create();

    $this->actingAs($this->user)
        ->post(route('invoices.whatsapp-api', $invoice), ['contact' => $contact->id])
        ->assertSessionHasErrors(['whatsapp' => 'R. Sharma has not agreed to WhatsApp messages.']);

    Http::assertNothingSent();
    expect(Message::count())->toBe(0);
});

test('nothing is sent while WhatsApp is switched off or a template is missing', function () {
    Http::fake();
    $contact = optedInContact();
    $messenger = app(WhatsAppMessenger::class);

    $this->profile->forceFill(['whatsapp_templates' => []])->save();
    expect($messenger->blocker($contact, WhatsAppTemplates::INVOICE_ISSUED))->toBe('Choose the "Invoice issued" template in Settings › WhatsApp.');

    $this->profile->forceFill(['whatsapp_enabled' => false])->save();
    expect($messenger->blocker($contact, WhatsAppTemplates::INVOICE_ISSUED))->toBe('WhatsApp is not connected yet. Set it up in Settings › WhatsApp.');
});

test('a message Meta rejects is marked failed with Meta\'s reason', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Template name does not exist in the translation']], 400)]);
    $contact = optedInContact();
    $invoice = Invoice::factory()->for($contact->client)->create();

    $this->actingAs($this->user)->post(route('invoices.whatsapp-api', $invoice), ['contact' => $contact->id]);

    $message = Message::sole();

    expect($message->status)->toBe('failed')
        ->and($message->error)->toBe('Template name does not exist in the translation');
});

test('overdue invoices are sent as payment reminders with the balance due', function () {
    fakeMeta();
    $contact = optedInContact();
    $invoice = Invoice::factory()->overdueBy(10)->for($contact->client)->create(['amount_settled' => 1_000_000]);

    $this->actingAs($this->user)->post(route('invoices.whatsapp-api', $invoice), ['contact' => $contact->id]);

    Http::assertSent(fn (Request $request) => $request['template']['name'] === 'payment_reminder'
        && $request['template']['components'][1]['parameters'][2]['text'] === '₹53,720.00');
});

test('clients with automation get a thank-you when a payment is recorded', function () {
    fakeMeta();
    $client = Client::factory()->create(['whatsapp_automation' => true]);
    optedInContact($client);
    $invoice = Invoice::factory()->for($client)->create(['number' => 'INV/2026-27/0001']);

    $this->actingAs($this->user)->post(route('invoices.payments.store', $invoice), [
        'amount' => '63720', 'paid_on' => '2026-10-20', 'method' => 'upi',
    ]);

    Http::assertSent(fn (Request $request) => $request['template']['name'] === 'payment_received'
        && $request['template']['components'][0]['parameters'][1]['text'] === '₹63,720.00');
});

test('clients without automation get no automatic messages', function () {
    Http::fake();
    $client = Client::factory()->create(['whatsapp_automation' => false]);
    optedInContact($client);
    $invoice = Invoice::factory()->for($client)->create();

    $this->actingAs($this->user)->post(route('invoices.payments.store', $invoice), [
        'amount' => '1000', 'paid_on' => '2026-10-20', 'method' => 'upi',
    ]);

    Http::assertNothingSent();
});

test('the daily checks send one renewal notice and one overdue reminder a week', function () {
    fakeMeta();
    $client = Client::factory()->create(['whatsapp_automation' => true]);
    optedInContact($client);
    Subscription::factory()->for($client)->renewingIn(20)->create();
    Invoice::factory()->overdueBy(3)->for($client)->create();

    $this->artisan('crm:daily-checks');
    $this->artisan('crm:daily-checks');

    expect(Message::query()->where('template', WhatsAppTemplates::RENEWAL_DUE)->count())->toBe(1)
        ->and(Message::query()->where('template', WhatsAppTemplates::PAYMENT_REMINDER)->count())->toBe(1);
});

test('Meta can verify the webhook with the verify token', function () {
    $this->get(route('webhooks.whatsapp.verify').'?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=12345')
        ->assertOk()
        ->assertSeeText('12345');

    $this->get(route('webhooks.whatsapp.verify').'?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=12345')
        ->assertForbidden();
});

test('webhooks without a valid signature are rejected', function () {
    $this->postJson(route('webhooks.whatsapp'), ['entry' => []], ['X-Hub-Signature-256' => 'sha256=forged'])
        ->assertForbidden();
});

test('delivery updates move a message forward but never backwards', function () {
    $message = Message::query()->create([
        'client_id' => Client::factory()->create()->id, 'channel' => 'whatsapp_api', 'template' => 'invoice_issued',
        'recipient' => '+919845012345', 'status' => 'sent', 'provider_message_id' => 'wamid.ABC', 'sent_at' => now(),
    ]);
    $status = fn (string $value) => ['entry' => [['changes' => [['field' => 'messages', 'value' => ['statuses' => [['id' => 'wamid.ABC', 'status' => $value]]]]]]]];

    signedWebhook($status('read'))->assertOk();
    signedWebhook($status('delivered'))->assertOk();

    expect($message->refresh()->status)->toBe('read');
});

test('a failed delivery records the reason', function () {
    $message = Message::query()->create([
        'client_id' => Client::factory()->create()->id, 'channel' => 'whatsapp_api', 'template' => 'invoice_issued',
        'recipient' => '+919845012345', 'status' => 'sent', 'provider_message_id' => 'wamid.ABC', 'sent_at' => now(),
    ]);

    signedWebhook(['entry' => [['changes' => [['field' => 'messages', 'value' => ['statuses' => [[
        'id' => 'wamid.ABC', 'status' => 'failed', 'errors' => [['title' => 'Message undeliverable', 'error_data' => ['details' => 'Recipient is not on WhatsApp']]],
    ]]]]]]]]);

    expect($message->refresh()->status)->toBe('failed')
        ->and($message->error)->toBe('Message undeliverable. Recipient is not on WhatsApp');
});

test('a client\'s reply is logged on their timeline with a reminder to answer', function () {
    $contact = optedInContact();
    $payload = ['entry' => [['changes' => [['field' => 'messages', 'value' => [
        'contacts' => [['wa_id' => '919845012345', 'profile' => ['name' => 'Sharma']]],
        'messages' => [['id' => 'wamid.IN1', 'from' => '919845012345', 'type' => 'text', 'text' => ['body' => 'Paid today via UPI']]],
    ]]]]]];

    signedWebhook($payload)->assertOk();
    signedWebhook($payload)->assertOk();

    $activity = Activity::sole();

    expect($activity->type)->toBe(ActivityType::WhatsApp)
        ->and($activity->body)->toBe('R. Sharma wrote: Paid today via UPI')
        ->and(Message::query()->where('direction', 'inbound')->sole()->client_id)->toBe($contact->client_id)
        ->and(Reminder::query()->where('type', ReminderType::FollowUp)->sole()->title)->toBe('Reply on WhatsApp to R. Sharma: Paid today via UPI');
});

test('a message from an unknown number becomes a reminder', function () {
    signedWebhook(['entry' => [['changes' => [['field' => 'messages', 'value' => [
        'contacts' => [['wa_id' => '919900011122', 'profile' => ['name' => 'New School']]],
        'messages' => [['id' => 'wamid.IN2', 'from' => '919900011122', 'type' => 'text', 'text' => ['body' => 'Need a demo']]],
    ]]]]]]);

    expect(Reminder::sole()->title)->toBe('Reply on WhatsApp to New School (not a saved contact): Need a demo')
        ->and(Activity::count())->toBe(0);
});

test('template approvals from Meta update the template status', function () {
    signedWebhook(['entry' => [['changes' => [['field' => 'message_template_status_update', 'value' => [
        'event' => 'APPROVED', 'message_template_name' => 'invoice_issued',
    ]]]]]]);

    expect(CompanyProfile::current()->whatsapp_template_statuses)->toBe(['invoice_issued' => 'APPROVED']);
});

test('the settings page never sends the token or app secret to the browser', function () {
    $this->actingAs($this->user)
        ->get(route('whatsapp-settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/whatsapp')
            ->where('settings.has_access_token', true)
            ->missing('settings.access_token')
            ->where('webhookUrl', route('webhooks.whatsapp')))
        ->assertDontSee('secret-token')
        ->assertDontSee('app-secret');

    $this->actingAs($this->user)->get(route('business-profile.edit'))->assertDontSee('secret-token');
});

test('saving settings keeps the stored token when left blank and encrypts a new one', function () {
    $payload = ['enabled' => true, 'waba_id' => '1029384756', 'phone_number_id' => '5647382910', 'templates' => ['invoice_issued' => ['name' => 'invoice_issued', 'language' => 'en']]];

    $this->actingAs($this->user)->patch(route('whatsapp-settings.update'), $payload)->assertSessionHasNoErrors();
    expect(CompanyProfile::current()->whatsapp_access_token)->toBe('secret-token');

    $this->actingAs($this->user)->patch(route('whatsapp-settings.update'), [...$payload, 'access_token' => 'new-token']);

    expect(CompanyProfile::current()->whatsapp_access_token)->toBe('new-token')
        ->and(DB::table('company_profiles')->value('whatsapp_access_token'))->not->toContain('new-token');
});

test('the connection test and template sync talk to Meta', function () {
    Http::fake([
        'graph.facebook.com/v26.0/5647382910*' => Http::response(['display_phone_number' => '+91 98450 00000', 'verified_name' => 'Acme', 'quality_rating' => 'GREEN']),
        'graph.facebook.com/v26.0/1029384756/message_templates*' => Http::response(['data' => [
            ['name' => 'renewal_due', 'language' => 'en_US', 'status' => 'APPROVED', 'category' => 'UTILITY'],
        ]]),
    ]);
    $this->profile->forceFill(['whatsapp_templates' => []])->save();

    $this->actingAs($this->user)->post(route('whatsapp-settings.test'))->assertSessionHasNoErrors();
    $this->actingAs($this->user)->post(route('whatsapp-settings.sync-templates'))->assertSessionHasNoErrors();

    $profile = CompanyProfile::current();

    expect($profile->whatsapp_template_statuses)->toBe(['renewal_due' => 'APPROVED'])
        ->and($profile->whatsappTemplate('renewal_due'))->toBe(['name' => 'renewal_due', 'language' => 'en_US']);
});

test('a test message uses Meta\'s hello_world template', function () {
    fakeMeta();

    $this->actingAs($this->user)
        ->post(route('whatsapp-settings.test-message'), ['to' => '98450 12345'])
        ->assertSessionHasNoErrors();

    Http::assertSent(fn (Request $request) => $request['template']['name'] === 'hello_world' && $request['to'] === '919845012345');
});

test('the mobile app can send an invoice on WhatsApp', function () {
    fakeMeta();
    Sanctum::actingAs($this->user);
    $contact = optedInContact();
    $invoice = Invoice::factory()->for($contact->client)->create();

    $this->postJson(route('api.v1.invoices.whatsapp-send', $invoice), ['contact_id' => $contact->id])
        ->assertOk()
        ->assertJsonPath('data.messages.0.status', 'sent');
});

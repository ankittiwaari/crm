<?php

namespace App\Http\Controllers;

use App\Enums\MessageChannel;
use App\Mail\InvoiceMail;
use App\Models\Contact;
use App\Models\Invoice;
use App\Services\Invoices\InvoiceMessages;
use App\Services\WhatsApp\WhatsAppException;
use App\Services\WhatsApp\WhatsAppMessenger;
use App\Services\WhatsApp\WhatsAppTemplates;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class InvoiceMessageController extends Controller
{
    /**
     * Log the WhatsApp message and return the click-to-chat link with it prefilled.
     */
    public function whatsapp(Request $request, Invoice $invoice, InvoiceMessages $messages): JsonResponse
    {
        abort_if($invoice->isDraft(), 409, 'Issue the invoice before sending it.');

        $contact = Contact::query()
            ->whereBelongsTo($invoice->client)
            ->findOrFail($request->integer('contact'));
        $number = $contact->whatsappTarget();

        abort_if($number === null, 422, 'This contact has no WhatsApp number.');

        $text = $messages->whatsappText($invoice, $contact);

        $invoice->messages()->create([
            'client_id' => $invoice->client_id,
            'contact_id' => $contact->id,
            'user_id' => $request->user()?->id,
            'channel' => MessageChannel::WhatsAppLink,
            'template' => 'invoice_issued',
            'recipient' => $number,
            'body' => $text,
            'sent_at' => now(),
        ]);

        return response()->json(['url' => 'https://wa.me/'.ltrim($number, '+').'?text='.rawurlencode($text)]);
    }

    /**
     * Email the invoice PDF to a contact, or to the client's own email address.
     */
    public function email(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_if($invoice->isDraft(), 409, 'Issue the invoice before sending it.');

        $contact = $request->integer('contact')
            ? Contact::query()->whereBelongsTo($invoice->client)->find($request->integer('contact'))
            : null;
        $email = $contact->email ?? $invoice->client->email;

        if (blank($email)) {
            return back()->withErrors(['email' => 'Add an email address for this client or contact first.']);
        }

        Mail::to($email, $contact?->name)->queue(new InvoiceMail($invoice, $contact?->name));

        $invoice->messages()->create([
            'client_id' => $invoice->client_id,
            'contact_id' => $contact?->id,
            'user_id' => $request->user()?->id,
            'channel' => MessageChannel::Email,
            'template' => 'invoice_issued',
            'recipient' => $email,
            'sent_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice :number emailed to :email (:amount due).', [
            'number' => $invoice->number,
            'email' => $email,
            'amount' => Money::format($invoice->balanceDue()),
        ])]);

        return to_route('invoices.show', $invoice);
    }

    /**
     * Send the invoice through the WhatsApp Cloud API (PDF attached), or a payment reminder when it is overdue.
     */
    public function whatsappApi(Request $request, Invoice $invoice, WhatsAppMessenger $messenger): RedirectResponse
    {
        $contact = Contact::query()->whereBelongsTo($invoice->client)->findOrFail($request->integer('contact'));
        $type = $invoice->isOverdue() ? WhatsAppTemplates::PAYMENT_REMINDER : WhatsAppTemplates::INVOICE_ISSUED;

        try {
            $messenger->invoice($invoice, $contact, $type, $request->user());
        } catch (WhatsAppException $exception) {
            return back()->withErrors(['whatsapp' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sending on WhatsApp to :name. Delivery updates appear below.', ['name' => $contact->name])]);

        return to_route('invoices.show', $invoice);
    }
}

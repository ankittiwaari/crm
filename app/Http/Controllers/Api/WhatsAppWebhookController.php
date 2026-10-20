<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\CompanyProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Receives WhatsApp delivery updates, client replies and template approvals from Meta.
 */
class WhatsAppWebhookController extends Controller
{
    /**
     * Meta's one-time check when you save the callback URL in the app dashboard.
     */
    public function verify(Request $request): Response
    {
        $token = CompanyProfile::current()->whatsapp_verify_token;

        abort_unless(
            $request->query('hub_mode') === 'subscribe'
                && filled($token)
                && hash_equals((string) $token, (string) $request->query('hub_verify_token')),
            403,
        );

        return response((string) $request->query('hub_challenge'), 200, ['Content-Type' => 'text/plain']);
    }

    public function receive(Request $request): Response
    {
        $secret = CompanyProfile::current()->whatsapp_app_secret;
        $signature = (string) $request->header('X-Hub-Signature-256');

        abort_if(
            blank($secret) || ! hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), (string) $secret), $signature),
            403,
        );

        ProcessWhatsAppWebhook::dispatch($request->json()->all());

        return response()->noContent(200);
    }
}

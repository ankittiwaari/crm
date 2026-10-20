<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Services\WhatsApp\WhatsAppException;
use App\Services\WhatsApp\WhatsAppGateway;
use App\Services\WhatsApp\WhatsAppTemplates;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Connects the CRM to its own WhatsApp Business Account through a Direct Developer Meta app.
 */
class WhatsAppSettingsController extends Controller
{
    public function edit(): Response
    {
        $profile = CompanyProfile::current();

        if (blank($profile->whatsapp_verify_token)) {
            $profile->forceFill(['whatsapp_verify_token' => Str::random(40)])->save();
        }

        return Inertia::render('settings/whatsapp', [
            'settings' => [
                'enabled' => $profile->whatsapp_enabled,
                'waba_id' => $profile->whatsapp_waba_id,
                'phone_number_id' => $profile->whatsapp_phone_number_id,
                'display_number' => $profile->whatsapp_display_number,
                'has_access_token' => filled($profile->whatsapp_access_token),
                'has_app_secret' => filled($profile->whatsapp_app_secret),
                'verify_token' => $profile->whatsapp_verify_token,
                'templates' => $profile->whatsapp_templates ?? [],
                'template_statuses' => $profile->whatsapp_template_statuses ?? [],
                'templates_synced_at' => $profile->whatsapp_templates_synced_at?->toIso8601String(),
                'ready' => $profile->whatsappReady(),
            ],
            'webhookUrl' => route('webhooks.whatsapp'),
            'definitions' => WhatsAppTemplates::definitions(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['boolean'],
            'waba_id' => ['nullable', 'digits_between:5,30'],
            'phone_number_id' => ['nullable', 'digits_between:5,30'],
            'display_number' => ['nullable', 'string', 'max:20'],
            'access_token' => ['nullable', 'string', 'max:1000'],
            'app_secret' => ['nullable', 'string', 'max:200'],
            'templates' => ['array'],
            'templates.*.name' => ['nullable', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'templates.*.language' => ['nullable', 'string', 'max:10'],
        ], [
            'templates.*.name.regex' => 'Template names use lowercase letters, numbers and underscores, as in WhatsApp Manager.',
            'waba_id.digits_between' => 'The WhatsApp Business Account ID is a long number from WhatsApp Manager.',
            'phone_number_id.digits_between' => 'The phone number ID is a long number shown under API Setup.',
        ]);

        $profile = CompanyProfile::current();
        $templates = [];

        foreach (array_keys(WhatsAppTemplates::definitions()) as $type) {
            $template = $validated['templates'][$type] ?? [];

            if (filled($template['name'] ?? null)) {
                $templates[$type] = ['name' => $template['name'], 'language' => $template['language'] ?: 'en'];
            }
        }

        $profile->forceFill([
            'whatsapp_enabled' => (bool) ($validated['enabled'] ?? false),
            'whatsapp_waba_id' => $validated['waba_id'] ?? null,
            'whatsapp_phone_number_id' => $validated['phone_number_id'] ?? null,
            'whatsapp_display_number' => PhoneNumber::normalize($validated['display_number'] ?? null),
            'whatsapp_templates' => $templates,
        ]);

        // Secrets are only replaced when a new value is typed in.
        if (filled($validated['access_token'] ?? null)) {
            $profile->whatsapp_access_token = $validated['access_token'];
        }

        if (filled($validated['app_secret'] ?? null)) {
            $profile->whatsapp_app_secret = $validated['app_secret'];
        }

        $profile->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('WhatsApp settings saved.')]);

        return to_route('whatsapp-settings.edit');
    }

    /**
     * Check the token and phone number ID by asking Meta for the number's details.
     */
    public function test(WhatsAppGateway $gateway): RedirectResponse
    {
        try {
            $number = $gateway->phoneNumber();
        } catch (WhatsAppException $exception) {
            return back()->withErrors(['connection' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Connected to :number (:name), quality :quality.', [
            'number' => $number['display_phone_number'] ?? '?',
            'name' => $number['verified_name'] ?? '?',
            'quality' => $number['quality_rating'] ?? 'unknown',
        ])]);

        return back();
    }

    /**
     * Fetch template approval statuses from Meta, and pick templates whose names match the defaults.
     */
    public function syncTemplates(WhatsAppGateway $gateway): RedirectResponse
    {
        try {
            $templates = $gateway->templates();
        } catch (WhatsAppException $exception) {
            return back()->withErrors(['templates' => $exception->getMessage()]);
        }

        $profile = CompanyProfile::current();
        $statuses = [];
        $configured = $profile->whatsapp_templates ?? [];

        foreach ($templates as $template) {
            $statuses[$template['name']] = $template['status'];

            if (array_key_exists($template['name'], WhatsAppTemplates::definitions()) && ! isset($configured[$template['name']])) {
                $configured[$template['name']] = ['name' => $template['name'], 'language' => $template['language']];
            }
        }

        $profile->forceFill([
            'whatsapp_template_statuses' => $statuses,
            'whatsapp_templates' => $configured,
            'whatsapp_templates_synced_at' => now(),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':count templates found.', ['count' => count($templates)])]);

        return back();
    }

    /**
     * Send Meta's built-in "hello_world" template to a number, to prove sending works.
     */
    public function testMessage(Request $request, WhatsAppGateway $gateway): RedirectResponse
    {
        $request->merge(['to' => PhoneNumber::normalize($request->string('to')->value())]);
        $validated = $request->validate(['to' => ['required', 'regex:/^\+[1-9]\d{7,14}$/']], [
            'to.regex' => 'Enter a valid WhatsApp number, e.g. 98450 12345.',
        ]);

        try {
            $gateway->sendTemplate($validated['to'], 'hello_world', 'en_US');
        } catch (WhatsAppException $exception) {
            return back()->withErrors(['to' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test message sent to :number.', ['number' => $validated['to']])]);

        return back();
    }
}

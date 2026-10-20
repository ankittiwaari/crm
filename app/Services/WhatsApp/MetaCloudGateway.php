<?php

namespace App\Services\WhatsApp;

use App\Models\CompanyProfile;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * WhatsApp Cloud API (Meta Graph API) for the CRM's own WhatsApp Business Account.
 */
class MetaCloudGateway implements WhatsAppGateway
{
    public function __construct(
        private ?string $accessToken,
        private ?string $phoneNumberId,
        private ?string $wabaId,
    ) {}

    public static function fromProfile(CompanyProfile $profile): self
    {
        return new self($profile->whatsapp_access_token, $profile->whatsapp_phone_number_id, $profile->whatsapp_waba_id);
    }

    public function sendTemplate(string $to, string $template, string $language, array $components = []): string
    {
        $response = $this->send(fn (PendingRequest $http) => $http->post("{$this->phoneNumberId()}/messages", [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => ltrim($to, '+'),
            'type' => 'template',
            'template' => array_filter([
                'name' => $template,
                'language' => ['code' => $language],
                'components' => $components ?: null,
            ]),
        ]));

        $id = $response->json('messages.0.id');

        if (! is_string($id)) {
            throw new WhatsAppException('WhatsApp did not return a message id.');
        }

        return $id;
    }

    public function phoneNumber(): array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->get($this->phoneNumberId(), [
            'fields' => 'display_phone_number,verified_name,quality_rating',
        ]));

        return [
            'display_phone_number' => $response->json('display_phone_number'),
            'verified_name' => $response->json('verified_name'),
            'quality_rating' => $response->json('quality_rating'),
        ];
    }

    public function templates(): array
    {
        if (blank($this->wabaId)) {
            throw new WhatsAppException('Add your WhatsApp Business Account ID first.');
        }

        $response = $this->send(fn (PendingRequest $http) => $http->get("{$this->wabaId}/message_templates", [
            'fields' => 'name,language,status,category',
            'limit' => 200,
        ]));

        return array_values(array_map(fn (array $template): array => [
            'name' => (string) ($template['name'] ?? ''),
            'language' => (string) ($template['language'] ?? ''),
            'status' => (string) ($template['status'] ?? ''),
            'category' => isset($template['category']) ? (string) $template['category'] : null,
        ], (array) $response->json('data', [])));
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     */
    private function send(callable $request): Response
    {
        if (blank($this->accessToken)) {
            throw new WhatsAppException('Add your WhatsApp access token first.');
        }

        $http = Http::baseUrl(rtrim((string) config('services.whatsapp.graph_url'), '/').'/'.config('services.whatsapp.graph_version'))
            ->withToken((string) $this->accessToken)
            ->acceptJson()
            ->timeout(15)
            ->connectTimeout(5);

        try {
            $response = $request($http);
        } catch (ConnectionException $exception) {
            throw new WhatsAppException('Could not reach WhatsApp: '.$exception->getMessage(), previous: $exception);
        }

        if ($response->failed()) {
            $message = $response->json('error.error_user_msg')
                ?? $response->json('error.message')
                ?? 'WhatsApp request failed with status '.$response->status().'.';

            throw new WhatsAppException(trim((string) $message));
        }

        return $response;
    }

    private function phoneNumberId(): string
    {
        if (blank($this->phoneNumberId)) {
            throw new WhatsAppException('Add your WhatsApp phone number ID first.');
        }

        return (string) $this->phoneNumberId;
    }
}

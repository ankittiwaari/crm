<?php

namespace App\Services\WhatsApp;

/**
 * Sends WhatsApp messages. The app uses Meta's Cloud API; a business solution provider could be
 * swapped in by binding another implementation.
 */
interface WhatsAppGateway
{
    /**
     * Send an approved template message and return the provider's message id.
     *
     * @param  string  $to  E.164 number, e.g. +919845012345.
     * @param  list<array<mixed>>  $components  Template components, passed to Meta as JSON.
     *
     * @throws WhatsAppException
     */
    public function sendTemplate(string $to, string $template, string $language, array $components = []): string;

    /**
     * Details of the sending number, to check the connection.
     *
     * @return array{display_phone_number: string|null, verified_name: string|null, quality_rating: string|null}
     *
     * @throws WhatsAppException
     */
    public function phoneNumber(): array;

    /**
     * Templates on the WhatsApp Business Account with their approval status.
     *
     * @return list<array{name: string, language: string, status: string, category: string|null}>
     *
     * @throws WhatsAppException
     */
    public function templates(): array;
}

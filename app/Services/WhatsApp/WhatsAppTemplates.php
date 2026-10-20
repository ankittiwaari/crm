<?php

namespace App\Services\WhatsApp;

/**
 * The message templates the CRM sends. Create each one in WhatsApp Manager (category: Utility)
 * with exactly these numbered variables, then enter its name in Settings › WhatsApp.
 */
final class WhatsAppTemplates
{
    public const string INVOICE_ISSUED = 'invoice_issued';

    public const string PAYMENT_REMINDER = 'payment_reminder';

    public const string RENEWAL_DUE = 'renewal_due';

    public const string PAYMENT_RECEIVED = 'payment_received';

    /**
     * @return array<string, array{label: string, header: string|null, body: string, variables: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            self::INVOICE_ISSUED => [
                'label' => 'Invoice issued',
                'header' => 'Document (the invoice PDF)',
                'body' => 'Dear {{1}}, please find attached invoice {{2}} for {{3}}, due by {{4}}. Thank you, {{5}}',
                'variables' => ['Contact name', 'Invoice number', 'Amount', 'Due date', 'Your business name'],
            ],
            self::PAYMENT_REMINDER => [
                'label' => 'Payment reminder',
                'header' => 'Document (the invoice PDF)',
                'body' => 'Dear {{1}}, this is a reminder that invoice {{2}} for {{3}} was due on {{4}}. Please ignore this message if you have already paid. Regards, {{5}}',
                'variables' => ['Contact name', 'Invoice number', 'Balance due', 'Due date', 'Your business name'],
            ],
            self::RENEWAL_DUE => [
                'label' => 'Renewal coming up',
                'header' => null,
                'body' => 'Dear {{1}}, your {{2}} subscription is due for renewal on {{3}}. The renewal amount is {{4}} plus GST. Regards, {{5}}',
                'variables' => ['Contact name', 'Product', 'Renewal date', 'Amount', 'Your business name'],
            ],
            self::PAYMENT_RECEIVED => [
                'label' => 'Payment received',
                'header' => null,
                'body' => 'Dear {{1}}, we have received {{2}} towards invoice {{3}}. Thank you for your payment. Regards, {{4}}',
                'variables' => ['Contact name', 'Amount received', 'Invoice number', 'Your business name'],
            ],
        ];
    }

    /**
     * Fill a template body with its values, for the message log.
     *
     * @param  list<string>  $values
     */
    public static function preview(string $type, array $values): string
    {
        $body = self::definitions()[$type]['body'];

        foreach ($values as $index => $value) {
            $body = str_replace('{{'.($index + 1).'}}', $value, $body);
        }

        return $body;
    }
}

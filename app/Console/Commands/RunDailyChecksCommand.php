<?php

namespace App\Console\Commands;

use App\Actions\Renewals\RunDailyChecks;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('crm:daily-checks')]
#[Description('Create renewal, promo and payment reminders, prepare auto-renew drafts and expire lapsed subscriptions')]
class RunDailyChecksCommand extends Command
{
    public function handle(RunDailyChecks $checks): int
    {
        $result = $checks->handle();

        $this->components->twoColumnDetail('Renewal reminders', (string) $result['renewal_reminders']);
        $this->components->twoColumnDetail('Draft renewal invoices', (string) $result['draft_invoices']);
        $this->components->twoColumnDetail('Promo reminders', (string) $result['promo_reminders']);
        $this->components->twoColumnDetail('Payment reminders', (string) $result['payment_reminders']);
        $this->components->twoColumnDetail('Subscriptions expired', (string) $result['expired']);

        return self::SUCCESS;
    }
}

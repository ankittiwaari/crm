<?php

use App\Enums\BillingCycle;
use Carbon\CarbonImmutable;

test('a period ends the day before the same date in the next cycle', function (BillingCycle $cycle, string $start, string $end) {
    expect($cycle->periodEnd(CarbonImmutable::parse($start))?->toDateString())->toBe($end);
})->with([
    'yearly' => [BillingCycle::Yearly, '2026-10-27', '2027-10-26'],
    'quarterly' => [BillingCycle::Quarterly, '2026-04-01', '2026-06-30'],
    'monthly from the 31st clamps to February' => [BillingCycle::Monthly, '2027-01-31', '2027-02-27'],
    'monthly in a leap year' => [BillingCycle::Monthly, '2028-01-31', '2028-02-28'],
]);

test('one-time charges have no period', function () {
    expect(BillingCycle::OneTime->periodEnd(CarbonImmutable::parse('2026-10-20')))->toBeNull()
        ->and(BillingCycle::OneTime->periodContaining(CarbonImmutable::parse('2026-10-20'), CarbonImmutable::parse('2026-10-20')))->toBeNull();
});

test('the current period is counted in whole cycles from the original start date', function (string $start, string $today, string $periodStart, string $periodEnd) {
    [$from, $to] = BillingCycle::Yearly->periodContaining(CarbonImmutable::parse($start), CarbonImmutable::parse($today));

    expect($from->toDateString())->toBe($periodStart)
        ->and($to->toDateString())->toBe($periodEnd);
})->with([
    'third year' => ['2023-10-27', '2026-10-20', '2025-10-27', '2026-10-26'],
    'on the renewal date a new period starts' => ['2023-10-27', '2026-10-27', '2026-10-27', '2027-10-26'],
    'a start date in the future' => ['2026-11-01', '2026-10-20', '2026-11-01', '2027-10-31'],
]);

test('monthly periods from the 31st do not drift', function () {
    [$from, $to] = BillingCycle::Monthly->periodContaining(CarbonImmutable::parse('2026-01-31'), CarbonImmutable::parse('2026-05-15'));

    expect($from->toDateString())->toBe('2026-04-30')
        ->and($to->toDateString())->toBe('2026-05-30');
});

test('the period start is worked out from its end date', function () {
    expect(BillingCycle::Yearly->periodStartFor(CarbonImmutable::parse('2026-10-26'))?->toDateString())->toBe('2025-10-27');
});

test('cycles convert to billings per year', function () {
    expect(BillingCycle::Monthly->perYear())->toBe(12)
        ->and(BillingCycle::HalfYearly->perYear())->toBe(2)
        ->and(BillingCycle::OneTime->perYear())->toBe(0);
});

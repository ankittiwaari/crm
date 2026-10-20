<?php

use App\Support\FinancialYear;
use Carbon\CarbonImmutable;

test('the financial year runs from April to March', function (string $date, string $label) {
    expect(FinancialYear::for(CarbonImmutable::parse($date))->label())->toBe($label);
})->with([
    'October' => ['2026-10-20', '2026-27'],
    '1 April starts a new year' => ['2027-04-01', '2027-28'],
    '31 March ends the year' => ['2027-03-31', '2026-27'],
    'turn of the century' => ['2100-01-15', '2099-00'],
]);

test('a financial year knows its first and last day', function () {
    $year = FinancialYear::for(CarbonImmutable::parse('2026-10-20'));

    expect($year->start()->toDateString())->toBe('2026-04-01')
        ->and($year->end()->toDateString())->toBe('2027-03-31');
});

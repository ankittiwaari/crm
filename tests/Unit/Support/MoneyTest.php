<?php

use App\Support\Money;

test('rupee amounts convert to paise', function (string|int $rupees, int $paise) {
    expect(Money::toPaise($rupees))->toBe($paise);
})->with([
    'whole rupees' => ['45000', 4500000],
    'Indian grouping' => ['1,25,000', 12500000],
    'one decimal' => ['99.5', 9950],
    'two decimals' => ['0.29', 29],
    'rupee sign and spaces' => ['₹ 1,000.05', 100005],
    'integer' => [500, 50000],
]);

test('invalid rupee amounts are rejected', function (string $rupees) {
    Money::toPaise($rupees);
})->with(['-5', '1.234', 'abc', ''])->throws(InvalidArgumentException::class);

test('paise convert back to a two-decimal rupee string', function () {
    expect(Money::toRupees(12500005))->toBe('125000.05')
        ->and(Money::toRupees(0))->toBe('0.00');
});

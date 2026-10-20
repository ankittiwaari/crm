<?php

use App\Support\PhoneNumber;

test('Indian numbers are normalised to E.164', function (string $input, string $expected) {
    expect(PhoneNumber::normalize($input))->toBe($expected);
})->with([
    'ten digits' => ['98450 12345', '+919845012345'],
    'leading zero' => ['098450-12345', '+919845012345'],
    'country code without plus' => ['919845012345', '+919845012345'],
    'already E.164' => ['+91 98450 12345', '+919845012345'],
    'foreign number' => ['+44 20 7946 0958', '+442079460958'],
]);

test('empty input normalises to null', function () {
    expect(PhoneNumber::normalize('  '))->toBeNull()
        ->and(PhoneNumber::normalize(null))->toBeNull();
});

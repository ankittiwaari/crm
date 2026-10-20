<?php

use App\Enums\IndianState;
use App\Support\Gstin;

test('a GSTIN with a correct check character is valid', function (string $gstin) {
    expect(Gstin::isValid($gstin))->toBeTrue();
})->with([
    'Maharashtra' => '27AAPFU0939F1ZV',
    'Karnataka' => '29AAGCB7383J1Z4',
]);

test('a GSTIN is invalid when', function (string $gstin) {
    expect(Gstin::isValid($gstin))->toBeFalse();
})->with([
    'the check character is wrong' => '27AAPFU0939F1ZW',
    'a character is mistyped' => '27AAPFU0939F1ZX',
    'the state code does not exist' => '99AAPFU0939F1ZV',
    'the 14th character is not Z' => '27AAPFU0939F1YV',
    'it is too short' => '27AAPFU0939F1Z',
    'it is lowercase' => '27aapfu0939f1zv',
]);

test('tryFrom ignores spaces and letter case', function () {
    expect(Gstin::tryFrom(' 27aapfu 0939f1zv ')?->value)->toBe('27AAPFU0939F1ZV');
});

test('tryFrom returns null for an invalid GSTIN', function () {
    expect(Gstin::tryFrom('27AAPFU0939F1ZW'))->toBeNull()
        ->and(Gstin::tryFrom(null))->toBeNull();
});

test('the state and PAN are read from the GSTIN', function () {
    $gstin = Gstin::tryFrom('29AAGCB7383J1Z4');

    expect($gstin->state())->toBe(IndianState::Karnataka)
        ->and($gstin->pan())->toBe('AAGCB7383J');
});

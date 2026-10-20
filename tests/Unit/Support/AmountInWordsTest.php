<?php

use App\Support\AmountInWords;

test('amounts are spelled out with Indian numbering', function (int $paise, string $words) {
    expect(AmountInWords::rupees($paise))->toBe($words);
})->with([
    'thousands' => [6_372_000, 'Indian Rupees Sixty-Three Thousand Seven Hundred Twenty Only'],
    'lakhs' => [12_500_000, 'Indian Rupees One Lakh Twenty-Five Thousand Only'],
    'crores' => [1_234_567_800, 'Indian Rupees One Crore Twenty-Three Lakh Forty-Five Thousand Six Hundred Seventy-Eight Only'],
    'paise' => [10_150, 'Indian Rupees One Hundred One and Fifty Paise Only'],
    'teens' => [1_900, 'Indian Rupees Nineteen Only'],
    'zero' => [0, 'Indian Rupees Zero Only'],
]);

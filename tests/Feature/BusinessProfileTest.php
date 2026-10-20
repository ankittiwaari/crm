<?php

use App\Enums\MsmeCategory;
use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A complete, valid form submission.
 *
 * @return array<string, mixed>
 */
function businessProfilePayload(array $overrides = []): array
{
    return [
        'legal_name' => 'Acme Software Solutions',
        'trade_name' => 'Acme',
        'address_line' => '12 MG Road',
        'city' => 'Bengaluru',
        'state_code' => '29',
        'pincode' => '560001',
        'email' => 'billing@acme.test',
        'phone' => '+919845012345',
        'gstin' => '29AAGCB7383J1Z4',
        'udyam_number' => 'UDYAM-KA-03-0012345',
        'msme_category' => 'micro',
        'show_msme_clause' => true,
        'bank_account_number' => '1234 5678 9012',
        'bank_ifsc' => 'hdfc0001234',
        'upi_id' => 'acme@hdfcbank',
        'invoice_prefix' => 'INV',
        'payment_terms_days' => 30,
        ...$overrides,
    ];
}

test('guests are redirected to the login page', function () {
    $this->get(route('business-profile.edit'))->assertRedirect(route('login'));
    $this->patch(route('business-profile.update'), businessProfilePayload())->assertRedirect(route('login'));
});

test('the business profile page shows the saved details', function () {
    $user = User::factory()->create();
    CompanyProfile::factory()->create(['gstin' => '29AAGCB7383J1Z4']);

    $this->actingAs($user)
        ->get(route('business-profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('business-profile/edit')
            ->where('profile.gstin', '29AAGCB7383J1Z4')
            ->where('msmeMaxPaymentDays', 45)
            ->has('states', 37));
});

test('saving the profile stores normalised details and derives state and PAN from the GSTIN', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('business-profile.update'), businessProfilePayload([
            'gstin' => '27aapfu0939f1zv',
            'state_code' => '29',
            'pan' => 'ZZZZZ9999Z',
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('business-profile.edit'));

    $profile = CompanyProfile::current();

    expect($profile->gstin)->toBe('27AAPFU0939F1ZV')
        ->and($profile->state_code)->toBe('27')
        ->and($profile->pan)->toBe('AAPFU0939F')
        ->and($profile->bank_account_number)->toBe('123456789012')
        ->and($profile->bank_ifsc)->toBe('HDFC0001234')
        ->and($profile->msme_category)->toBe(MsmeCategory::Micro);
});

test('changing the GSTIN records who changed it and the old and new values', function () {
    $user = User::factory()->create();
    $profile = CompanyProfile::factory()->create(['gstin' => '29AAGCB7383J1Z4']);

    $this->actingAs($user)
        ->patch(route('business-profile.update'), businessProfilePayload(['gstin' => '27AAPFU0939F1ZV']))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('company_profile_changes', [
        'company_profile_id' => $profile->id,
        'user_id' => $user->id,
        'field' => 'gstin',
        'old_value' => '29AAGCB7383J1Z4',
        'new_value' => '27AAPFU0939F1ZV',
    ]);
});

test('saving without changes records no audit entries', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('business-profile.update'), businessProfilePayload());
    $countAfterFirstSave = CompanyProfile::current()->changes()->count();

    $this->actingAs($user)->patch(route('business-profile.update'), businessProfilePayload());

    expect(CompanyProfile::current()->changes()->count())->toBe($countAfterFirstSave);
});

test('an invalid GSTIN is rejected with a helpful message', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('business-profile.update'), businessProfilePayload(['gstin' => '27AAPFU0939F1ZW']))
        ->assertSessionHasErrors(['gstin' => 'The GSTIN is not a valid GSTIN. Check the format and the last (check) character.']);
});

test('an invalid Udyam number is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('business-profile.update'), businessProfilePayload(['udyam_number' => 'UDYAM-KA-3-12345']))
        ->assertSessionHasErrors(['udyam_number' => 'The Udyam number must look like UDYAM-KA-03-0012345.']);
});

test('the MSME category is required with a Udyam number', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('business-profile.update'), businessProfilePayload(['msme_category' => null]))
        ->assertSessionHasErrors('msme_category');
});

test('payment terms cannot exceed 45 days while the MSME clause is printed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('business-profile.update'), businessProfilePayload(['payment_terms_days' => 60]))
        ->assertSessionHasErrors(['payment_terms_days' => 'Payment terms can be at most 45 days while the MSME clause is printed on invoices.']);
});

test('payment terms can exceed 45 days when the MSME clause is off', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('business-profile.update'), businessProfilePayload(['payment_terms_days' => 60, 'show_msme_clause' => false]))
        ->assertSessionHasNoErrors();

    expect(CompanyProfile::current()->payment_terms_days)->toBe(60);
});

test('bank and UPI details must be well formed', function (string $field, string $value, string $message) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('business-profile.update'), businessProfilePayload([$field => $value]))
        ->assertSessionHasErrors([$field => $message]);
})->with([
    'IFSC' => ['bank_ifsc', 'HDFC1234', 'The IFSC must look like HDFC0001234.'],
    'UPI ID' => ['upi_id', 'acme-at-bank', 'The UPI ID must look like name@bank.'],
    'account number' => ['bank_account_number', '12AB', 'The account number must be 9 to 18 digits.'],
]);

test('a new logo replaces the old one', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    Storage::disk('public')->put('company/old.png', 'old');
    CompanyProfile::factory()->create(['logo_path' => 'company/old.png']);

    $this->actingAs($user)
        ->patch(route('business-profile.update'), businessProfilePayload([
            'logo' => UploadedFile::fake()->image('logo.png', 256, 256),
        ]))
        ->assertSessionHasNoErrors();

    $path = CompanyProfile::current()->logo_path;

    Storage::disk('public')->assertExists($path);
    Storage::disk('public')->assertMissing('company/old.png');
});

test('an SVG logo is rejected', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('business-profile.update'), businessProfilePayload([
            'logo' => UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml'),
        ]))
        ->assertSessionHasErrors('logo');
});

test('the Udyam certificate is stored privately and can be downloaded', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('business-profile.update'), businessProfilePayload([
            'udyam_certificate' => UploadedFile::fake()->create('udyam.pdf', 120, 'application/pdf'),
        ]))
        ->assertSessionHasNoErrors();

    Storage::disk('local')->assertExists(CompanyProfile::current()->udyam_certificate_path);

    $this->actingAs($user)
        ->get(route('business-profile.certificate'))
        ->assertOk()
        ->assertDownload('udyam-certificate.pdf');
});

test('the certificate download returns 404 when none is stored', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('business-profile.certificate'))
        ->assertNotFound();
});

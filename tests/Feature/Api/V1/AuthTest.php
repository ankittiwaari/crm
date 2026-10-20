<?php

use App\Models\User;
use Laravel\Fortify\Fortify;
use Laravel\Sanctum\PersonalAccessToken;
use PragmaRX\Google2FA\Google2FA;

/**
 * A user with confirmed two-factor authentication and the given recovery codes.
 *
 * @param  list<string>  $recoveryCodes
 * @return array{User, string}
 */
function twoFactorUser(array $recoveryCodes = ['recovery-one', 'recovery-two']): array
{
    $secret = (new Google2FA)->generateSecretKey();
    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
        'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode($recoveryCodes)),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return [$user, $secret];
}

test('signing in returns a bearer token for the device', function () {
    $user = User::factory()->create();

    $response = $this->postJson(route('api.v1.auth.token'), [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Pixel 8',
    ])->assertCreated();

    expect($response->json('token_type'))->toBe('Bearer')
        ->and($response->json('user.email'))->toBe($user->email)
        ->and($response->json('expires_at'))->not->toBeNull()
        ->and($user->tokens()->sole()->name)->toBe('Pixel 8');

    $this->withToken($response->json('token'))->getJson(route('api.v1.me'))->assertOk()->assertJsonPath('data.id', $user->id);
});

test('a wrong password is rejected', function () {
    $user = User::factory()->create();

    $this->postJson(route('api.v1.auth.token'), ['email' => $user->email, 'password' => 'nope', 'device_name' => 'Pixel'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => __('auth.failed')]);
});

test('accounts with an unverified email cannot sign in on the app', function () {
    $user = User::factory()->unverified()->create();

    $this->postJson(route('api.v1.auth.token'), ['email' => $user->email, 'password' => 'password', 'device_name' => 'Pixel'])
        ->assertJsonValidationErrors(['email' => 'Verify your email address before signing in on the app.']);
});

test('two-factor accounts must send a code from their authenticator app', function () {
    [$user, $secret] = twoFactorUser();

    $challenge = $this->postJson(route('api.v1.auth.token'), ['email' => $user->email, 'password' => 'password', 'device_name' => 'Pixel'])
        ->assertOk()
        ->assertJsonPath('two_factor', true)
        ->json('challenge');

    expect($user->tokens()->count())->toBe(0);

    $this->postJson(route('api.v1.auth.two-factor'), ['challenge' => $challenge, 'code' => '000000', 'device_name' => 'Pixel'])
        ->assertJsonValidationErrors('code');

    $this->postJson(route('api.v1.auth.two-factor'), [
        'challenge' => $challenge,
        'code' => (new Google2FA)->getCurrentOtp($secret),
        'device_name' => 'Pixel',
    ])->assertCreated()->assertJsonStructure(['token']);
});

test('a recovery code works once', function () {
    [$user] = twoFactorUser();
    $challenge = fn () => $this->postJson(route('api.v1.auth.token'), ['email' => $user->email, 'password' => 'password', 'device_name' => 'Pixel'])->json('challenge');

    $this->postJson(route('api.v1.auth.two-factor'), ['challenge' => $challenge(), 'recovery_code' => 'recovery-one', 'device_name' => 'Pixel'])
        ->assertCreated();

    $this->postJson(route('api.v1.auth.two-factor'), ['challenge' => $challenge(), 'recovery_code' => 'recovery-one', 'device_name' => 'Pixel'])
        ->assertJsonValidationErrors('recovery_code');
});

test('an expired or tampered challenge is rejected', function () {
    [$user, $secret] = twoFactorUser();
    $challenge = $this->postJson(route('api.v1.auth.token'), ['email' => $user->email, 'password' => 'password', 'device_name' => 'Pixel'])->json('challenge');

    $this->travel(6)->minutes();

    $this->postJson(route('api.v1.auth.two-factor'), ['challenge' => $challenge, 'code' => (new Google2FA)->getCurrentOtp($secret), 'device_name' => 'Pixel'])
        ->assertJsonValidationErrors(['challenge' => 'This sign-in attempt has expired. Sign in again.']);

    $this->postJson(route('api.v1.auth.two-factor'), ['challenge' => 'forged', 'code' => '123456', 'device_name' => 'Pixel'])
        ->assertJsonValidationErrors('challenge');
});

test('signing out revokes only this device\'s token', function () {
    $user = User::factory()->create();
    $phone = $user->createToken('Phone')->plainTextToken;
    $user->createToken('Tablet');

    $this->withToken($phone)->deleteJson(route('api.v1.auth.logout'))->assertNoContent();

    expect(PersonalAccessToken::query()->pluck('name')->all())->toBe(['Tablet']);
});

test('requests without a token are rejected with 401', function () {
    $this->getJson(route('api.v1.dashboard'))->assertUnauthorized();
});

test('repeated failed sign-ins are rate limited', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->postJson(route('api.v1.auth.token'), ['email' => $user->email, 'password' => 'nope', 'device_name' => 'Pixel']);
    }

    $this->postJson(route('api.v1.auth.token'), ['email' => $user->email, 'password' => 'password', 'device_name' => 'Pixel'])
        ->assertTooManyRequests();
});

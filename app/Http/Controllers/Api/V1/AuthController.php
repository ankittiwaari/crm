<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use JsonException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

/**
 * Token login for the mobile app. Accounts with two-factor authentication get a short-lived
 * challenge first and must send a code from their authenticator app (or a recovery code).
 */
class AuthController extends Controller
{
    /**
     * How long a two-factor challenge stays valid.
     */
    private const int CHALLENGE_MINUTES = 5;

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['email' => 'Verify your email address before signing in on the app.']);
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return response()->json([
                'two_factor' => true,
                'challenge' => Crypt::encryptString((string) json_encode([
                    'user' => $user->id,
                    'expires' => now()->addMinutes(self::CHALLENGE_MINUTES)->timestamp,
                ])),
            ]);
        }

        return $this->issueToken($user, $validated['device_name']);
    }

    /**
     * Finish signing in with an authenticator code or a recovery code.
     */
    public function twoFactor(Request $request, TwoFactorAuthenticationProvider $provider): JsonResponse
    {
        $validated = $request->validate([
            'challenge' => ['required', 'string'],
            'code' => ['nullable', 'string', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = $this->challengedUser($validated['challenge']);

        if (filled($validated['code'] ?? null)) {
            $secret = Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret);

            if (! $provider->verify($secret, $validated['code'])) {
                throw ValidationException::withMessages(['code' => __('The provided two factor authentication code was invalid.')]);
            }
        } else {
            $recoveryCode = collect($user->recoveryCodes())->first(fn (string $code): bool => hash_equals($code, (string) $validated['recovery_code']));

            if ($recoveryCode === null) {
                throw ValidationException::withMessages(['recovery_code' => __('The provided two factor recovery code was invalid.')]);
            }

            $user->replaceRecoveryCode($recoveryCode);
        }

        return $this->issueToken($user, $validated['device_name']);
    }

    /**
     * Sign out on this device.
     */
    public function destroy(Request $request): Response
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }

    private function issueToken(User $user, string $deviceName): JsonResponse
    {
        $token = $user->createToken($deviceName);
        $minutes = config('sanctum.expiration');

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $minutes ? now()->addMinutes((int) $minutes)->toIso8601String() : null,
            'user' => UserResource::make($user)->resolve(),
        ], 201);
    }

    private function challengedUser(string $challenge): User
    {
        try {
            $payload = json_decode(Crypt::decryptString($challenge), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            $payload = null;
        }

        $userId = is_array($payload) && ($payload['expires'] ?? 0) >= now()->timestamp ? ($payload['user'] ?? null) : null;
        $user = is_int($userId) ? User::query()->find($userId) : null;

        if ($user === null || ! $user->hasEnabledTwoFactorAuthentication()) {
            throw ValidationException::withMessages(['challenge' => 'This sign-in attempt has expired. Sign in again.']);
        }

        return $user;
    }
}

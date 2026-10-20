<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\WhatsAppSettingsController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

    Route::get('settings/whatsapp', [WhatsAppSettingsController::class, 'edit'])->name('whatsapp-settings.edit');
    Route::patch('settings/whatsapp', [WhatsAppSettingsController::class, 'update'])->name('whatsapp-settings.update');
    Route::post('settings/whatsapp/test', [WhatsAppSettingsController::class, 'test'])->name('whatsapp-settings.test');
    Route::post('settings/whatsapp/templates/sync', [WhatsAppSettingsController::class, 'syncTemplates'])->name('whatsapp-settings.sync-templates');
    Route::post('settings/whatsapp/test-message', [WhatsAppSettingsController::class, 'testMessage'])
        ->middleware('throttle:10,1')
        ->name('whatsapp-settings.test-message');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');

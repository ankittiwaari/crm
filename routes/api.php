<?php

use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ReminderController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Mobile app API, version 1. Authenticate with a Bearer token from POST /api/v1/auth/token.
| Amounts in responses are integer paise; amounts in requests are rupees ("48000.50").
*/

Route::get('webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('webhooks.whatsapp.verify');
Route::post('webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])
    ->middleware('throttle:600,1')
    ->name('webhooks.whatsapp');

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::middleware('throttle:api-login')->group(function () {
        Route::post('auth/token', [AuthController::class, 'store'])->name('auth.token');
        Route::post('auth/two-factor', [AuthController::class, 'twoFactor'])->name('auth.two-factor');
    });

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::delete('auth/token', [AuthController::class, 'destroy'])->name('auth.logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');

        Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
        Route::delete('devices', [DeviceController::class, 'destroy'])->name('devices.destroy');

        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('reports', ReportController::class)->name('reports');
        Route::get('products', [ProductController::class, 'index'])->name('products.index');

        Route::apiResource('clients', ClientController::class);
        Route::apiResource('clients.contacts', ContactController::class)->only(['store', 'update', 'destroy'])->shallow();
        Route::apiResource('clients.activities', ActivityController::class)->only(['store', 'destroy'])->shallow();
        Route::apiResource('clients.subscriptions', SubscriptionController::class)->only(['store', 'update', 'destroy'])->shallow();
        Route::post('subscriptions/{subscription}/rate-changes', [SubscriptionController::class, 'changeRate'])->name('subscriptions.rate-changes');
        Route::post('subscriptions/{subscription}/renewals', [SubscriptionController::class, 'renew'])->name('subscriptions.renewals');
        Route::delete('renewals/{period}', [SubscriptionController::class, 'undoRenewal'])->name('renewals.destroy');
        Route::get('clients/{client}/invoice-suggestions', [InvoiceController::class, 'suggestions'])->name('clients.invoice-suggestions');

        Route::apiResource('invoices', InvoiceController::class);
        Route::post('invoices/{invoice}/finalise', [InvoiceController::class, 'finalise'])->name('invoices.finalise');
        Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::post('invoices/{invoice}/whatsapp', [InvoiceController::class, 'whatsapp'])->name('invoices.whatsapp');
        Route::post('invoices/{invoice}/whatsapp-send', [InvoiceController::class, 'sendWhatsApp'])->name('invoices.whatsapp-send');
        Route::post('invoices/{invoice}/email', [InvoiceController::class, 'email'])->name('invoices.email');
        Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment'])->name('invoices.payments.store');
        Route::delete('payments/{payment}', [InvoiceController::class, 'deletePayment'])->name('payments.destroy');

        Route::apiResource('reminders', ReminderController::class)->except('show');
        Route::apiResource('expenses', ExpenseController::class)->except('show');
        Route::get('expenses/{expense}/receipt', [ExpenseController::class, 'receipt'])->name('expenses.receipt');
    });
});

<?php

use App\Http\Controllers\BusinessProfileController;
use App\Http\Controllers\ClientActivityController;
use App\Http\Controllers\ClientContactController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceCancelController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceFinaliseController;
use App\Http\Controllers\InvoiceMessageController;
use App\Http\Controllers\InvoicePaymentController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SubscriptionRateChangeController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('business-profile', [BusinessProfileController::class, 'edit'])->name('business-profile.edit');
    Route::patch('business-profile', [BusinessProfileController::class, 'update'])->name('business-profile.update');
    Route::resource('clients', ClientController::class)->except(['create', 'edit']);
    Route::resource('clients.contacts', ClientContactController::class)->only(['store', 'update', 'destroy'])->shallow();
    Route::resource('clients.activities', ClientActivityController::class)->only(['store', 'destroy'])->shallow();
    Route::resource('clients.subscriptions', SubscriptionController::class)->only(['store', 'update', 'destroy'])->shallow();
    Route::post('subscriptions/{subscription}/rate-changes', [SubscriptionRateChangeController::class, 'store'])
        ->name('subscriptions.rate-changes.store');

    Route::resource('products', ProductController::class)->except(['create', 'show', 'edit']);

    Route::resource('invoices', InvoiceController::class);
    Route::post('invoices/{invoice}/finalise', InvoiceFinaliseController::class)->name('invoices.finalise');
    Route::post('invoices/{invoice}/cancel', InvoiceCancelController::class)->name('invoices.cancel');
    Route::get('invoices/{invoice}/pdf', [InvoicePdfController::class, 'show'])->name('invoices.pdf');
    Route::post('invoices/{invoice}/whatsapp', [InvoiceMessageController::class, 'whatsapp'])->name('invoices.whatsapp');
    Route::post('invoices/{invoice}/email', [InvoiceMessageController::class, 'email'])->name('invoices.email');
    Route::resource('invoices.payments', InvoicePaymentController::class)->only(['store', 'destroy'])->shallow();

    Route::get('business-profile/udyam-certificate', [BusinessProfileController::class, 'certificate'])->name('business-profile.certificate');
});

Route::get('i/{invoice}', [InvoicePdfController::class, 'public'])
    ->middleware(['signed', 'throttle:30,1'])
    ->name('invoices.public-pdf');

require __DIR__.'/settings.php';

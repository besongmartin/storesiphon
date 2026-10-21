<?php

use App\Http\Controllers\BtcPayWebhook;
use App\Http\Controllers\DownloadExport;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureAdmin;
use App\Livewire\Admin;
use App\Livewire\Billing;
use App\Livewire\Dashboard;
use App\Livewire\Extractions;
use App\Livewire\TryIt;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::get('/try', TryIt::class)->name('try');

// Payment provider notifications (HMAC-verified; CSRF-exempt in bootstrap/app.php).
Route::post('/webhooks/btcpay', BtcPayWebhook::class)->middleware('throttle:webhooks')->name('webhooks.btcpay');

Route::get('/legal/{document}', fn (string $document) => view('legal.show', ['document' => $document]))
    ->whereIn('document', ['terms', 'privacy', 'refunds', 'store-owners'])
    ->name('legal');

Route::middleware(['auth', EnsureAccountIsActive::class, 'verified'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    Route::get('/extractions', Extractions\Index::class)->name('extractions.index');
    Route::get('/extractions/new', Extractions\Create::class)->name('extractions.create');
    Route::get('/extractions/{job}', Extractions\Show::class)->name('extractions.show');
    Route::get('/exports/{export}/download', DownloadExport::class)->name('exports.download');

    Route::get('/billing', Billing\Index::class)->name('billing.index');
    Route::get('/billing/invoices/{invoice}', Billing\InvoiceShow::class)->name('billing.invoices.show');

    Route::middleware(EnsureAdmin::class)->prefix('admin')->name('admin.')->group(function () {
        Route::get('/payments', Admin\Payments::class)->name('payments');
    });

    Route::view('/settings/profile', 'settings.profile')->name('settings.profile');
    Route::view('/settings/security', 'settings.security')->name('settings.security');
});

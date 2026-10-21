<?php

use App\Http\Controllers\DownloadExport;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Livewire\Dashboard;
use App\Livewire\Extractions;
use App\Livewire\TryIt;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::get('/try', TryIt::class)->name('try');

Route::get('/legal/{document}', fn (string $document) => view('legal.show', ['document' => $document]))
    ->whereIn('document', ['terms', 'privacy', 'refunds', 'store-owners'])
    ->name('legal');

Route::middleware(['auth', EnsureAccountIsActive::class, 'verified'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    Route::get('/extractions', Extractions\Index::class)->name('extractions.index');
    Route::get('/extractions/new', Extractions\Create::class)->name('extractions.create');
    Route::get('/extractions/{job}', Extractions\Show::class)->name('extractions.show');
    Route::get('/exports/{export}/download', DownloadExport::class)->name('exports.download');

    Route::view('/settings/profile', 'settings.profile')->name('settings.profile');
    Route::view('/settings/security', 'settings.security')->name('settings.security');
});

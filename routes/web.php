<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('/legal/{document}', fn (string $document) => view('legal.show', ['document' => $document]))
    ->whereIn('document', ['terms', 'privacy', 'refunds', 'store-owners'])
    ->name('legal');

Route::middleware(['auth', EnsureAccountIsActive::class, 'verified'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    Route::view('/settings/profile', 'settings.profile')->name('settings.profile');
    Route::view('/settings/security', 'settings.security')->name('settings.security');
});

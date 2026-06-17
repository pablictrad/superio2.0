<?php
use Livewire\Volt\Volt;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');



Volt::route('settings/profile', 'pages.settings.profile')
    ->name('profile.edit');
});

Route::middleware(['auth', 'verified'])->group(function () {
   Volt::route('settings/appearance', 'pages.settings.appearance');

    Volt::route('settings/security', 'pages.settings.security')

        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('security.edit');
});

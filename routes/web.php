<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'auth.login')->name('login');
    Route::livewire('/forgot-password', 'auth.forgot-password')->name('password.request');
    Route::livewire('/reset-password/{token}', 'auth.reset-password')->name('password.reset');
});

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::middleware(['auth', 'tenant'])->prefix('app')->name('app.')->group(function () {
    Route::livewire('/dashboard', 'pages::tenant.dashboard')->name('dashboard');
});

Route::middleware(['auth', 'platform.admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('/dashboard', 'pages::admin.dashboard')->name('dashboard');
});

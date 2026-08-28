<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::public.landing')->name('home');
Route::livewire('/pricing', 'pages::public.pricing')->name('pricing');

Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'auth.login')->name('login');
    Route::livewire('/register', 'auth.register')->name('register');
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

    Route::middleware('role:owner')->group(function () {
        Route::livewire('/settings', 'pages::tenant.settings')->name('settings');

        Route::livewire('/products', 'pages::tenant.products.index')->name('products.index');
        Route::livewire('/products/{product}', 'pages::tenant.products.show')->name('products.show');
        Route::livewire('/inventory', 'pages::tenant.inventory.index')->name('inventory.index');
    });
});

Route::middleware(['auth', 'platform.admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('/dashboard', 'pages::admin.dashboard')->name('dashboard');
});

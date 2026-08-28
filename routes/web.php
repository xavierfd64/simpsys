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
    Route::middleware('role:owner,cashier')->group(function () {
        Route::livewire('/pos', 'pages::tenant.pos.index')->name('pos');
        Route::livewire('/sales', 'pages::tenant.sales.index')->name('sales.index');
        Route::livewire('/sales/{sale}', 'pages::tenant.sales.show')->name('sales.show');
    });

    Route::middleware('role:owner,kitchen_staff')->group(function () {
        Route::livewire('/kitchen', 'pages::tenant.kitchen.index')->name('kitchen');
    });

    Route::middleware('role:owner')->group(function () {
        Route::livewire('/dashboard', 'pages::tenant.dashboard')->name('dashboard');
        Route::livewire('/reports', 'pages::tenant.reports.index')->name('reports');
        Route::livewire('/settings', 'pages::tenant.settings')->name('settings');

        Route::livewire('/products', 'pages::tenant.products.index')->name('products.index');
        Route::livewire('/products/{product}', 'pages::tenant.products.show')->name('products.show');
        Route::livewire('/inventory', 'pages::tenant.inventory.index')->name('inventory.index');
        Route::livewire('/supplies', 'pages::tenant.supplies.index')->name('supplies.index');
        Route::livewire('/expenses', 'pages::tenant.expenses.index')->name('expenses.index');
        Route::livewire('/users', 'pages::tenant.users.index')->name('users.index');
    });
});

Route::middleware(['auth', 'platform.admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('/dashboard', 'pages::admin.dashboard')->name('dashboard');
});

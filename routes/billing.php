<?php

use Illuminate\Support\Facades\Route;
use Sanjayacloud\Plans\Http\Controllers\BillingController;
use Sanjayacloud\Plans\Plans;

Route::middleware(config('plans.billing.middleware', ['web', 'auth']))
    ->prefix(config('plans.billing.prefix', 'billing'))
    ->name('plans.billing.')
    ->group(function () {
        Route::get('/', [BillingController::class, 'index'])->name('index');

        if (Plans::billsThroughStripe()) {
            Route::post('/checkout/{plan}', [BillingController::class, 'checkout'])->name('checkout');
            Route::get('/portal', [BillingController::class, 'portal'])->name('portal');
        }
    });

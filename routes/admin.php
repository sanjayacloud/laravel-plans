<?php

use Illuminate\Support\Facades\Route;
use Sanjayacloud\Plans\Http\Controllers\Admin\DashboardController;
use Sanjayacloud\Plans\Http\Controllers\Admin\PlanController;
use Sanjayacloud\Plans\Http\Controllers\Admin\UserController;
use Sanjayacloud\Plans\Plans;

Route::middleware([...config('plans.admin.middleware', ['web', 'auth']), 'can:'.Plans::adminGate()])
    ->prefix(config('plans.admin.prefix', 'admin'))
    ->name('plans.admin.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::resource('plans', PlanController::class)->except('show');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::put('/users/{user}/trial', [UserController::class, 'updateTrial'])->name('users.trial.update');
        Route::delete('/users/{user}/trial', [UserController::class, 'endTrial'])->name('users.trial.end');
        Route::put('/users/{user}/grant', [UserController::class, 'grantPlan'])->name('users.grant');
        Route::delete('/users/{user}/grant', [UserController::class, 'revokePlan'])->name('users.grant.revoke');
        Route::post('/users/{user}/super-admin', [UserController::class, 'toggleSuperAdmin'])->name('users.super-admin');

        if (Plans::billsThroughStripe()) {
            Route::post('/users/{user}/subscription/cancel', [UserController::class, 'cancelSubscription'])->name('users.subscription.cancel');
            Route::post('/users/{user}/subscription/resume', [UserController::class, 'resumeSubscription'])->name('users.subscription.resume');
        }
    });

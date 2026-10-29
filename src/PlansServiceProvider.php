<?php

namespace Sanjayacloud\Plans;

use Illuminate\Auth\Events\Registered;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Sanjayacloud\Plans\Commands\GrantSuperAdminCommand;
use Sanjayacloud\Plans\Commands\StartTrialsCommand;
use Sanjayacloud\Plans\Http\Middleware\EnsureUserHasFeature;
use Sanjayacloud\Plans\Listeners\StartTrialOnRegistration;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PlansServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('plans')
            ->hasConfigFile()
            ->hasViews('plans')
            ->hasMigrations([
                'create_plans_table',
                'add_plan_columns_to_users_table',
                'create_plan_admin_actions_table',
            ])
            ->hasCommands([
                GrantSuperAdminCommand::class,
                StartTrialsCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(PlanResolver::class);
        $this->app->singleton(StripeBilling::class);
    }

    /**
     * Routes and the gate are registered after the app's providers have had a chance
     * to define the gate or change config, so both stay overridable.
     */
    public function packageBooted(): void
    {
        $this->app->make(Router::class)->aliasMiddleware('plan.feature', EnsureUserHasFeature::class);

        Event::listen(Registered::class, StartTrialOnRegistration::class);

        $this->app->booted(function (): void {
            if (! Gate::has(Plans::adminGate())) {
                Gate::define(Plans::adminGate(), fn ($user): bool => (bool) $user->is_super_admin);
            }

            if ($this->app->routesAreCached()) {
                return;
            }

            if (config('plans.billing.enabled', true)) {
                $this->loadRoutesFrom(__DIR__.'/../routes/billing.php');
            }

            if (config('plans.admin.enabled', true)) {
                $this->loadRoutesFrom(__DIR__.'/../routes/admin.php');
            }
        });
    }
}

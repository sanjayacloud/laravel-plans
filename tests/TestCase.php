<?php

namespace Sanjayacloud\Plans\Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\CashierServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Sanjayacloud\Plans\PlansServiceProvider;
use Sanjayacloud\Plans\Tests\Fixtures\User;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Cashier::useCustomerModel(User::class);
    }

    protected function getPackageProviders($app): array
    {
        return [
            CashierServiceProvider::class,
            PlansServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        tap($app['config'], function (Repository $config) {
            $config->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
            $config->set('database.default', 'testing');
            $config->set('database.connections.testing', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);
            $config->set('cashier.webhook.secret', null);
            $config->set('plans.user_model', User::class);
            $config->set('plans.features', [
                'exports' => 'CSV exports',
                'api_access' => 'API access',
            ]);
            $config->set('plans.limits', [
                'projects' => 'Projects',
            ]);
        });
    }

    protected function defineRoutes($router): void
    {
        Route::get('/login', fn () => 'login')->name('login');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        $this->loadMigrationsFrom(dirname((new \ReflectionClass(Cashier::class))->getFileName(), 2).'/database/migrations');

        foreach (['create_plans_table', 'add_plan_columns_to_users_table', 'create_plan_admin_actions_table'] as $migrationName) {
            (include __DIR__."/../database/migrations/{$migrationName}.php.stub")->up();
        }
    }
}

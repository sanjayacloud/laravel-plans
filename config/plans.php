<?php

return [

    /*
    |--------------------------------------------------------------------------
    | User model
    |--------------------------------------------------------------------------
    |
    | The model that has plans. Add the Sanjayacloud\Plans\Concerns\HasPlans
    | trait to it (and Laravel\Cashier\Billable if you bill through Stripe).
    |
    */

    'user_model' => 'App\\Models\\User',

    'tables' => [
        'plans' => 'plans',
        'admin_actions' => 'plan_admin_actions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Features and limits
    |--------------------------------------------------------------------------
    |
    | Features a plan can unlock, keyed by the name you check in code
    | ($user->hasFeature('exports') or the plan.feature:exports middleware),
    | and usage limits a plan can set ($user->planLimit('projects')).
    | A limit a plan leaves blank is unlimited.
    |
    */

    'features' => [
        // 'exports' => 'CSV exports',
    ],

    'limits' => [
        // 'projects' => 'Projects',
    ],

    /*
    |--------------------------------------------------------------------------
    | Trials
    |--------------------------------------------------------------------------
    |
    | New users get the trial plan's trial when the Registered event fires.
    | No card is needed; the trial is stored in users.trial_ends_at.
    |
    */

    'trial' => [
        'start_on_registration' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe (Laravel Cashier)
    |--------------------------------------------------------------------------
    |
    | Only used when laravel/cashier is installed and the user model is Billable.
    |
    */

    'subscription_type' => 'default',

    'currency_symbol' => '$',

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | The package pages extend this layout and fill this section. Point them at
    | your own layout, or publish the views (plans-views) to restyle them.
    |
    */

    'layout' => 'plans::layouts.default',

    'layout_section' => 'content',

    /*
    | Where users without a feature are sent. Set to null to answer with 402.
    */
    'upgrade_route' => 'plans.billing.index',

    'billing' => [
        'enabled' => true,
        'prefix' => 'billing',
        'middleware' => ['web', 'auth'],
    ],

    'admin' => [
        'enabled' => true,
        'prefix' => 'admin',
        'middleware' => ['web', 'auth'],

        /*
        | Gate that protects the admin area. The package defines it as
        | "the user's is_super_admin column is true"; define it yourself to change that.
        */
        'gate' => 'manage-plans',

        'per_page' => 25,
    ],

];

# Laravel Plans

[![Tests](https://github.com/sanjayacloud/laravel-plans/actions/workflows/tests.yml/badge.svg)](https://github.com/sanjayacloud/laravel-plans/actions/workflows/tests.yml)

Free and paid plans for Laravel apps: feature gating, usage limits, no-card free trials, complimentary plans, optional Stripe billing through [Laravel Cashier](https://laravel.com/docs/billing), and a super admin area to run it all.

- **Plans** live in the database with monthly and yearly prices, features, limits and Stripe price IDs.
- **One resolver** decides each user's plan: a valid Stripe subscription, then an admin-granted plan, then the free trial, then the default free plan.
- **Gate features** with `$user->hasFeature('exports')` or the `plan.feature:exports` middleware (browsers are sent to the pricing page, APIs get a `402`).
- **Limit usage** with `$user->planLimit('projects')` (`null` means unlimited).
- **Pricing page** with Stripe Checkout and the Stripe billing portal.
- **Admin area** for plans, users, trials, complimentary plans, cancelling and resuming subscriptions, with an audit log.
- **Cashier is optional.** Without it you still get plans, trials, grants, limits and the admin area.

## Requirements

- PHP 8.3+
- Laravel 11, 12, or 13
- Optional: `laravel/cashier` 15 or 16 for Stripe billing

## Installation

```bash
composer require sanjayacloud/laravel-plans
php artisan vendor:publish --tag="plans-config"
php artisan vendor:publish --tag="plans-migrations"
php artisan migrate
```

The migrations create `plans` and `plan_admin_actions`, and add `is_super_admin`, `granted_plan_id`, `granted_until` and (unless Cashier already added it) `trial_ends_at` to your users table.

Add the `HasPlans` trait to your user model:

```php
use Laravel\Cashier\Billable; // optional
use Sanjayacloud\Plans\Concerns\HasPlans;

class User extends Authenticatable
{
    use Billable, HasPlans;
}
```

Make yourself a super admin:

```bash
php artisan plans:grant-admin you@example.com
```

Then open `/admin` to create your plans, and `/billing` to see the pricing page.

## Configuration

Declare the features and limits your app checks in `config/plans.php`. The admin plan form and the pricing page are built from these lists.

```php
'features' => [
    'exports' => 'CSV exports',
    'api_access' => 'API access',
],

'limits' => [
    'projects' => 'Projects',
],
```

Other options:

| Key | Default | What it does |
| --- | --- | --- |
| `user_model` | `App\Models\User` | The model that has plans |
| `tables.plans`, `tables.admin_actions` | `plans`, `plan_admin_actions` | Table names |
| `trial.start_on_registration` | `true` | Start the trial plan's trial when `Registered` fires |
| `subscription_type` | `default` | Cashier subscription type to read |
| `currency_symbol` | `$` | Used when formatting prices |
| `layout`, `layout_section` | `plans::layouts.default`, `content` | Layout the pages extend and the section they fill |
| `upgrade_route` | `plans.billing.index` | Where the feature middleware sends browsers (`null` for a 402) |
| `billing.enabled`, `billing.prefix`, `billing.middleware` | `true`, `billing`, `['web', 'auth']` | Pricing page routes |
| `admin.enabled`, `admin.prefix`, `admin.middleware` | `true`, `admin`, `['web', 'auth']` | Admin routes |
| `admin.gate` | `manage-plans` | Gate protecting the admin area |

## Checking plans in code

```php
$user->currentPlan();        // ?Plan
$user->planStatus();         // PlanStatus::Subscribed, GracePeriod, Trial, Complimentary or Free
$user->planStatus()->label(); // "Free trial"
$user->hasFeature('exports');
$user->planLimit('projects'); // ?int, null when unlimited
$user->onPlanTrial();
$user->startPlanTrial(30);    // or startPlanTrial() for the trial plan's length

User::query()->wherePlanStatus(PlanStatus::Trial)->count();
```

The resolved plan is cached on the model for the request. Call `$user->forgetResolvedPlan()` after changing anything that affects it.

Enforcing a limit is up to your app, because only it knows what to count:

```php
$limit = $request->user()->planLimit('projects');

if ($limit !== null && $request->user()->projects()->count() >= $limit) {
    return back()->with('error', "Your plan allows {$limit} projects. Upgrade for more.");
}
```

### Middleware

```php
Route::middleware('plan.feature:exports')->group(function () {
    Route::get('/exports', ExportController::class);
});
```

Users whose plan lacks the feature are redirected to the pricing page with an `error` flash message. JSON and `api/*` requests get:

```json
{ "message": "CSV exports isn't included in your plan. Upgrade to unlock it.", "feature": "exports" }
```

with status `402 Payment Required`.

### Trial banner

Drop the banner into your layout to remind users how long their trial has left, that it ended, or that their subscription is cancelling:

```blade
<x-plans::banner />
```

## Stripe billing

With Cashier installed and your user model `Billable`, the pricing page offers Stripe Checkout for every active plan that has Stripe price IDs, and the billing portal for existing customers. Set up Cashier as usual (keys, the `stripe/*` CSRF exception, and the webhook pointing at `/stripe/webhook`). Plan changes made in Stripe arrive through Cashier's webhook and the resolver picks them up, because plans are matched by price ID.

A valid subscription on a price no plan knows about falls back to the trial plan, so paying users never lose access because a price was retired.

## Admin area

Users who pass the `admin.gate` gate (by default, `is_super_admin` is true) can:

- See users by plan status, estimated MRR, recent sign-ups and cancellations.
- Create, edit and deactivate plans. Plans with subscribers or complimentary users can't be deleted, and there is always exactly one default free plan and at most one trial plan.
- Search and filter users, extend or end trials, grant a plan for free (forever or until a date), cancel or resume Stripe subscriptions, and grant or remove super admin access.

Every admin change is recorded in `plan_admin_actions` and shown on the user's page.

To change who counts as an admin, define the gate yourself in a service provider:

```php
Gate::define('manage-plans', fn (User $user) => $user->email === 'owner@example.com');
```

## Customising the pages

Point the pages at your own layout:

```php
'layout' => 'layouts.app',
'layout_section' => 'content',
```

Or publish the views and restyle them:

```bash
php artisan vendor:publish --tag="plans-views"
```

Published views live in `resources/views/vendor/plans`. The defaults use plain Tailwind classes with dark mode.

## Commands

| Command | What it does |
| --- | --- |
| `plans:grant-admin {email} [--revoke]` | Give or remove super admin access |
| `plans:start-trials [--days=]` | Start a trial for existing users who never had one, a grant or a subscription. Useful when introducing paid plans to an existing app. |

## Testing

```bash
composer test
composer analyse
```

## Changelog

See [CHANGELOG](CHANGELOG.md).

## Security

See [SECURITY](SECURITY.md).

## License

The MIT License (MIT). See [LICENSE](LICENSE.md).

<?php

namespace Sanjayacloud\Plans\Commands;

use Illuminate\Console\Command;
use Sanjayacloud\Plans\Models\Plan;
use Sanjayacloud\Plans\Plans;

class StartTrialsCommand extends Command
{
    protected $signature = 'plans:start-trials
                            {--days= : Length of the trial in days (defaults to the trial plan\'s trial length)}';

    protected $description = 'Start a trial for existing users who never had one, so introducing paid plans does not lock anyone out straight away';

    /**
     * Users who already had a trial, were granted a plan or have any subscription are left alone.
     */
    public function handle(): int
    {
        $days = $this->option('days') !== null ? (int) $this->option('days') : (Plan::trialPlan()->trial_days ?? 0);

        if ($days < 1) {
            $this->error('The trial must be at least one day long. Pass --days or set trial days on the trial plan.');

            return self::FAILURE;
        }

        $updatedCount = Plans::userQuery()
            ->whereNull('trial_ends_at')
            ->whereNull('granted_plan_id')
            ->when(Plans::billsThroughStripe(), fn ($userQuery) => $userQuery->whereDoesntHave('subscriptions'))
            ->update(['trial_ends_at' => now()->addDays($days)]);

        $this->info("Started a {$days}-day trial for {$updatedCount} users.");

        return self::SUCCESS;
    }
}

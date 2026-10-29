<?php

namespace Sanjayacloud\Plans\Commands;

use Illuminate\Console\Command;
use Sanjayacloud\Plans\Plans;

class GrantSuperAdminCommand extends Command
{
    protected $signature = 'plans:grant-admin
                            {email : Email address of the user}
                            {--revoke : Remove super admin access instead}';

    protected $description = 'Give a user (or take away) super admin access to manage plans and subscriptions';

    public function handle(): int
    {
        $user = Plans::userQuery()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error("No user has the email {$this->argument('email')}.");

            return self::FAILURE;
        }

        $makeSuperAdmin = ! $this->option('revoke');

        if ((bool) $user->is_super_admin === $makeSuperAdmin) {
            $this->info($makeSuperAdmin ? "{$user->name} is already a super admin." : "{$user->name} is not a super admin.");

            return self::SUCCESS;
        }

        $user->forceFill(['is_super_admin' => $makeSuperAdmin])->save();

        $this->info($makeSuperAdmin ? "{$user->name} is now a super admin." : "{$user->name} is no longer a super admin.");

        return self::SUCCESS;
    }
}

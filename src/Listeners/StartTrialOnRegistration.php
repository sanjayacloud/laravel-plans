<?php

namespace Sanjayacloud\Plans\Listeners;

use Illuminate\Auth\Events\Registered;

class StartTrialOnRegistration
{
    public function handle(Registered $event): void
    {
        $user = $event->user;

        if (! config('plans.trial.start_on_registration') || ! method_exists($user, 'startPlanTrial') || $user->trial_ends_at !== null) {
            return;
        }

        $user->startPlanTrial();
    }
}

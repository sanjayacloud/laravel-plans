<?php

namespace Sanjayacloud\Plans\Enums;

enum PlanStatus: string
{
    case Subscribed = 'subscribed';
    case GracePeriod = 'grace_period';
    case Trial = 'trial';
    case Complimentary = 'complimentary';
    case Free = 'free';

    public function label(): string
    {
        return match ($this) {
            self::Subscribed => 'Subscribed',
            self::GracePeriod => 'Cancelling',
            self::Trial => 'Free trial',
            self::Complimentary => 'Complimentary',
            self::Free => 'Free',
        };
    }
}

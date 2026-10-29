<?php

namespace Sanjayacloud\Plans\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Sanjayacloud\Plans\Concerns\HasPlans;

class UserWithoutBilling extends Authenticatable
{
    /** @use HasFactory<UserWithoutBillingFactory> */
    use HasFactory, HasPlans;

    protected $table = 'users';

    protected $guarded = [];

    protected static function newFactory(): UserWithoutBillingFactory
    {
        return UserWithoutBillingFactory::new();
    }
}

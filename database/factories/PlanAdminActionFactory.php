<?php

namespace Sanjayacloud\Plans\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Sanjayacloud\Plans\Models\PlanAdminAction;
use Sanjayacloud\Plans\Plans;

/**
 * @extends Factory<PlanAdminAction>
 */
class PlanAdminActionFactory extends Factory
{
    protected $model = PlanAdminAction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admin_id' => Plans::userModel()::factory()->state(['is_super_admin' => true]),
            'target_user_id' => Plans::userModel()::factory(),
            'action' => 'trial_updated',
            'details' => ['to' => now()->addDays(7)->toDateString()],
        ];
    }
}

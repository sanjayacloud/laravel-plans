<?php

namespace Sanjayacloud\Plans\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Sanjayacloud\Plans\Database\Factories\PlanAdminActionFactory;
use Sanjayacloud\Plans\Plans;

/**
 * An audit log entry for something a super admin did to a user's plan or access.
 *
 * @property int $id
 * @property int|null $admin_id
 * @property int|null $target_user_id
 * @property string $action
 * @property array<string, mixed>|null $details
 */
class PlanAdminAction extends Model
{
    /** @use HasFactory<PlanAdminActionFactory> */
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'target_user_id',
        'action',
        'details',
    ];

    public function getTable(): string
    {
        return config('plans.tables.admin_actions', 'plan_admin_actions');
    }

    protected function casts(): array
    {
        return [
            'details' => 'array',
        ];
    }

    protected static function newFactory(): PlanAdminActionFactory
    {
        return PlanAdminActionFactory::new();
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Plans::userModel(), 'admin_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(Plans::userModel(), 'target_user_id');
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function record(Model $admin, ?Model $targetUser, string $action, array $details = []): self
    {
        return self::query()->create([
            'admin_id' => $admin->getKey(),
            'target_user_id' => $targetUser?->getKey(),
            'action' => $action,
            'details' => $details === [] ? null : $details,
        ]);
    }
}

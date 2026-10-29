<?php

namespace Sanjayacloud\Plans\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Sanjayacloud\Plans\Http\Requests\SavePlanRequest;
use Sanjayacloud\Plans\Models\Plan;
use Sanjayacloud\Plans\Models\PlanAdminAction;

class PlanController extends Controller
{
    public function index(): View
    {
        $plans = Plan::query()->ordered()->withCount('grantedUsers')->get()
            ->each(function (Plan $plan): void {
                $plan->setAttribute('active_subscriptions_count', $plan->subscriptionsQuery()?->active()->count() ?? 0);
            });

        return view('plans::admin.plans.index', compact('plans'));
    }

    public function create(): View
    {
        return view('plans::admin.plans.create', ['plan' => new Plan(['is_active' => true, 'features' => [], 'limits' => []])]);
    }

    public function store(SavePlanRequest $request): RedirectResponse
    {
        $plan = DB::transaction(function () use ($request): Plan {
            $plan = Plan::query()->create($request->planAttributes());
            $this->keepSingleFlags($plan);

            return $plan;
        });

        PlanAdminAction::record($request->user(), null, 'plan_created', ['plan_id' => $plan->id, 'name' => $plan->name]);

        return redirect()->route('plans.admin.plans.index')->with('success', "{$plan->name} plan created.");
    }

    public function edit(Plan $plan): View
    {
        return view('plans::admin.plans.edit', compact('plan'));
    }

    public function update(SavePlanRequest $request, Plan $plan): RedirectResponse
    {
        DB::transaction(function () use ($request, $plan): void {
            $plan->update($request->planAttributes());
            $this->keepSingleFlags($plan);
        });

        PlanAdminAction::record($request->user(), null, 'plan_updated', [
            'plan_id' => $plan->id,
            'name' => $plan->name,
            'changes' => array_keys($plan->getChanges()),
        ]);

        return redirect()->route('plans.admin.plans.index')->with('success', "{$plan->name} plan updated.");
    }

    /**
     * Plans that anyone uses can only be deactivated, so nobody silently loses access.
     */
    public function destroy(Request $request, Plan $plan): RedirectResponse
    {
        if ($plan->is_default_free) {
            return back()->with('error', "The default free plan can't be deleted.");
        }

        if ($plan->subscriptionsQuery()?->active()->exists() || $plan->grantedUsers()->exists()) {
            return back()->with('error', "{$plan->name} has subscribers or complimentary users. Deactivate it instead.");
        }

        $plan->delete();

        PlanAdminAction::record($request->user(), null, 'plan_deleted', ['plan_id' => $plan->id, 'name' => $plan->name]);

        return redirect()->route('plans.admin.plans.index')->with('success', "{$plan->name} plan deleted.");
    }

    /**
     * Only one plan can be the default free plan and only one can be the trial plan.
     */
    private function keepSingleFlags(Plan $plan): void
    {
        if ($plan->is_default_free) {
            Plan::query()->whereKeyNot($plan->id)->update(['is_default_free' => false]);
        }

        if ($plan->is_trial_plan) {
            Plan::query()->whereKeyNot($plan->id)->update(['is_trial_plan' => false]);
        }
    }
}

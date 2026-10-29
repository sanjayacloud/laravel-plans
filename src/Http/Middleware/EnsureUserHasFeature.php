<?php

namespace Sanjayacloud\Plans\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Sanjayacloud\Plans\Plans;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasFeature
{
    /**
     * Block the request unless the user's current plan includes the feature.
     * JSON requests get a 402; browsers are sent to the upgrade page.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if ($user !== null && method_exists($user, 'hasFeature') && $user->hasFeature($feature)) {
            return $next($request);
        }

        $message = Plans::upgradeMessage($feature);
        $upgradeRoute = config('plans.upgrade_route');

        if ($request->expectsJson() || $request->is('api/*') || $upgradeRoute === null || ! Route::has($upgradeRoute)) {
            return response()->json([
                'message' => $message,
                'feature' => $feature,
            ], Response::HTTP_PAYMENT_REQUIRED);
        }

        return redirect()->route($upgradeRoute)->with('error', $message);
    }
}

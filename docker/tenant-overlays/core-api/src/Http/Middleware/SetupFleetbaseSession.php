<?php

namespace Fleetbase\Http\Middleware;

use Fleetbase\Models\Company;
use Fleetbase\Models\CompanyUser;
use Fleetbase\Models\User;
use Fleetbase\Scopes\CompanyScope;
use Fleetbase\Support\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class SetupFleetbaseSession
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     */
    public function handle($request, \Closure $next)
    {
        $user = $request->user();
        if ($user instanceof User) {
            if (!in_array($user->status, [null, 'active'], true)) {
                return response()->error('Your user access is no longer active.', 401);
            }

            $hasActiveMembership = CompanyUser::withoutGlobalScope(CompanyScope::class)
                ->where('user_uuid', $user->uuid)
                ->where('company_uuid', $user->company_uuid)
                ->where('status', 'active')
                ->exists();
            $hasActiveCompany = Company::withoutGlobalScope(CompanyScope::class)
                ->active()
                ->where('uuid', $user->company_uuid)
                ->exists();

            if (!$hasActiveMembership || !$hasActiveCompany) {
                return response()->error('Your organization access is no longer active.', 401);
            }
        }

        Auth::setSession($user);
        Auth::setSandboxSession($request);

        if (method_exists($user, 'currentAccessToken')) {
            $personalAccessToken = $user->currentAccessToken();
            if ($personalAccessToken && $personalAccessToken instanceof PersonalAccessToken) {
                Auth::setApiKey($personalAccessToken);
            }
        }

        return $next($request);
    }
}

<?php

namespace Fleetbase\Http\Middleware;

use Fleetbase\Support\TenantContext;

class EnforceTenantContext
{
    public function handle($request, \Closure $next)
    {
        $companyUuid = app()->bound('session') ? app('session')->get('company') : null;

        if (is_string($companyUuid) && trim($companyUuid) !== '') {
            TenantContext::activate($companyUuid);
        } else {
            TenantContext::enforce();
        }

        try {
            return $next($request);
        } finally {
            TenantContext::clear();
        }
    }
}

<?php

namespace Fleetbase\Jobs\Middleware;

use Fleetbase\Support\TenantContext;

class UseTenantContext
{
    public function __construct(public readonly string $companyUuid)
    {
    }

    public function handle($job, \Closure $next): mixed
    {
        return TenantContext::run(
            $this->companyUuid,
            fn () => $next($job),
            syncSession: false
        );
    }
}

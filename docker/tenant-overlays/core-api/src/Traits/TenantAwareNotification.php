<?php

namespace Fleetbase\Traits;

use Fleetbase\Jobs\Middleware\UseTenantContext;
use Fleetbase\Support\TenantContext;

trait TenantAwareNotification
{
    public function middleware($notifiable, string $channel): array
    {
        $companyUuid = TenantContext::companyUuid()
            ?? data_get($this, 'companyUuid')
            ?? data_get($this, 'company.uuid')
            ?? data_get($this, 'invite.company_uuid')
            ?? data_get($this, 'chatMessage.company_uuid')
            ?? session('company')
            ?? data_get($notifiable, 'company_uuid');

        return $companyUuid ? [new UseTenantContext($companyUuid)] : [];
    }
}

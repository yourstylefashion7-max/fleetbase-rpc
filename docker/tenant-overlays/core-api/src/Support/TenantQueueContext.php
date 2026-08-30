<?php

namespace Fleetbase\Support;

final class TenantQueueContext
{
    public static function payload(): array
    {
        return [
            'fleetbase_tenant' => [
                'company_uuid' => TenantContext::companyUuid(),
            ],
        ];
    }

    public static function activateFromPayload(array $payload): void
    {
        $companyUuid = data_get($payload, 'fleetbase_tenant.company_uuid');
        if (is_string($companyUuid) && trim($companyUuid) !== '') {
            TenantContext::activate($companyUuid);

            return;
        }

        TenantContext::enforce();
    }

    public static function clear(): void
    {
        TenantContext::clear();
    }
}

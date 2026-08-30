<?php

namespace Fleetbase\Traits;

use Fleetbase\Support\TenantContext;

/**
 * Stamps newly created tenant-owned records from the trusted context.
 */
trait AssignsTenant
{
    public static function bootAssignsTenant(): void
    {
        static::creating(function ($model) {
            if (!$model->isFillable('company_uuid')) {
                return;
            }

            $companyUuid = TenantContext::companyUuid();
            if (empty($companyUuid) && !TenantContext::isRequired()) {
                $companyUuid = session('company');
            }
            if (!empty($companyUuid)) {
                $model->company_uuid = $companyUuid;

                return;
            }

            if (TenantContext::isRequired()) {
                throw new \LogicException('Tenant-owned records require an active tenant context.');
            }
        });
    }
}

<?php

namespace Fleetbase\Scopes;

use Fleetbase\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * CompanyScope — Tenant Isolation Global Scope.
 *
 * Automatically constrains every Eloquent query on models that carry a
 * `company_uuid` column to the company in the active TenantContext.
 * This is the primary defence against the cross-tenant IDOR
 * vulnerability (GHSA-3wj9-hh56-7fw7) where single-record operations
 * (find, update, delete) were performed by UUID/public_id alone without
 * verifying the resource belonged to the caller's company.
 *
 * Behaviour
 * ---------
 * - Applied automatically to every model that calls `addGlobalScope(new CompanyScope)`.
 * - Activates when middleware has required a TenantContext and the model's
 *   table has a `company_uuid` column (checked once per connection/table).
 * - Applies in HTTP workers hosted by console runtimes such as Laravel Octane.
 * - Fails closed when tenant context is required but has no company UUID.
 * - Does not activate outside a required context, preserving installer,
 *   unauthenticated, and explicitly system-level workflows.
 *
 *
 * Escape Hatches
 * --------------
 * When a query genuinely needs to cross company boundaries (e.g. super-admin
 * tooling, system-level lookups), call one of the macro helpers added by
 * this scope's extend() method:
 *
 *   Model::withoutCompanyScope()->where(...)->get();
 *   Model::withoutGlobalScope(CompanyScope::class)->where(...)->get();
 *
 * The `withoutCompanyScope()` macro is the preferred, readable form.
 */
class CompanyScope implements Scope
{
    public const MODE_AUTO             = 'auto';
    public const MODE_TENANT_ONLY      = 'tenant-only';
    public const MODE_TENANT_OR_GLOBAL = 'tenant-or-global';
    public const MODE_INDIRECT         = 'indirect';
    public const MODE_GLOBAL           = 'global';

    /**
     * Per-process cache of which table names have a `company_uuid` column.
     * Avoids repeated Schema::hasColumn() calls on the same table.
     *
     * @var array<string, bool>
     */
    protected static array $columnCache = [];

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        $companyUuid = TenantContext::companyUuid();

        if (empty($companyUuid) && !TenantContext::isRequired()) {
            return;
        }

        $mode = method_exists($model, 'tenantScopeMode') ? $model->tenantScopeMode() : static::MODE_AUTO;
        if ($mode === static::MODE_GLOBAL) {
            return;
        }

        if (empty($companyUuid)) {
            $builder->whereRaw('1 = 0');

            return;
        }

        if ($mode === static::MODE_INDIRECT) {
            $relation = method_exists($model, 'tenantScopeRelation') ? $model->tenantScopeRelation() : null;
            if (empty($relation)) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->whereHas($relation);

            return;
        }

        // Only apply when the model's table actually has its declared tenant column.
        // Cache the result per connection, table, and column to avoid repeated introspection.
        $table        = $model->getTable();
        $tenantColumn = method_exists($model, 'tenantScopeColumn') ? $model->tenantScopeColumn() : 'company_uuid';
        $connection   = $model->getConnection();
        $cacheKey     = ($model->getConnectionName() ?? '') . '|' . spl_object_id($connection) . '|' . $table . '|' . $tenantColumn;
        if (!isset(static::$columnCache[$cacheKey])) {
            static::$columnCache[$cacheKey] = $connection->getSchemaBuilder()->hasColumn($table, $tenantColumn);
        }

        if (!static::$columnCache[$cacheKey]) {
            if (in_array($mode, [static::MODE_AUTO, static::MODE_TENANT_ONLY, static::MODE_TENANT_OR_GLOBAL], true)) {
                $builder->whereRaw('1 = 0');
            }

            return;
        }

        $companyColumn = $model->qualifyColumn($tenantColumn);
        if ($mode === static::MODE_TENANT_OR_GLOBAL) {
            $builder->where(function (Builder $query) use ($companyColumn, $companyUuid) {
                $query->where($companyColumn, $companyUuid)->orWhereNull($companyColumn);
            });

            return;
        }

        $builder->where($companyColumn, $companyUuid);
    }

    /**
     * Extend the query builder with the withoutCompanyScope macro.
     *
     * @return void
     */
    public function extend(Builder $builder)
    {
        $this->addWithoutCompanyScope($builder);
    }

    /**
     * Add the withoutCompanyScope macro to the builder.
     *
     * @return void
     */
    protected function addWithoutCompanyScope(Builder $builder)
    {
        $builder->macro('withoutCompanyScope', function (Builder $builder) {
            return $builder->withoutGlobalScope(CompanyScope::class);
        });
    }

    /**
     * Flush the column existence cache.
     * Useful in tests where tables may be created/dropped between cases.
     */
    public static function flushColumnCache(): void
    {
        static::$columnCache = [];
    }
}

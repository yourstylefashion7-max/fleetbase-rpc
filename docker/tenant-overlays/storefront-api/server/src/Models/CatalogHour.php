<?php

namespace Fleetbase\Storefront\Models;

use Fleetbase\Scopes\CompanyScope;
use Fleetbase\Traits\HasApiModelBehavior;
use Fleetbase\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CatalogHour extends StorefrontModel
{
    use HasUuid;
    use HasApiModelBehavior;
    use SoftDeletes;

    public function tenantScopeMode(): string
    {
        return CompanyScope::MODE_INDIRECT;
    }

    public function tenantScopeRelation(): string
    {
        return 'catalog';
    }

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'catalog_hours';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'catalog_uuid',
        'day_of_week',
        'start',
        'end',
    ];

    /**
     * The relationships that should always be loaded.
     *
     * @var array
     */
    protected $with = [];

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Catalog::class, 'catalog_uuid', 'uuid');
    }
}

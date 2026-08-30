<?php

namespace Fleetbase\Models;

use Fleetbase\Casts\Json;
use Fleetbase\Scopes\CompanyScope;
use Fleetbase\Traits\Filterable;
use Fleetbase\Traits\HasApiModelBehavior;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\Searchable;

class DashboardWidget extends Model
{
    use HasUuid;
    use HasApiModelBehavior;
    use Searchable;
    use Filterable;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'dashboard_widgets';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'dashboard_uuid',
        'name',
        'component',
        'grid_options',
        'options',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'grid_options' => Json::class,
        'options'      => Json::class,
    ];

    public function tenantScopeMode(): string
    {
        return CompanyScope::MODE_INDIRECT;
    }

    public function tenantScopeRelation(): string
    {
        return 'dashboard';
    }

    public function dashboard()
    {
        return $this->belongsTo(Dashboard::class, 'dashboard_uuid', 'uuid');
    }
}

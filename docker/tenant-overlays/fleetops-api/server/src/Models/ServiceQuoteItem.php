<?php

namespace Fleetbase\FleetOps\Models;

use Fleetbase\Models\Model;
use Fleetbase\Scopes\CompanyScope;
use Fleetbase\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceQuoteItem extends Model
{
    use HasUuid;

    public function tenantScopeMode(): string
    {
        return CompanyScope::MODE_INDIRECT;
    }

    public function tenantScopeRelation(): string
    {
        return 'serviceQuote';
    }

    public function serviceQuote(): BelongsTo
    {
        return $this->belongsTo(ServiceQuote::class, 'service_quote_uuid', 'uuid');
    }

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'service_quote_items';

    /**
     * These attributes that can be queried.
     *
     * @var array
     */
    protected $searchableColumns = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['_key', 'service_quote_uuid', 'amount', 'currency', 'details', 'code'];

    /**
     * Dynamic attributes that are appended to object.
     *
     * @var array
     */
    protected $appends = [];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [];
}

<?php

namespace Fleetbase\Models;

use Fleetbase\Scopes\CompanyScope;
use Fleetbase\Support\Utils;
use Fleetbase\Traits\Filterable;
use Fleetbase\Traits\HasApiModelBehavior;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\Searchable;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    use HasUuid;
    use HasApiModelBehavior;
    use Filterable;

    use Searchable;

    public static function boot()
    {
        parent::boot();
        static::addGlobalScope(new CompanyScope());
    }

    public function tenantScopeMode(): string
    {
        return CompanyScope::MODE_TENANT_ONLY;
    }

    public function tenantScopeColumn(): string
    {
        return 'company_id';
    }
    protected $with              = ['subject', 'causer'];
    protected $appends           = ['humanized_subject_type', 'humanized_causer_type'];
    protected $searchableColumns = ['subject_type', 'description', 'log_name'];

    public function getHumanizedSubjectTypeAttribute(): ?string
    {
        if (empty($this->attributes['subject_type'])) {
            return null;
        }

        $segments = explode('\\', $this->attributes['subject_type']);
        $name     = end($segments);
        $name     = Str::snake($name);

        return Utils::humanize($name);
    }

    public function getHumanizedCauserTypeAttribute(): ?string
    {
        if (empty($this->attributes['causer_type'])) {
            return null;
        }

        $segments = explode('\\', $this->attributes['causer_type']);
        $name     = end($segments);
        $name     = Str::snake($name);

        return Utils::humanize($name);
    }
}

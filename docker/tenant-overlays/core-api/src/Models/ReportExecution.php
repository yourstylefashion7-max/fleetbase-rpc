<?php

namespace Fleetbase\Models;

use Fleetbase\Scopes\CompanyScope;
use Fleetbase\Traits\HasUuid;

class ReportExecution extends Model
{
    use HasUuid;
    use \Fleetbase\Traits\AssignsTenant;

    /**
     * The database table used by the model.
     */
    protected $table = 'report_executions';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uuid',
        'report_uuid',
        'user_uuid',
        'company_uuid',
        'execution_time',
        'result_count',
        'query_config',
        'status',
        'error_message',
        'started_at',
        'completed_at',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'query_config'   => 'array',
        'execution_time' => 'float',
        'result_count'   => 'integer',
        'started_at'     => 'datetime',
        'completed_at'   => 'datetime',
    ];

    public function tenantScopeMode(): string
    {
        return CompanyScope::MODE_TENANT_ONLY;
    }

    /**
     * Relationships.
     */
    public function report()
    {
        return $this->belongsTo(Report::class, 'report_uuid');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_uuid');
    }
}

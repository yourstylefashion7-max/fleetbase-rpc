<?php

namespace Fleetbase\Models;

use Fleetbase\Scopes\CompanyScope;
use Fleetbase\Traits\AssignsTenant;
use Fleetbase\Traits\Filterable;
use Fleetbase\Traits\HasApiModelBehavior;
use Fleetbase\Traits\Searchable;
use Illuminate\Notifications\DatabaseNotification;

class Notification extends DatabaseNotification
{
    use HasApiModelBehavior;
    use AssignsTenant;
    use Searchable;
    use Filterable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['id', 'company_uuid', 'read_at', 'data', 'notifiable_id', 'notifiable_type', 'type'];

    public static function boot()
    {
        parent::boot();
        static::addGlobalScope(new CompanyScope());
    }

    public function tenantScopeMode(): string
    {
        return CompanyScope::MODE_TENANT_ONLY;
    }

    /**
     * The searchable columns.
     *
     * @var array
     */
    protected $searchableColumns = ['data->message'];

    /**
     * Marks the notification as read.
     *
     * @param bool $save
     */
    public function markAsRead($save = true): Notification
    {
        $this->read_at = now();

        if ($save) {
            $this->save();
        }

        return $this;
    }

    /**
     * Delete the notification.
     *
     * @return void
     */
    public function deleteNotification()
    {
        $this->delete();
    }
}

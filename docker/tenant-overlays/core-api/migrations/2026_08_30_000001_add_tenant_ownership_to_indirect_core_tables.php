<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const TABLES = [
        'group_users'           => 'group_users_company_uuid_idx',
        'schedule_availability' => 'schedule_availability_company_uuid_idx',
        'user_devices'          => 'user_devices_company_uuid_idx',
        'notifications'         => 'notifications_company_uuid_idx',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName => $indexName) {
            if (!Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'company_uuid')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                $table->string('company_uuid', 191)->nullable();
                $table->index('company_uuid', $indexName);
            });
        }

        $this->backfillFromOwner('group_users', 'group_uuid', 'groups');
        // Device records have no immutable organization provenance. Existing rows
        // must be assigned explicitly by the operator instead of guessed from the
        // user's mutable current-company pointer.
        $this->backfillNotifications();
        $this->backfillScheduleAvailability();
        $this->assertNoUnownedTenantRows();
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES, true) as $tableName => $indexName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'company_uuid')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
                $table->dropColumn('company_uuid');
            });
        }
    }

    private function backfillFromOwner(string $tableName, string $ownerColumn, string $ownerTable): void
    {
        if (!Schema::hasTable($tableName) || !Schema::hasTable($ownerTable) || !Schema::hasColumn($tableName, 'company_uuid')) {
            return;
        }

        DB::table($tableName)
            ->whereNull('company_uuid')
            ->orderBy('id')
            ->get(['id', $ownerColumn])
            ->each(function ($row) use ($tableName, $ownerColumn, $ownerTable) {
                $companyUuid = DB::table($ownerTable)->where('uuid', $row->{$ownerColumn})->value('company_uuid');
                if ($companyUuid) {
                    DB::table($tableName)->where('id', $row->id)->update(['company_uuid' => $companyUuid]);
                }
            });
    }

    private function backfillNotifications(): void
    {
        if (!Schema::hasTable('notifications') || !Schema::hasTable('companies') || !Schema::hasColumn('notifications', 'company_uuid')) {
            return;
        }

        DB::table('notifications')
            ->whereNull('company_uuid')
            ->orderBy('id')
            ->get(['id', 'data'])
            ->each(function ($row) {
                $data = is_string($row->data) ? json_decode($row->data, true) : (array) $row->data;
                if (!is_array($data)) {
                    return;
                }

                $companyUuid = data_get($data, 'companyId') ?? data_get($data, 'company_uuid');
                if (!$companyUuid || !DB::table('companies')->where('uuid', $companyUuid)->exists()) {
                    return;
                }

                DB::table('notifications')->where('id', $row->id)->update(['company_uuid' => $companyUuid]);
            });
    }

    private function backfillScheduleAvailability(): void
    {
        if (!Schema::hasTable('schedule_availability') || !Schema::hasColumn('schedule_availability', 'company_uuid')) {
            return;
        }

        $ownerTables = [
            'driver'  => 'drivers',
            'vehicle' => 'vehicles',
            'user'    => 'users',
        ];

        DB::table('schedule_availability')
            ->whereNull('company_uuid')
            ->orderBy('id')
            ->get(['id', 'subject_uuid', 'subject_type'])
            ->each(function ($row) use ($ownerTables) {
                $ownerType  = strtolower(class_basename($row->subject_type));
                $ownerTable = $ownerTables[$ownerType] ?? null;
                if (!$ownerTable || !Schema::hasTable($ownerTable)) {
                    return;
                }

                $companyUuid = DB::table($ownerTable)->where('uuid', $row->subject_uuid)->value('company_uuid');
                if ($companyUuid) {
                    DB::table('schedule_availability')->where('id', $row->id)->update(['company_uuid' => $companyUuid]);
                }
            });
    }

    private function assertNoUnownedTenantRows(): void
    {
        foreach (array_keys(self::TABLES) as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'company_uuid')) {
                continue;
            }

            $unowned = DB::table($tableName)->whereNull('company_uuid')->count();
            if ($unowned > 0) {
                throw new RuntimeException("Tenant ownership migration stopped: {$tableName} has {$unowned} ambiguous rows. Assign company_uuid explicitly, then rerun the migration.");
            }
        }
    }
};

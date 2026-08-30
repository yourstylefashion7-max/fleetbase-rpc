<?php

namespace Fleetbase\Console\Commands;

use Fleetbase\Models\Report;
use Fleetbase\Models\ReportExecution;
use Fleetbase\Scopes\CompanyScope;
use Fleetbase\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunScheduledReports extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'reports:run-scheduled 
                            {--dry-run : Show what would be executed without running}
                            {--report= : Run specific report by UUID or public ID}';

    /**
     * The console command description.
     */
    protected $description = 'Execute scheduled reports that are due';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun       = $this->option('dry-run');
        $specificReport = $this->option('report');

        if ($specificReport) {
            return $this->runSpecificReport($specificReport, $isDryRun);
        }

        return $this->runDueReports($isDryRun);
    }

    /**
     * Run a specific report.
     */
    protected function runSpecificReport(string $reportId, bool $isDryRun): int
    {
        $report = Report::withoutGlobalScope(CompanyScope::class)
            ->whereHas('company', fn ($query) => $query->active())
            ->where(function ($query) use ($reportId) {
                $query->where('uuid', $reportId)
                      ->orWhere('public_id', $reportId);
            })->first();

        if (!$report) {
            $this->error("Report not found: {$reportId}");

            return 1;
        }

        if ($isDryRun) {
            $this->info("Would execute report: {$report->title} ({$report->public_id})");

            return 0;
        }

        return $this->executeReportForTenant($report) ? 0 : 1;
    }

    /**
     * Run all due reports.
     */
    protected function runDueReports(bool $isDryRun): int
    {
        $dueReports = Report::withoutGlobalScope(CompanyScope::class)
            ->whereHas('company', fn ($query) => $query->active())
            ->where('is_scheduled', true)
            ->whereNotNull('next_scheduled_run')
            ->where('next_scheduled_run', '<=', now())
            ->orderBy('next_scheduled_run')
            ->get();

        if ($dueReports->isEmpty()) {
            $this->info('No scheduled reports are due for execution.');

            return 0;
        }

        $this->info("Found {$dueReports->count()} reports due for execution.");

        if ($isDryRun) {
            $this->table(
                ['Title', 'Public ID', 'Frequency', 'Next Run'],
                $dueReports->map(function ($report) {
                    return [
                        $report->title,
                        $report->public_id,
                        $report->schedule_frequency,
                        $report->next_scheduled_run->format('Y-m-d H:i:s'),
                    ];
                })->toArray()
            );

            return 0;
        }

        $successCount = 0;
        $failureCount = 0;

        foreach ($dueReports as $report) {
            if ($this->executeReportForTenant($report)) {
                $successCount++;
            } else {
                $failureCount++;
            }
        }

        $this->info("Execution complete: {$successCount} successful, {$failureCount} failed.");

        return $failureCount > 0 ? 1 : 0;
    }

    /**
     * Execute a report with a required tenant context and restore worker state.
     */
    protected function executeReportForTenant(Report $report): bool
    {
        if (empty($report->company_uuid)) {
            $this->error("Report has no organization: {$report->public_id}");

            return false;
        }

        return TenantContext::run(
            $report->company_uuid,
            fn () => $this->executeReport($report),
            syncSession: false
        );
    }

    /**
     * Execute a single report.
     */
    protected function executeReport(Report $report): bool
    {
        $this->info("Executing report: {$report->title} ({$report->public_id})");

        // Create execution record
        $execution = ReportExecution::create([
            'report_uuid'  => $report->uuid,
            'company_uuid' => $report->company_uuid,
            'status'       => 'running',
            'started_at'   => now(),
        ]);

        try {
            $startTime = microtime(true);

            // Execute the report
            $results = $report->execute();

            if (($results['success'] ?? true) === false) {
                throw new \Exception($results['error'] ?? 'Report execution failed.');
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            $resultCount   = $results['meta']['total_rows'] ?? count($results['results'] ?? []);

            // Update execution record
            $execution->update([
                'status'         => 'completed',
                'execution_time' => $executionTime,
                'result_count'   => $resultCount,
                'completed_at'   => now(),
            ]);

            // Update next scheduled run
            $report->next_scheduled_run = $report->calculateNextRun();
            $report->save();

            $this->info("✓ Report executed successfully in {$executionTime}ms ({$resultCount} rows)");

            // Log success
            Log::info('Scheduled report executed successfully', [
                'report_uuid'    => $report->uuid,
                'report_title'   => $report->title,
                'execution_time' => $executionTime,
                'result_count'   => $resultCount,
            ]);

            return true;
        } catch (\Exception $e) {
            // Update execution record with error
            $execution->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);

            $this->error("✗ Report execution failed: {$e->getMessage()}");

            // Log error
            Log::error('Scheduled report execution failed', [
                'report_uuid'  => $report->uuid,
                'report_title' => $report->title,
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
            ]);

            return false;
        }
    }
}

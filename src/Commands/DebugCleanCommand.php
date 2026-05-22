<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Commands;

use Illuminate\Console\Command;

class DebugCleanCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'agent:debug-clean {--days= : Clean and purge files older than specified number of days}';

    /**
     * The console command description.
     */
    protected $description = 'Clean and purge all active Laravel Agent-Debugger log files';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $logPath = config('agent-debugger.log_path', storage_path('logs'));
        $days = $this->option('days');

        if (!is_dir($logPath)) {
            $this->error("Directory does not exist: {$logPath}");
            return Command::FAILURE;
        }

        if ($days !== null) {
            $daysLimit = (int)$days;
            $cutoffTime = time() - ($daysLimit * 86400);

            // Clean daily log files older than X days
            $files = glob($logPath . '/agent_debug-*.log');
            $count = 0;
            if (is_array($files)) {
                foreach ($files as $file) {
                    if (file_exists($file) && filemtime($file) < $cutoffTime) {
                        unlink($file);
                        $count++;
                    }
                }
            }

            // Check single log modification time
            $singleFile = $logPath . '/agent_debug.log';
            if (file_exists($singleFile) && filemtime($singleFile) < $cutoffTime) {
                file_put_contents($singleFile, '');
                $this->info("Purged single log file because it was older than {$daysLimit} days.");
            }

            // Clean Inertia payload logs older than X days
            $inertiaFiles = glob($logPath . '/agent-debugger/inertia/*.json');
            if (is_array($inertiaFiles)) {
                foreach ($inertiaFiles as $file) {
                    if (file_exists($file) && filemtime($file) < $cutoffTime) {
                        unlink($file);
                    }
                }
            }

            $this->info("🧹 Purged {$count} daily log files older than {$daysLimit} days.");
            return Command::SUCCESS;
        }

        // Full clean if no days restriction
        $singleFile = $logPath . '/agent_debug.log';
        if (file_exists($singleFile)) {
            file_put_contents($singleFile, '');
            $this->info("Purged single log file: " . basename($singleFile));
        }

        $files = glob($logPath . '/agent_debug-*.log');
        $count = 0;
        if (is_array($files)) {
            foreach ($files as $file) {
                if (file_exists($file)) {
                    unlink($file);
                    $count++;
                }
            }
        }

        // Clean all Inertia payload logs
        $inertiaFiles = glob($logPath . '/agent-debugger/inertia/*.json');
        if (is_array($inertiaFiles)) {
            foreach ($inertiaFiles as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }

        $this->info("Successfully deleted {$count} daily logging files.");
        $this->info("🧹 Debug log workspace directory is fully clean!");

        return Command::SUCCESS;
    }
}

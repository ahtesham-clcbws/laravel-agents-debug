<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Commands;

use Illuminate\Console\Command;

class DebugStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agent:debug-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display active status, log positions, configurations, and directory footprints';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $enabled = config('agent-debugger.enabled', false);
        $style = config('agent-debugger.log_style', 'date-wise');
        $logPath = config('agent-debugger.log_path', storage_path('logs'));
        $indicator = config('agent-debugger.show_frontend_indicator', true);
        $nPlusOne = config('agent-debugger.detect_n_plus_one', true);
        $drift = config('agent-debugger.track_config_drift', true);

        $this->newLine();
        $this->info('🛡️  Laravel Agent-Debugger Status Panel  🛡️');
        $this->newLine();

        $statusString = $enabled ? '🟢 ACTIVE (Profiling Enabled)' : '🔴 DISABLED';
        $this->line("Status:            {$statusString}");
        $this->line("Log Style:         <comment>{$style}</comment>");
        $this->line("Logs Directory:    <comment>{$logPath}</comment>");
        $this->line("Frontend Border:   " . ($indicator ? '🟢 ENABLED' : '🔴 DISABLED'));
        $this->line("N+1 Detector:      " . ($nPlusOne ? '🟢 ACTIVE' : '🔴 DISABLED'));
        $this->line("Config Tracker:    " . ($drift ? '🟢 ACTIVE' : '🔴 DISABLED'));

        $this->newLine();
        $this->info('📂 File Footprint Details:');

        if (is_dir($logPath)) {
            $files = glob($logPath . '/agent_debug*.log');
            if (!empty($files)) {
                $headers = ['Log File Name', 'File Size (KB)', 'Last Modified'];
                $rows = [];
                foreach ($files as $file) {
                    $size = round(filesize($file) / 1024, 2);
                    $modified = date('Y-m-d H:i:s', filemtime($file));
                    $rows[] = [basename($file), $size, $modified];
                }
                $this->table($headers, $rows);
            } else {
                $this->line('No active debug logs found inside target directory.');
            }
        } else {
            $this->error('Target logs folder directory does not exist.');
        }

        $this->newLine();
        return 0;
    }
}

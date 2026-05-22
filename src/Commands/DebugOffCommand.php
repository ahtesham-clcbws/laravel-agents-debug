<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Commands;

use Illuminate\Console\Command;

class DebugOffCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agent:debug-off';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Disable the Laravel Agent-Debugger live request profiling';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            $this->error('No .env file found at base directory.');
            return 1;
        }

        $envContent = file_get_contents($envPath);
        $key = 'AGENT_DEBUGGER_ENABLED';

        if (str_contains($envContent, $key)) {
            $envContent = preg_replace(
                "/^{$key}=(.*)/m",
                "{$key}=false",
                $envContent
            );
        } else {
            $envContent .= "\n{$key}=false\n";
        }

        file_put_contents($envPath, $envContent);

        $this->call('config:clear');

        $this->info('🛑 Laravel Agent-Debugger has been successfully DISABLED.');
        
        return 0;
    }
}

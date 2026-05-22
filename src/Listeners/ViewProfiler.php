<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Listeners;

use Illuminate\Support\Facades\View;
use LaravelAgentDebugger\DebugLoggerManager;

class ViewProfiler
{
    protected DebugLoggerManager $manager;

    public function __construct(DebugLoggerManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Subscribe to Laravel's view composing pipeline
     */
    public function subscribe(): void
    {
        // View::composer('*') hooks into every single blade template/component composition
        View::composer('*', function ($view) {
            $this->manager->addView($view->getName());
        });
    }
}

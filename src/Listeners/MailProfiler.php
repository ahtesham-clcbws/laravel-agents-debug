<?php

declare(strict_types=1);

namespace LaravelAgentDebugger\Listeners;

use Illuminate\Support\Facades\Event;
use LaravelAgentDebugger\DebugLoggerManager;

/**
 * Event listener to profile outgoing mail messages during request lifecycle.
 */
class MailProfiler
{
    public function __construct(
        protected readonly DebugLoggerManager $manager
    ) {}

    /**
     * Subscribe to outgoing mail sending events.
     *
     * @return void
     */
    public function subscribe(): void
    {
        Event::listen(\Illuminate\Mail\Events\MessageSending::class, function ($event): void {
            $message = $event->message;
            $to = [];
            if (method_exists($message, 'getTo')) {
                foreach ($message->getTo() as $address) {
                    $to[] = $address->getAddress();
                }
            }
            $from = [];
            if (method_exists($message, 'getFrom')) {
                foreach ($message->getFrom() as $address) {
                    $from[] = $address->getAddress();
                }
            }
            $body = method_exists($message, 'getHtmlBody') ? ($message->getHtmlBody() ?: $message->getTextBody()) : '';
            $subject = method_exists($message, 'getSubject') ? $message->getSubject() : '(No Subject)';

            $emailLink = null;
            if (!empty($body)) {
                $dir = storage_path('logs/agent-debugger/emails');
                if (!is_dir($dir)) {
                    @mkdir($dir, 0777, true);
                }
                $fileName = 'email_' . microtime(true) . '_' . uniqid() . '.html';
                $filePath = $dir . '/' . $fileName;
                @file_put_contents($filePath, $body);
                $emailLink = 'file://' . $filePath;
            }

            $this->manager->addEmail([
                'subject' => $subject,
                'to' => implode(', ', $to),
                'from' => implode(', ', $from),
                'body' => $body,
                'link' => $emailLink,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        });
    }
}

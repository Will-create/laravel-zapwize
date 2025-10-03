<?php

namespace Zapwize\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Zapwize\Laravel\Facades\Zapwize;
use Zapwize\Laravel\Models\ZapwizeMessage;

class RetryFailedMessages extends Command
{
    protected $signature = 'zapwize:retry-failed {--limit=10 : Number of messages to retry}';
    protected $description = 'Retry sending failed messages';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        
        $failedMessages = ZapwizeMessage::failed()
            ->where('retry_count', '<', 3)
            ->limit($limit)
            ->get();

        if ($failedMessages->isEmpty()) {
            $this->info('No failed messages to retry.');
            return 0;
        }

        $this->info("Retrying {$failedMessages->count()} failed messages...");

        $successCount = 0;
        $failureCount = 0;

        foreach ($failedMessages as $message) {
            try {
                $this->line("Retrying message ID: {$message->id}");
                
                switch ($message->type) {
                    case ZapwizeMessage::TYPE_TEXT:
                        $response = Zapwize::sendMessage(
                            $message->phone,
                            $message->content['text']
                        );
                        break;
                        
                    case ZapwizeMessage::TYPE_IMAGE:
                        $response = Zapwize::sendImage(
                            $message->phone,
                            $message->content
                        );
                        break;
                        
                    // Add other message types as needed
                    default:
                        throw new \Exception("Unsupported message type: {$message->type}");
                }

                $message->markAsSent($response['id'] ?? null);
                $successCount++;
                $this->info("  ✅ Success");
                
            } catch (\Exception $e) {
                $message->markAsFailed($e->getMessage());
                $failureCount++;
                $this->error("  ❌ Failed: " . $e->getMessage());
            }
        }

        $this->info("\n📊 Retry Results:");
        $this->table(
            ['Result', 'Count'],
            [
                ['Success', $successCount],
                ['Failed', $failureCount],
            ]
        );

        return 0;
    }
}

<?php

namespace Zapwize\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Zapwize\Laravel\Facades\Zapwize;
use Zapwize\Laravel\Models\ZapwizeMessage;
use Zapwize\Laravel\Exceptions\ZapwizeException;

class TestConnection extends Command
{
    protected $signature = 'zapwize:test-connection';
    protected $description = 'Test Zapwize API connection';

    public function handle(): int
    {
        $this->info('Testing Zapwize connection...');

        try {
            $serverInfo = Zapwize::getServerInfo();
            
            if ($serverInfo) {
                $this->info('✅ Connection successful!');
                $this->table(
                    ['Key', 'Value'],
                    [
                        ['Base URL', $serverInfo['baseurl'] ?? 'N/A'],
                        ['Phone', $serverInfo['phone'] ?? 'N/A'],
                        ['Status', 'Connected'],
                    ]
                );
            } else {
                $this->error('❌ Connection failed - No server info available');
                return 1;
            }
        } catch (ZapwizeException $e) {
            $this->error('❌ Connection failed: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}

class SendTestMessage extends Command
{
    protected $signature = 'zapwize:send-test {phone} {message}';
    protected $description = 'Send a test message via Zapwize';

    public function handle(): int
    {
        $phone = $this->argument('phone');
        $message = $this->argument('message');

        $this->info("Sending test message to {$phone}...");

        try {
            // Check if it's a WhatsApp number first
            if (!Zapwize::isWhatsAppNumber($phone)) {
                $this->warn('⚠️  Warning: This number may not be registered on WhatsApp');
            }

            $response = Zapwize::sendMessage($phone, $message);
            
            $this->info('✅ Message sent successfully!');
            $this->line('Response: ' . json_encode($response, JSON_PRETTY_PRINT));
        } catch (ZapwizeException $e) {
            $this->error('❌ Failed to send message: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}

class ClearCache extends Command
{
    protected $signature = 'zapwize:clear-cache';
    protected $description = 'Clear Zapwize cached data';

    public function handle(): int
    {
        $this->info('Clearing Zapwize cache...');

        try {
            Zapwize::clearCache();
            $this->info('✅ Cache cleared successfully!');
        } catch (\Exception $e) {
            $this->error('❌ Failed to clear cache: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}

class MessageStatus extends Command
{
    protected $signature = 'zapwize:status {--phone= : Filter by phone number} {--status= : Filter by status} {--limit=50 : Number of messages to show}';
    protected $description = 'Show message status and statistics';

    public function handle(): int
    {
        $query = ZapwizeMessage::query();

        if ($phone = $this->option('phone')) {
            $query->byPhone($phone);
        }

        if ($status = $this->option('status')) {
            $query->where('status', $status);
        }

        $limit = (int) $this->option('limit');
        $messages = $query->orderBy('created_at', 'desc')->limit($limit)->get();

        if ($messages->isEmpty()) {
            $this->info('No messages found.');
            return 0;
        }

        // Show statistics
        $this->info('📊 Message Statistics:');
        $stats = ZapwizeMessage::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $this->table(
            ['Status', 'Count'],
            collect($stats)->map(fn($count, $status) => [$status, $count])->toArray()
        );

        // Show recent messages
        $this->info("\n📱 Recent Messages:");
        $this->table(
            ['ID', 'Phone', 'Type', 'Status', 'Content', 'Created'],
            $messages->map(function ($message) {
                return [
                    $message->id,
                    $message->formatted_phone,
                    $message->type,
                    $message->status,
                    \Str::limit($message->content_text, 50),
                    $message->created_at->diffForHumans(),
                ];
            })->toArray()
        );

        return 0;
    }
}

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
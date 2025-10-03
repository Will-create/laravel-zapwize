<?php

namespace Zapwize\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Zapwize\Laravel\Models\ZapwizeMessage;

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

<?php

namespace Zapwize\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Zapwize\Laravel\Facades\Zapwize;
use Zapwize\Laravel\Exceptions\ZapwizeException;

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

<?php

namespace Zapwize\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Zapwize\Laravel\Facades\Zapwize;
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

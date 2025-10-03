<?php

namespace Zapwize\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Zapwize\Laravel\Facades\Zapwize;

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

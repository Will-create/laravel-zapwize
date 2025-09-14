<?php

namespace Zapwize\Laravel\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConnectionEstablished
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $serverInfo;

    public function __construct(array $serverInfo)
    {
        $this->serverInfo = $serverInfo;
    }
}

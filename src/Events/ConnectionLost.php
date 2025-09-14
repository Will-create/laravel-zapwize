<?php

namespace Zapwize\Laravel\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConnectionLost
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $reason;

    public function __construct(string $reason)
    {
        $this->reason = $reason;
    }
}

<?php

namespace Zapwize\Laravel\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $phone;
    public string $message;
    public array $response;
    public array $options;

    public function __construct(string $phone, string $message, array $response, array $options = [])
    {
        $this->phone = $phone;
        $this->message = $message;
        $this->response = $response;
        $this->options = $options;
    }
}

<?php

namespace Zapwize\Laravel\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $message;

    public function __construct(array $message)
    {
        $this->message = $message;
    }
}

class ConnectionEstablished
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $serverInfo;

    public function __construct(array $serverInfo)
    {
        $this->serverInfo = $serverInfo;
    }
}

class ConnectionLost
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $reason;

    public function __construct(string $reason)
    {
        $this->reason = $reason;
    }
}

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

class MediaSent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $phone;
    public array $media;
    public string $type;
    public array $response;
    public array $options;

    public function __construct(string $phone, array $media, string $type, array $response, array $options = [])
    {
        $this->phone = $phone;
        $this->media = $media;
        $this->type = $type;
        $this->response = $response;
        $this->options = $options;
    }
}
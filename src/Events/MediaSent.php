<?php

namespace Zapwize\Laravel\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

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

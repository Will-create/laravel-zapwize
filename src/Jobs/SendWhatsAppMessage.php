<?php

namespace Zapwize\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Zapwize\Laravel\Facades\Zapwize;
use Zapwize\Laravel\Exceptions\ZapwizeException;

class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $phone;
    protected string $message;
    protected array $options;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(string $phone, string $message, array $options = [])
    {
        $this->phone = $phone;
        $this->message = $message;
        $this->options = $options;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $result = Zapwize::sendMessage($this->phone, $this->message, $this->options);
            
            Log::info('WhatsApp message sent successfully via queue', [
                'phone' => $this->phone,
                'message_length' => strlen($this->message),
                'result' => $result,
            ]);
        } catch (ZapwizeException $e) {
            Log::error('Failed to send WhatsApp message via queue', [
                'phone' => $this->phone,
                'message' => $this->message,
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('WhatsApp message job failed permanently', [
            'phone' => $this->phone,
            'message' => $this->message,
            'exception' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}
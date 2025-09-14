<?php

namespace Zapwize\Laravel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Zapwize\Laravel\Events\MessageReceived;
use Zapwize\Laravel\Models\ZapwizeMessage;

class WebhookController extends Controller
{
    /**
     * Handle incoming webhook from Zapwize
     */
    public function handle(Request $request): Response
    {
        // Verify webhook signature if secret is configured
        if (!$this->verifySignature($request)) {
            Log::warning('Zapwize webhook signature verification failed');
            return response('Unauthorized', 401);
        }

        try {
            $payload = $request->all();
            
            Log::info('Zapwize webhook received', ['payload' => $payload]);

            // Process different types of webhook events
            $this->processWebhookEvent($payload);

            return response('OK', 200);
        } catch (\Exception $e) {
            Log::error('Zapwize webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response('Internal Server Error', 500);
        }
    }

    /**
     * Verify webhook signature
     */
    protected function verifySignature(Request $request): bool
    {
        $secret = config('zapwize.webhook.secret');
        
        if (!$secret) {
            return true; // No signature verification if no secret is set
        }

        $signature = $request->header('X-Zapwize-Signature');
        $payload = $request->getContent();
        
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Process webhook event
     */
    protected function processWebhookEvent(array $payload): void
    {
        $eventType = $payload['type'] ?? 'unknown';

        switch ($eventType) {
            case 'message':
                $this->handleIncomingMessage($payload);
                break;
                
            case 'status':
                $this->handleMessageStatus($payload);
                break;
                
            case 'ready':
                $this->handleConnectionReady($payload);
                break;
                
            default:
                Log::info('Unknown webhook event type', ['type' => $eventType]);
                break;
        }
    }

    /**
     * Handle incoming message
     */
    protected function handleIncomingMessage(array $payload): void
    {
        $messageData = $payload['data'] ?? [];
        
        // Store message in database if needed
        if (config('zapwize.store_incoming_messages', false)) {
            ZapwizeMessage::create([
                'phone' => $messageData['from'] ?? '',
                'chat_id' => $messageData['chatId'] ?? '',
                'message_id' => $messageData['id'] ?? '',
                'type' => $this->detectMessageType($messageData),
                'content' => $messageData,
                'status' => ZapwizeMessage::STATUS_DELIVERED,
                'metadata' => [
                    'direction' => 'incoming',
                    'timestamp' => $messageData['timestamp'] ?? now(),
                ],
            ]);
        }

        // Dispatch event for message received
        event(new MessageReceived($messageData));
    }

    /**
     * Handle message status update
     */
    protected function handleMessageStatus(array $payload): void
    {
        $statusData = $payload['data'] ?? [];
        $messageId = $statusData['id'] ?? '';
        $status = $statusData['status'] ?? '';

        if (!$messageId) {
            return;
        }

        $message = ZapwizeMessage::where('message_id', $messageId)->first();
        
        if (!$message) {
            return;
        }

        switch ($status) {
            case 'sent':
                $message->markAsSent();
                break;
                
            case 'delivered':
                $message->markAsDelivered();
                break;
                
            case 'read':
                $message->markAsRead();
                break;
                
            case 'failed':
                $message->markAsFailed($statusData['error'] ?? 'Unknown error');
                break;
        }

        Log::info('Message status updated', [
            'message_id' => $messageId,
            'status' => $status,
        ]);
    }

    /**
     * Handle connection ready event
     */
    protected function handleConnectionReady(array $payload): void
    {
        Log::info('Zapwize connection ready', $payload);
    }

    /**
     * Detect message type from message data
     */
    protected function detectMessageType(array $messageData): string
    {
        if (isset($messageData['imageMessage'])) {
            return ZapwizeMessage::TYPE_IMAGE;
        }

        if (isset($messageData['videoMessage'])) {
            return ZapwizeMessage::TYPE_VIDEO;
        }

        if (isset($messageData['audioMessage'])) {
            return ZapwizeMessage::TYPE_AUDIO;
        }

        if (isset($messageData['documentMessage'])) {
            return ZapwizeMessage::TYPE_DOCUMENT;
        }

        if (isset($messageData['locationMessage'])) {
            return ZapwizeMessage::TYPE_LOCATION;
        }

        if (isset($messageData['contactMessage'])) {
            return ZapwizeMessage::TYPE_CONTACT;
        }

        if (isset($messageData['pollCreationMessage'])) {
            return ZapwizeMessage::TYPE_POLL;
        }

        return ZapwizeMessage::TYPE_TEXT;
    }
}
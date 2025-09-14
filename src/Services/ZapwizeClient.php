<?php

namespace Zapwize\Laravel\Services;

use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Zapwize\Laravel\Exceptions\ZapwizeException;
use Zapwize\Laravel\Jobs\SendWhatsAppMessage;
use Zapwize\Laravel\Models\ZapwizeMessage;
use Zapwize\Laravel\Events\MessageReceived;
use Zapwize\Laravel\Events\ConnectionEstablished;
use Zapwize\Laravel\Events\ConnectionLost;

class ZapwizeClient
{
    protected HttpClient $http;
    protected array $config;
    protected ?array $serverInfo = null;
    protected string $cacheKey = 'zapwize_server_info';

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'api_key' => null,
            'base_url' => 'https://api.zapwize.com/v1',
            'timeout' => 30,
            'log_channel' => 'default',
            'log_level' => 'info',
        ], $config);

        if (empty($this->config['api_key'])) {
            throw new ZapwizeException('API key is required');
        }

        $this->http = app(HttpClient::class);
        $this->initialize();
    }

    /**
     * Initialize the client and get server information
     */
    protected function initialize(): void
    {
        try {
            // Try to get server info from cache first
            $this->serverInfo = Cache::get($this->cacheKey);

            if (!$this->serverInfo) {
                $response = $this->http->timeout($this->config['timeout'])
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $this->config['api_key'],
                        'Content-Type' => 'application/json',
                    ])
                    ->get($this->config['base_url']);

                if (!$response->successful()) {
                    throw new ZapwizeException('Failed to initialize: ' . $response->body());
                }

                $data = $response->json();
                if (!$data['success'] ?? false) {
                    throw new ZapwizeException('Invalid API response');
                }

                $this->serverInfo = $data['value'];
                
                if (!$this->validateServerInfo()) {
                    throw new ZapwizeException('Invalid server configuration');
                }

                // Cache server info for 1 hour
                Cache::put($this->cacheKey, $this->serverInfo, 3600);
            }

            event(new ConnectionEstablished($this->serverInfo));
            
        } catch (\Exception $e) {
            $this->logError('Initialization failed', $e);
            throw new ZapwizeException('Client initialization failed: ' . $e->getMessage());
        }
    }

    /**
     * Validate server information
     */
    protected function validateServerInfo(): bool
    {
        $required = ['baseurl', 'url', 'token', 'msgapi', 'mediaapi'];
        
        foreach ($required as $field) {
            if (empty($this->serverInfo[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Send a text message
     */
    public function sendMessage(string $phone, string $message, array $options = []): array
    {
        $this->ensureConnection();

        $payload = [
            'chatid' => $this->formatChatId($phone),
            'content' => array_filter([
                'text' => $message,
                'mentions' => $options['mentions'] ?? null,
                'quoted' => $options['quoted'] ?? null,
            ]),
        ];

        return $this->makeRequest($this->getMessageUrl(), $payload);
    }

    /**
     * Send an image
     */
    public function sendImage(string $phone, array $media, array $options = []): array
    {
        return $this->sendMediaContent($phone, $media, 'image', $options);
    }

    /**
     * Send a video
     */
    public function sendVideo(string $phone, array $media, array $options = []): array
    {
        return $this->sendMediaContent($phone, $media, 'video', $options);
    }

    /**
     * Send an audio file
     */
    public function sendAudio(string $phone, array $media, array $options = []): array
    {
        return $this->sendMediaContent($phone, $media, 'audio', $options);
    }

    /**
     * Send a document
     */
    public function sendDocument(string $phone, array $media, array $options = []): array
    {
        return $this->sendMediaContent($phone, $media, 'document', $options);
    }

    /**
     * Send media content
     */
    protected function sendMediaContent(string $phone, array $media, string $mediaCategory, array $options = []): array
    {
        $this->ensureConnection();

        if (empty($media)) {
            throw new ZapwizeException('Media object is required');
        }

        $content = array_merge([
            'mediaCategory' => $mediaCategory,
            'type' => isset($media['url']) ? 'url' : 'base64',
        ], $media);

        // Add optional fields
        if (isset($options['caption'])) $content['caption'] = $options['caption'];
        if (isset($options['viewOnce'])) $content['viewOnce'] = $options['viewOnce'];
        if (isset($options['gif']) && $mediaCategory === 'video') $content['gif'] = $options['gif'];
        if (isset($options['ptv']) && $mediaCategory === 'video') $content['ptv'] = $options['ptv'];
        if (isset($options['quoted'])) $content['quoted'] = $options['quoted'];

        $payload = [
            'chatid' => $this->formatChatId($phone),
            'content' => $content,
        ];

        return $this->makeRequest($this->getMediaUrl(), $payload);
    }

    /**
     * Send location
     */
    public function sendLocation(string $phone, float $lat, float $lng, array $options = []): array
    {
        $this->ensureConnection();

        $payload = [
            'chatid' => $this->formatChatId($phone),
            'content' => array_filter([
                'lat' => $lat,
                'lng' => $lng,
                'quoted' => $options['quoted'] ?? null,
            ]),
        ];

        return $this->makeRequest($this->getMessageUrl(), $payload);
    }

    /**
     * Send contact
     */
    public function sendContact(string $phone, array $contactData, array $options = []): array
    {
        $this->ensureConnection();

        if (empty($contactData['name']) || empty($contactData['phone'])) {
            throw new ZapwizeException('Contact name and phone are required');
        }

        $payload = [
            'chatid' => $this->formatChatId($phone),
            'content' => array_filter([
                'name' => $contactData['name'],
                'phone' => $contactData['phone'],
                'org' => $contactData['org'] ?? '',
                'quoted' => $options['quoted'] ?? null,
            ]),
        ];

        return $this->makeRequest($this->getMessageUrl(), $payload);
    }

    /**
     * Send reaction
     */
    public function sendReaction(string $chatId, string $reaction, string $messageKey): array
    {
        $this->ensureConnection();

        if (empty($messageKey)) {
            throw new ZapwizeException('Message key is required for reactions');
        }

        $payload = [
            'chatid' => $this->formatChatId($chatId),
            'content' => [
                'reaction' => $reaction,
                'key' => $messageKey,
            ],
        ];

        return $this->makeRequest($this->getMessageUrl(), $payload);
    }

    /**
     * Send poll
     */
    public function sendPoll(string $phone, array $pollData, array $options = []): array
    {
        $this->ensureConnection();

        if (empty($pollData['name']) || empty($pollData['options']) || count($pollData['options']) < 2) {
            throw new ZapwizeException('Poll name and at least 2 options are required');
        }

        $payload = [
            'chatid' => $this->formatChatId($phone),
            'content' => array_filter([
                'name' => $pollData['name'],
                'options' => array_map('strval', $pollData['options']),
                'selectableCount' => max(1, min($pollData['selectableCount'] ?? 1, count($pollData['options']))),
                'toAnnouncementGroup' => $pollData['toAnnouncementGroup'] ?? false,
                'quoted' => $options['quoted'] ?? null,
            ]),
        ];

        return $this->makeRequest($this->getMessageUrl(), $payload);
    }

    /**
     * Forward message
     */
    public function forwardMessage(string $phone, array $message, array $options = []): array
    {
        $this->ensureConnection();

        if (empty($message)) {
            throw new ZapwizeException('Message object is required for forwarding');
        }

        $payload = [
            'chatid' => $this->formatChatId($phone),
            'content' => array_filter([
                'content' => $message,
                'quoted' => $options['quoted'] ?? null,
            ]),
        ];

        return $this->makeRequest($this->getMessageUrl(), $payload);
    }

    /**
     * Pin message
     */
    public function pinMessage(string $chatId, string $messageKey, array $options = []): array
    {
        $this->ensureConnection();

        if (empty($messageKey)) {
            throw new ZapwizeException('Message key is required for pinning');
        }

        $payload = [
            'chatid' => $this->formatChatId($chatId),
            'content' => [
                'key' => $messageKey,
                'unpin' => $options['unpin'] ?? false,
                'duration' => max(0, $options['duration'] ?? 86400),
            ],
        ];

        return $this->makeRequest($this->getMessageUrl(), $payload);
    }

    /**
     * Check if a number is a WhatsApp number
     */
    public function isWhatsAppNumber(string $phone): bool
    {
        try {
            $cleanPhone = $this->formatChatId($phone);
            $response = $this->http->timeout(10)
                ->get('https://zapwize.com/api/iswhatsapp', [
                    'phone' => $cleanPhone,
                ]);

            $data = $response->json();
            return $data['success'] && $data['value'];
        } catch (\Exception $e) {
            $this->logError('Failed to check WhatsApp number', $e);
            return false;
        }
    }

    /**
     * Send message asynchronously using Laravel Queue
     */
    public function sendMessageAsync(string $phone, string $message, array $options = []): void
    {
        Queue::connection($this->config['queue']['connection'] ?? 'default')
            ->pushOn(
                $this->config['queue']['queue'] ?? 'zapwize',
                new SendWhatsAppMessage($phone, $message, $options)
            );
    }

    /**
     * Get server information
     */
    public function getServerInfo(): ?array
    {
        return $this->serverInfo;
    }

    /**
     * Clear cached server information
     */
    public function clearCache(): void
    {
        Cache::forget($this->cacheKey);
        $this->serverInfo = null;
    }

    /**
     * Make HTTP request
     */
    protected function makeRequest(string $url, array $payload): array
    {
        try {
            $headers = [
                'Content-Type' => 'application/json',
                'x-phone' => str_replace(':', '_', $this->serverInfo['phone']),
                'x-token' => $this->serverInfo['token'],
                'x-apikey' => $this->config['api_key'],
            ];

            $response = $this->http->timeout($this->config['timeout'])
                ->withHeaders($headers)
                ->post($url, $payload);

            if (!$response->successful()) {
                $errorData = $response->json();
                $errorMessage = $errorData[0]['error'] ?? $response->body();
                throw new ZapwizeException("Request failed: {$errorMessage}");
            }

            $data = $response->json();
            
            // Log successful message
            $this->logInfo('Message sent successfully', [
                'chatid' => $payload['chatid'],
                'response' => $data,
            ]);

            return $data;
        } catch (\Exception $e) {
            $this->logError('Request failed', $e, ['payload' => $payload]);
            throw new ZapwizeException("Failed to make request: {$e->getMessage()}");
        }
    }

    /**
     * Ensure connection is established
     */
    protected function ensureConnection(): void
    {
        if (!$this->serverInfo) {
            throw new ZapwizeException('Client not initialized');
        }
    }

    /**
     * Format chat ID
     */
    protected function formatChatId(string $phone): string
    {
        return preg_replace('/\D/', '', $phone);
    }

    /**
     * Get message URL
     */
    protected function getMessageUrl(): string
    {
        return rtrim($this->serverInfo['baseurl'], '/') . '/' . $this->serverInfo['msgapi'];
    }

    /**
     * Get media URL
     */
    protected function getMediaUrl(): string
    {
        return rtrim($this->serverInfo['baseurl'], '/') . '/' . $this->serverInfo['mediaapi'];
    }

    /**
     * Log error
     */
    protected function logError(string $message, \Exception $exception, array $context = []): void
    {
        Log::channel($this->config['log_channel'])
            ->error($message, array_merge($context, [
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]));
    }

    /**
     * Log info
     */
    protected function logInfo(string $message, array $context = []): void
    {
        Log::channel($this->config['log_channel'])
            ->info($message, $context);
    }
}
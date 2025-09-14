<?php

namespace Zapwize\Laravel\Services;

use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Zapwize\Laravel\Exceptions\ZapwizeException;
use Zapwize\Laravel\Events\MessageReceived;
use Zapwize\Laravel\Events\ConnectionEstablished;
use Zapwize\Laravel\Events\ConnectionLost;
use Ratchet\Client\connect;
use Ratchet\Client\WebSocket;
use React\EventLoop\Factory as LoopFactory;

class ZapwizeClient
{
    protected HttpClient $http;
    protected array $config;
    protected ?array $serverInfo = null;
    protected ?WebSocket $socket = null;
    protected bool $connected = false;
    protected int $reconnectAttempts = 0;
    protected $loop;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'api_key' => null,
            'base_url' => 'https://api.zapwize.com/v1',
            'timeout' => 30,
            'max_reconnect_attempts' => 10,
            'reconnect_delay' => 5000,
            'log_channel' => 'default',
            'log_level' => 'info',
        ], $config);

        if (empty($this->config['api_key'])) {
            throw new ZapwizeException('API key is required');
        }

        $this->http = app(HttpClient::class);
        $this->loop = LoopFactory::create();
        $this->initialize();
    }

    protected function initialize(): void
    {
        try {
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
            if (!($data['success'] ?? false)) {
                throw new ZapwizeException('Invalid API response');
            }

            $this->serverInfo = $data['value'];

            if (!$this->validateServerInfo()) {
                throw new ZapwizeException('Invalid server configuration');
            }

            $this->connectWebSocket();
        } catch (\Exception $e) {
            $this->logError('Initialization failed', $e);
            throw new ZapwizeException('Client initialization failed: ' . $e->getMessage());
        }
    }

    protected function connectWebSocket(): void
    {
        if (!$this->serverInfo) {
            return;
        }

        $wsUrl = sprintf(
            '%s?apikey=%s&token=%s&phone=%s',
            $this->serverInfo['url'],
            $this->config['api_key'],
            $this->serverInfo['token'],
            $this->serverInfo['phone']
        );

        ($this->loop)(function () use ($wsUrl) {
            connect($wsUrl)->then(function (WebSocket $conn) {
                $this->connected = true;
                $this->reconnectAttempts = 0;
                $this->socket = $conn;
                Event::dispatch(new ConnectionEstablished($this->serverInfo));

                $conn->on('message', function ($msg) {
                    $message = json_decode($msg, true);
                    if (isset($message['type']) && $message['type'] === 'ready') {
                        Event::dispatch(new ConnectionEstablished($message));
                    } else {
                        Event::dispatch(new MessageReceived($message));
                    }
                });

                $conn->on('close', function ($code = null, $reason = null) {
                    $this->connected = false;
                    $this->socket = null;
                    Event::dispatch(new ConnectionLost());
                    $this->handleReconnect();
                });
            }, function (\Exception $e) {
                $this->logError('WebSocket connection failed', $e);
                $this->handleReconnect();
            });
        });
    }

    protected function handleReconnect(): void
    {
        if ($this->reconnectAttempts >= $this->config['max_reconnect_attempts']) {
            $this->logError('Max reconnect attempts reached', new \Exception('Max reconnect attempts reached'));
            return;
        }

        $this->reconnectAttempts++;
        $delay = $this->config['reconnect_delay'] * pow(2, $this->reconnectAttempts - 1);

        $this->loop->addTimer(min($delay, 60000) / 1000, function () {
            if (!$this->connected) {
                $this->initialize();
            }
        });
    }

    public function disconnect(): void
    {
        if ($this->socket) {
            $this->socket->close();
        }
        $this->connected = false;
        $this->reconnectAttempts = $this->config['max_reconnect_attempts'];
    }

    public function isConnected(): bool
    {
        return $this->connected && $this->socket;
    }

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

    public function sendImage(string $phone, array $media, array $options = []): array
    {
        return $this->sendMediaContent($phone, $media, 'image', $options);
    }

    public function sendVideo(string $phone, array $media, array $options = []): array
    {
        return $this->sendMediaContent($phone, $media, 'video', $options);
    }

    public function sendAudio(string $phone, array $media, array $options = []): array
    {
        return $this->sendMediaContent($phone, $media, 'audio', $options);
    }

    public function sendDocument(string $phone, array $media, array $options = []): array
    {
        return $this->sendMediaContent($phone, $media, 'document', $options);
    }

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

    public function getServerInfo(): ?array
    {
        return $this->serverInfo;
    }

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

    protected function ensureConnection(): void
    {
        if (!$this->serverInfo || !$this->connected) {
            throw new ZapwizeException('Client not initialized or disconnected');
        }
    }

    protected function formatChatId(string $phone): string
    {
        return preg_replace('/\D/', '', $phone);
    }

    protected function getMessageUrl(): string
    {
        return rtrim($this->serverInfo['baseurl'], '/') . '/' . $this->serverInfo['msgapi'];
    }

    protected function getMediaUrl(): string
    {
        return rtrim($this->serverInfo['baseurl'], '/') . '/' . $this->serverInfo['mediaapi'];
    }

    protected function logError(string $message, \Exception $exception, array $context = []): void
    {
        Log::channel($this->config['log_channel'])
            ->error($message, array_merge($context, [
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]));
    }

    protected function logInfo(string $message, array $context = []): void
    {
        Log::channel($this->config['log_channel'])
            ->info($message, $context);
    }

    public function getLoop()
    {
        return $this->loop;
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}

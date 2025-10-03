<?php

namespace Zapwize\Laravel\Tests\Unit;

use Zapwize\Laravel\Tests\TestCase;
use Zapwize\Laravel\Services\ZapwizeClient;
use Zapwize\Laravel\Exceptions\ZapwizeException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Event;
use Zapwize\Laravel\Events\ConnectionEstablished;

class ZapwizeClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $webSocket = \Mockery::mock(\Ratchet\Client\WebSocket::class);
        $webSocket->shouldReceive('on')->withAnyArgs()->andReturnNull();
        $webSocket->shouldReceive('close')->withAnyArgs()->andReturnNull();

        $connector = \Mockery::mock('alias:Ratchet\Client\connect');
        $connector->shouldReceive('__invoke')->withAnyArgs()->andReturn(\React\Promise\resolve($webSocket));
    }

    protected function getConnectedClient(): ZapwizeClient
    {
        $client = $this->app->make(ZapwizeClient::class);

        $webSocket = \Mockery::mock(\Ratchet\Client\WebSocket::class);
        $webSocket->shouldReceive('close');

        $reflection = new \ReflectionClass($client);

        $connected = $reflection->getProperty('connected');
        $connected->setAccessible(true);
        $connected->setValue($client, true);

        $socket = $reflection->getProperty('socket');
        $socket->setAccessible(true);
        $socket->setValue($client, $webSocket);

        $serverInfo = $reflection->getProperty('serverInfo');
        $serverInfo->setAccessible(true);
        $serverInfo->setValue($client, [
            'baseurl' => 'https://fake-server.zapwize.com',
            'url' => 'https://fake-server.zapwize.com',
            'token' => 'fake-token',
            'msgapi' => 'api/message',
            'mediaapi' => 'api/media',
            'phone' => '123456789'
        ]);

        return $client;
    }

    /** @test */
    public function it_throws_exception_when_sending_image_with_empty_media()
    {
        $this->expectException(ZapwizeException::class);
        $this->expectExceptionMessage('Media object is required');

        $client = $this->getConnectedClient();
        $client->sendImage('1234567890', []);
    }

    /** @test */
    public function it_throws_exception_when_sending_contact_with_empty_name()
    {
        $this->expectException(ZapwizeException::class);
        $this->expectExceptionMessage('Contact name and phone are required');

        $client = $this->getConnectedClient();
        $client->sendContact('1234567890', ['phone' => '1234567890']);
    }

    /** @test */
    public function it_throws_exception_when_sending_contact_with_empty_phone()
    {
        $this->expectException(ZapwizeException::class);
        $this->expectExceptionMessage('Contact name and phone are required');

        $client = $this->getConnectedClient();
        $client->sendContact('1234567890', ['name' => 'Test']);
    }

    /** @test */
    public function it_throws_exception_when_sending_reaction_with_empty_message_key()
    {
        $this->expectException(ZapwizeException::class);
        $this->expectExceptionMessage('Message key is required for reactions');

        $client = $this->getConnectedClient();
        $client->sendReaction('1234567890', '👍', '');
    }

    /** @test */
    public function it_throws_exception_when_sending_poll_with_empty_name()
    {
        $this->expectException(ZapwizeException::class);
        $this->expectExceptionMessage('Poll name and at least 2 options are required');

        $client = $this->getConnectedClient();
        $client->sendPoll('1234567890', ['options' => ['A', 'B']]);
    }

    /** @test */
    public function it_throws_exception_when_sending_poll_with_less_than_two_options()
    {
        $this->expectException(ZapwizeException::class);
        $this->expectExceptionMessage('Poll name and at least 2 options are required');

        $client = $this->getConnectedClient();
        $client->sendPoll('1234567890', ['name' => 'Question?', 'options' => ['A']]);
    }

    /** @test */
    public function it_throws_exception_when_forwarding_empty_message()
    {
        $this->expectException(ZapwizeException::class);
        $this->expectExceptionMessage('Message object is required for forwarding');

        $client = $this->getConnectedClient();
        $client->forwardMessage('1234567890', []);
    }

    /** @test */
    public function it_throws_exception_when_pinning_message_with_empty_message_key()
    {
        $this->expectException(ZapwizeException::class);
        $this->expectExceptionMessage('Message key is required for pinning');

        $client = $this->getConnectedClient();
        $client->pinMessage('1234567890', '');
    }

    /** @test */
    public function it_can_disconnect()
    {
        $client = $this->getConnectedClient();
        $this->assertTrue($client->isConnected());

        $client->disconnect();
        $this->assertFalse($client->isConnected());
    }

    /** @test */
    public function it_fires_connection_established_event_on_successful_connection()
    {
        Event::fake();

        $client = $this->app->make(ZapwizeClient::class);
        $client->getLoop()->run();

        Event::assertDispatched(ConnectionEstablished::class);
    }
}

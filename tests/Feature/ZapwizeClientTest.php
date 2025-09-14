<?php

namespace Zapwize\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Zapwize\Laravel\Facades\Zapwize;
use Zapwize\Laravel\Tests\TestCase;
use Zapwize\Laravel\Exceptions\ZapwizeException;

class ZapwizeClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mock the successful initialization request
        Http::fake([
            'https://api.zapwize.com/v1' => Http::response([
                'success' => true,
                'value' => [
                    'baseurl' => 'https://fake-server.zapwize.com',
                    'url' => 'https://fake-server.zapwize.com',
                    'token' => 'fake-token',
                    'msgapi' => 'api/message',
                    'mediaapi' => 'api/media',
                    'phone' => '123456789'
                ]
            ]),
            'https://fake-server.zapwize.com/*' => Http::response(['success' => true]),
            'https://zapwize.com/api/iswhatsapp*' => Http::response(['success' => true, 'value' => true]),
        ]);
    }

    /** @test */
    public function it_initializes_successfully_with_valid_api_key()
    {
        $serverInfo = Zapwize::getServerInfo();
        $this->assertNotNull($serverInfo);
        $this->assertEquals('fake-token', $serverInfo['token']);
    }

    /** @test */
    public function it_throws_exception_if_api_key_is_missing()
    {
        $this->app['config']->set('zapwize.api_key', null);
        $this->expectException(ZapwizeException::class);
        $this->expectExceptionMessage('API key is required');

        // This will trigger the exception in the service provider when resolving the client
        $this->app->make(\Zapwize\Laravel\Services\ZapwizeClient::class);
    }

    /** @test */
    public function it_sends_a_text_message_successfully()
    {
        $response = Zapwize::sendMessage('1234567890', 'Hello');

        $this->assertTrue($response['success']);
    }

    /** @test */
    public function it_sends_an_image_message_successfully()
    {
        $media = ['url' => 'http://example.com/image.jpg'];
        $response = Zapwize::sendImage('1234567890', $media);

        $this->assertTrue($response['success']);
    }
    
    /** @test */
    public function it_sends_a_video_message_successfully()
    {
        $media = ['url' => 'http://example.com/video.mp4'];
        $response = Zapwize::sendVideo('1234567890', $media);

        $this->assertTrue($response['success']);
    }

    /** @test */
    public function it_sends_an_audio_message_successfully()
    {
        $media = ['url' => 'http://example.com/audio.mp3'];
        $response = Zapwize::sendAudio('1234567890', $media);

        $this->assertTrue($response['success']);
    }

    /** @test */
    public function it_sends_a_document_message_successfully()
    {
        $media = ['url' => 'http://example.com/document.pdf'];
        $response = Zapwize::sendDocument('1234567890', $media);

        $this->assertTrue($response['success']);
    }

    /** @test */
    public function it_sends_a_location_message_successfully()
    {
        $response = Zapwize::sendLocation('1234567890', 12.34, 56.78);

        $this->assertTrue($response['success']);
    }

    /** @test */
    public function it_sends_a_contact_message_successfully()
    {
        $contact = ['name' => 'Test', 'phone' => '1234567890'];
        $response = Zapwize::sendContact('1234567890', $contact);

        $this->assertTrue($response['success']);
    }

    /** @test */
    public function it_sends_a_poll_message_successfully()
    {
        $poll = ['name' => 'Question?', 'options' => ['A', 'B']];
        $response = Zapwize::sendPoll('1234567890', $poll);

        $this->assertTrue($response['success']);
    }

    /** @test */
    public function it_checks_if_a_number_is_a_whatsapp_number()
    {
        $isWhatsApp = Zapwize::isWhatsAppNumber('1234567890');

        $this->assertTrue($isWhatsApp);
    }
}

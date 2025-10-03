<?php

namespace Zapwize\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Zapwize\Laravel\Facades\Zapwize;
use Zapwize\Laravel\Tests\TestCase;
use Zapwize\Laravel\Exceptions\ZapwizeException;
use Ratchet\Client\WebSocket;

class ZapwizeClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // This test uses real HTTP requests.
        // Make sure to set your ZAPWIZE_API_KEY in your .env file.
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
        $client = $this->app->make(\Zapwize\Laravel\Services\ZapwizeClient::class);
        $client->getLoop()->run();
        $response = Zapwize::sendMessage(env('ZAPWIZE_PHONE'), 'Hello');

        $this->assertTrue($response['success']);
    }

    /** @test */
    public function it_sends_an_image_message_successfully()
    {
        $client = $this->app->make(\Zapwize\Laravel\Services\ZapwizeClient::class);
        $client->getLoop()->run();
        $media = ['url' => 'http://example.com/image.jpg'];
        $response = Zapwize::sendImage(env('ZAPWIZE_PHONE'), $media);

        $this->assertTrue($response['success']);
    }
    
    /** @test */
    public function it_sends_a_video_message_successfully()
    {
        $client = $this->app->make(\Zapwize\Laravel\Services\ZapwizeClient::class);
        $client->getLoop()->run();
        $media = ['url' => 'http://example.com/video.mp4'];
        $response = Zapwize::sendVideo(env('ZAPWIZE_PHONE'), $media);

        $this->assertTrue($response['success']);
    }

    /** @test */
    public function it_sends_an_audio_message_successfully()
    {
        $client = $this->app->make(\Zapwize\Laravel\Services\ZapwizeClient::class);
        $client->getLoop()->run();
        $media = ['url' => 'http://example.com/audio.mp3'];
        $response = Zapwize::sendAudio(env('ZAPWIZE_PHONE'), $media);

        $this->assertTrue($response['success']);
    }

    /** @test */
    public function it_sends_a_document_message_successfully()
    {
        $client = $this->app->make(\Zapwize\Laravel\Services\ZapwizeClient::class);
        $client->getLoop()->run();
        $media = ['url' => 'http://example.com/document.pdf'];
        $response = Zapwize::sendDocument(env('ZAPWIZE_PHONE'), $media);

        $this->assertTrue($response['success']);
    }

    /** @test */
    public function it_sends_a_location_message_successfully()
    {
        $client = $this->app->make(\Zapwize\Laravel\Services\ZapwizeClient::class);
        $client->getLoop()->run();
        $response = Zapwize::sendLocation(env('ZAPWIZE_PHONE'), 12.34, 56.78);

        $this->assertTrue($response['success']);
    }

    /** @test */
    public function it_sends_a_contact_message_successfully()
    {
        $client = $this->app->make(\Zapwize\Laravel\Services\ZapwizeClient::class);
        $client->getLoop()->run();
        $contact = ['name' => 'Test', 'phone' => '1234567890'];
        $response = Zapwize::sendContact(env('ZAPWIZE_PHONE'), $contact);

        $this->assertTrue($response['success']);
    }

    /** @test */
    public function it_sends_a_poll_message_successfully()
    {
        $client = $this->app->make(\Zapwize\Laravel\Services\ZapwizeClient::class);
        $client->getLoop()->run();
        $poll = ['name' => 'Question?', 'options' => ['A', 'B']];
        $response = Zapwize::sendPoll(env('ZAPWIZE_PHONE'), $poll);

        $this->assertTrue($response['success']);
    }

    /** @test */
    public function it_checks_if_a_number_is_a_whatsapp_number()
    {
        $client = $this->app->make(\Zapwize\Laravel\Services\ZapwizeClient::class);
        $client->getLoop()->run();
        $isWhatsApp = Zapwize::isWhatsAppNumber(env('ZAPWIZE_PHONE'));

        $this->assertTrue($isWhatsApp);
    }
}
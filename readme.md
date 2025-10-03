# Laravel Zapwize

[![Latest Version on Packagist](https://img.shields.io/packagist/v/zapwize/laravel.svg?style=flat-square)](https://packagist.org/packages/zapwize/laravel)
[![Total Downloads](https://img.shields.io/packagist/dt/zapwize/laravel.svg?style=flat-square)](https://packagist.org/packages/zapwize/laravel)

This package provides a simple and expressive way to interact with the [Zapwize WhatsApp Web API](https://zapwize.com/) in your Laravel applications. Send text messages, images, videos, documents, and more with ease.

## Features

-   Fluent and easy-to-use API
-   Send various message types: text, image, video, audio, document, location, contact, poll
-   Asynchronous message sending using Laravel Queues
-   Webhook handling for incoming messages and status updates
-   Secure webhook signature verification
-   Automatic caching of server information for better performance
-   Configurable logging and connection settings

## Installation

You can install the package via composer:

```bash
composer require zapwize/laravel
```

## Configuration

1.  Publish the configuration file:

    ```bash
    php artisan vendor:publish --provider="Zapwize\Laravel\ZapwizeServiceProvider" --tag="config"
    ```

    This will create a `config/zapwize.php` file in your application.

2.  Add the following environment variables to your `.env` file:

    ```env
    ZAPWIZE_API_KEY=your_zapwize_api_key

    # Optional queue settings
    ZAPWIZE_QUEUE_CONNECTION=database
    ZAPWIZE_QUEUE=zapwize
    ```

## Requirements

- PHP 8.2 or higher
- Laravel 9, 10, 11 or 12

## Usage

You can use the `Zapwize` facade to access the client methods.

### Sending Messages

#### Text Message

```php
use Zapwize\Laravel\Facades\Zapwize;

$response = Zapwize::sendMessage('1234567890', 'Hello from Laravel!');
```

#### Image Message

```php
use Zapwize\Laravel\Facades\Zapwize;

$media = [
    'url' => 'https://example.com/image.jpg',
    // or 'base64' => 'data:image/jpeg;base64,...'
];

$options = [
    'caption' => 'This is a caption.',
];

$response = Zapwize::sendImage('1234567890', $media, $options);
```

#### Video Message

```php
use Zapwize\Laravel\Facades\Zapwize;

$media = [
    'url' => 'https://example.com/video.mp4',
];

$options = [
    'caption' => 'This is a video.',
    'gif' => false, // Set to true for GIF videos
];

$response = Zapwize::sendVideo('1234567890', $media, $options);
```

#### Audio Message

```php
use Zapwize\Laravel\Facades\Zapwize;

$media = [
    'url' => 'https://example.com/audio.mp3',
];

$response = Zapwize::sendAudio('1234567890', $media);
```

#### Document Message

```php
use Zapwize\Laravel\Facades\Zapwize;

$media = [
    'url' => 'https://example.com/document.pdf',
    'filename' => 'MyDocument.pdf',
];

$response = Zapwize::sendDocument('1234567890', $media);
```

#### Location Message

```php
use Zapwize\Laravel\Facades\Zapwize;

$response = Zapwize::sendLocation('1234567890', 37.7749, -122.4194);
```

#### Contact Message

```php
use Zapwize\Laravel\Facades\Zapwize;

$contact = [
    'name' => 'John Doe',
    'phone' => '0987654321',
];

$response = Zapwize::sendContact('1234567890', $contact);
```

#### Poll Message

```php
use Zapwize\Laravel\Facades\Zapwize;

$poll = [
    'name' => 'What is your favorite color?',
    'options' => ['Red', 'Green', 'Blue'],
    'selectableCount' => 1,
];

$response = Zapwize::sendPoll('1234567890', $poll);
```

### Asynchronous Sending

To send messages without blocking the main thread, you can use the `sendMessageAsync` method. Make sure your Laravel Queue is configured correctly.

```php
use Zapwize\Laravel\Facades\Zapwize;

Zapwize::sendMessageAsync('1234567890', 'This message is sent asynchronously.');
```

### Webhooks

To handle incoming messages and status updates, you need to set up a webhook.

1.  In your `config/zapwize.php`, set `webhook.enabled` to `true` and configure the `webhook.url` and `webhook.secret`.
2.  The package automatically registers the webhook route at `/zapwize/webhook`. You can customize this in the config file.
3.  Listen for the `Zapwize\Laravel\Events\MessageReceived` event to process incoming messages:

    In your `EventServiceProvider.php`:

    ```php
    protected $listen = [
        \Zapwize\Laravel\Events\MessageReceived::class => [
            \App\Listeners\ProcessIncomingWhatsAppMessage::class,
        ],
    ];
    ```

    Create the listener:

    ```php
    namespace App\Listeners;

    use Zapwize\Laravel\Events\MessageReceived;

    class ProcessIncomingWhatsAppMessage
    {
        public function handle(MessageReceived $event)
        {
            $messageData = $event->message;
            // Process the incoming message...
        }
    }
    ```

### Other Methods

#### Check if a number is a WhatsApp number

```php
use Zapwize\Laravel\Facades\Zapwize;

$isWhatsAppNumber = Zapwize::isWhatsAppNumber('1234567890');
```

#### Get Server Information

```php
use Zapwize\Laravel\Facades\Zapwize;

$serverInfo = Zapwize::getServerInfo();
```

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

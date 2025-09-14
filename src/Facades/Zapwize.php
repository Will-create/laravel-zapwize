<?php

namespace Zapwize\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array sendMessage(string $phone, string $message, array $options = [])
 * @method static array sendImage(string $phone, array $media, array $options = [])
 * @method static array sendVideo(string $phone, array $media, array $options = [])
 * @method static array sendAudio(string $phone, array $media, array $options = [])
 * @method static array sendDocument(string $phone, array $media, array $options = [])
 * @method static array sendLocation(string $phone, float $lat, float $lng, array $options = [])
 * @method static array sendContact(string $phone, array $contactData, array $options = [])
 * @method static array sendReaction(string $chatId, string $reaction, string $messageKey)
 * @method static array sendPoll(string $phone, array $pollData, array $options = [])
 * @method static array forwardMessage(string $phone, array $message, array $options = [])
 * @method static array pinMessage(string $chatId, string $messageKey, array $options = [])
 * @method static bool isWhatsAppNumber(string $phone)
 * @method static void sendMessageAsync(string $phone, string $message, array $options = [])
 * @method static array|null getServerInfo()
 * @method static void clearCache()
 *
 * @see \Zapwize\Laravel\Services\ZapwizeClient
 */
class Zapwize extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'zapwize';
    }
}
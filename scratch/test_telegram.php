<?php
require_once 'vendor/autoload.php';

use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramTransportFactory;
use Symfony\Component\Notifier\Chatter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpClient\HttpClient;

$dsn = 'telegram://8419432860:AAEaxp2Iy0q935tlwshZOMvDCBw8E8onh-o@default?channel=8412019255';

$factory = new TelegramTransportFactory(new EventDispatcher(), HttpClient::create());
$transport = $factory->create(new \Symfony\Component\Notifier\Transport\Dsn($dsn));

$chatter = new Chatter($transport);
$message = new ChatMessage("👋 Hello from StartHub! Your Telegram integration is working perfectly.");
$message->transport('telegram');

try {
    $chatter->send($message);
    echo "Message sent successfully to Telegram!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

<?php
require_once 'vendor/autoload.php';

// Load .env manually for this test
$lines = explode("\n", file_get_contents('.env'));
$config = [];
foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0 || !strpos($line, '=')) continue;
    list($key, $val) = explode('=', $line, 2);
    $config[trim($key)] = trim($val);
}

$apiKey = $config['VONAGE_KEY'] ?? '';
$apiSecret = $config['VONAGE_SECRET'] ?? '';
$from = $config['VONAGE_FROM'] ?? 'StartHub';
$to = '+21695420377'; // I'll use a sample or prompt the user, but for now I'll just check if the keys work

echo "Testing Vonage API Connection...\n";
echo "API Key: $apiKey\n";

$ch = curl_init('https://rest.nexmo.com/sms/json');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'api_key' => $apiKey,
    'api_secret' => $apiSecret,
    'to' => $to,
    'from' => $from,
    'text' => 'StartHub Test SMS: Your code is 123456'
]));

$response = curl_exec($ch);
curl_close($ch);

echo "Response from Vonage:\n";
echo $response . "\n";

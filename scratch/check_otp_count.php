<?php
require_once 'vendor/autoload.php';
try {
    $dsn = 'mysql:host=127.0.0.1;dbname=starthub;charset=utf8mb4';
    $pdo = new PDO($dsn, 'root', '');
    $since = date('Y-m-d H:i:s', strtotime('-1 hour'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM password_reset_tokens WHERE date_creation >= :since");
    $stmt->execute(['since' => $since]);
    echo "OTP Requests in the last hour: " . $stmt->fetchColumn() . "\n";
} catch (Exception $e) {
    echo $e->getMessage();
}

<?php
require_once 'vendor/autoload.php';
try {
    $dsn = 'mysql:host=127.0.0.1;dbname=starthub;charset=utf8mb4';
    $pdo = new PDO($dsn, 'root', '');
    $pdo->exec("DELETE FROM password_reset_tokens");
    echo "Tokens cleared successfully.\n";
} catch (Exception $e) {
    echo $e->getMessage();
}

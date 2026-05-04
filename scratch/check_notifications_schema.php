<?php
require_once 'vendor/autoload.php';
try {
    $dsn = 'mysql:host=127.0.0.1;dbname=starthub;charset=utf8mb4';
    $pdo = new PDO($dsn, 'root', '');
    $stmt = $pdo->query("DESCRIBE notifications");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($columns);
} catch (Exception $e) {
    echo $e->getMessage();
}

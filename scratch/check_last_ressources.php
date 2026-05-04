<?php
require_once 'vendor/autoload.php';
try {
    $dsn = 'mysql:host=127.0.0.1;dbname=starthub;charset=utf8mb4';
    $pdo = new PDO($dsn, 'root', '');
    $stmt = $pdo->query("SELECT id_ressource, nom, moderation_score, moderation_reason, is_banned, image_r FROM ressources ORDER BY id_ressource DESC LIMIT 5");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($rows);
} catch (Exception $e) {
    echo $e->getMessage();
}

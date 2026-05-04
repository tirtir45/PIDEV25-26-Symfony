<?php
require_once 'vendor/autoload.php';
// We just want to run a DESCRIBE query using PDO to see the columns
try {
    $dsn = 'mysql:host=127.0.0.1;dbname=starthub;charset=utf8mb4';
    $pdo = new PDO($dsn, 'root', '');
    $stmt = $pdo->query("DESCRIBE reclamations");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($columns);
} catch (Exception $e) {
    echo $e->getMessage();
}

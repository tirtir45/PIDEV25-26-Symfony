<?php
require_once 'vendor/autoload.php';
try {
    $dsn = 'mysql:host=127.0.0.1;dbname=starthub;charset=utf8mb4';
    $pdo = new PDO($dsn, 'root', '');
    
    echo "Truncating notifications table...\n";
    $pdo->exec("TRUNCATE TABLE notifications");
    
    echo "Setting primary key and auto_increment...\n";
    $pdo->exec("ALTER TABLE notifications MODIFY id INT(10) NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (id)");
    
    echo "Success!\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}

<?php
require 'vendor/autoload.php';

$pdo = new PDO('mysql:host=127.0.0.1;dbname=starthub;charset=utf8', 'root', '');
$hash = password_hash('Azerty123', PASSWORD_BCRYPT);
$stmt = $pdo->prepare('UPDATE utilisateurs SET mot_de_passe = ? WHERE email IN (?, ?, ?)');
$stmt->execute([$hash, 'adm@gmail.com', 'for@gmail.com', 'arijselmi580@gmail.com']);
echo "Updated " . $stmt->rowCount() . " accounts.\nHash: $hash\n";

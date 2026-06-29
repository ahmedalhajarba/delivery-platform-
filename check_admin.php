<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=newp2_db;charset=utf8mb4', 'root', '123456');
$stmt = $pdo->query("SELECT id, name, email FROM users WHERE email='admin@admin.com'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($row);

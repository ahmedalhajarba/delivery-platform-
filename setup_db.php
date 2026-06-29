<?php
$mysqli = new mysqli('127.0.0.1', 'root', '', '', 3306);

if ($mysqli->connect_error) {
    die("✗ Connect Error: " . $mysqli->connect_error);
}

// Drop existing database
if ($mysqli->query('DROP DATABASE IF EXISTS newp2_db')) {
    echo "✓ Dropped old database (if existed)\n";
} else {
    echo "✗ Error dropping database: " . $mysqli->error . "\n";
}

// Create fresh database
if ($mysqli->query('CREATE DATABASE newp2_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')) {
    echo "✓ Database newp2_db created successfully\n";
} else {
    die("✗ Error creating database: " . $mysqli->error . "\n");
}

// Select the database
$mysqli->select_db('newp2_db');

// Verify connection
$result = $mysqli->query('SELECT DATABASE()');
$row = $result->fetch_assoc();
echo "✓ Connected to: " . $row['DATABASE()'] . "\n";

$mysqli->close();
?>

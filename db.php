<?php
$host = "fdb1034.awardspace.net";
$dbname = "4752089_noteswap";
$username = "4752089_noteswap";
$password = "myname82";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET time_zone = '+05:00'");
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
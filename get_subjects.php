<?php
require_once 'db.php';
session_start();
if (!isset($_SESSION['user_id'])) { echo json_encode([]); exit(); }

$section  = $_GET['section']  ?? 'BSCS';
$semester = intval($_GET['semester'] ?? 1);

$stmt = $pdo->prepare("SELECT subject FROM curriculum WHERE section = ? AND semester = ? ORDER BY subject ASC");
$stmt->execute([$section, $semester]);
$subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);

header('Content-Type: application/json');
echo json_encode($subjects);
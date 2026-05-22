<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$receiver_id = intval($_POST['receiver_id']);
$message = trim($_POST['message'] ?? '');
$filename = null;
$file_type = null;

if (isset($_FILES['chat_file']) && $_FILES['chat_file']['error'] === 0) {
    $allowed = ['pdf', 'png', 'jpg', 'jpeg', 'docx'];
    $ext = strtolower(pathinfo($_FILES['chat_file']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, $allowed) && $_FILES['chat_file']['size'] <= 5 * 1024 * 1024) {
        $filename = uniqid() . "_" . basename($_FILES['chat_file']['name']);
        $file_type = in_array($ext, ['png', 'jpg', 'jpeg']) ? 'image' : 'file';
        move_uploaded_file($_FILES['chat_file']['tmp_name'], "uploads/" . $filename);
    }
}

if (!empty($message) || $filename) {
    $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message, filename, file_type) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $receiver_id, $message, $filename, $file_type]);
}

header("Location: messages.php?with=" . $receiver_id);
exit();
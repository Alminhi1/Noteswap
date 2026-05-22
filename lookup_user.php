<?php
require_once 'db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Please log in first.']);
    exit();
}

$user_id = intval($_SESSION['user_id']);
$lookup_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($lookup_id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Enter a valid student ID.']);
    exit();
}

if ($lookup_id === $user_id) {
    echo json_encode([
        'ok' => false,
        'message' => 'That is your own ID.',
        'status' => 'self'
    ]);
    exit();
}

$stmt = $pdo->prepare("SELECT id, name, avatar_seed FROM users WHERE id = ?");
$stmt->execute([$lookup_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['ok' => false, 'message' => 'No student found with that ID.']);
    exit();
}

$stmt = $pdo->prepare("
    SELECT sender_id, receiver_id, status
    FROM friend_requests
    WHERE (sender_id = ? AND receiver_id = ?)
    OR (sender_id = ? AND receiver_id = ?)
    LIMIT 1
");
$stmt->execute([$user_id, $lookup_id, $lookup_id, $user_id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

$status = 'available';
$status_text = 'Ready to send request';
if ($request) {
    if ($request['status'] === 'accepted') {
        $status = 'friends';
        $status_text = 'Already in your chats';
    } elseif ($request['status'] === 'pending' && intval($request['sender_id']) === $user_id) {
        $status = 'sent';
        $status_text = 'Request already sent';
    } elseif ($request['status'] === 'pending') {
        $status = 'incoming';
        $status_text = 'They already sent you a request';
    } else {
        $status = 'available';
        $status_text = 'Ready to send request again';
    }
}

$avatar_seed = $user['avatar_seed'] ?: 'default';

echo json_encode([
    'ok' => true,
    'id' => intval($user['id']),
    'name' => $user['name'],
    'avatar' => 'https://api.dicebear.com/9.x/fun-emoji/svg?seed=' . urlencode($avatar_seed),
    'status' => $status,
    'status_text' => $status_text,
    'can_send' => $status === 'available'
]);

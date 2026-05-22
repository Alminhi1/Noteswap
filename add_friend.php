<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (isset($_POST['receiver_id'])) {
    $receiver_id = intval($_POST['receiver_id']);
    if ($receiver_id === $user_id) {
        $_SESSION['friend_flash'] = [
            'type' => 'error',
            'title' => 'That is your own ID',
            'message' => 'Share your ID with classmates, but add someone else to start a chat.'
        ];
    } else {
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
        $stmt->execute([$receiver_id]);
        $receiver = $stmt->fetch();
        if ($receiver) {
            $stmt = $pdo->prepare("
                SELECT * FROM friend_requests
                WHERE (sender_id = ? AND receiver_id = ?)
                OR (sender_id = ? AND receiver_id = ?)
            ");
            $stmt->execute([$user_id, $receiver_id, $receiver_id, $user_id]);
            $existing = $stmt->fetch();
            if (!$existing) {
                $stmt = $pdo->prepare("INSERT INTO friend_requests (sender_id, receiver_id) VALUES (?, ?)");
                $stmt->execute([$user_id, $receiver_id]);
                $_SESSION['friend_flash'] = [
                    'type' => 'success',
                    'title' => 'Request sent',
                    'message' => 'Your friend request is now waiting for ' . $receiver['name'] . ' to accept.'
                ];
            } elseif ($existing['status'] === 'pending' && intval($existing['sender_id']) === $user_id) {
                $_SESSION['friend_flash'] = [
                    'type' => 'info',
                    'title' => 'Already sent',
                    'message' => 'Your request to ' . $receiver['name'] . ' is still pending.'
                ];
            } elseif ($existing['status'] === 'pending') {
                $_SESSION['friend_flash'] = [
                    'type' => 'info',
                    'title' => 'They already requested you',
                    'message' => $receiver['name'] . ' is waiting in your friend requests below.'
                ];
            } elseif ($existing['status'] === 'accepted') {
                $_SESSION['friend_flash'] = [
                    'type' => 'info',
                    'title' => 'Already friends',
                    'message' => 'You and ' . $receiver['name'] . ' can already chat.'
                ];
            } else {
                $stmt = $pdo->prepare("
                    UPDATE friend_requests
                    SET sender_id = ?, receiver_id = ?, status = 'pending'
                    WHERE id = ?
                ");
                $stmt->execute([$user_id, $receiver_id, $existing['id']]);
                $_SESSION['friend_flash'] = [
                    'type' => 'success',
                    'title' => 'Request sent again',
                    'message' => 'A fresh request is now waiting for ' . $receiver['name'] . '.'
                ];
            }
        } else {
            $_SESSION['friend_flash'] = [
                'type' => 'error',
                'title' => 'No student found',
                'message' => 'No NoteSwap account exists with ID #' . $receiver_id . '.'
            ];
        }
    }
}

if (isset($_POST['accept_request'])) {
    $req_id = intval($_POST['req_id']);
    $stmt = $pdo->prepare("UPDATE friend_requests SET status = 'accepted' WHERE id = ? AND receiver_id = ?");
    $stmt->execute([$req_id, $user_id]);
}

if (isset($_POST['reject_request'])) {
    $req_id = intval($_POST['req_id']);
    $stmt = $pdo->prepare("UPDATE friend_requests SET status = 'rejected' WHERE id = ? AND receiver_id = ?");
    $stmt->execute([$req_id, $user_id]);
}

header("Location: messages.php");
exit();

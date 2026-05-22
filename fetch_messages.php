<?php
date_default_timezone_set('Asia/Karachi');
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_GET['with'])) exit();

$user_id = $_SESSION['user_id'];
$chat_with = intval($_GET['with']);

// Mark as read
$stmt = $pdo->prepare("UPDATE chat_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?");
$stmt->execute([$chat_with, $user_id]);

// Fetch messages
$stmt = $pdo->prepare("
    SELECT chat_messages.*, users.name AS sender_name
    FROM chat_messages
    JOIN users ON chat_messages.sender_id = users.id
    WHERE (sender_id = ? AND receiver_id = ?)
    OR (sender_id = ? AND receiver_id = ?)
    ORDER BY chat_messages.created_at ASC
");
$stmt->execute([$user_id, $chat_with, $chat_with, $user_id]);
$messages = $stmt->fetchAll();

foreach ($messages as $msg):
?>
<div class="message-wrap <?= $msg['sender_id'] == $user_id ? 'sent' : 'received' ?>">
    <div class="message-bubble">
        <?php if ($msg['message']): ?>
            <p><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
        <?php endif; ?>
        <?php if ($msg['filename']): ?>
            <?php if ($msg['file_type'] === 'image'): ?>
                <img src="uploads/<?= htmlspecialchars($msg['filename']) ?>" class="chat-image" alt="Image">
            <?php else: ?>
                <a href="uploads/<?= htmlspecialchars($msg['filename']) ?>" class="file-link" download>📎 Download File</a>
            <?php endif; ?>
        <?php endif; ?>
        <span class="message-time"><?= date('h:i A', strtotime($msg['created_at'])) ?></span>
    </div>
</div>
<?php endforeach; ?>
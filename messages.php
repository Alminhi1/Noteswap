<?php
date_default_timezone_set('Asia/Karachi');
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$chat_with = isset($_GET['with']) ? intval($_GET['with']) : null;
$friend_flash = $_SESSION['friend_flash'] ?? null;
unset($_SESSION['friend_flash']);

// Fetch my avatar
$stmt_myav = $pdo->prepare("SELECT avatar_seed FROM users WHERE id = ?");
$stmt_myav->execute([$user_id]);
$my_avatar_seed = $stmt_myav->fetchColumn() ?? 'default';
$my_avatar_url  = "https://api.dicebear.com/9.x/fun-emoji/svg?seed=" . urlencode($my_avatar_seed);
$chat_friend = null;

if ($chat_with) {
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
    $stmt->execute([$chat_with]);
    $chat_friend = $stmt->fetch();

    $stmt = $pdo->prepare("UPDATE chat_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?");
    $stmt->execute([$chat_with, $user_id]);
}

$stmt = $pdo->prepare("
    SELECT users.id, users.name 
    FROM friend_requests
    JOIN users ON (
        CASE 
            WHEN friend_requests.sender_id = ? THEN friend_requests.receiver_id 
            ELSE friend_requests.sender_id 
        END = users.id
    )
    WHERE (friend_requests.sender_id = ? OR friend_requests.receiver_id = ?)
    AND friend_requests.status = 'accepted'
");
$stmt->execute([$user_id, $user_id, $user_id]);
$friends = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT friend_requests.*, users.name AS sender_name 
    FROM friend_requests 
    JOIN users ON friend_requests.sender_id = users.id
    WHERE friend_requests.receiver_id = ? AND friend_requests.status = 'pending'
");
$stmt->execute([$user_id]);
$pending_requests = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT friend_requests.*, users.name AS receiver_name, users.avatar_seed AS receiver_avatar_seed
    FROM friend_requests
    JOIN users ON friend_requests.receiver_id = users.id
    WHERE friend_requests.sender_id = ? AND friend_requests.status = 'pending'
    ORDER BY friend_requests.id DESC
");
$stmt->execute([$user_id]);
$sent_requests = $stmt->fetchAll();

$messages = [];
if ($chat_with) {
    // Mark messages as read FIRST
    $stmt = $pdo->prepare("UPDATE chat_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?");
    $stmt->execute([$chat_with, $user_id]);

    // Then fetch messages
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat — NoteSwap</title>
    <!-- ANTI-FLASH: runs before page renders -->
    <script>
        const t = localStorage.getItem('theme');
        if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    </script>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="dashboard-page">
<nav class="navbar">
    <a href="dashboard.php" class="nav-brand">
        <span class="brand-icon">
            <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
                <rect x="3" y="2" width="16" height="20" rx="3" stroke="#7c3aed" stroke-width="2" fill="none"/>
                <path d="M7 8h8M7 12h6" stroke="#7c3aed" stroke-width="2" stroke-linecap="round"/>
                <path d="M14 18c0-2.2 1.8-4 4-4s4 1.8 4 4-1.8 4-4 4-4-1.8-4-4z" stroke="#f59e0b" stroke-width="2" fill="none"/>
                <path d="M20.5 21.5l2.5 2.5" stroke="#f59e0b" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </span>
        NoteSwap
    </a>
    <div class="nav-links">
        
        <a href="upload.php" class="btn btn-primary">+ Upload Note</a>
        <a href="dashboard.php" class="btn btn-secondary">← Dashboard</a>
        <button class="btn btn-secondary" onclick="toggleDark()" id="theme-btn">🌙</button>
        <a href="profile.php" class="nav-avatar" title="My Profile"
   style="padding:0;overflow:hidden">
    <img src="<?= $my_avatar_url ?>" alt="avatar"
         style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block">
</a>
    </div>
</nav>
<div class="chat-layout">
    <div class="chat-sidebar">
        <div class="your-id-box" style="display:flex;align-items:center;gap:12px">
    <img src="<?= $my_avatar_url ?>" alt="avatar"
         style="width:42px;height:42px;border-radius:50%;border:2px solid rgba(255,255,255,0.3);flex-shrink:0">
    <div>
        <div style="font-size:11px;opacity:0.7">Your ID</div>
        <strong style="font-size:20px;display:block">#<?= $user_id ?></strong>
        <span class="id-hint">Share this so friends can add you</span>
    </div>
</div>
        <div class="add-friend-box">
            <?php if ($friend_flash): ?>
                <div class="friend-flash friend-flash-<?= htmlspecialchars($friend_flash['type']) ?>">
                    <strong><?= htmlspecialchars($friend_flash['title']) ?></strong>
                    <span><?= htmlspecialchars($friend_flash['message']) ?></span>
                </div>
            <?php endif; ?>
            <form method="POST" action="add_friend.php" id="add-friend-form">
                <div class="form-group">
                    <label>Add by ID</label>
                    <input type="number" name="receiver_id" id="friend-id-input" placeholder="Enter friend's ID" required autocomplete="off">
                </div>
                <div class="friend-preview" id="friend-preview" aria-live="polite">
                    <div class="friend-preview-empty">Type an ID to preview the student.</div>
                </div>
                <button type="submit" class="btn btn-primary">Send Request</button>
            </form>
        </div>
        <?php if (!empty($pending_requests)): ?>
        <div class="requests-box">
            <h4>Friend Requests</h4>
            <?php foreach ($pending_requests as $req): ?>
            <div class="request-item">
                <span><?= htmlspecialchars($req['sender_name']) ?></span>
                <div class="request-actions">
                    <form method="POST" action="add_friend.php" style="display:inline">
                        <input type="hidden" name="req_id" value="<?= $req['id'] ?>">
                        <button type="submit" name="accept_request" class="btn-sm btn-accept">✓</button>
                    </form>
                    <form method="POST" action="add_friend.php" style="display:inline">
                        <input type="hidden" name="req_id" value="<?= $req['id'] ?>">
                        <button type="submit" name="reject_request" class="btn-sm btn-reject">✗</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($sent_requests)): ?>
        <div class="requests-box sent-requests-box">
            <h4>Sent Requests</h4>
            <?php foreach ($sent_requests as $sent):
                $sent_seed = $sent['receiver_avatar_seed'] ?? 'default';
                $sent_av = "https://api.dicebear.com/9.x/fun-emoji/svg?seed=" . urlencode($sent_seed);
            ?>
            <div class="sent-request-item">
                <img src="<?= $sent_av ?>" alt="avatar">
                <div>
                    <strong><?= htmlspecialchars($sent['receiver_name']) ?></strong>
                    <span>Waiting for accept</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="friends-list">
            <h4>Chats</h4>
            <?php if (empty($friends)): ?>
                <p class="no-friends">No friends yet.</p>
            <?php else: ?>
                <?php foreach ($friends as $friend):
    $stmt_fav = $pdo->prepare("SELECT avatar_seed FROM users WHERE id = ?");
    $stmt_fav->execute([$friend['id']]);
    $f_seed = $stmt_fav->fetchColumn() ?? 'default';
    $f_av   = "https://api.dicebear.com/9.x/fun-emoji/svg?seed=" . urlencode($f_seed);
?>
<a href="messages.php?with=<?= $friend['id'] ?>"
   class="friend-item <?= $chat_with == $friend['id'] ? 'active' : '' ?>">
    <img src="<?= $f_av ?>" alt="avatar"
         style="width:38px;height:38px;border-radius:50%;object-fit:cover;
                flex-shrink:0;border:2px solid #2e2a42">
    <span><?= htmlspecialchars($friend['name']) ?></span>
</a>
<?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="chat-main">
        <?php if ($chat_friend): ?>
        <div class="chat-header">
            <?php
$stmt_chav = $pdo->prepare("SELECT avatar_seed FROM users WHERE id = ?");
$stmt_chav->execute([$chat_with]);
$ch_seed = $stmt_chav->fetchColumn() ?? 'default';
$ch_av   = "https://api.dicebear.com/9.x/fun-emoji/svg?seed=" . urlencode($ch_seed);
?>
<img src="<?= $ch_av ?>" alt="avatar"
     style="width:38px;height:38px;border-radius:50%;object-fit:cover;border:2px solid #2e2a42">
            <span><?= htmlspecialchars($chat_friend['name']) ?></span>
        </div>
        <div class="chat-messages" id="chat-messages">
            <?php foreach ($messages as $msg): ?>
            <div class="message-wrap <?= $msg['sender_id'] == $user_id ? 'sent' : 'received' ?>">
                <div class="message-bubble">
                    <?php if ($msg['message']): ?>
                        <p><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                    <?php endif; ?>
                    <?php if ($msg['filename']): ?>
                        <?php if ($msg['file_type'] === 'image'): ?>
                            <img src="uploads/<?= htmlspecialchars($msg['filename']) ?>" class="chat-image">
                        <?php else: ?>
                            <a href="uploads/<?= htmlspecialchars($msg['filename']) ?>" class="file-link" download>📎 Download File</a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <span class="message-time"><?= date('h:i A', strtotime($msg['created_at'])) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="chat-input-area">
    <form id="chat-form" enctype="multipart/form-data" class="chat-form">
        <input type="hidden" name="receiver_id" value="<?= $chat_with ?>">
        <label for="chat_file" class="attach-btn" title="Attach file">📎</label>
        <input type="file" id="chat_file" name="chat_file"
               accept=".pdf,.docx,.png,.jpg,.jpeg" style="display:none"
               onchange="showFileName(this)">
        <div class="chat-input-wrap">
            <input type="text" name="message" id="message-input"
                   placeholder="Type a message..." autocomplete="off">
            <span id="file-name-display" class="file-name-display"></span>
        </div>
        <button type="submit" class="send-btn">➤</button>
    </form>
</div>
        <?php else: ?>
        <div class="chat-empty">
            <p>👈 Select a friend to start chatting</p>
            <p class="id-hint">Or add someone using their ID</p>
        </div>
        <?php endif; ?>
    </div>
</div>
<script>
const chatBox = document.getElementById('chat-messages');
if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;

function showFileName(input) {
    const display = document.getElementById('file-name-display');
    display.textContent = input.files[0] ? input.files[0].name : '';
}

const friendInput = document.getElementById('friend-id-input');
const friendPreview = document.getElementById('friend-preview');
const addFriendForm = document.getElementById('add-friend-form');
const addFriendBtn = addFriendForm ? addFriendForm.querySelector('button[type="submit"]') : null;
let lookupTimer = null;
let lastLookupId = '';

function setFriendPreview(html, canSend) {
    if (!friendPreview) return;
    friendPreview.innerHTML = html;
    if (addFriendBtn) {
        addFriendBtn.disabled = !canSend;
        addFriendBtn.textContent = canSend ? 'Send Request' : 'Check ID first';
    }
}

function escapeHtml(value) {
    return value.replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

if (friendInput && friendPreview) {
    if (addFriendBtn) addFriendBtn.disabled = true;

    friendInput.addEventListener('input', () => {
        const id = friendInput.value.trim();
        clearTimeout(lookupTimer);

        if (!id) {
            lastLookupId = '';
            setFriendPreview('<div class="friend-preview-empty">Type an ID to preview the student.</div>', false);
            return;
        }

        setFriendPreview('<div class="friend-preview-empty">Looking up ID #' + escapeHtml(id) + '...</div>', false);

        lookupTimer = setTimeout(async () => {
            lastLookupId = id;
            try {
                const response = await fetch('lookup_user.php?id=' + encodeURIComponent(id));
                const data = await response.json();
                if (friendInput.value.trim() !== lastLookupId) return;

                if (!data.ok) {
                    setFriendPreview(
                        '<div class="friend-preview-empty friend-preview-error">' + escapeHtml(data.message) + '</div>',
                        false
                    );
                    return;
                }

                setFriendPreview(
                    '<div class="friend-preview-card">' +
                        '<img src="' + data.avatar + '" alt="avatar">' +
                        '<div><strong>' + escapeHtml(data.name) + '</strong>' +
                        '<span>#' + data.id + ' - ' + escapeHtml(data.status_text) + '</span></div>' +
                    '</div>',
                    data.can_send
                );
            } catch (err) {
                setFriendPreview('<div class="friend-preview-empty friend-preview-error">Could not check that ID right now.</div>', false);
            }
        }, 250);
    });
}

// Submit form via fetch to keep focus
const form = document.getElementById('chat-form');
if (form) {
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const input = document.getElementById('message-input');
        const formData = new FormData(form);

        try {
            await fetch('send_message.php', {
                method: 'POST',
                body: formData
            });
        } catch(err) {}

        // Clear input and refocus immediately
        input.value = '';
        document.getElementById('file-name-display').textContent = '';
        document.getElementById('chat_file').value = '';
        input.focus();

        // Refresh messages
        refreshMessages();
    });
}

function refreshMessages() {
    <?php if ($chat_with): ?>
    fetch('fetch_messages.php?with=<?= $chat_with ?>')
        .then(r => r.text())
        .then(html => {
            const box = document.getElementById('chat-messages');
            if (box) {
                box.innerHTML = html;
                box.scrollTop = box.scrollHeight;
            }
        });
    <?php endif; ?>
}

// Auto refresh every 3 seconds
<?php if ($chat_with): ?>
setInterval(refreshMessages, 3000);
<?php endif; ?>

function toggleDark() {
    const html = document.documentElement;
    const isDark = html.getAttribute('data-theme') === 'dark';
    html.setAttribute('data-theme', isDark ? 'light' : 'dark');
    document.getElementById('theme-btn').textContent = isDark ? '🌙' : '☀️';
    localStorage.setItem('theme', isDark ? 'light' : 'dark');
}
const saved = localStorage.getItem('theme');
if (saved === 'dark') {
    document.documentElement.setAttribute('data-theme', 'dark');
    document.getElementById('theme-btn').textContent = '☀️';
}
</script>
<script>
function toggleDark() {
    const html = document.documentElement;
    const isDark = html.getAttribute('data-theme') === 'dark';
    html.setAttribute('data-theme', isDark ? 'light' : 'dark');
    document.getElementById('theme-btn').textContent = isDark ? '🌙' : '☀️';
    localStorage.setItem('theme', isDark ? 'light' : 'dark');
}
const saved = localStorage.getItem('theme');
if (saved === 'dark') {
    document.documentElement.setAttribute('data-theme', 'dark');
    document.getElementById('theme-btn').textContent = '☀️';
}
</script>

</body>
</html>

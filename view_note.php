<?php
date_default_timezone_set('Asia/Karachi');
require_once 'db.php';
require_once 'bookmark_helper.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$note_id   = intval($_GET['id'] ?? 0);
$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Fetch note FIRST and store immediately
$stmt = $pdo->prepare("
    SELECT notes.*, users.name AS uploader
    FROM notes
    JOIN users ON notes.user_id = users.id
    WHERE notes.id = ?
");
$stmt->execute([$note_id]);
$note = $stmt->fetch(); // ← fetch right here before anything overwrites $stmt

if (!$note) {
    header("Location: dashboard.php");
    exit();
}

$bm_pinned   = false;
$bm_favorite = false;
try {
    ensure_note_user_bookmarks_table($pdo);
    $stmt_bm = $pdo->prepare("SELECT is_pinned, is_favorite FROM note_user_bookmarks WHERE user_id = ? AND note_id = ?");
    $stmt_bm->execute([$user_id, $note_id]);
    $bm = $stmt_bm->fetch(PDO::FETCH_ASSOC);
    if ($bm) {
        $bm_pinned   = (bool) (int) $bm['is_pinned'];
        $bm_favorite = (bool) (int) $bm['is_favorite'];
    }
} catch (Exception $e) {
    // bookmarks optional if DB error
}

$view_bookmark_redirect = 'view_note.php?id=' . (int) $note_id;

// Handle delete note
if (isset($_POST['delete_note']) && $note['user_id'] == $user_id) {
    $stmt2 = $pdo->prepare("SELECT filename FROM note_files WHERE note_id = ?");
    $stmt2->execute([$note_id]);
    $files_to_delete = $stmt2->fetchAll();
    foreach ($files_to_delete as $f) {
        $path = __DIR__ . "/uploads/" . $f['filename'];
        if (file_exists($path)) unlink($path);
    }
    $pdo->prepare("DELETE FROM note_files WHERE note_id = ?")->execute([$note_id]);
    $pdo->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?")->execute([$note_id, $user_id]);
    header("Location: dashboard.php");
    exit();
}

// Handle post comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_comment'])) {
    $comment_text = trim($_POST['comment_text']);
    if (!empty($comment_text)) {
        $stmt2 = $pdo->prepare("INSERT INTO comments (note_id, user_id, comment) VALUES (?, ?, ?)");
        $stmt2->execute([$note_id, $user_id, $comment_text]);
    }
    header("Location: view_note.php?id=" . $note_id . "#comments");
    exit();
}

// Handle delete comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment'])) {
    $comment_id = intval($_POST['comment_id']);
    $pdo->prepare("DELETE FROM comments WHERE id = ? AND user_id = ?")->execute([$comment_id, $user_id]);
    header("Location: view_note.php?id=" . $note_id . "#comments");
    exit();
}

// Increment view count
$pdo->prepare("UPDATE notes SET views = views + 1 WHERE id = ?")->execute([$note_id]);

// Fetch comments
$stmt2 = $pdo->prepare("
    SELECT comments.*, users.name AS commenter
    FROM comments
    JOIN users ON comments.user_id = users.id
    WHERE comments.note_id = ?
    ORDER BY comments.created_at ASC
");
$stmt2->execute([$note_id]);
$comments = $stmt2->fetchAll();

// Fetch files
$stmt2 = $pdo->prepare("SELECT * FROM note_files WHERE note_id = ?");
$stmt2->execute([$note_id]);
$files = $stmt2->fetchAll();

function ns_safe_upload_name($filename) {
    $filename = basename(str_replace('\\', '/', rawurldecode($filename)));
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $name_without_ext = pathinfo($filename, PATHINFO_FILENAME);
    $safe_base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name_without_ext);
    $safe_base = trim($safe_base, '._-');

    if ($safe_base === '') {
        $safe_base = 'note_file';
    }

    return $safe_base . ($ext ? "." . strtolower($ext) : "");
}

function ns_find_uploaded_file($filename) {
    $uploads_dir = __DIR__ . "/uploads";
    $raw_name = ltrim(str_replace('\\', '/', $filename), '/');
    $decoded_name = rawurldecode($raw_name);
    $raw_base = basename($raw_name);
    $decoded_base = basename($decoded_name);
    $safe_base = ns_safe_upload_name($filename);
    $original_tail = preg_replace('/^[A-Za-z0-9]{13}_/', '', $decoded_base);
    $safe_tail = ns_safe_upload_name($original_tail);

    $candidates = [
        __DIR__ . "/" . $raw_name,
        __DIR__ . "/" . $decoded_name,
        $uploads_dir . "/" . $raw_name,
        $uploads_dir . "/" . $decoded_name,
        $uploads_dir . "/" . $raw_base,
        $uploads_dir . "/" . $decoded_base,
        $uploads_dir . "/" . $safe_base
    ];

    foreach (array_unique($candidates) as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    if (!is_dir($uploads_dir)) {
        return null;
    }

    $needles = array_unique([$raw_base, $decoded_base, $safe_base]);
    foreach (scandir($uploads_dir) as $disk_name) {
        foreach ($needles as $needle) {
            if (strcasecmp($disk_name, $needle) === 0) {
                return $uploads_dir . "/" . $disk_name;
            }
        }
    }

    foreach (scandir($uploads_dir) as $disk_name) {
        $safe_disk_name = ns_safe_upload_name($disk_name);
        if (
            ($original_tail && strcasecmp(substr($disk_name, -strlen($original_tail)), $original_tail) === 0) ||
            ($safe_tail && strcasecmp(substr($safe_disk_name, -strlen($safe_tail)), $safe_tail) === 0)
        ) {
            return $uploads_dir . "/" . $disk_name;
        }
    }

    return null;
}

foreach ($files as &$file) {
    $found_path = ns_find_uploaded_file($file['filename']);

    if ($found_path) {
        $actual_name = basename($found_path);
        if ($actual_name !== $file['filename']) {
            $stmt_fix = $pdo->prepare("UPDATE note_files SET filename = ? WHERE note_id = ? AND filename = ?");
            $stmt_fix->execute([$actual_name, $note_id, $file['filename']]);
            $file['filename'] = $actual_name;
        }
    }
}
unset($file);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($note['title']) ?> — NoteSwap</title>
    <script>
        const t = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-theme', t);
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
        <a href="dashboard.php" class="btn btn-secondary">← Back</a>
        <button class="btn btn-secondary" onclick="toggleDark()" id="theme-btn">🌙</button>
        
    </div>
</nav>

<div class="container">
    <div class="view-note-card">

        <!-- HEADER -->
        <div class="view-note-header">
            <div class="note-subject" style="margin-bottom:12px">
                <?= htmlspecialchars($note['subject']) ?>
            </div>
            <h1 class="view-note-title"><?= htmlspecialchars($note['title']) ?></h1>
            
            <div class="view-note-meta">
                <span>📚 <?= htmlspecialchars($note['section']) ?> — Semester <?= $note['semester'] ?></span>
                <span>👤 <?= htmlspecialchars($note['uploader']) ?></span>
                <span>🕐 <?= date('d M Y', strtotime($note['created_at'])) ?></span>
                <span>👁 <?= $note['views'] ?> views</span>
            </div>
            <div class="view-note-bookmark-actions">
                <form method="post" action="toggle_note_bookmark.php" class="note-bm-form">
                    <input type="hidden" name="note_id" value="<?= (int) $note_id ?>">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($view_bookmark_redirect) ?>">
                    <?php if ($bm_pinned): ?>
                        <input type="hidden" name="bookmark_action" value="unpin">
                        <button type="submit" class="ns-note-action ns-note-action--pin is-active" title="Remove from top of your dashboard lists">
                            <span class="ns-note-action-icon" aria-hidden="true">📌</span><span>Pinned</span>
                        </button>
                    <?php else: ?>
                        <input type="hidden" name="bookmark_action" value="pin">
                        <button type="submit" class="ns-note-action ns-note-action--pin" title="Pin to top of your dashboard lists">
                            <span class="ns-note-action-icon" aria-hidden="true">📌</span><span>Pin</span>
                        </button>
                    <?php endif; ?>
                </form>
                <form method="post" action="toggle_note_bookmark.php" class="note-bm-form">
                    <input type="hidden" name="note_id" value="<?= (int) $note_id ?>">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($view_bookmark_redirect) ?>">
                    <?php if ($bm_favorite): ?>
                        <input type="hidden" name="bookmark_action" value="unfavorite">
                        <button type="submit" class="ns-note-action ns-note-action--save is-active" title="Remove from your favorites">
                            <span class="ns-note-action-icon" aria-hidden="true">⭐</span><span>Saved</span>
                        </button>
                    <?php else: ?>
                        <input type="hidden" name="bookmark_action" value="favorite">
                        <button type="submit" class="ns-note-action ns-note-action--save" title="Save to your favorites list">
                            <span class="ns-note-action-icon" aria-hidden="true">🔖</span><span>Save</span>
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- DESCRIPTION -->
        <?php if ($note['description']): ?>
        <div class="view-note-desc">
            <h3>About this note</h3>
            <p><?= nl2br(htmlspecialchars($note['description'])) ?></p>
        </div>
        <?php endif; ?>

        <!-- FILES -->
        <?php if (!empty($files)): ?>
        <div class="view-note-files">
            <h3>Attached Files (<?= count($files) ?>)</h3>
            <?php foreach ($files as $file):
                $ext          = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
                $url_name     = str_replace('\\', '/', ltrim($file['filename'], '/'));
                $url_parts    = array_map('rawurlencode', explode('/', $url_name));
                $url_path      = substr($url_name, 0, 8) === 'uploads/'
                    ? implode('/', $url_parts)
                    : "uploads/" . implode('/', $url_parts);
                $file_url     = htmlspecialchars($url_path);
                $display_name = substr($file['filename'], 14);
                $server_file_exists = ns_find_uploaded_file($file['filename']) !== null;
            ?>
            <div class="file-viewer-block">
                <div class="file-viewer-header">
                    <span class="file-viewer-name">
                        <?= $file['file_type']==='image' ? '🖼️' : ($ext==='pdf' ? '📄' : '📝') ?>
                        <?= htmlspecialchars($display_name) ?>
                    </span>
                    <?php if ($server_file_exists): ?>
                    <a href="<?= $file_url ?>" download class="btn"
                       style="width:auto;padding:7px 18px;font-size:13px;
                              background:var(--accent-light);color:var(--accent-dark);
                              border:1.5px solid var(--accent);border-radius:50px">
                        ⬇ Download
                    </a>
                    <?php endif; ?>
                </div>

                <?php if (!$server_file_exists): ?>
                    <div class="file-viewer-body docx-placeholder">
                        <div class="docx-icon">⚠</div>
                        <p>This note was saved, but its file was not uploaded to the server. Please upload this note again.</p>
                        <?php if ($note['user_id'] == $user_id): ?>
                            <a href="upload.php" class="btn btn-primary"
                               style="width:auto;padding:10px 24px;margin-top:8px">
                                Upload Again
                            </a>
                        <?php endif; ?>
                    </div>
                <?php elseif ($file['file_type'] === 'image'): ?>
                    <div class="file-viewer-body">
                        <img src="<?= $file_url ?>" class="inline-image"
                             onclick="openModal(this.src)" alt="Note image">
                    </div>
                <?php elseif ($ext === 'pdf'): ?>
                    <div class="file-viewer-body pdf-viewer-wrap">
                        <iframe src="<?= $file_url ?>" class="pdf-iframe" title="PDF Preview">
                            <p>Cannot display PDF. <a href="<?= $file_url ?>" download>Download instead</a></p>
                        </iframe>
                    </div>
                <?php elseif ($ext === 'docx'): ?>
                    <div class="file-viewer-body docx-placeholder">
                        <div class="docx-icon">📝</div>
                        <p>Word documents cannot be previewed in the browser.</p>
                        <a href="<?= $file_url ?>" download class="btn btn-primary"
                           style="width:auto;padding:10px 24px;margin-top:8px">
                            ⬇ Download to view
                        </a>
                    </div>
                <?php else: ?>
                    <div class="file-viewer-body docx-placeholder">
                        <div class="docx-icon">💾</div>
                        <p>This file type cannot be previewed.</p>
                        <a href="<?= $file_url ?>" download class="btn btn-primary"
                           style="width:auto;padding:10px 24px;margin-top:8px">
                            ⬇ Download File
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="empty-state" style="padding:40px">
                <div class="empty-icon">📭</div>
                <p>No files attached to this note.</p>
            </div>
        <?php endif; ?>

        <!-- COMMENTS -->
        <div class="comments-section" id="comments">
            <h3>💬 Comments (<?= count($comments) ?>)</h3>

            <?php foreach ($comments as $c): ?>
            <div class="comment-item">
                <div class="comment-avatar">
                    <?= strtoupper(substr($c['commenter'], 0, 1)) ?>
                </div>
                <div class="comment-body">
                    <div class="comment-header">
                        <strong><?= htmlspecialchars($c['commenter']) ?></strong>
                        <span class="comment-time">
                            <?= date('d M Y, h:i A', strtotime($c['created_at'])) ?>
                        </span>
                    </div>
                    <p class="comment-text"><?= nl2br(htmlspecialchars($c['comment'])) ?></p>
                </div>
                <?php if ($c['user_id'] == $user_id): ?>
                    <form method="POST" style="flex-shrink:0"
                          onsubmit="return confirm('Delete this comment?')">
                        <input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
                        <button type="submit" name="delete_comment"
                                style="background:none;border:none;cursor:pointer;
                                       color:var(--text-light);font-size:14px;padding:4px">
                            🗑
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <?php if (empty($comments)): ?>
                <p class="no-comments">No comments yet. Be the first to comment!</p>
            <?php endif; ?>

            <form method="POST" class="comment-form">
                <div class="comment-input-wrap">
                    <div class="comment-avatar">
                        <?= strtoupper(substr($user_name, 0, 1)) ?>
                    </div>
                    <textarea name="comment_text" placeholder="Write a comment..."
                              rows="2" required></textarea>
                </div>
                <div style="display:flex;justify-content:flex-end;margin-top:8px">
                    <button type="submit" name="post_comment" class="btn btn-primary"
                            style="width:auto;padding:9px 24px;font-size:13px">
                        Post Comment
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

<!-- IMAGE ZOOM MODAL -->
<div id="img-modal" onclick="closeModal()"
     style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
            background:rgba(0,0,0,0.9);z-index:9999;align-items:center;
            justify-content:center;cursor:zoom-out">
    <img id="modal-img"
         style="max-width:92%;max-height:92%;border-radius:12px">
</div>

<script>
function toggleDark() {
    const current = document.documentElement.getAttribute('data-theme');
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    document.getElementById('theme-btn').textContent = next === 'dark' ? '☀️' : '🌙';
    localStorage.setItem('theme', next);
}
const saved = localStorage.getItem('theme') || 'dark';
document.documentElement.setAttribute('data-theme', saved);
document.getElementById('theme-btn').textContent = saved === 'dark' ? '☀️' : '🌙';

function openModal(src) {
    document.getElementById('modal-img').src = src;
    document.getElementById('img-modal').style.display = 'flex';
}
function closeModal() {
    document.getElementById('img-modal').style.display = 'none';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>
</body>
</html>

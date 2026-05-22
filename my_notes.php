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
$success   = "";
$error     = "";

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_note'])) {
    $note_id = intval($_POST['note_id']);
    $stmt = $pdo->prepare("SELECT filename FROM note_files WHERE note_id = ?");
    $stmt->execute([$note_id]);
    $files_to_del = $stmt->fetchAll();
    foreach ($files_to_del as $f) {
        $path = __DIR__ . "/uploads/" . $f['filename'];
        if (file_exists($path)) unlink($path);
    }
    $pdo->prepare("DELETE FROM note_files WHERE note_id = ?")->execute([$note_id]);
    $pdo->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?")->execute([$note_id, $user_id]);
    $success = "Note deleted successfully.";
}

// Handle edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_note'])) {
    $note_id  = intval($_POST['note_id']);
    $title    = trim($_POST['new_title']);
    $desc     = trim($_POST['new_desc']);
    if (!empty($title)) {
        $pdo->prepare("UPDATE notes SET title=?, description=? WHERE id=? AND user_id=?")
            ->execute([$title, $desc, $note_id, $user_id]);
        $success = "Note updated successfully.";
    }
}

// Fetch my notes
$stmt = $pdo->prepare("
    SELECT notes.*,
           (SELECT COUNT(*) FROM comments WHERE note_id = notes.id) AS comment_count,
           (SELECT COUNT(*) FROM note_files WHERE note_id = notes.id) AS file_count
    FROM notes
    WHERE notes.user_id = ?
    ORDER BY notes.created_at DESC
");
$stmt->execute([$user_id]);
$my_notes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Uploads — NoteSwap</title>
    <script>const t=localStorage.getItem('theme')||'dark';document.documentElement.setAttribute('data-theme',t);</script>
    <link rel="stylesheet" href="css/style.css">
    <style>
        [data-theme="dark"]  body { background:#0f0e17; color:#f3f0ff; }
        [data-theme="light"] body { background:#f0f4f8; color:#111827; }

        .page-container { max-width:900px; margin:0 auto; padding:32px 20px 60px; }

        .page-header {
            display:flex; justify-content:space-between; align-items:center;
            margin-bottom:28px; flex-wrap:wrap; gap:12px;
        }

        .page-header h1 {
            font-size:24px; font-weight:700;
            font-family:'Plus Jakarta Sans',sans-serif;
        }
        [data-theme="dark"]  .page-header h1 { color:#f3f0ff; }
        [data-theme="light"] .page-header h1 { color:#111827; }

        .upload-count {
            font-size:13px; font-weight:600;
            background:rgba(124,58,237,0.15);
            color:#a78bfa; padding:4px 14px;
            border-radius:50px; border:1px solid rgba(124,58,237,0.3);
        }

        /* NOTE ITEM */
        .my-note-block {
            border-radius:14px; border:1px solid;
            margin-bottom:14px; overflow:hidden;
            transition:border-color 0.2s;
        }
        [data-theme="dark"]  .my-note-block { background:#1a1825; border-color:#2e2a42; }
        [data-theme="light"] .my-note-block { background:#ffffff;  border-color:#e5e7eb; }
        .my-note-block:hover { border-color:#7c3aed; }

        /* VIEW ROW */
        .my-note-view-row {
            display:flex; align-items:center;
            padding:18px 20px; gap:16px;
        }

        .my-note-info { flex:1; min-width:0; }

        .my-note-subj {
            display:inline-flex; font-size:10px; font-weight:700;
            text-transform:uppercase; letter-spacing:0.8px;
            padding:3px 10px; border-radius:50px;
            margin-bottom:6px;
        }
        [data-theme="dark"]  .my-note-subj { color:#a78bfa; background:rgba(124,58,237,0.18); }
        [data-theme="light"] .my-note-subj { color:#7c3aed; background:#ede9fe; }

        .my-note-title-text {
            font-size:16px; font-weight:600;
            white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
            margin-bottom:4px;
            font-family:'Plus Jakarta Sans',sans-serif;
        }
        [data-theme="dark"]  .my-note-title-text { color:#f3f0ff; }
        [data-theme="light"] .my-note-title-text { color:#111827; }

        .my-note-meta {
            display:flex; gap:14px; font-size:12px; flex-wrap:wrap;
        }
        [data-theme="dark"]  .my-note-meta { color:#8b82a7; }
        [data-theme="light"] .my-note-meta { color:#9ca3af; }

        .my-note-btns {
            display:flex; gap:8px; flex-shrink:0; flex-wrap:wrap;
        }

        .mn-btn {
            padding:7px 16px; border-radius:50px;
            font-size:12px; font-weight:600;
            cursor:pointer; border:1px solid;
            text-decoration:none; display:inline-flex;
            align-items:center; gap:5px;
            transition:all 0.2s; font-family:'Inter',sans-serif;
            white-space:nowrap;
        }

        .mn-btn-view {
            background:rgba(124,58,237,0.15);
            color:#a78bfa; border-color:rgba(124,58,237,0.3);
        }
        .mn-btn-view:hover { background:#7c3aed; color:white; border-color:#7c3aed; }

        .mn-btn-edit {
            background:rgba(245,158,11,0.12);
            color:#f59e0b; border-color:rgba(245,158,11,0.3);
        }
        .mn-btn-edit:hover { background:#f59e0b; color:#0f0e17; border-color:#f59e0b; }

        .mn-btn-del {
            background:rgba(239,68,68,0.1);
            color:#f87171; border-color:rgba(239,68,68,0.3);
        }
        .mn-btn-del:hover { background:#ef4444; color:white; border-color:#ef4444; }

        /* EDIT FORM */
        .my-note-edit-row {
            display:none;
            padding:0 20px 20px;
            border-top:1px solid;
        }
        [data-theme="dark"]  .my-note-edit-row { border-top-color:#2e2a42; background:#150d2e; }
        [data-theme="light"] .my-note-edit-row { border-top-color:#f3f4f6; background:#f9fafb; }

        .my-note-edit-row.visible { display:block; }

        .edit-form-inner { padding-top:16px; }

        .edit-field { margin-bottom:14px; }
        .edit-field label {
            display:block; font-size:11px; font-weight:700;
            text-transform:uppercase; letter-spacing:0.8px;
            margin-bottom:6px;
        }
        [data-theme="dark"]  .edit-field label { color:#8b82a7; }
        [data-theme="light"] .edit-field label { color:#6b7280; }

        .edit-field input,
        .edit-field textarea {
            width:100%; padding:10px 14px;
            border-radius:10px; border:1px solid;
            font-size:14px; font-family:'Inter',sans-serif;
            outline:none; transition:border-color 0.2s;
            resize:vertical;
        }
        [data-theme="dark"]  .edit-field input,
        [data-theme="dark"]  .edit-field textarea {
            background:#1a1825; color:#f3f0ff; border-color:#2e2a42;
        }
        [data-theme="light"] .edit-field input,
        [data-theme="light"] .edit-field textarea {
            background:#ffffff; color:#111827; border-color:#e5e7eb;
        }
        .edit-field input:focus,
        .edit-field textarea:focus { border-color:#7c3aed; }

        .edit-btns { display:flex; gap:8px; margin-top:4px; }

        /* EMPTY STATE */
        .mn-empty {
            text-align:center; padding:80px 20px;
        }
        [data-theme="dark"]  .mn-empty { color:#8b82a7; }
        [data-theme="light"] .mn-empty { color:#9ca3af; }
        .mn-empty-icon { font-size:52px; display:block; margin-bottom:16px; }
        .mn-empty p { font-size:16px; margin-bottom:20px; }

        /* ALERT */
        .mn-alert {
            padding:12px 18px; border-radius:10px;
            margin-bottom:20px; font-size:14px;
            display:flex; align-items:center; gap:8px;
        }
        .mn-alert-success {
            background:rgba(16,185,129,0.12);
            color:#34d399; border:1px solid rgba(16,185,129,0.3);
        }
        .mn-alert-error {
            background:rgba(239,68,68,0.1);
            color:#f87171; border:1px solid rgba(239,68,68,0.3);
        }
    </style>
</head>
<body>

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
        <a href="favorites.php" class="btn btn-secondary">⭐ Favorites</a>
        <a href="dashboard.php" class="btn btn-secondary">← Dashboard</a>
        <button class="btn btn-secondary" onclick="toggleDark()" id="theme-btn">🌙</button>
        <a href="profile.php" class="nav-avatar"><?= strtoupper(substr($user_name,0,1)) ?></a>
    </div>
</nav>

<div class="page-container">

    <?php if ($success): ?>
        <div class="mn-alert mn-alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="page-header">
        <h1>📂 My Uploads</h1>
        <div style="display:flex;align-items:center;gap:10px">
            <span class="upload-count"><?= count($my_notes) ?> notes</span>
            <a href="upload.php" class="mn-btn mn-btn-view" style="background:#7c3aed;color:white;border-color:#7c3aed">
                + Upload New
            </a>
        </div>
    </div>

    <?php if (empty($my_notes)): ?>
        <div class="mn-empty">
            <span class="mn-empty-icon">📭</span>
            <p>You haven't uploaded any notes yet.</p>
            <a href="upload.php" class="mn-btn mn-btn-view"
               style="background:#7c3aed;color:white;border-color:#7c3aed;padding:10px 24px;font-size:14px">
                Upload your first note
            </a>
        </div>
    <?php else: ?>
        <?php foreach ($my_notes as $note): ?>
        <div class="my-note-block" id="block-<?= $note['id'] ?>">

            <!-- VIEW ROW -->
            <div class="my-note-view-row">
                <div class="my-note-info">
                    <span class="my-note-subj"><?= htmlspecialchars($note['subject']) ?></span>
                    <div class="my-note-title-text"><?= htmlspecialchars($note['title']) ?></div>
                    <div class="my-note-meta">
                        <span>📚 <?= htmlspecialchars($note['section']) ?> Sem <?= $note['semester'] ?></span>
                        <span>📎 <?= $note['file_count'] ?> file(s)</span>
                        <span>👁 <?= $note['views'] ?? 0 ?> views</span>
                        <span>💬 <?= $note['comment_count'] ?> comments</span>
                        <span>🕐 <?= date('d M Y', strtotime($note['created_at'])) ?></span>
                    </div>
                </div>
                <div class="my-note-btns">
                    <a href="view_note.php?id=<?= $note['id'] ?>" class="mn-btn mn-btn-view">
                        👁 View
                    </a>
                    <button onclick="toggleEdit(<?= $note['id'] ?>)" class="mn-btn mn-btn-edit">
                        ✏️ Edit
                    </button>
                    <form method="POST" style="display:inline"
                          onsubmit="return confirm('Delete this note permanently?')">
                        <input type="hidden" name="note_id" value="<?= $note['id'] ?>">
                        <button type="submit" name="delete_note" class="mn-btn mn-btn-del">
                            🗑 Delete
                        </button>
                    </form>
                </div>
            </div>

            <!-- EDIT ROW -->
            <div class="my-note-edit-row" id="edit-<?= $note['id'] ?>">
                <div class="edit-form-inner">
                    <form method="POST">
                        <input type="hidden" name="note_id" value="<?= $note['id'] ?>">
                        <div class="edit-field">
                            <label>Title</label>
                            <input type="text" name="new_title"
                                   value="<?= htmlspecialchars($note['title']) ?>" required>
                        </div>
                        <div class="edit-field">
                            <label>Description</label>
                            <textarea name="new_desc" rows="3"><?= htmlspecialchars($note['description']) ?></textarea>
                        </div>
                        <div class="edit-btns">
                            <button type="submit" name="edit_note" class="mn-btn mn-btn-view"
                                    style="background:#7c3aed;color:white;border-color:#7c3aed">
                                💾 Save Changes
                            </button>
                            <button type="button" onclick="toggleEdit(<?= $note['id'] ?>)"
                                    class="mn-btn mn-btn-edit">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<script>
function toggleEdit(id) {
    const editRow = document.getElementById('edit-' + id);
    editRow.classList.toggle('visible');
}

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
</script>
</body>
</html>
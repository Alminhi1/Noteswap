<?php
date_default_timezone_set('Asia/Karachi');
require_once 'db.php';
require_once 'bookmark_helper.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT avatar_seed FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$avatar_seed = $stmt->fetchColumn() ?? 'default';
$avatar_url  = 'https://api.dicebear.com/9.x/fun-emoji/svg?seed=' . urlencode($avatar_seed);

$search_query = trim($_GET['search'] ?? '');
$fav_redirect = 'favorites.php';
if ($search_query !== '') {
    $fav_redirect .= '?search=' . rawurlencode($search_query);
}

$notes = [];

try {
    ensure_note_user_bookmarks_table($pdo);
} catch (Exception $e) {
    // continue; list may be empty
}

try {

    $params = [$user_id];
    $where  = 'nub.user_id = ? AND nub.is_favorite = 1';

    if ($search_query !== '') {
        $where     .= ' AND (notes.title LIKE ? OR notes.subject LIKE ? OR notes.description LIKE ?)';
        $st        = '%' . $search_query . '%';
        $params[] = $st;
        $params[] = $st;
        $params[] = $st;
    }

    $stmt = $pdo->prepare("
        SELECT notes.*, users.name AS uploader,
               (SELECT COUNT(*) FROM comments WHERE comments.note_id = notes.id) AS comment_count,
               nub.is_pinned AS user_is_pinned,
               nub.is_favorite AS user_is_favorite
        FROM note_user_bookmarks nub
        INNER JOIN notes ON notes.id = nub.note_id
        INNER JOIN users ON notes.user_id = users.id
        WHERE $where
        ORDER BY nub.is_pinned DESC, nub.updated_at DESC, notes.created_at DESC
    ");
    $stmt->execute($params);
    $notes = $stmt->fetchAll();
} catch (Exception $e) {
    $notes = [];
}

$fav_total = 0;
try {
    $fav_count_stmt = $pdo->prepare('SELECT COUNT(*) FROM note_user_bookmarks WHERE user_id = ? AND is_favorite = 1');
    $fav_count_stmt->execute([$user_id]);
    $fav_total = (int) $fav_count_stmt->fetchColumn();
} catch (Exception $e) {
    $fav_total = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Favorites — NoteSwap</title>
    <script>
        const t = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-theme', t);
    </script>
    <link rel="stylesheet" href="css/style.css">
    <style>
        [data-theme="dark"]  body { background:#221f31; color:#f3f0ff; }
        [data-theme="light"] body { background:#efeef3; color:#111827; }

        .page-container { max-width:960px; margin:0 auto; padding:32px 20px 60px; }

        .page-header {
            display:flex; justify-content:space-between; align-items:flex-start;
            margin-bottom:24px; flex-wrap:wrap; gap:14px;
        }
        .page-header h1 {
            font-size:24px; font-weight:700; font-family:'Plus Jakarta Sans',sans-serif;
            margin:0 0 6px 0;
        }
        [data-theme="dark"]  .page-header h1 { color:#f3f0ff; }
        [data-theme="light"] .page-header h1 { color:#111827; }

        .page-sub {
            font-size:14px; margin:0;
        }
        [data-theme="dark"]  .page-sub { color:#8b82a7; }
        [data-theme="light"] .page-sub { color:#6b7280; }

        .fav-count-pill {
            font-size:13px; font-weight:600;
            padding:6px 14px; border-radius:50px;
            background:rgba(124,58,237,0.15);
            color:#a78bfa; border:1px solid rgba(124,58,237,0.35);
        }

        .search-row {
            margin-bottom:22px;
            display:flex; gap:10px; flex-wrap:wrap; align-items:center;
        }
        .search-row form { display:flex; gap:8px; flex:1; min-width:220px; max-width:420px; }
        .search-row input {
            flex:1; padding:11px 14px; border-radius:50px; border:1px solid;
            font-size:14px; font-family:'Inter',sans-serif; outline:none;
        }
        [data-theme="dark"]  .search-row input {
            background:#1a1825; border-color:#2e2a42; color:#f3f0ff;
        }
        [data-theme="light"] .search-row input {
            background:#ffffff; border-color:#e5e7eb; color:#111827;
        }
        .search-row input:focus { border-color:#7c3aed; }
        .search-btn {
            padding:10px 20px; border-radius:50px; border:none;
            background:#7c3aed; color:white; font-weight:600; font-size:13px;
            cursor:pointer; font-family:'Plus Jakarta Sans',sans-serif;
            white-space:nowrap;
        }
        .search-btn:hover { background:#6d28d9; }
        .clear-link {
            font-size:13px; color:#a78bfa; text-decoration:none; font-weight:600;
        }

        .fav-note-block {
            border-radius:14px; border:1px solid;
            margin-bottom:14px; overflow:hidden;
            transition:border-color 0.2s;
        }
        [data-theme="dark"]  .fav-note-block { background:#1a1825; border-color:#2e2a42; }
        [data-theme="light"] .fav-note-block { background:#ffffff; border-color:#e5e7eb; }
        .fav-note-block:hover { border-color:#7c3aed; }

        .fav-note-inner {
            display:flex; align-items:flex-start; padding:18px 20px; gap:14px;
            flex-wrap:wrap;
        }

        .fav-note-info { flex:1; min-width:200px; }

        .fav-badges {
            display:flex; flex-wrap:wrap; gap:6px; margin-bottom:8px;
        }
        .fav-badge-pill {
            font-size:10px; font-weight:700; text-transform:uppercase;
            letter-spacing:0.5px; padding:4px 10px; border-radius:50px;
        }
        [data-theme="dark"]  .fav-badge-subj { color:#a78bfa; background:rgba(124,58,237,0.18); }
        [data-theme="light"] .fav-badge-subj { color:#7c3aed; background:#ede9fe; }

        .fav-badge-pin {
            color:#f59e0b; background:rgba(245,158,11,0.12);
            border:1px solid rgba(245,158,11,0.35);
        }

        .fav-title {
            font-size:17px; font-weight:600;
            font-family:'Plus Jakarta Sans',sans-serif;
            margin:0 0 6px 0; line-height:1.35;
        }
        [data-theme="dark"]  .fav-title { color:#f3f0ff; }
        [data-theme="light"] .fav-title { color:#111827; }

        .fav-desc {
            font-size:13px; line-height:1.55; margin:0 0 8px 0;
            display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;
            overflow:hidden;
        }
        [data-theme="dark"]  .fav-desc { color:#8b82a7; }
        [data-theme="light"] .fav-desc { color:#6b7280; }

        .fav-meta {
            font-size:12px; display:flex; gap:12px; flex-wrap:wrap;
        }
        [data-theme="dark"]  .fav-meta { color:#6b6480; }
        [data-theme="light"] .fav-meta { color:#9ca3af; }

        .fav-actions {
            display:flex; flex-wrap:wrap; gap:8px; align-items:center;
        }

        .fav-btn-form { margin:0; display:inline; }
        .fav-act-btn {
            appearance:none; font-size:12px; font-weight:600;
            padding:8px 14px; border-radius:50px; border:1px solid;
            cursor:pointer; font-family:inherit; text-decoration:none;
            display:inline-flex; align-items:center; gap:4px;
            transition:background 0.15s, border-color 0.15s, color 0.15s;
        }
        [data-theme="dark"] .fav-act-btn.neutral {
            background:#1e1b2e; color:#c4b5fd; border-color:#3d3666;
        }
        [data-theme="light"] .fav-act-btn.neutral {
            background:#f5f3ff; color:#6d28d9; border-color:#ddd6fe;
        }
        .fav-act-btn.view {
            background:rgba(124,58,237,0.15); color:#a78bfa;
            border-color:rgba(124,58,237,0.35);
        }
        .fav-act-btn.view:hover { background:#7c3aed; color:white; border-color:#7c3aed; }
        .fav-act-btn.warn {
            background:rgba(239,68,68,0.08); color:#f87171; border-color:rgba(239,68,68,0.35);
        }
        .fav-act-btn.warn:hover { background:#ef4444; color:white; border-color:#ef4444; }

        .mn-empty {
            text-align:center; padding:72px 20px;
        }
        [data-theme="dark"]  .mn-empty { color:#8b82a7; }
        [data-theme="light"] .mn-empty { color:#9ca3af; }
        .mn-empty-icon { font-size:52px; display:block; margin-bottom:16px; }
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
        <a href="upload.php" class="btn btn-primary" style="width:auto">+ Upload</a>
        <a href="dashboard.php" class="btn btn-secondary">← Dashboard</a>
        <button class="btn btn-secondary" onclick="toggleDark()" id="theme-btn">🌙</button>
        <a href="profile.php" class="nav-avatar" title="My Profile"
           style="padding:0;overflow:hidden;width:38px;height:38px">
            <img src="<?= htmlspecialchars($avatar_url) ?>" alt="avatar"
                 style="width:100%;height:100%;border-radius:50%;object-fit:cover;display:block">
        </a>
    </div>
</nav>

<div class="page-container">
    <div class="page-header">
        <div>
            <h1>⭐ My Favorites</h1>
            <p class="page-sub">Notes you saved — only visible to you</p>
        </div>
        <span class="fav-count-pill"><?= $fav_total ?> saved</span>
    </div>

    <div class="search-row">
        <form method="get" action="">
            <input type="search" name="search" placeholder="Search your favorites..."
                   value="<?= htmlspecialchars($search_query) ?>">
            <button type="submit" class="search-btn">Search</button>
        </form>
        <?php if ($search_query !== ''): ?>
            <a href="favorites.php" class="clear-link">Clear search</a>
        <?php endif; ?>
    </div>

    <?php if (empty($notes)): ?>
        <div class="mn-empty">
            <span class="mn-empty-icon"><?= $search_query !== '' ? '🔍' : '⭐' ?></span>
            <?php if ($search_query !== ''): ?>
                <p>No favorites match your search.</p>
            <?php else: ?>
                <p>You have not saved any notes yet.</p>
                <p style="font-size:14px;margin-top:8px">Browse the dashboard and tap <strong>Save</strong> on any note.</p>
            <?php endif; ?>
            <a href="dashboard.php" class="btn btn-primary" style="margin-top:20px;display:inline-block;width:auto">
                Go to dashboard
            </a>
        </div>
    <?php else: ?>
        <?php foreach ($notes as $note):
            $stmt_f = $pdo->prepare('SELECT * FROM note_files WHERE note_id = ? LIMIT 1');
            $stmt_f->execute([$note['id']]);
            $first_file = $stmt_f->fetch();
        ?>
        <div class="fav-note-block">
            <div class="fav-note-inner">
                <div class="fav-note-info">
                    <div class="fav-badges">
                        <span class="fav-badge-pill fav-badge-subj"><?= htmlspecialchars($note['subject']) ?></span>
                        <?php if (!empty($note['user_is_pinned'])): ?>
                            <span class="fav-badge-pill fav-badge-pin">📌 Pinned</span>
                        <?php endif; ?>
                    </div>
                    <h2 class="fav-title"><?= htmlspecialchars($note['title']) ?></h2>
                    <?php if ($note['description'] !== ''): ?>
                        <p class="fav-desc"><?= htmlspecialchars($note['description']) ?></p>
                    <?php endif; ?>
                    <div class="fav-meta">
                        <span>👤 <?= htmlspecialchars($note['uploader']) ?></span>
                        <span>🕐 <?= date('d M Y', strtotime($note['created_at'])) ?></span>
                        <span>👁 <?= (int) ($note['views'] ?? 0) ?></span>
                        <span>💬 <?= (int) ($note['comment_count'] ?? 0) ?></span>
                    </div>
                </div>
                <div class="fav-actions">
                    <?php if ($first_file): ?>
                        <a href="uploads/<?= htmlspecialchars($first_file['filename']) ?>"
                           download class="fav-act-btn neutral">⬇ Download</a>
                    <?php endif; ?>
                    <a href="view_note.php?id=<?= (int) $note['id'] ?>" class="fav-act-btn view">👁 View</a>
                    <form method="post" action="toggle_note_bookmark.php" class="fav-btn-form">
                        <input type="hidden" name="note_id" value="<?= (int) $note['id'] ?>">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($fav_redirect) ?>">
                        <?php if (!empty($note['user_is_pinned'])): ?>
                            <input type="hidden" name="bookmark_action" value="unpin">
                            <button type="submit" class="ns-note-action ns-note-action--pin is-active" title="Unpin"><span class="ns-note-action-icon" aria-hidden="true">📌</span><span>Unpin</span></button>
                        <?php else: ?>
                            <input type="hidden" name="bookmark_action" value="pin">
                            <button type="submit" class="ns-note-action ns-note-action--pin" title="Pin"><span class="ns-note-action-icon" aria-hidden="true">📌</span><span>Pin</span></button>
                        <?php endif; ?>
                    </form>
                    <form method="post" action="toggle_note_bookmark.php" class="fav-btn-form">
                        <input type="hidden" name="note_id" value="<?= (int) $note['id'] ?>">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($fav_redirect) ?>">
                        <input type="hidden" name="bookmark_action" value="unfavorite">
                        <button type="submit" class="ns-note-action ns-note-action--save is-active" title="Remove from favorites"><span class="ns-note-action-icon" aria-hidden="true">⭐</span><span>Remove</span></button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
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
</script>
</body>
</html>

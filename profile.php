<?php
date_default_timezone_set('Asia/Karachi');
require_once 'db.php';
require_once 'bookmark_helper.php';
require_once 'contributor_helper.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$success   = "";
$error     = "";

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Handle avatar update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_avatar'])) {
    $seed = trim($_POST['avatar_seed']);
    $pdo->prepare("UPDATE users SET avatar_seed = ? WHERE id = ?")->execute([$seed, $user_id]);
    $_SESSION['avatar_seed'] = $seed;
    $success = "Avatar updated!";
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name     = trim($_POST['name']);
    $section  = $_POST['section'];
    $semester = intval($_POST['semester']);
    $new_pass = $_POST['new_password'];
    $confirm  = $_POST['confirm_password'];

    if (empty($name)) {
        $error = "Name cannot be empty.";
    } else {
        if (!empty($new_pass)) {
            if (strlen($new_pass) < 6) {
                $error = "Password must be at least 6 characters.";
            } elseif ($new_pass !== $confirm) {
                $error = "Passwords do not match.";
            } else {
                $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET name=?, section=?, semester=?, password=? WHERE id=?")
                    ->execute([$name, $section, $semester, $hashed, $user_id]);
                $success = "Profile and password updated!";
            }
        } else {
            $pdo->prepare("UPDATE users SET name=?, section=?, semester=? WHERE id=?")
                ->execute([$name, $section, $semester, $user_id]);
            $success = "Profile updated!";
        }
        if (empty($error)) {
            $_SESSION['user_name'] = $name;
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        }
    }
}

// Handle note edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_note'])) {
    $note_id = intval($_POST['note_id']);
    $title   = trim($_POST['new_title']);
    $desc    = trim($_POST['new_desc']);
    if (!empty($title)) {
        $pdo->prepare("UPDATE notes SET title=?, description=? WHERE id=? AND user_id=?")
            ->execute([$title, $desc, $note_id, $user_id]);
        $success = "Note updated!";
    }
}

// Handle note delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_note'])) {
    $note_id = intval($_POST['note_id']);
    $stmt2 = $pdo->prepare("SELECT filename FROM note_files WHERE note_id = ?");
    $stmt2->execute([$note_id]);
    foreach ($stmt2->fetchAll() as $f) {
        $path = __DIR__ . "/uploads/" . $f['filename'];
        if (file_exists($path)) unlink($path);
    }
    $pdo->prepare("DELETE FROM note_files WHERE note_id = ?")->execute([$note_id]);
    $pdo->prepare("DELETE FROM notes WHERE id=? AND user_id=?")->execute([$note_id, $user_id]);
    $success = "Note deleted.";
}

// Stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notes WHERE user_id = ?");
$stmt->execute([$user_id]);
$note_count = (int) $stmt->fetchColumn();

$contribution_score = ns_contribution_score($note_count);
$contributor_tier   = ns_contributor_tier_label($note_count);
$top_rule      = ns_top_contributor_rule($pdo);
$is_top_auto   = ns_is_top_contributor($user_id, $note_count, $top_rule);
$is_top_manual = ns_is_manual_top_contributor($pdo, $user_id);
$is_top_contributor = $is_top_auto || $is_top_manual;
$is_site_admin = !empty($user['is_admin']);

$top_contributor_title = 'Top contributors need more than ' . (int) $top_rule['t_exclusive']
    . ' notes right now (bar rises if 5+ students pass the current cutoff). No ranks — same tag for everyone who qualifies.';
if ($is_top_manual && $is_top_auto) {
    $top_contributor_title = 'You qualify by uploads AND have an admin award. Admin tags are extra and do not remove upload-based tags from other students.';
} elseif ($is_top_manual) {
    $top_contributor_title = 'Top contributor — awarded by an admin. This is separate from upload-based tags; others can still earn the tag from uploads (max 4 from that rule is unchanged).';
}

$fav_count = 0;
try {
    ensure_note_user_bookmarks_table($pdo);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM note_user_bookmarks WHERE user_id = ? AND is_favorite = 1");
    $stmt->execute([$user_id]);
    $fav_count = (int) $stmt->fetchColumn();
} catch (Exception $e) {
    $fav_count = 0;
}

// Friends
$stmt = $pdo->prepare("
    SELECT users.id, users.name, users.section, users.semester, users.avatar_seed
    FROM friend_requests
    JOIN users ON (CASE WHEN friend_requests.sender_id=? THEN friend_requests.receiver_id ELSE friend_requests.sender_id END = users.id)
    WHERE (friend_requests.sender_id=? OR friend_requests.receiver_id=?)
    AND friend_requests.status='accepted'
");
$stmt->execute([$user_id, $user_id, $user_id]);
$friends = $stmt->fetchAll();

$avatar_seed = $user['avatar_seed'] ?? 'default';
$avatar_url  = "https://api.dicebear.com/9.x/fun-emoji/svg?seed=" . urlencode($avatar_seed);

// 30 preset avatar seeds
$preset_seeds = [
    'sunny','happy','cool','star','moon','cloud','fire','ice','wave','leaf',
    'stone','wind','spark','bold','calm','swift','bright','deep','pure','sharp',
    'wild','soft','keen','zest','glow','rush','dawn','dusk','mist','peak'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile — NoteSwap</title>
    <script>const t=localStorage.getItem('theme')||'dark';document.documentElement.setAttribute('data-theme',t);</script>
    <link rel="stylesheet" href="css/style.css">
    <style>
        [data-theme="dark"]  body { background:#221f31; color:#f3f0ff; }
[data-theme="light"] body { background:#efeef3; color:#111827; }

        .page-container { max-width:1000px; margin:0 auto; padding:32px 20px 60px; }

        /* PROFILE LAYOUT */
        .profile-layout { display:grid; grid-template-columns:280px 1fr; gap:24px; align-items:start; }

        /* LEFT CARD */
        .profile-left-card {
            border-radius:16px; border:1px solid; padding:28px 20px; text-align:center;
        }
        [data-theme="dark"]  .profile-left-card { background:#1a1825; border-color:#2e2a42; }
        [data-theme="light"] .profile-left-card { background:#ffffff;  border-color:#e5e7eb; }

        /* AVATAR */
        .profile-avatar-wrap {
            position:relative; width:90px; height:90px;
            margin:0 auto 14px; cursor:pointer;
        }

        .profile-avatar-img {
            width:90px; height:90px; border-radius:50%;
            border:3px solid #7c3aed;
            background:#1e1b2e;
            display:block; object-fit:cover;
            transition:opacity 0.2s;
        }

        .profile-avatar-overlay {
            position:absolute; inset:0; border-radius:50%;
            background:rgba(124,58,237,0.7);
            display:flex; align-items:center; justify-content:center;
            opacity:0; transition:opacity 0.2s;
            font-size:12px; font-weight:600; color:white;
            flex-direction:column; gap:3px;
        }

        .profile-avatar-wrap:hover .profile-avatar-overlay { opacity:1; }
        .profile-avatar-wrap:hover .profile-avatar-img { opacity:0.6; }

        .profile-name {
            font-size:18px; font-weight:700; margin-bottom:4px;
            font-family:'Plus Jakarta Sans',sans-serif;
        }
        [data-theme="dark"]  .profile-name { color:#f3f0ff; }
        [data-theme="light"] .profile-name { color:#111827; }

        .profile-email { font-size:13px; margin-bottom:12px; }
        [data-theme="dark"]  .profile-email { color:#8b82a7; }
        [data-theme="light"] .profile-email { color:#9ca3af; }

        .profile-badge {
            display:inline-block; background:rgba(124,58,237,0.15);
            color:#a78bfa; font-size:12px; font-weight:700;
            padding:4px 14px; border-radius:50px;
            border:1px solid rgba(124,58,237,0.3);
            margin-bottom:6px;
        }

        .profile-id { font-size:12px; margin-top:6px; }
        [data-theme="dark"]  .profile-id { color:#6b6480; }
        [data-theme="light"] .profile-id { color:#9ca3af; }

        /* Role badges (admin / top contributor) — by avatar */
        .profile-avatar-ring {
            position: absolute;
            inset: -4px;
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
        }
        .profile-avatar-wrap.has-admin .profile-avatar-ring {
            box-shadow: 0 0 0 2px #ef4444, 0 0 18px rgba(239, 68, 68, 0.35);
        }
        .profile-avatar-wrap.has-top .profile-avatar-img {
            box-shadow: 0 0 0 2px #f59e0b, 0 0 20px rgba(245, 158, 11, 0.25);
        }
        .profile-avatar-wrap.has-admin.has-top .profile-avatar-ring {
            box-shadow: 0 0 0 2px #ef4444, 0 0 14px rgba(239, 68, 68, 0.3);
        }
        .profile-avatar-img { position: relative; z-index: 1; }

        .profile-role-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            justify-content: center;
            margin: 10px 0 4px;
            min-height: 0;
        }
        .role-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 5px 11px;
            border-radius: 50px;
            border: 1px solid;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .role-tag-admin {
            background: rgba(239, 68, 68, 0.12);
            color: #f87171;
            border-color: rgba(239, 68, 68, 0.45);
        }
        .role-tag-top {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(234, 179, 8, 0.12));
            color: #fbbf24;
            border-color: rgba(245, 158, 11, 0.5);
        }
        [data-theme="light"] .role-tag-admin {
            background: #fef2f2;
            color: #b91c1c;
            border-color: #fecaca;
        }
        [data-theme="light"] .role-tag-top {
            color: #b45309;
            border-color: #fcd34d;
            background: #fffbeb;
        }
        .tier-hint {
            font-size: 11px;
            margin: 0 0 10px;
            text-align: center;
        }
        [data-theme="dark"]  .tier-hint { color: #8b82a7; }
        [data-theme="light"] .tier-hint { color: #6b7280; }

        /* STATS */
        .profile-stats {
            border-radius:12px; border:1px solid; overflow:hidden; margin-top:16px;
        }
        [data-theme="dark"]  .profile-stats { background:#150d2e; border-color:#2e2a42; }
        [data-theme="light"] .profile-stats { background:#f9fafb; border-color:#f3f4f6; }

        .stat-row {
            display:flex; justify-content:space-between; align-items:center;
            padding:13px 16px; border-bottom:1px solid;
        }
        [data-theme="dark"]  .stat-row { border-bottom-color:#1e1b2e; }
        [data-theme="light"] .stat-row { border-bottom-color:#f3f4f6; }
        .stat-row:last-child { border-bottom:none; }

        .stat-lbl { font-size:13px; }
        [data-theme="dark"]  .stat-lbl { color:#8b82a7; }
        [data-theme="light"] .stat-lbl { color:#6b7280; }

        .stat-val { font-size:18px; font-weight:700; color:#7c3aed; font-family:'Plus Jakarta Sans',sans-serif; }

        /* FRIENDS */
        .friends-card {
            border-radius:12px; border:1px solid; overflow:hidden; margin-top:16px;
        }
        [data-theme="dark"]  .friends-card { background:#1a1825; border-color:#2e2a42; }
        [data-theme="light"] .friends-card { background:#ffffff;  border-color:#e5e7eb; }

        .friends-head {
            font-size:10px; font-weight:700; text-transform:uppercase;
            letter-spacing:1px; padding:12px 16px; border-bottom:1px solid;
        }
        [data-theme="dark"]  .friends-head { color:#8b82a7; border-bottom-color:#2e2a42; }
        [data-theme="light"] .friends-head { color:#9ca3af; border-bottom-color:#f3f4f6; }

        .friend-row {
            display:flex; align-items:center; gap:10px;
            padding:11px 16px; border-bottom:1px solid; transition:background 0.15s;
        }
        [data-theme="dark"]  .friend-row { border-bottom-color:#1e1b2e; }
        [data-theme="light"] .friend-row { border-bottom-color:#f3f4f6; }
        .friend-row:last-child { border-bottom:none; }
        [data-theme="dark"]  .friend-row:hover { background:rgba(124,58,237,0.06); }
        [data-theme="light"] .friend-row:hover { background:#f5f3ff; }

        .friend-av-img {
            width:34px; height:34px; border-radius:50%;
            border:2px solid #2e2a42; object-fit:cover;
        }

        .friend-name { font-size:14px; font-weight:600; }
        [data-theme="dark"]  .friend-name { color:#f3f0ff; }
        [data-theme="light"] .friend-name { color:#111827; }

        .friend-sub { font-size:11px; }
        [data-theme="dark"]  .friend-sub { color:#8b82a7; }
        [data-theme="light"] .friend-sub { color:#9ca3af; }

        /* RIGHT CARDS */
        .profile-right { display:flex; flex-direction:column; gap:20px; }

        .profile-card {
            border-radius:14px; border:1px solid; padding:28px;
        }
        [data-theme="dark"]  .profile-card { background:#1a1825; border-color:#2e2a42; }
        [data-theme="light"] .profile-card { background:#ffffff;  border-color:#e5e7eb; }

        .profile-card h3 {
            font-size:17px; font-weight:700; margin-bottom:20px;
            font-family:'Plus Jakarta Sans',sans-serif;
        }
        [data-theme="dark"]  .profile-card h3 { color:#f3f0ff; }
        [data-theme="light"] .profile-card h3 { color:#111827; }

        /* FORM FIELDS */
        .pf-group { margin-bottom:16px; }
        .pf-label {
            display:block; font-size:11px; font-weight:700;
            text-transform:uppercase; letter-spacing:0.8px; margin-bottom:6px;
        }
        [data-theme="dark"]  .pf-label { color:#8b82a7; }
        [data-theme="light"] .pf-label { color:#6b7280; }

        .pf-input, .pf-select, .pf-textarea {
            width:100%; padding:11px 14px;
            border-radius:10px; border:1px solid;
            font-size:14px; font-family:'Inter',sans-serif;
            outline:none; transition:border-color 0.2s;
        }
        [data-theme="dark"]  .pf-input, [data-theme="dark"]  .pf-select,
        [data-theme="dark"]  .pf-textarea { background:#0f0e17; color:#f3f0ff; border-color:#2e2a42; }
        [data-theme="light"] .pf-input, [data-theme="light"] .pf-select,
        [data-theme="light"] .pf-textarea { background:#f9fafb; color:#111827; border-color:#e5e7eb; }
        .pf-input:focus, .pf-select:focus, .pf-textarea:focus { border-color:#7c3aed; }

        .pf-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }

        .pf-divider { border:none; border-top:1px solid; margin:20px 0; }
        [data-theme="dark"]  .pf-divider { border-color:#2e2a42; }
        [data-theme="light"] .pf-divider { border-color:#f3f4f6; }

        .pf-hint { font-size:12px; margin-bottom:14px; }
        [data-theme="dark"]  .pf-hint { color:#6b6480; }
        [data-theme="light"] .pf-hint { color:#9ca3af; }

        /* BUTTONS */
        .pf-btn {
            padding:10px 24px; border-radius:50px; border:none;
            font-size:13px; font-weight:600; cursor:pointer;
            font-family:'Plus Jakarta Sans',sans-serif; transition:all 0.2s;
            text-decoration:none; display:inline-flex; align-items:center; gap:6px;
        }
        .pf-btn-primary { background:#7c3aed; color:white; }
        .pf-btn-primary:hover { background:#6d28d9; }
        .pf-btn-secondary { background:rgba(124,58,237,0.12); color:#a78bfa; border:1px solid rgba(124,58,237,0.3); }
        .pf-btn-secondary:hover { background:#7c3aed; color:white; }

        /* MY UPLOADS CARD */
        .uploads-link-card {
            border-radius:14px; border:1px solid; padding:20px 24px;
            display:flex; justify-content:space-between; align-items:center;
            text-decoration:none; transition:border-color 0.2s;
        }
        [data-theme="dark"]  .uploads-link-card { background:#1a1825; border-color:#2e2a42; }
        [data-theme="light"] .uploads-link-card { background:#ffffff;  border-color:#e5e7eb; }
        .uploads-link-card:hover { border-color:#7c3aed; }

        .uploads-link-title { font-size:16px; font-weight:600; font-family:'Plus Jakarta Sans',sans-serif; }
        [data-theme="dark"]  .uploads-link-title { color:#f3f0ff; }
        [data-theme="light"] .uploads-link-title { color:#111827; }

        .uploads-link-sub { font-size:13px; margin-top:3px; }
        [data-theme="dark"]  .uploads-link-sub { color:#8b82a7; }
        [data-theme="light"] .uploads-link-sub { color:#9ca3af; }

        /* ALERTS */
        .pf-alert {
            padding:12px 18px; border-radius:10px;
            margin-bottom:20px; font-size:14px;
            display:flex; align-items:center; gap:8px;
        }
        .pf-alert-success { background:rgba(16,185,129,0.12); color:#34d399; border:1px solid rgba(16,185,129,0.3); }
        .pf-alert-error   { background:rgba(239,68,68,0.1);   color:#f87171; border:1px solid rgba(239,68,68,0.3); }

        /* ── AVATAR PICKER MODAL ── */
        .avatar-modal-overlay {
            display:none; position:fixed; inset:0;
            background:rgba(0,0,0,0.75); backdrop-filter:blur(4px);
            z-index:9999; align-items:center; justify-content:center;
        }
        .avatar-modal-overlay.open { display:flex; }

        .avatar-modal {
            border-radius:20px; padding:28px; width:92%; max-width:520px;
            max-height:80vh; overflow-y:auto;
            box-shadow:0 24px 60px rgba(0,0,0,0.5);
        }
        [data-theme="dark"]  .avatar-modal { background:#1a1825; border:1px solid #2e2a42; }
        [data-theme="light"] .avatar-modal { background:#ffffff;  border:1px solid #e5e7eb; }

        .avatar-modal-header {
            display:flex; justify-content:space-between; align-items:center;
            margin-bottom:20px;
        }

        .avatar-modal-title {
            font-size:18px; font-weight:700; font-family:'Plus Jakarta Sans',sans-serif;
        }
        [data-theme="dark"]  .avatar-modal-title { color:#f3f0ff; }
        [data-theme="light"] .avatar-modal-title { color:#111827; }

        .avatar-modal-close {
            background:none; border:none; cursor:pointer;
            font-size:18px; padding:4px 8px; border-radius:8px;
            transition:background 0.2s; line-height:1;
        }
        [data-theme="dark"]  .avatar-modal-close { color:#8b82a7; }
        [data-theme="light"] .avatar-modal-close { color:#6b7280; }
        [data-theme="dark"]  .avatar-modal-close:hover { background:#2e2a42; }
        [data-theme="light"] .avatar-modal-close:hover { background:#f3f4f6; }

        .avatar-modal-sub {
            font-size:13px; margin-bottom:20px;
        }
        [data-theme="dark"]  .avatar-modal-sub { color:#8b82a7; }
        [data-theme="light"] .avatar-modal-sub { color:#6b7280; }

        /* AVATAR GRID */
        .avatar-grid {
            display:grid; grid-template-columns:repeat(6,1fr); gap:12px;
            margin-bottom:24px;
        }

        .avatar-option {
            position:relative; cursor:pointer;
            border-radius:50%; border:3px solid transparent;
            transition:all 0.2s; overflow:hidden;
            aspect-ratio:1;
        }
        .avatar-option:hover { border-color:#7c3aed; transform:scale(1.08); }
        .avatar-option.selected { border-color:#f59e0b; box-shadow:0 0 0 2px #f59e0b; }

        .avatar-option img {
            width:100%; height:100%; display:block;
            border-radius:50%; object-fit:cover;
        }

        .avatar-option .av-check {
            position:absolute; inset:0; border-radius:50%;
            background:rgba(245,158,11,0.3);
            display:flex; align-items:center; justify-content:center;
            font-size:16px; opacity:0;
        }
        .avatar-option.selected .av-check { opacity:1; }

        .avatar-modal-footer {
            display:flex; gap:10px; justify-content:flex-end;
        }

        @media (max-width:768px) {
            .profile-layout { grid-template-columns:1fr; }
            .pf-row { grid-template-columns:1fr; }
            .avatar-grid { grid-template-columns:repeat(5,1fr); }
        }
        @media (max-width:480px) {
            .avatar-grid { grid-template-columns:repeat(4,1fr); }
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
        <a href="profile.php" class="nav-avatar" title="My Profile">
            <img src="<?= $avatar_url ?>" alt="avatar"
                 style="width:100%;height:100%;border-radius:50%;object-fit:cover">
        </a>
    </div>
</nav>

<div class="page-container">

    <?php if ($success): ?>
        <div class="pf-alert pf-alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="pf-alert pf-alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="profile-layout">

        <!-- LEFT -->
        <div>
            <div class="profile-left-card">

                <!-- AVATAR -->
                <?php
                $av_wrap_class = 'profile-avatar-wrap';
                if ($is_site_admin) {
                    $av_wrap_class .= ' has-admin';
                }
                if ($is_top_contributor) {
                    $av_wrap_class .= ' has-top';
                }
                ?>
                <div class="<?= htmlspecialchars($av_wrap_class) ?>" onclick="openAvatarModal()">
                    <?php if ($is_site_admin): ?>
                        <span class="profile-avatar-ring" aria-hidden="true"></span>
                    <?php endif; ?>
                    <img src="<?= $avatar_url ?>" alt="avatar" class="profile-avatar-img">
                    <div class="profile-avatar-overlay">
                        <span style="font-size:20px">✏️</span>
                        <span>Change</span>
                    </div>
                </div>

                <div class="profile-role-tags">
                    <?php if ($is_site_admin): ?>
                        <span class="role-tag role-tag-admin" title="This account can access the admin portal">🛡️ Admin</span>
                    <?php endif; ?>
                    <?php if ($is_top_contributor): ?>
                        <span class="role-tag role-tag-top" title="<?= htmlspecialchars($top_contributor_title) ?>">🏆 Top contributor</span>
                    <?php endif; ?>
                </div>

                <h2 class="profile-name"><?= htmlspecialchars($user['name']) ?></h2>
                <p class="profile-email"><?= htmlspecialchars($user['email']) ?></p>
                <div class="profile-badge">
                    <?= htmlspecialchars($user['section'] ?? 'BSCS') ?> &nbsp;·&nbsp;
                    Semester <?= $user['semester'] ?? 1 ?>
                </div>
                <p class="tier-hint"><?= htmlspecialchars($contributor_tier) ?> · <?= $contribution_score ?> pts</p>
                <p class="profile-id">ID: <strong style="color:#7c3aed">#<?= $user_id ?></strong></p>

                <!-- STATS -->
                <div class="profile-stats">
                    <div class="stat-row">
                        <span class="stat-lbl">Notes uploaded</span>
                        <span class="stat-val"><?= $note_count ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-lbl">Contribution score</span>
                        <span class="stat-val"><?= $contribution_score ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-lbl">Friends</span>
                        <span class="stat-val"><?= count($friends) ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-lbl">Saved notes</span>
                        <span class="stat-val"><?= $fav_count ?></span>
                    </div>
                </div>

                <!-- FRIENDS -->
                <?php if (!empty($friends)): ?>
                <div class="friends-card">
                    <p class="friends-head">My Friends</p>
                    <?php foreach ($friends as $f):
                        $f_seed = $f['avatar_seed'] ?? 'default';
                        $f_av   = "https://api.dicebear.com/9.x/fun-emoji/svg?seed=" . urlencode($f_seed);
                    ?>
                    <div class="friend-row">
                        <img src="<?= $f_av ?>" alt="avatar" class="friend-av-img">
                        <div style="flex:1;min-width:0">
                            <div class="friend-name"><?= htmlspecialchars($f['name']) ?></div>
                            <div class="friend-sub">
                                <?= htmlspecialchars($f['section'] ?? '') ?>
                                <?= $f['semester'] ? '· Sem '.$f['semester'] : '' ?>
                            </div>
                        </div>
                        <a href="messages.php?with=<?= $f['id'] ?>"
                           style="font-size:18px;text-decoration:none">💬</a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- RIGHT -->
        <div class="profile-right">

            <!-- EDIT PROFILE -->
            <div class="profile-card">
                <h3>✏️ Edit Profile</h3>
                <form method="POST">
                    <div class="pf-group">
                        <label class="pf-label">Full Name</label>
                        <input type="text" name="name" class="pf-input"
                               value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>
                    <div class="pf-row">
                        <div class="pf-group">
                            <label class="pf-label">Section</label>
                            <select name="section" class="pf-select">
                                <option value="BSCS" <?= ($user['section']??'')==='BSCS'?'selected':'' ?>>BSCS</option>
                                <option value="BSAI" <?= ($user['section']??'')==='BSAI'?'selected':'' ?>>BSAI</option>
                                <option value="BSSE" <?= ($user['section']??'')==='BSSE'?'selected':'' ?>>BSSE</option>
                            </select>
                        </div>
                        <div class="pf-group">
                            <label class="pf-label">Semester</label>
                            <select name="semester" class="pf-select">
                                <?php for ($i=1;$i<=8;$i++): ?>
                                    <option value="<?=$i?>" <?=($user['semester']??1)==$i?'selected':''?>>
                                        Semester <?=$i?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <hr class="pf-divider">
                    <p class="pf-hint">Leave blank to keep your current password.</p>
                    <div class="pf-group">
                        <label class="pf-label">New Password</label>
                        <input type="password" name="new_password" class="pf-input"
                               placeholder="Leave empty to keep current">
                    </div>
                    <div class="pf-group">
                        <label class="pf-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="pf-input"
                               placeholder="Repeat new password">
                    </div>
                    <button type="submit" name="update_profile" class="pf-btn pf-btn-primary">
                        💾 Save Changes
                    </button>
                </form>
            </div>

            <!-- MY UPLOADS LINK -->
            <a href="my_notes.php" class="uploads-link-card">
                <div>
                    <div class="uploads-link-title">📂 My Uploaded Notes</div>
                    <div class="uploads-link-sub">View, edit and delete all your notes</div>
                </div>
                <span style="font-size:20px;color:#7c3aed">→</span>
            </a>

            <a href="favorites.php" class="uploads-link-card">
                <div>
                    <div class="uploads-link-title">⭐ My Favorites</div>
                    <div class="uploads-link-sub">
                        <?= $fav_count ?> saved note<?= $fav_count === 1 ? '' : 's' ?> — open your list
                    </div>
                </div>
                <span style="font-size:20px;color:#7c3aed">→</span>
            </a>

        </div>
    </div>
</div>

<!-- AVATAR PICKER MODAL -->
<div class="avatar-modal-overlay" id="avatar-modal-overlay">
    <div class="avatar-modal">
        <div class="avatar-modal-header">
            <span class="avatar-modal-title">Choose your avatar</span>
            <button class="avatar-modal-close" onclick="closeAvatarModal()">✕</button>
        </div>
        <p class="avatar-modal-sub">Click an avatar to select it, then click Save.</p>

        <div class="avatar-grid" id="avatar-grid">
            <?php foreach ($preset_seeds as $seed): ?>
            <div class="avatar-option <?= $seed === $avatar_seed ? 'selected' : '' ?>"
                 onclick="selectAvatar('<?= $seed ?>', this)"
                 data-seed="<?= $seed ?>">
                <img src="https://api.dicebear.com/9.x/fun-emoji/svg?seed=<?= urlencode($seed) ?>"
                     alt="<?= $seed ?>" loading="lazy">
                <div class="av-check">✓</div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="avatar-modal-footer">
            <button class="pf-btn pf-btn-secondary" onclick="closeAvatarModal()">Cancel</button>
            <form method="POST" id="avatar-form" style="display:inline">
                <input type="hidden" name="avatar_seed" id="selected-seed-input"
                       value="<?= htmlspecialchars($avatar_seed) ?>">
                <button type="submit" name="save_avatar" class="pf-btn pf-btn-primary">
                    ✅ Save Avatar
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// Avatar modal
function openAvatarModal() {
    document.getElementById('avatar-modal-overlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeAvatarModal() {
    document.getElementById('avatar-modal-overlay').classList.remove('open');
    document.body.style.overflow = '';
}
function selectAvatar(seed, el) {
    document.querySelectorAll('.avatar-option').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('selected-seed-input').value = seed;
}
document.getElementById('avatar-modal-overlay').addEventListener('click', function(e) {
    if (e.target === this) closeAvatarModal();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAvatarModal(); });

// Dark mode
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
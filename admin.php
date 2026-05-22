<?php
date_default_timezone_set('Asia/Karachi');
require_once 'db.php';
require_once 'contributor_helper.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$admin_name = $_SESSION['admin_name'];
$msg = "";
$msg_type = "success";

// DELETE USER
if (isset($_POST['delete_user'])) {
    $uid = intval($_POST['user_id']);
    $stmt = $pdo->prepare("SELECT nf.filename FROM note_files nf JOIN notes n ON nf.note_id = n.id WHERE n.user_id = ?");
    $stmt->execute([$uid]);
    foreach ($stmt->fetchAll() as $f) { $p = __DIR__."/uploads/".$f['filename']; if(file_exists($p)) unlink($p); }
    $stmt = $pdo->prepare("SELECT filename FROM chat_messages WHERE (sender_id=? OR receiver_id=?) AND filename IS NOT NULL");
    $stmt->execute([$uid,$uid]);
    foreach ($stmt->fetchAll() as $f) { $p = __DIR__."/uploads/".$f['filename']; if(file_exists($p)) unlink($p); }
    $pdo->prepare("DELETE FROM comments WHERE user_id=?")->execute([$uid]);
    $pdo->prepare("DELETE FROM chat_messages WHERE sender_id=? OR receiver_id=?")->execute([$uid,$uid]);
    $pdo->prepare("DELETE FROM friend_requests WHERE sender_id=? OR receiver_id=?")->execute([$uid,$uid]);
    $pdo->prepare("DELETE FROM note_files WHERE note_id IN (SELECT id FROM notes WHERE user_id=?)")->execute([$uid]);
    $pdo->prepare("DELETE FROM notes WHERE user_id=?")->execute([$uid]);
    try {
        ns_ensure_manual_top_contributor_table($pdo);
        $pdo->prepare('DELETE FROM manual_top_contributors WHERE user_id = ?')->execute([$uid]);
    } catch (Exception $e) {
        /* ignore */
    }
    $pdo->prepare("DELETE FROM users WHERE id=? AND is_admin=0")->execute([$uid]);
    $msg = "User deleted successfully.";
    header("Location: admin.php?tab=users&msg=".urlencode($msg)); exit();
}

// DELETE NOTE
if (isset($_POST['delete_note'])) {
    $nid = intval($_POST['note_id']);
    $stmt = $pdo->prepare("SELECT filename FROM note_files WHERE note_id=?");
    $stmt->execute([$nid]);
    foreach ($stmt->fetchAll() as $f) { $p = __DIR__."/uploads/".$f['filename']; if(file_exists($p)) unlink($p); }
    $pdo->prepare("DELETE FROM comments WHERE note_id=?")->execute([$nid]);
    $pdo->prepare("DELETE FROM note_files WHERE note_id=?")->execute([$nid]);
    $pdo->prepare("DELETE FROM notes WHERE id=?")->execute([$nid]);
    $msg = "Note deleted.";
    header("Location: admin.php?tab=notes&msg=".urlencode($msg)); exit();
}

// TOGGLE ADMIN
if (isset($_POST['toggle_admin'])) {
    $uid = intval($_POST['user_id']);
    $cur = intval($_POST['current_admin']);
    $new = $cur ? 0 : 1;
    $pdo->prepare("UPDATE users SET is_admin=? WHERE id=?")->execute([$new,$uid]);
    $msg = $new ? "Promoted to admin." : "Admin rights removed.";
    header("Location: admin.php?tab=users&msg=".urlencode($msg)); exit();
}

// Manual Top contributor (additive — does not change upload-based eligibility for others)
if (isset($_POST['grant_manual_top_contributor'])) {
    $uid = (int) ($_POST['user_id'] ?? 0);
    if ($uid > 0) {
        try {
            ns_grant_manual_top_contributor($pdo, $uid, (int) $_SESSION['admin_id']);
            $msg = 'Top contributor tag granted (admin). Upload-based tags are unchanged.';
        } catch (Exception $e) {
            $msg = 'Could not grant Top contributor tag.';
        }
    }
    header('Location: admin.php?tab=users&msg=' . urlencode($msg));
    exit();
}

if (isset($_POST['revoke_manual_top_contributor'])) {
    $uid = (int) ($_POST['user_id'] ?? 0);
    if ($uid > 0) {
        try {
            ns_revoke_manual_top_contributor($pdo, $uid);
            $msg = 'Admin Top contributor tag removed (upload-based tag stays if they still qualify).';
        } catch (Exception $e) {
            $msg = 'Could not remove manual tag.';
        }
    }
    header('Location: admin.php?tab=users&msg=' . urlencode($msg));
    exit();
}

if (isset($_GET['msg'])) $msg = $_GET['msg'];

$tab = $_GET['tab'] ?? 'overview';

// STATS
$total_users    = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_notes    = $pdo->query("SELECT COUNT(*) FROM notes")->fetchColumn();
$total_messages = $pdo->query("SELECT COUNT(*) FROM chat_messages")->fetchColumn();
$total_files    = $pdo->query("SELECT COUNT(*) FROM note_files")->fetchColumn();
$total_comments = $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();
$top_uploaders  = $pdo->query("SELECT users.name,users.section,users.semester,COUNT(notes.id) AS uploads FROM users LEFT JOIN notes ON notes.user_id=users.id GROUP BY users.id ORDER BY uploads DESC LIMIT 5")->fetchAll();
$top_notes      = $pdo->query("SELECT notes.title,notes.views,users.name AS uploader FROM notes JOIN users ON notes.user_id=users.id ORDER BY notes.views DESC LIMIT 5")->fetchAll();
$top_subjects   = $pdo->query("SELECT subject,COUNT(*) AS cnt FROM notes GROUP BY subject ORDER BY cnt DESC LIMIT 5")->fetchAll();
$sections       = $pdo->query("SELECT section,COUNT(*) AS cnt FROM users GROUP BY section")->fetchAll();
$recent_users   = $pdo->query("SELECT name,email,section,semester,created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();

$top_rule_admin       = null;
$manual_top_id_set    = [];
if ($tab === 'users') {
    $top_rule_admin    = ns_top_contributor_rule($pdo);
    $manual_top_id_set = ns_manual_top_contributor_id_set($pdo);
    $users = $pdo->query("SELECT users.*,(SELECT COUNT(*) FROM notes WHERE user_id=users.id) AS nc,(SELECT COUNT(*) FROM chat_messages WHERE sender_id=users.id) AS mc,(SELECT COUNT(*) FROM friend_requests WHERE (sender_id=users.id OR receiver_id=users.id) AND status='accepted') AS fc FROM users ORDER BY users.created_at DESC")->fetchAll();
}
if ($tab === 'notes') {
    $all_notes = $pdo->query("SELECT notes.*,users.name AS uploader,(SELECT COUNT(*) FROM note_files WHERE note_id=notes.id) AS fc,(SELECT COUNT(*) FROM comments WHERE note_id=notes.id) AS cc FROM notes JOIN users ON notes.user_id=users.id ORDER BY notes.created_at DESC")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — NoteSwap</title>
<script>var th=localStorage.getItem('theme')||'dark';document.documentElement.setAttribute('data-theme',th);</script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
[data-theme=dark]{--bg:#0f0e17;--card:#1a1825;--card2:#1e1b2e;--border:#2e2a42;--text:#f3f0ff;--muted:#8b82a7}
[data-theme=light]{--bg:#f0f4f8;--card:#fff;--card2:#f9fafb;--border:#e5e7eb;--text:#111827;--muted:#6b7280}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
a{text-decoration:none;color:inherit}

/* NAV */
nav{display:flex;align-items:center;justify-content:space-between;padding:12px 24px;background:var(--card);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:99}
.brand{display:flex;align-items:center;gap:8px;font-family:'Plus Jakarta Sans',sans-serif;font-size:18px;font-weight:800;background:linear-gradient(135deg,#ef4444,#f97316);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.nav-r{display:flex;align-items:center;gap:8px}
.nbtn{padding:6px 14px;border-radius:50px;font-size:12px;font-weight:600;border:1px solid var(--border);background:var(--card2);color:var(--muted);cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif}
.nbtn:hover{border-color:#ef4444;color:#ef4444}
.nbtn-red{background:#ef4444;color:#fff;border-color:#ef4444}
.nbtn-red:hover{opacity:.88;color:#fff}

/* TABS */
.tabs{display:flex;gap:2px;padding:0 24px;background:var(--card);border-bottom:1px solid var(--border);overflow-x:auto}
.tabs a{display:block;padding:12px 20px;font-size:13px;font-weight:600;color:var(--muted);border-bottom:3px solid transparent;white-space:nowrap;font-family:'Plus Jakarta Sans',sans-serif;transition:color .2s}
.tabs a:hover{color:var(--text)}
.tabs a.on{color:#7c3aed;border-bottom-color:#7c3aed}

/* CONTENT */
.wrap{max-width:1280px;margin:0 auto;padding:24px 20px 60px}

/* ALERT */
.alert{padding:12px 18px;border-radius:10px;margin-bottom:20px;font-size:13px;background:rgba(16,185,129,.12);color:#34d399;border:1px solid rgba(16,185,129,.3)}

/* STATS */
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:24px}
.scard{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:18px;transition:all .2s}
.scard:hover{border-color:#7c3aed;transform:translateY(-2px)}
.snum{display:block;font-size:30px;font-weight:800;color:#7c3aed;font-family:'Plus Jakarta Sans',sans-serif;margin-bottom:4px}
.slbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted)}

/* TOP GRID */
.tgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin-bottom:24px}
.tcard{background:var(--card);border:1px solid var(--border);border-radius:14px;overflow:hidden}
.thead{padding:12px 16px;border-bottom:1px solid var(--border);font-size:13px;font-weight:700;font-family:'Plus Jakarta Sans',sans-serif}
.trow{display:flex;justify-content:space-between;align-items:center;padding:10px 16px;border-bottom:1px solid var(--border);font-size:13px}
.trow:last-child{border-bottom:none}
.tname{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:68%;color:var(--text)}
.tval{font-weight:700;color:#7c3aed;font-family:'Plus Jakarta Sans',sans-serif;flex-shrink:0}

/* TABLE */
.th2{font-size:15px;font-weight:700;font-family:'Plus Jakarta Sans',sans-serif;margin:24px 0 12px}
.twrap{overflow-x:auto;border-radius:12px;border:1px solid var(--border)}
table{width:100%;border-collapse:collapse;font-size:13px}
th{background:var(--card2);padding:10px 12px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);border-bottom:1px solid var(--border)}
td{padding:10px 12px;border-bottom:1px solid var(--border);color:var(--text);vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:rgba(124,58,237,.04)}

/* BADGES */
.b{display:inline-flex;align-items:center;padding:2px 9px;border-radius:50px;font-size:11px;font-weight:700}
.bp{background:rgba(124,58,237,.15);color:#a78bfa}
.br{background:rgba(239,68,68,.12);color:#f87171}
.bg{background:rgba(16,185,129,.12);color:#34d399}
.ba{background:rgba(245,158,11,.12);color:#f59e0b}

/* ACTION BTN */
.ab{padding:4px 11px;border-radius:50px;font-size:11px;font-weight:700;cursor:pointer;border:1px solid;font-family:'Plus Jakarta Sans',sans-serif;transition:all .2s}
.ab-del{background:rgba(239,68,68,.12);color:#f87171;border-color:rgba(239,68,68,.3)}
.ab-del:hover{background:#ef4444;color:#fff}
.ab-pro{background:rgba(245,158,11,.12);color:#f59e0b;border-color:rgba(245,158,11,.3)}
.ab-pro:hover{background:#f59e0b;color:#0f0e17}
.ab-view{background:rgba(124,58,237,.12);color:#a78bfa;border-color:rgba(124,58,237,.3)}
.ab-view:hover{background:#7c3aed;color:#fff}
.ab-tc{background:rgba(245,158,11,.14);color:#f59e0b;border-color:rgba(245,158,11,.35)}
.ab-tc:hover{background:#f59e0b;color:#0f0e17}
.ab-tcx{background:rgba(107,114,128,.12);color:var(--muted);border-color:var(--border)}
.ab-tcx:hover{border-color:#ef4444;color:#f87171}

/* AVATAR */
.av{width:30px;height:30px;border-radius:50%;object-fit:cover;border:2px solid var(--border)}
.av-admin{box-shadow:0 0 0 2px #ef4444,0 0 12px rgba(239,68,68,.35);border-color:#fca5a5}

/* SEARCH */
.srch{width:100%;padding:9px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card2);color:var(--text);font-size:13px;outline:none;margin-bottom:14px;font-family:'Inter',sans-serif}
.srch:focus{border-color:#7c3aed}

@media(max-width:600px){
    nav{padding:10px 14px}
    .wrap{padding:16px 12px 40px}
    .tabs a{padding:10px 14px;font-size:12px}
}
</style>
</head>
<body>

<nav>
    <div class="brand">
        <svg width="22" height="22" viewBox="0 0 28 28" fill="none">
            <rect x="3" y="2" width="16" height="20" rx="3" stroke="#ef4444" stroke-width="2" fill="none"/>
            <path d="M7 8h8M7 12h6" stroke="#ef4444" stroke-width="2" stroke-linecap="round"/>
            <path d="M14 18c0-2.2 1.8-4 4-4s4 1.8 4 4-1.8 4-4 4-4-1.8-4-4z" stroke="#f59e0b" stroke-width="2" fill="none"/>
            <path d="M20.5 21.5l2.5 2.5" stroke="#f59e0b" stroke-width="2" stroke-linecap="round"/>
        </svg>
        NoteSwap Admin
    </div>
    <div class="nav-r">
        <span style="font-size:13px;color:var(--muted)">👋 <?= htmlspecialchars($admin_name) ?></span>
        <a href="dashboard.php" class="nbtn">← Student View</a>
        <button class="nbtn" onclick="toggleDark()" id="tbtn">🌙</button>
        <a href="admin_logout.php" class="nbtn nbtn-red">Logout</a>
    </div>
</nav>

<div class="tabs">
    <a href="admin.php?tab=overview" class="<?= $tab==='overview'?'on':'' ?>">📊 Overview</a>
    <a href="admin.php?tab=users"    class="<?= $tab==='users'   ?'on':'' ?>">👥 Users (<?= $total_users ?>)</a>
    <a href="admin.php?tab=notes"    class="<?= $tab==='notes'   ?'on':'' ?>">📂 Notes (<?= $total_notes ?>)</a>
    <a href="admin.php?tab=reports"  class="<?= $tab==='reports' ?'on':'' ?>">📈 Reports</a>
</div>

<div class="wrap">

<?php if ($msg): ?>
<div class="alert">✅ <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if ($tab === 'overview'): ?>
<!-- ══ OVERVIEW ══ -->
<div class="stats">
    <div class="scard"><span class="snum"><?= $total_users ?></span><span class="slbl">👥 Users</span></div>
    <div class="scard"><span class="snum"><?= $total_notes ?></span><span class="slbl">📂 Notes</span></div>
    <div class="scard"><span class="snum"><?= $total_files ?></span><span class="slbl">📎 Files</span></div>
    <div class="scard"><span class="snum"><?= $total_messages ?></span><span class="slbl">💬 Messages</span></div>
    <div class="scard"><span class="snum"><?= $total_comments ?></span><span class="slbl">🗨️ Comments</span></div>
</div>

<div class="tgrid">
    <div class="tcard">
        <div class="thead">🏆 Top Uploaders</div>
        <?php foreach ($top_uploaders as $u): ?>
        <div class="trow">
            <span class="tname"><?= htmlspecialchars($u['name']) ?> <span style="color:var(--muted);font-size:11px"><?= htmlspecialchars($u['section']??'') ?></span></span>
            <span class="tval"><?= $u['uploads'] ?> notes</span>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="tcard">
        <div class="thead">👁 Most Viewed Notes</div>
        <?php foreach ($top_notes as $n): ?>
        <div class="trow">
            <span class="tname"><?= htmlspecialchars(mb_substr($n['title'],0,26)) ?><?= mb_strlen($n['title'])>26?'...':'' ?></span>
            <span class="tval"><?= $n['views'] ?> views</span>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="tcard">
        <div class="thead">📚 Top Subjects</div>
        <?php foreach ($top_subjects as $s): ?>
        <div class="trow">
            <span class="tname"><?= htmlspecialchars(mb_substr($s['subject'],0,26)) ?></span>
            <span class="tval"><?= $s['cnt'] ?> notes</span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="th2">🆕 Recent Sign-ups</div>
<div class="twrap">
    <table>
        <thead><tr><th>Name</th><th>Email</th><th>Section</th><th>Semester</th><th>Joined</th></tr></thead>
        <tbody>
        <?php foreach ($recent_users as $u): ?>
        <tr>
            <td><?= htmlspecialchars($u['name']) ?></td>
            <td style="color:var(--muted)"><?= htmlspecialchars($u['email']) ?></td>
            <td><span class="b bp"><?= htmlspecialchars($u['section']??'N/A') ?></span></td>
            <td>Sem <?= $u['semester']??'-' ?></td>
            <td style="color:var(--muted)"><?= date('d M Y',strtotime($u['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php elseif ($tab === 'users'): ?>
<!-- ══ USERS ══ -->
<div class="th2">👥 All Users</div>
<input class="srch" placeholder="🔍 Search by name or email..." oninput="filterTable(this,'utbl')">
<div class="twrap">
    <table id="utbl">
        <thead><tr><th>ID</th><th>Av</th><th>Name</th><th>Email</th><th>Section</th><th>Sem</th><th>Notes</th><th>Msgs</th><th>🏆 Top</th><th>Role</th><th>Joined</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u):
            $av = "https://api.dicebear.com/9.x/fun-emoji/svg?seed=".urlencode($u['avatar_seed']??'default');
            $uid = (int) $u['id'];
            $nc  = (int) $u['nc'];
            $tc_auto = $top_rule_admin ? ns_is_top_contributor($uid, $nc, $top_rule_admin) : false;
            $tc_man  = isset($manual_top_id_set[$uid]);
        ?>
        <tr>
            <td style="color:var(--muted)">#<?= $u['id'] ?></td>
            <td><img src="<?= $av ?>" class="av<?= !empty($u['is_admin']) ? ' av-admin' : '' ?>" alt="" title="<?= !empty($u['is_admin']) ? 'Admin' : '' ?>"></td>
            <td style="font-weight:600"><?= htmlspecialchars($u['name']) ?></td>
            <td style="color:var(--muted);font-size:12px"><?= htmlspecialchars($u['email']) ?></td>
            <td><span class="b bp"><?= htmlspecialchars($u['section']??'N/A') ?></span></td>
            <td><?= $u['semester']??'-' ?></td>
            <td><span class="b ba"><?= $u['nc'] ?></span></td>
            <td><?= $u['mc'] ?></td>
            <td style="font-size:11px;max-width:120px">
                <?php if ($tc_auto): ?><span class="b ba" title="Upload-based Top contributor">Upload</span><?php endif; ?>
                <?php if ($tc_man): ?><?= $tc_auto ? ' ' : '' ?><span class="b ba" style="background:rgba(245,158,11,.15);color:#f59e0b;border-color:rgba(245,158,11,.35)" title="Admin-granted (extra)">Admin</span><?php endif; ?>
                <?php if (!$tc_auto && !$tc_man): ?>—<?php endif; ?>
            </td>
            <td><?= $u['is_admin'] ? '<span class="b br">Admin</span>' : '<span class="b bg">Student</span>' ?></td>
            <td style="color:var(--muted);font-size:11px"><?= date('d M Y',strtotime($u['created_at'])) ?></td>
            <td>
                <div style="display:flex;gap:5px;flex-wrap:wrap;max-width:220px">
                    <?php if ($tc_man): ?>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <button type="submit" name="revoke_manual_top_contributor" class="ab ab-tcx"
                            title="Remove only the admin-given tag">🏆 Revoke</button>
                    </form>
                    <?php else: ?>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <button type="submit" name="grant_manual_top_contributor" class="ab ab-tc"
                            title="Adds Top contributor in addition to upload-based winners">🏆 Grant</button>
                    </form>
                    <?php endif; ?>
                <?php if ($u['id'] != $_SESSION['admin_id']): ?>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <input type="hidden" name="current_admin" value="<?= $u['is_admin'] ?>">
                        <button type="submit" name="toggle_admin" class="ab ab-pro"
                            onclick="return confirm('<?= $u['is_admin']?'Remove admin?':'Make admin?' ?>')">
                            <?= $u['is_admin']?'⬇ Demote':'⬆ Promote' ?>
                        </button>
                    </form>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <button type="submit" name="delete_user" class="ab ab-del"
                            onclick="return confirm('Delete <?= htmlspecialchars(addslashes($u['name'])) ?> and ALL their data?')">
                            🗑
                        </button>
                    </form>
                <?php else: ?>
                    <span style="font-size:11px;color:var(--muted);align-self:center">You</span>
                <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php elseif ($tab === 'notes'): ?>
<!-- ══ NOTES ══ -->
<div class="th2">📂 All Notes</div>
<input class="srch" placeholder="🔍 Search by title, subject or uploader..." oninput="filterTable(this,'ntbl')">
<div class="twrap">
    <table id="ntbl">
        <thead><tr><th>ID</th><th>Title</th><th>Subject</th><th>Uploader</th><th>Section</th><th>Sem</th><th>Files</th><th>Views</th><th>Comments</th><th>Date</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($all_notes as $n): ?>
        <tr>
            <td style="color:var(--muted)">#<?= $n['id'] ?></td>
            <td style="font-weight:600;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($n['title']) ?>">
                <?= htmlspecialchars(mb_substr($n['title'],0,28)) ?><?= mb_strlen($n['title'])>28?'...':'' ?>
            </td>
            <td><span class="b bp" style="font-size:10px"><?= htmlspecialchars(mb_substr($n['subject'],0,18)) ?></span></td>
            <td><?= htmlspecialchars($n['uploader']) ?></td>
            <td><?= htmlspecialchars($n['section']) ?></td>
            <td><?= $n['semester'] ?></td>
            <td><span class="b ba"><?= $n['fc'] ?></span></td>
            <td>👁 <?= $n['views']??0 ?></td>
            <td>💬 <?= $n['cc'] ?></td>
            <td style="color:var(--muted);font-size:11px"><?= date('d M Y',strtotime($n['created_at'])) ?></td>
            <td>
                <div style="display:flex;gap:5px">
                    <a href="view_note.php?id=<?= $n['id'] ?>" class="ab ab-view" target="_blank">👁</a>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="note_id" value="<?= $n['id'] ?>">
                        <button type="submit" name="delete_note" class="ab ab-del"
                            onclick="return confirm('Delete this note?')">🗑</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php elseif ($tab === 'reports'): ?>
<!-- ══ REPORTS ══ -->
<div class="tgrid">
    <div class="tcard">
        <div class="thead">🎓 Users by Section</div>
        <?php foreach ($sections as $s): ?>
        <div class="trow">
            <span class="tname"><?= htmlspecialchars($s['section']??'Not Set') ?></span>
            <span class="tval"><?= $s['cnt'] ?> students</span>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="tcard">
        <div class="thead">🏆 Top Uploaders</div>
        <?php foreach ($top_uploaders as $u): ?>
        <div class="trow">
            <span class="tname"><?= htmlspecialchars($u['name']) ?></span>
            <span class="tval"><?= $u['uploads'] ?> notes</span>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="tcard">
        <div class="thead">👁 Most Viewed</div>
        <?php foreach ($top_notes as $n): ?>
        <div class="trow">
            <span class="tname"><?= htmlspecialchars(mb_substr($n['title'],0,24)) ?>...</span>
            <span class="tval"><?= $n['views'] ?> views</span>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="tcard">
        <div class="thead">📚 Top Subjects</div>
        <?php foreach ($top_subjects as $s): ?>
        <div class="trow">
            <span class="tname"><?= htmlspecialchars(mb_substr($s['subject'],0,26)) ?></span>
            <span class="tval"><?= $s['cnt'] ?> notes</span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="th2">📋 Platform Summary</div>
<div class="twrap">
    <table>
        <thead><tr><th>Metric</th><th>Value</th></tr></thead>
        <tbody>
            <tr><td>Total Users</td><td><strong><?= $total_users ?></strong></td></tr>
            <tr><td>Total Notes</td><td><strong><?= $total_notes ?></strong></td></tr>
            <tr><td>Total Files Uploaded</td><td><strong><?= $total_files ?></strong></td></tr>
            <tr><td>Total Messages</td><td><strong><?= $total_messages ?></strong></td></tr>
            <tr><td>Total Comments</td><td><strong><?= $total_comments ?></strong></td></tr>
            <tr><td>Avg Notes per User</td><td><strong><?= $total_users>0?round($total_notes/$total_users,1):0 ?></strong></td></tr>
        </tbody>
    </table>
</div>

<?php endif; ?>
</div>

<script>
function filterTable(inp, id) {
    var q = inp.value.toLowerCase();
    document.querySelectorAll('#'+id+' tbody tr').forEach(function(r){
        r.style.display = r.textContent.toLowerCase().indexOf(q) > -1 ? '' : 'none';
    });
}
function toggleDark() {
    var cur = document.documentElement.getAttribute('data-theme');
    var nxt = cur==='dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', nxt);
    document.getElementById('tbtn').textContent = nxt==='dark' ? '🌙' : '☀️';
    localStorage.setItem('theme', nxt);
}
document.getElementById('tbtn').textContent = (localStorage.getItem('theme')||'dark')==='dark' ? '🌙' : '☀️';
</script>
</body>
</html>
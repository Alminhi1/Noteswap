<?php
date_default_timezone_set('Asia/Karachi');
require_once 'db.php';
require_once 'bookmark_helper.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

$stmt = $pdo->prepare("SELECT section, semester, avatar_seed FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_prefs  = $stmt->fetch();
$avatar_seed = $user_prefs['avatar_seed'] ?? 'default';
$avatar_url  = "https://api.dicebear.com/9.x/fun-emoji/svg?seed=" . urlencode($avatar_seed);

$filter_section  = $_GET['section']  ?? ($user_prefs['section']  ?? 'BSCS');
$filter_semester = intval($_GET['semester'] ?? ($user_prefs['semester'] ?? 1));
$search_query    = trim($_GET['search'] ?? '');

$stmt = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE receiver_id = ? AND is_read = 0");
$stmt->execute([$user_id]);
$unread_count = $stmt->fetchColumn();

$notes  = [];
$where  = [];
$params = [];

if ($filter_section !== 'ALL') {
    if ($filter_semester == 0) {
        // All semesters for this section — get all subjects
        $stmt = $pdo->prepare("SELECT DISTINCT subject FROM curriculum WHERE section = ?");
        $stmt->execute([$filter_section]);
    } else {
        // Specific semester — get subjects for this semester
        $stmt = $pdo->prepare("SELECT DISTINCT subject FROM curriculum WHERE section = ? AND semester = ?");
        $stmt->execute([$filter_section, $filter_semester]);
    }
    $curriculum_subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($curriculum_subjects)) {
        $placeholders = implode(',', array_fill(0, count($curriculum_subjects), '?'));
        // Match notes by SUBJECT ONLY — not by semester
        // This way Multivariable Calculus notes appear in both Sem 2 and Sem 4
        $where[]      = "notes.subject IN ($placeholders)";
        $params       = array_merge($params, $curriculum_subjects);
    }
    // We do NOT filter by notes.semester anymore
    // The curriculum already tells us which subjects belong to which semester
} else {
    // ALL sections selected
    if ($filter_semester != 0) {
        // Get all subjects that exist in this semester across any section
        $stmt = $pdo->prepare("SELECT DISTINCT subject FROM curriculum WHERE semester = ?");
        $stmt->execute([$filter_semester]);
        $all_sem_subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($all_sem_subjects)) {
            $placeholders = implode(',', array_fill(0, count($all_sem_subjects), '?'));
            $where[]      = "notes.subject IN ($placeholders)";
            $params       = array_merge($params, $all_sem_subjects);
        }
    }
    // If All sections + All semesters — no subject filter, show everything
}

if (!empty($search_query)) {
    $where[]  = "(notes.title LIKE ? OR notes.subject LIKE ? OR notes.description LIKE ?)";
    $st       = "%" . $search_query . "%";
    $params[] = $st; $params[] = $st; $params[] = $st;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

try {
    ensure_note_user_bookmarks_table($pdo);
} catch (Exception $e) {
    // list still loads without pinning if table creation fails
}

$bookmark_redirect_params = ['section' => $filter_section, 'semester' => $filter_semester];
if ($search_query !== '') {
    $bookmark_redirect_params['search'] = $search_query;
}
$bookmark_redirect = 'dashboard.php?' . http_build_query($bookmark_redirect_params);

$stmt = $pdo->prepare("
    SELECT notes.*, users.name AS uploader,
           (SELECT COUNT(*) FROM comments WHERE comments.note_id = notes.id) AS comment_count,
           IFNULL(nub.is_pinned, 0) AS user_is_pinned,
           IFNULL(nub.is_favorite, 0) AS user_is_favorite
    FROM notes JOIN users ON notes.user_id = users.id
    LEFT JOIN note_user_bookmarks nub ON nub.note_id = notes.id AND nub.user_id = ?
    $where_clause
    ORDER BY IFNULL(nub.is_pinned, 0) DESC, IFNULL(nub.is_favorite, 0) DESC, notes.created_at DESC
");
$stmt->execute(array_merge([$user_id], $params));
$notes = $stmt->fetchAll();

// Get user's subjects based on their section and semester
$user_section  = $user_prefs['section']  ?? 'BSCS';
$user_semester = $user_prefs['semester'] ?? 1;
$feed_section  = $filter_section  !== 'ALL' ? $filter_section  : $user_section;
$feed_semester = $filter_semester != 0      ? $filter_semester : $user_semester;

$stmt = $pdo->prepare("SELECT DISTINCT subject FROM curriculum WHERE section = ? AND semester = ?");
$stmt->execute([$feed_section, $feed_semester]);
$user_subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!empty($user_subjects)) {
    $feed_placeholders = implode(',', array_fill(0, count($user_subjects), '?'));
    $stmt = $pdo->prepare("
    SELECT notes.id, notes.title, notes.subject, notes.created_at, 
           users.name AS uploader, users.avatar_seed
    FROM notes JOIN users ON notes.user_id = users.id
    WHERE notes.subject IN ($feed_placeholders)
    AND notes.user_id != ?
    ORDER BY notes.created_at DESC
    LIMIT 10
");
    $feed_params = array_merge($user_subjects, [$user_id]);
    $stmt->execute($feed_params);
    $all_feed_notes = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("
    SELECT notes.id, notes.title, notes.subject, notes.created_at, 
           users.name AS uploader, users.avatar_seed
    FROM notes JOIN users ON notes.user_id = users.id
    WHERE notes.user_id != ?
    ORDER BY notes.created_at DESC LIMIT 10
");
    $stmt->execute([$user_id]);
    $all_feed_notes = $stmt->fetchAll();
}

$feed_notes      = array_slice($all_feed_notes, 0, 3);
$feed_extra      = count($all_feed_notes) > 3;
$feed_extra_count = max(0, count($all_feed_notes) - 3);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM notes WHERE user_id = ?");
$stmt->execute([$user_id]);
$my_uploads_count = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<title>Dashboard — NoteSwap</title>
<script>
const t = localStorage.getItem('theme') || 'dark';
document.documentElement.setAttribute('data-theme', t);
</script>
<link rel="stylesheet" href="css/style.css">
<style>
/* ── BASE ── */
/* ── TYPOGRAPHY ── */
:root {
    --font-jakarta: 'Plus Jakarta Sans', sans-serif;
    --font-inter: 'Inter', sans-serif;
}
.db-page { min-height:100vh; font-family:'Inter','Segoe UI',sans-serif; transition:background 0.3s,color 0.3s; }

[data-theme="dark"]  .db-page { background:#221f31; color:#f3f0ff; }
[data-theme="light"] .db-page { background:#efeef3; color:#111827; }

/* ── NAVBAR ── */
[data-theme="dark"]  .db-navbar { background:#0f0e17; border-bottom:1px solid #1e1b2e; }
[data-theme="light"] .db-navbar { background:#ffffff;  border-bottom:1px solid #e5e7eb; }

.db-navbar {
    padding:12px 28px;
    display:flex; align-items:center; justify-content:space-between;
    position:sticky; top:0; z-index:100;
    overflow:hidden;
}

.db-nav-brand {
    display:flex; align-items:center; gap:8px;
    font-size:18px; font-weight:700; text-decoration:none;
    margin-right: auto;
}
[data-theme="dark"]  .db-nav-brand { 
    background: linear-gradient(135deg, #7c3aed, #f59e0b);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
[data-theme="light"] .db-nav-brand { 
    background: linear-gradient(135deg, #7c3aed, #f59e0b);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.db-nav-right { display:flex; align-items:center; gap:10px; }

.db-nav-btn {
    display:flex; align-items:center; gap:6px;
    padding:8px 16px; border-radius:50px;
    font-size:13px; font-weight:600;
    cursor:pointer; text-decoration:none;
    border:1px solid transparent;
    font-family:'Inter',sans-serif; transition:all 0.2s; white-space:nowrap;
}
.db-nav-btn-purple { background:#7c3aed; color:white; border-color:#7c3aed; }
.db-nav-btn-purple:hover { background:#6d28d9; }
[data-theme="dark"]  .db-nav-btn-dark { background:#1e1b2e; color:#a89fc0; border-color:#2e2a42; }
[data-theme="light"] .db-nav-btn-dark { background:#f3f4f6; color:#4b5563; border-color:#e5e7eb; }
.db-nav-btn-dark:hover { border-color:#7c3aed !important; }

.db-nav-avatar {
    width:36px; height:36px; border-radius:50%;
    background:linear-gradient(135deg,#7c3aed,#f59e0b);
    color:white; font-size:14px; font-weight:700;
    display:flex; align-items:center; justify-content:center;
    text-decoration:none; border:2px solid transparent; transition:border-color 0.2s;
}
.db-nav-avatar:hover { border-color:#7c3aed; }

.chat-rel { position:relative; }
.notif-badge {
    position:absolute; top:-5px; right:-5px;
    background:#ef4444; color:white; font-size:9px; font-weight:700;
    width:16px; height:16px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
}

/* ── CONTAINER ── */
.db-container { max-width:1200px; margin:0 auto; padding:20px 20px 48px; }

/* ── WELCOME BANNER ── */
[data-theme="dark"] .welcome-banner { 
    background: #211C3D; /* The dark charcoal/slate */
    border: none;
    box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.5), 
                0 0 15px rgba(255, 255, 255, 0.02);
}
/* Light Theme: Simple & Clean */
[data-theme="light"] .welcome-banner { 
    background: #ffffff; 
    border: none; /* No border in the image */
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 
                0 4px 10px -5px rgba(0, 0, 0, 0.05);
}

/* Make all text black for light theme */
[data-theme="light"] .welcome-name {
    color: #000000; 
    font-weight: 800; /* Extra bold like the image */
}

/* Force the name span to be black too (overriding the gold) */
[data-theme="light"] .welcome-name span {
    color: #000000; 
}

/* Darker subtext for the light theme */
[data-theme="light"] .welcome-sub {
    color: #1a1a1a; 
    opacity: 0.9;
}

/* Remove the glow/gradient circle for the light theme */
[data-theme="light"] .welcome-banner::after {
    display: none; 
}

.welcome-banner {
    border-radius: 12px; 
    display: flex; 
    align-items: center; 
    overflow: hidden; 
    margin-bottom: 20px; 
    /* Reduced Y-axis height: removed min-height and used tighter padding */
    padding: 10px 0; 
    position: relative;
    border: 1px solid transparent;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.welcome-banner:hover {
    transform: translateY(-4px); /* Moves it up slightly */
    box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.3); /* Deepens the shadow */
}
.welcome-illo {
    width: 200px; /* Adjust this based on how wide your PNG is */
    min-width: 200px;
    display: flex; 
    align-items: flex-end; /* Keeps the character standing on the bottom edge */
    justify-content: center;
    padding-left: 20px;
    z-index: 1;
}

.banner-img {
    width: 100%;       /* Makes the image fill the container width */
    height: auto;      /* Maintains the aspect ratio */
    max-height: 150px; /* Limits the height so it doesn't stretch the banner Y-axis */
    object-fit: contain; /* Ensures the whole image is visible without cropping */
    display: block;
}

.welcome-text {
    flex: 1; 
    padding: 15px 40px 15px 20px; /* Tight padding to keep it slim */
    display: flex; 
    flex-direction: column; 
    justify-content: center;
    z-index: 1;
}

/* ── MAIN GRID ── */
.db-grid {
    display:grid;
    grid-template-columns:1fr 280px;
    gap:20px;
    align-items:start;
}
.db-sidebar {
    position: static;
    align-self: start;
}

/* ── FILTER + SEARCH ROW ── */
.filter-row-wrap {
    display:flex;
    align-items:center;
    gap:14px;
    margin-bottom:20px;
    
}

/* Filter card */
/* --- THEME COLORS --- */
[data-theme="dark"] .db-filter-card { 
    background: #1a1825; 
    border: 1px solid #2e2a42;
}


[data-theme="light"] .db-filter-card { 
    background: #ffffff; 
    border: 1px solid #e5e7eb; /* Light border seen in pic */
}


[data-theme="light"] .db-filter-lbl {
    color: #000000; /* Filter Notes is black in light mode */
}

[data-theme="light"] .db-dd-lbl {
    color: #6b7280; /* Section/Semester is medium grey */
}

/* Light theme dropdowns: Soft grey background */
[data-theme="light"] 
   .db-dd {
    background-color: #f3f4f6; /* Very light grey background for the pills */
    color: #1a1a1a; /* Dark text */
    border: 1px solid #e5e7eb;
    /* Black arrow for light mode */
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='black'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
}

/* Hover effect for light mode buttons */
[data-theme="light"] .db-dd:hover {
    background-color: #e5e7eb;
}
/* --- THE CARD CONTAINER --- */
.db-filter-card {
    border-radius: 14px; 
    padding: 14px 18px; /* Strict padding to keep height low */
    width: 350px; /* Increased width to allow dropdowns to be longer */
    box-shadow: none;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
   
}
.db-filter-card:hover {
    transform: translateY(-4px); /* Moves it up slightly */
    box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.3); /* Deepens the shadow */
}

/* --- MAIN LABEL (FILTER NOTES) --- */
.db-filter-lbl {
    font-family: var(--font-inter);
    font-size: 15px; 
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 12px;
    color: #6a6475; /* Muted color from your pic */
}

/* --- SECTION/SEMESTER LABELS --- */
.db-dd-lbl {
    display: block;
    font-family: var(--font-inter);
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    margin-bottom: 6px;
    color: #6a6475; 
}

/* --- THE DROPDOWNS (LONG & LIGHTER) --- */
.db-dd {
    width: 100%;
    padding: 8px 15px; /* Thinner padding for smaller height */
    border-radius: 12px; /* Slightly less rounded, more like the pic */
    font-family: var(--font-inter);
    font-size: 13px; 
    font-weight: 600;
    color: #ffffff;
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='white'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 12px;
    min-width: 0;
}

/* Dark mode dropdown (Lighter than pitch black) */
[data-theme="dark"] .db-dd {
    background-color: #242231; /* Lighter grey-navy to match the pic */
    border: 1px solid #2e2a42;
}

.db-filter-row { 
    display: flex; 
    gap: 12px; /* Gap between the two dropdown groups */
}

.db-dd-group {
    flex: 1; /* This makes the dropdown containers long */
}
/* Search bar floating */
.db-search-float { flex:1; min-width:0; }

[data-theme="dark"]  .db-search-wrap { background:#1a1825; border-color:#2e2a42; }
[data-theme="light"] .db-search-wrap { background:#ffffff;  border-color:#e5e7eb; }

.db-search-wrap {
    display:flex; align-items:center;
    border:1px solid; border-radius:50px;
    padding:0 6px 0 14px; gap:8px;
    transition:border-color 0.2s; width:100%;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.db-search-wrap:hover {
    transform: translateY(-4px); /* Moves it up slightly */
    box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.3); /* Deepens the shadow */
}
.db-search-wrap:focus-within { border-color:#7c3aed; }

[data-theme="dark"]  .db-search-wrap input { color:#f3f0ff; }
[data-theme="light"] .db-search-wrap input { color:#111827; }

.db-search-wrap input {
    flex:1; background:none; border:none;
    font-size:13px; outline:none; padding:10px 0;
    font-family:'Inter',sans-serif; min-width:0;
}
.db-search-wrap input::placeholder { color:#8b82a7; }

.db-search-btn {
    background:#7c3aed; color:white; border:none;
    border-radius:50px; padding:6px 14px;
    font-size:12px; font-weight:600; cursor:pointer;
    font-family:'Inter',sans-serif; transition:background 0.2s;
    white-space:nowrap; flex-shrink:0;
}
.db-search-btn:hover { background:#6d28d9; }

/* ── SECTION HEADING ── */
[data-theme="dark"]  .db-sec-head { color:#8b82a7; }
[data-theme="light"] .db-sec-head { color:#6b7280; }

.db-sec-head {
    font-size:11px; font-weight:700;
    text-transform:uppercase; letter-spacing:1.5px;
    margin-bottom:14px;
    display:flex; align-items:center; gap:10px;
}

[data-theme="dark"]  .db-count-pill { background:#1e1b2e; border-color:#2e2a42; color:#8b82a7; }
[data-theme="light"] .db-count-pill { background:#f3f4f6; border-color:#e5e7eb; color:#6b7280; }

.db-count-pill {
    font-size:11px; font-weight:600;
    padding:2px 10px; border-radius:50px; border:1px solid;
    text-transform:none; letter-spacing:0;
}

/* ── NOTE CARDS ── */
.notes-grid-db {
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(280px,1fr));
    gap:16px; margin-bottom:28px;
}

[data-theme="dark"]  .note-card-db { background:#1e1b2e; border-color:#2a2645;transition: transform 0.3s ease, box-shadow 0.3s ease; }
[data-theme="dark"]  .note-card-db:hover {
    border-color: transparent;
    box-shadow: 0 8px 32px rgba(124,58,237,0.25);
    background-image: linear-gradient(#1e1b2e, #1e1b2e),
                      linear-gradient(to right, #7c3aed, #f59e0b);
    background-origin: border-box;
    background-clip: padding-box, border-box;
    transform: translateY(-4px); /* Moves it up slightly */
    box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.3); /* Deepens the shadow */

}
[data-theme="light"] .note-card-db { background:#ffffff; border-color:#e5e7eb;transition: transform 0.3s ease, box-shadow 0.3s ease; }
[data-theme="light"] .note-card-db:hover { border-color:#7c3aed; 
        box-shadow:0 8px 32px rgba(124,58,237,0.12);
        transform: translateY(-4px); /* Moves it up slightly */
    box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.3); /* Deepens the shadow */
        }

.note-card-db {
    border-radius:14px; border:2px solid;
    padding:22px; display:flex; flex-direction:column;
    transition:all 0.25s; min-height:220px;
}

[data-theme="dark"]  .note-subj-db { color:#a78bfa; background:rgba(124,58,237,0.18); }
[data-theme="light"] .note-subj-db { color:#7c3aed; background:#ede9fe; }

.note-subj-db {
    display:inline-flex; align-items:center;
    font-size:10px; font-weight:700;
    text-transform:uppercase; letter-spacing:0.8px;
    padding:4px 12px; border-radius:50px;
    width:fit-content; margin-bottom:10px;
}

[data-theme="dark"]  .note-title-db { color:#f3f0ff; }
[data-theme="light"] .note-title-db { color:#111827; }
.note-title-db { font-size:16px; font-weight:600; line-height:1.4; margin-bottom:6px; }

[data-theme="dark"]  .note-desc-db { color:#8b82a7; }
[data-theme="light"] .note-desc-db { color:#6b7280; }
.note-desc-db {
    font-size:13px; line-height:1.6; flex-grow:1;
    display:-webkit-box; -webkit-line-clamp:3;
    -webkit-box-orient:vertical; overflow:hidden; margin-bottom:14px;
}

[data-theme="dark"]  .note-foot-db { border-top-color:#2e2a42; }
[data-theme="light"] .note-foot-db { border-top-color:#f3f4f6; }
.note-foot-db {
    display:flex; justify-content:space-between; align-items:center;
    padding-top:12px; border-top:1px solid; margin-bottom:12px;
}

.note-upl-db { display:flex; align-items:center; gap:8px; }
.note-upl-av {
    width:26px; height:26px; border-radius:50%;
    background:linear-gradient(135deg,#7c3aed,#f59e0b);
    color:white; font-size:10px; font-weight:700;
    display:flex; align-items:center; justify-content:center;
}

[data-theme="dark"]  .note-author-db { color:#8b82a7; }
[data-theme="light"] .note-author-db { color:#9ca3af; }
.note-author-db { font-size:12px; font-weight:500; }

.note-stats-db { display:flex; gap:10px; }
[data-theme="dark"]  .note-stat-db { color:#8b82a7; }
[data-theme="light"] .note-stat-db { color:#9ca3af; }
.note-stat-db { font-size:12px; display:flex; align-items:center; gap:3px; }

.note-acts-db { display:flex; gap:8px; }

/* Pin / Favorite (per user) — visuals in css/style.css (.ns-note-action) */
.note-bookmark-db {
    display:flex; flex-wrap:wrap; gap:8px;
    margin-bottom:10px;
}
.note-bookmark-db .ns-note-action {
    padding:6px 12px;
    font-size:11px;
    gap:5px;
}
.note-bookmark-db .ns-note-action .ns-note-action-icon { font-size:13px; }
.note-bm-form { margin:0; display:inline; }

[data-theme="dark"]  .note-dl-btn   { background:#2a2210; color:#d4a017; border-color:#3d3118; }
[data-theme="light"] .note-dl-btn   { background:#fef3c7; color:#d97706; border-color:#fcd34d; }
[data-theme="dark"]  .note-view-btn { background:#1e1645; color:#a78bfa; border-color:#2d1f5e; }
[data-theme="light"] .note-view-btn { background:#ede9fe; color:#7c3aed; border-color:#c4b5fd; }

.note-dl-btn, .note-view-btn {
    flex:1; text-align:center; padding:8px 0;
    border-radius:50px; font-size:12px; font-weight:600;
    text-decoration:none; border:1px solid;
    transition:all 0.2s; display:block;
}
.note-dl-btn:hover   { background:#f59e0b !important; color:#0f0e17 !important; border-color:#f59e0b !important; }
.note-view-btn:hover { background:#7c3aed !important; color:white   !important; border-color:#7c3aed !important; }

/* ── ACTIVITY ── */
[data-theme="dark"]  .act-heading { color:#8b82a7; }
[data-theme="light"] .act-heading { color:#6b7280; }
.act-heading { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; margin-bottom:14px; margin-top:28px; }

.act-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }

[data-theme="dark"]  .act-card { background:#1a1825; border-color:#2e2a42; }
[data-theme="light"] .act-card { background:#ffffff;  border-color:#e5e7eb; }
.act-card {
    border-radius:14px; border:1px solid;
    padding:18px; display:flex; align-items:center;
    gap:14px; text-decoration:none; transition:all 0.2s;
}
.act-card:hover { border-color:#7c3aed; transform:translateY(-2px); }

[data-theme="dark"]  .act-icon { background:rgba(124,58,237,0.15); }
[data-theme="light"] .act-icon { background:#ede9fe; }
.act-icon {
    width:42px; height:42px; border-radius:10px;
    display:flex; align-items:center; justify-content:center;
    font-size:20px; flex-shrink:0;
}

[data-theme="dark"]  .act-label { color:#f3f0ff; }
[data-theme="light"] .act-label { color:#111827; }
.act-label { font-size:14px; font-weight:600; }

[data-theme="dark"]  .act-sub { color:#8b82a7; }
[data-theme="light"] .act-sub { color:#9ca3af; }
.act-sub { font-size:12px; margin-top:2px; }

.act-arrow { margin-left:auto; font-size:16px; color:#8b82a7; }

/* ── COMMUNITY FEED ── */
[data-theme="dark"]  .feed-card { background:#1a1825; border-color:#2e2a42; }
[data-theme="light"] .feed-card { background:#ffffff;  border-color:#e5e7eb; }
.feed-card { border-radius:14px; border:1px solid; overflow:hidden; }

[data-theme="dark"]  .feed-head { color:#8b82a7; border-bottom-color:#2e2a42; background:#1a1825; }
[data-theme="light"] .feed-head { color:#6b7280; border-bottom-color:#f3f4f6; background:#f9fafb; }
.feed-head { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; padding:14px 16px; border-bottom:1px solid; }

[data-theme="dark"]  .feed-row { border-bottom-color:#2e2a42; }
[data-theme="light"] .feed-row { border-bottom-color:#f3f4f6; }
[data-theme="dark"]  .feed-row:hover { background:rgba(124,58,237,0.06); }
[data-theme="light"] .feed-row:hover { background:#f9fafb; }
.feed-row {
    display:flex; align-items:flex-start; gap:10px;
    padding:12px 16px; border-bottom:1px solid;
    text-decoration:none; transition:background 0.2s;
}
.feed-row:last-child { border-bottom:none; }

.feed-av {
    width:32px; height:32px; border-radius:50%;
    background:linear-gradient(135deg,#7c3aed,#f59e0b);
    color:white; font-size:12px; font-weight:700;
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
}

[data-theme="dark"]  .feed-txt { color:#8b82a7; }
[data-theme="light"] .feed-txt { color:#6b7280; }
[data-theme="dark"]  .feed-txt strong { color:#f3f0ff; }
[data-theme="light"] .feed-txt strong { color:#111827; }
.feed-txt { font-size:12px; line-height:1.5; margin-bottom:3px; }
.feed-nt  { color:#a78bfa; font-weight:600; }

[data-theme="dark"]  .feed-time { color:#6b6480; }
[data-theme="light"] .feed-time { color:#9ca3af; }
.feed-time { font-size:11px; }

/* ── EMPTY STATE ── */
.db-empty { text-align:center; padding:60px 20px; }
[data-theme="dark"]  .db-empty { color:#8b82a7; }
[data-theme="light"] .db-empty { color:#9ca3af; }
.db-empty-icon { font-size:48px; display:block; margin-bottom:14px; }
.db-empty p { font-size:15px; }

/* ── RESPONSIVE ── */
@media (max-width:1024px) {
    .db-grid { grid-template-columns:1fr 240px; }
}
@media (max-width:900px) {
    .db-grid { grid-template-columns:1fr; }
    .db-sidebar { position:static; }
    .act-grid { grid-template-columns:1fr 1fr; }
    .welcome-illo { width:130px; min-width:130px; }
    .welcome-text { padding:24px 20px 24px 12px; }
    .filter-row-wrap { flex-direction:column; align-items:stretch; }
    .db-filter-card { width:100%; }
    .db-search-float { width:100%; flex:none; }
}

@media (max-width:600px) {
    .db-container { padding:12px 12px 40px; }

    /* NAVBAR */
    .db-navbar { padding:10px 12px; gap:8px; flex-wrap:nowrap; }
    .db-nav-brand { font-size:14px; flex-shrink:0; }
    .db-nav-brand svg { width:16px; height:16px; }
    .db-nav-right { gap:5px; flex-shrink:0; }
    .db-nav-btn-purple { padding:6px 10px; font-size:11px; white-space:nowrap; }
    .db-nav-btn-dark { display:none; }
    .db-nav-avatar { width:28px; height:28px; flex-shrink:0; display:flex !important; }

    /* WELCOME BANNER */
    .welcome-banner {
        flex-direction:column;
        min-height:auto;
        border-radius:12px;
    }
    ..welcome-illo {
    width:100%;
    min-width:unset;
    padding:20px 16px 0;
    justify-content:center;
    height:130px;
    overflow:visible;
}
.welcome-illo svg { width:160px; height:130px; }
    .welcome-text {
        padding:12px 16px 20px;
        text-align:center;
    }
    .welcome-banner::after { display:none; }
    .welcome-name { font-size:26px; }
    .welcome-sub { font-size:13px; }

    /* FILTER */
    .filter-row-wrap { flex-direction:column; gap:10px; }
    .db-filter-card { width:100%; padding:12px 14px; }
    .db-filter-row { gap:8px; }
    .db-dd { width:100%; font-size:12px; padding:8px 10px; }
    .db-search-float { width:100%; flex:none; }
    .db-search-wrap input { font-size:12px; }

    /* NOTES */
    .notes-grid-db { grid-template-columns:1fr; gap:12px; }
    .note-card-db { padding:16px; min-height:auto; }
    .note-title-db { font-size:14px; }
    .note-desc-db { font-size:12px; -webkit-line-clamp:2; }
    .note-acts-db { flex-direction:column; gap:6px; }
    .note-dl-btn, .note-view-btn { padding:9px; font-size:12px; }

    /* SECTION HEADING */
    .db-sec-head { font-size:10px; flex-wrap:wrap; gap:6px; }

    /* ACTIVITY */
    .act-grid { grid-template-columns:1fr; gap:10px; }
    .act-card { padding:14px; }
    .act-label { font-size:13px; }

    /* COMMUNITY FEED */
    .feed-card { margin-top:0; }
    .feed-head { font-size:10px; }
    .feed-txt { font-size:11px; }
}

@media (max-width:380px) {
    .welcome-name { font-size:22px; }
}
/* Logo */
.db-nav-brand {
    font-family: var(--font-jakarta);
    font-size: 22px;
    font-weight: 700;
}
/* The name highlight */
.welcome-name { 
    font-family: var(--font-jakarta), sans-serif;
    font-size: 26px; 
    font-weight: 800; 
    color: #ffffff; 
    margin: 0; 
    line-height: 1.1;
}

/* The gold color for the name from your pic */
.welcome-name span {
    color: #b38b59; 
}

.welcome-sub { 
    font-family: var(--font-inter), sans-serif;
    font-size: 14px; 
    color: rgba(255, 255, 255, 0.6); 
    margin-top: 4px;
}

/* Hide the extra label to match the clean pic look */
.welcome-back-lbl {
    display: none;
}

/* THE GLOW - Adjusting to be subtle for dark theme */
.welcome-banner::after {
    content: ''; 
    position: absolute; 
    right: 40px; 
    top: 50%;
    transform: translateY(-50%);
    width: 240px; 
    height: 240px;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.05) 0%, transparent 70%);
    border-radius: 50%; 
    pointer-events: none;
}

/* Primary button */
.db-nav-btn-purple {
    font-family: var(--font-jakarta);
    font-size: 14px;
}

/* Nav links */
.db-nav-btn-dark {
    font-family: var(--font-inter);
    font-size: 14px;
}

/* Section headings */
.db-sec-head {
    font-family: var(--font-jakarta);
    font-size: 18px;
    font-weight: 700;
    text-transform: none;
    letter-spacing: 0;
}

.db-count-pill {
    font-family: var(--font-inter);
    font-size: 13px;
}

/* Card title */
.note-title-db {
    font-family: var(--font-jakarta);
    font-size: 16px;
    font-weight: 600;
}

/* Card description */
.note-desc-db {
    font-family: var(--font-inter);
    font-size: 13px;
}

/* Card date / stats */
.note-stat-db {
    font-family: var(--font-inter);
    font-size: 12px;
}

.note-author-db {
    font-family: var(--font-inter);
    font-size: 12px;
}

/* Category tag / subject badge */
.note-subj-db {
    font-family: var(--font-inter);
    font-size: 11px;
}

/* User avatar */
.db-nav-avatar {
    font-family: var(--font-jakarta);
    font-size: 14px;
    font-weight: 700;
}

.note-upl-av {
    font-family: var(--font-jakarta);
    font-size: 10px;
    font-weight: 700;
}





/* Search */
.db-search-wrap input {
    font-family: var(--font-inter);
    font-size: 13px;
}

.db-search-btn {
    font-family: var(--font-jakarta);
    font-size: 13px;
    font-weight: 600;
}

/* Download / View buttons */
.note-dl-btn,
.note-view-btn {
    font-family: var(--font-jakarta);
    font-size: 12px;
    font-weight: 600;
}

/* Activity cards */
.act-label {
    font-family: var(--font-jakarta);
    font-size: 14px;
    font-weight: 600;
}

.act-sub {
    font-family: var(--font-inter);
    font-size: 12px;
}

/* Community feed */
.feed-head {
    font-family: var(--font-jakarta);
    font-size: 13px;
    font-weight: 700;
    text-transform: none;
    letter-spacing: 0;
}

.feed-txt {
    font-family: var(--font-inter);
    font-size: 12px;
}

.feed-time {
    font-family: var(--font-inter);
    font-size: 11px;
}
.feed-view-more {
    display: block;
    text-align: center;
    padding: 12px 16px;
    font-size: 12px;
    font-weight: 600;
    color: #a78bfa;
    text-decoration: none;
    border-top: 1px solid #2e2a42;
    transition: background 0.2s;
    font-family: var(--font-inter);
}

.feed-view-more:hover {
    background: rgba(124,58,237,0.08);
    color: #7c3aed;
}

[data-theme="light"] .feed-view-more {
    color: #7c3aed;
    border-top-color: #f3f4f6;
}

[data-theme="light"] .feed-view-more:hover {
    background: #f5f3ff;
}
/* ── HAMBURGER BUTTON ── */
.hamburger-wrap {
    position: relative;
    margin-right: 12px;
}

.hamburger-btn {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 5px;
    width: 36px;
    height: 36px;
    background: none;
    border: none;
    cursor: pointer;
    padding: 6px;
    border-radius: 8px;
    transition: background 0.2s;
}

[data-theme="dark"]  .hamburger-btn:hover { background: #1e1b2e; }
[data-theme="light"] .hamburger-btn:hover { background: #f3f4f6; }

.hamburger-btn span {
    display: block;
    width: 100%;
    height: 2px;
    border-radius: 2px;
    transition: all 0.3s;
}

[data-theme="dark"]  .hamburger-btn span { background: #a89fc0; }
[data-theme="light"] .hamburger-btn span { background: #4b5563; }

.hamburger-btn.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.hamburger-btn.open span:nth-child(2) { opacity: 0; }
.hamburger-btn.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

/* ── SIDE MENU ── */
.side-menu {
    position: fixed;
    top: 0;
    left: -300px;
    width: 280px;
    height: 100vh;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    transition: left 0.3s cubic-bezier(0.4,0,0.2,1);
    box-shadow: 4px 0 24px rgba(0,0,0,0.3);
}

[data-theme="dark"]  .side-menu { background: #0f0e17; border-right: 1px solid #1e1b2e; }
[data-theme="light"] .side-menu { background: #ffffff;  border-right: 1px solid #e5e7eb; }

.side-menu.open { left: 0; }

/* OVERLAY */
.side-menu-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 9998;
    backdrop-filter: blur(2px);
}
.side-menu-overlay.open { display: block; }

/* HEADER */
.side-menu-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 20px 14px;
    border-bottom: 1px solid;
}
[data-theme="dark"]  .side-menu-header { border-color: #1e1b2e; }
[data-theme="light"] .side-menu-header { border-color: #f3f4f6; }

.side-menu-brand {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 16px;
    font-weight: 700;
    background: linear-gradient(135deg, #7c3aed, #f59e0b);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-family: var(--font-jakarta);
}

.side-menu-close {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 16px;
    padding: 4px 8px;
    border-radius: 6px;
    transition: background 0.2s;
    line-height: 1;
}
[data-theme="dark"]  .side-menu-close { color: #8b82a7; }
[data-theme="light"] .side-menu-close { color: #6b7280; }
[data-theme="dark"]  .side-menu-close:hover { background: #1e1b2e; }
[data-theme="light"] .side-menu-close:hover { background: #f3f4f6; }

/* USER CARD */
.side-menu-user {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
}

.side-menu-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: linear-gradient(135deg, #7c3aed, #f59e0b);
    color: white;
    font-size: 16px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-family: var(--font-jakarta);
}

.side-menu-name {
    font-size: 14px;
    font-weight: 600;
    font-family: var(--font-jakarta);
}
[data-theme="dark"]  .side-menu-name { color: #f3f0ff; }
[data-theme="light"] .side-menu-name { color: #111827; }

.side-menu-role {
    font-size: 12px;
    margin-top: 2px;
    font-family: var(--font-inter);
}
[data-theme="dark"]  .side-menu-role { color: #8b82a7; }
[data-theme="light"] .side-menu-role { color: #9ca3af; }

/* DIVIDER */
.side-menu-divider {
    height: 1px;
    margin: 6px 20px;
}
[data-theme="dark"]  .side-menu-divider { background: #1e1b2e; }
[data-theme="light"] .side-menu-divider { background: #f3f4f6; }

/* SECTION LABEL */
.side-menu-section-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 8px 20px 4px;
    font-family: var(--font-inter);
}
[data-theme="dark"]  .side-menu-section-label { color: #6b6480; }
[data-theme="light"] .side-menu-section-label { color: #9ca3af; }

/* MENU ITEMS */
.side-menu-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 20px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    font-family: var(--font-inter);
    transition: all 0.15s;
    border-left: 3px solid transparent;
    position: relative;
}
[data-theme="dark"]  .side-menu-item { color: #a89fc0; }
[data-theme="light"] .side-menu-item { color: #4b5563; }

[data-theme="dark"]  .side-menu-item:hover { background: rgba(124,58,237,0.08); color: #f3f0ff; border-left-color: #7c3aed; }
[data-theme="light"] .side-menu-item:hover { background: #f5f3ff; color: #7c3aed; border-left-color: #7c3aed; }

.side-menu-item.active {
    border-left-color: #7c3aed;
    font-weight: 600;
}
[data-theme="dark"]  .side-menu-item.active { background: rgba(124,58,237,0.1); color: #a78bfa; }
[data-theme="light"] .side-menu-item.active { background: #f5f3ff; color: #7c3aed; }

.side-menu-icon { font-size: 16px; width: 20px; text-align: center; flex-shrink: 0; }

.side-menu-badge {
    margin-left: auto;
    background: #ef4444;
    color: white;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 50px;
    font-family: var(--font-inter);
}

/* LOGOUT */
[data-theme="dark"]  .side-menu-logout { color: #f87171; }
[data-theme="light"] .side-menu-logout { color: #ef4444; }
[data-theme="dark"]  .side-menu-logout:hover { background: rgba(239,68,68,0.1); border-left-color: #ef4444; color: #f87171; }
[data-theme="light"] .side-menu-logout:hover { background: #fef2f2; border-left-color: #ef4444; color: #ef4444; }

/* FOOTER */
.side-menu-footer {
    margin-top: auto;
    padding: 16px 20px;
    font-size: 11px;
    font-family: var(--font-inter);
    text-align: center;
}
[data-theme="dark"]  .side-menu-footer { color: #6b6480; border-top: 1px solid #1e1b2e; }
[data-theme="light"] .side-menu-footer { color: #9ca3af; border-top: 1px solid #f3f4f6; }

/* SCROLLBAR for side menu */
.side-menu::-webkit-scrollbar { width: 4px; }
.side-menu::-webkit-scrollbar-track { background: transparent; }
.side-menu::-webkit-scrollbar-thumb { background: #2e2a42; border-radius: 4px; }

/* Final narrow-phone navbar overrides. These sit after the repeated font rules above. */
@media (max-width:600px) {
    .db-navbar {
        padding: 10px 12px;
        gap: 8px;
        overflow: visible;
    }

    .db-nav-brand {
        font-size: 18px;
        min-width: 0;
        flex-shrink: 1;
    }

    .db-nav-right {
        gap: 6px;
        flex-shrink: 0;
    }

    .db-nav-right .chat-rel {
        display: none;
    }

    .db-nav-right #theme-btn {
        display: flex;
        width: 34px;
        height: 34px;
        padding: 0;
        justify-content: center;
        font-size: 16px;
    }

    .db-nav-btn-purple {
        padding: 7px 12px;
        font-size: 12px;
    }

    .db-nav-avatar {
        width: 34px;
        height: 34px;
    }
}

@media (max-width:380px) {
    .db-navbar {
        padding: 10px 8px;
        gap: 6px;
    }

    .hamburger-btn {
        width: 34px;
        height: 34px;
    }

    .db-nav-brand {
        font-size: 0;
        gap: 0;
        margin-right: 2px;
    }

    .db-nav-brand svg {
        width: 24px;
        height: 24px;
    }

    .db-nav-right {
        gap: 5px;
    }

    .db-nav-btn-purple {
        padding: 7px 10px;
        font-size: 11px;
    }

    .db-nav-right #theme-btn,
    .db-nav-avatar {
        width: 32px;
        height: 32px;
    }
}
</style>
</head>
<body class="db-page">

<nav class="db-navbar">

    <!-- HAMBURGER + DROPDOWN -->
    <div class="hamburger-wrap">
        <button class="hamburger-btn" onclick="toggleMenu()" id="hamburger-btn" aria-label="Menu">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <div class="side-menu" id="side-menu">
            <div class="side-menu-header">
                <div class="side-menu-brand">
                    <svg width="22" height="22" viewBox="0 0 28 28" fill="none">
                        <rect x="3" y="2" width="16" height="20" rx="3" stroke="#7c3aed" stroke-width="2" fill="none"/>
                        <path d="M7 8h8M7 12h6" stroke="#7c3aed" stroke-width="2" stroke-linecap="round"/>
                        <path d="M14 18c0-2.2 1.8-4 4-4s4 1.8 4 4-1.8 4-4 4-4-1.8-4-4z" stroke="#f59e0b" stroke-width="2" fill="none"/>
                        <path d="M20.5 21.5l2.5 2.5" stroke="#f59e0b" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    NoteSwap
                </div>
                <button class="side-menu-close" onclick="toggleMenu()">✕</button>
            </div>

            <div class="side-menu-user">
                <div class="side-menu-avatar"><?= strtoupper(substr($user_name,0,1)) ?></div>
                <div>
                    <div class="side-menu-name"><?= htmlspecialchars($user_name) ?></div>
                    <div class="side-menu-role"><?= htmlspecialchars($user_prefs['section'] ?? 'BSCS') ?> · Semester <?= $user_prefs['semester'] ?? 1 ?></div>
                </div>
            </div>

            <div class="side-menu-divider"></div>

            <a href="dashboard.php" class="side-menu-item active">
                <span class="side-menu-icon">🏠</span>
                <span>Dashboard</span>
            </a>
            <a href="upload.php" class="side-menu-item">
                <span class="side-menu-icon">📤</span>
                <span>Upload Note</span>
            </a>
            <a href="messages.php" class="side-menu-item">
                <span class="side-menu-icon">💬</span>
                <span>Chat</span>
                <?php if ($unread_count > 0): ?>
                    <span class="side-menu-badge"><?= $unread_count ?></span>
                <?php endif; ?>
            </a>
            <a href="profile.php" class="side-menu-item">
                <span class="side-menu-icon">👤</span>
                <span>Profile</span>
            </a>
            <a href="my_notes.php" class="side-menu-item">
                <span class="side-menu-icon">📂</span>
                <span>My Notes</span>
            </a>
            <a href="favorites.php" class="side-menu-item">
                <span class="side-menu-icon">⭐</span>
                <span>Favorites</span>
            </a>

            <div class="side-menu-divider"></div>
            <p class="side-menu-section-label">Support</p>

            <a href="faq.php" class="side-menu-item">
                <span class="side-menu-icon">❓</span>
                <span>FAQs</span>
            </a>
            <a href="mailto:noteswap@support.com" class="side-menu-item">
                <span class="side-menu-icon">📧</span>
                <span>Contact Support</span>
            </a>

            <div class="side-menu-divider"></div>

            <a href="logout.php" class="side-menu-item side-menu-logout">
                <span class="side-menu-icon">🚪</span>
                <span>Logout</span>
            </a>

            <div class="side-menu-footer">
                NoteSwap · Arid Agriculture University
            </div>
        </div>

        <!-- Overlay -->
        <div class="side-menu-overlay" id="side-overlay" onclick="toggleMenu()"></div>
    </div>

    <a href="dashboard.php" class="db-nav-brand">
        <svg width="24" height="24" viewBox="0 0 28 28" fill="none">
            <rect x="3" y="2" width="16" height="20" rx="3" stroke="#7c3aed" stroke-width="2" fill="none"/>
            <path d="M7 8h8M7 12h6" stroke="#7c3aed" stroke-width="2" stroke-linecap="round"/>
            <path d="M14 18c0-2.2 1.8-4 4-4s4 1.8 4 4-1.8 4-4 4-4-1.8-4-4z" stroke="#f59e0b" stroke-width="2" fill="none"/>
            <path d="M20.5 21.5l2.5 2.5" stroke="#f59e0b" stroke-width="2" stroke-linecap="round"/>
        </svg>
        NoteSwap
    </a>

    <div class="db-nav-right">
        <a href="upload.php" class="db-nav-btn db-nav-btn-purple">+ Upload Note</a>
        <a href="messages.php" class="db-nav-btn db-nav-btn-dark chat-rel">
            💬 Chat
            <?php if ($unread_count > 0): ?>
                <span class="notif-badge"><?= $unread_count ?></span>
            <?php endif; ?>
        </a>
        <button class="db-nav-btn db-nav-btn-dark" onclick="toggleDark()" id="theme-btn">🌙</button>
        <a href="profile.php" class="db-nav-avatar" title="My Profile"
   style="padding:0;overflow:hidden">
    <img src="<?= $avatar_url ?>" alt="avatar"
         style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block">
</a>
    </div>
</nav>

<div class="db-container">

    <!-- WELCOME BANNER -->
    <div class="welcome-banner">
        <div class="welcome-illo">
    <img src="images/banner.png" alt="Welcome Character" class="banner-img">
</div>
        <div class="welcome-text">
        <h2 class="welcome-name">
            WELCOME BACK, <span><?= strtoupper(htmlspecialchars($user_name)) ?>!</span>
        </h2>
        <p class="welcome-sub">
            Continue your <?= htmlspecialchars($user_prefs['section'] ?? 'BSCS') ?> 
            Semester <?= $user_prefs['semester'] ?? 1 ?> study journey!
        </p>
    </div>
    </div>

    <!-- TWO COLUMN GRID -->
    <div class="db-grid">

        <!-- LEFT MAIN COLUMN -->
        <div>

            <!-- FILTER + SEARCH ROW -->
            <div class="filter-row-wrap">

                <!-- Filter card -->
                <div class="db-filter-card">
    <p class="db-filter-lbl">Filter Notes</p>
    <form method="GET" action="">
        <div class="db-filter-row">
            <div class="db-dd-group">
                <label class="db-dd-lbl">Section</label>
                <select name="section" class="db-dd" onchange="this.form.submit()">
                    <option value="ALL" <?= $filter_section=='ALL'?'selected':'' ?>>All</option>
                    <option value="BSCS" <?= $filter_section=='BSCS'?'selected':'' ?>>BSCS</option>
                    <option value="BSAI" <?= $filter_section=='BSAI'?'selected':'' ?>>BSAI</option>
                    <option value="BSSE" <?= $filter_section=='BSSE'?'selected':'' ?>>BSSE</option>
                </select>
            </div>
            
            <div class="db-dd-group">
                <label class="db-dd-lbl">Semester</label>
                <select name="semester" class="db-dd" onchange="this.form.submit()">
                    <option value="0" <?= $filter_semester==0?'selected':'' ?>>All</option>
                    <?php for ($i=1;$i<=8;$i++): ?>
                        <option value="<?=$i?>" <?=$filter_semester==$i?'selected':''?>>Sem <?=$i?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
    </form>
</div>

                <!-- Floating search -->
                <form method="GET" action="" class="db-search-float">
                    <input type="hidden" name="section"  value="<?= htmlspecialchars($filter_section) ?>">
                    <input type="hidden" name="semester" value="<?= $filter_semester ?>">
                    <div class="db-search-wrap">
                        <span style="font-size:14px;color:#8b82a7;flex-shrink:0">🔍</span>
                        <input type="text" name="search"
                               placeholder="Search anything..."
                               value="<?= htmlspecialchars($search_query) ?>">
                        <button type="submit" class="db-search-btn">Search</button>
                    </div>
                    <?php if (!empty($search_query)): ?>
                        <a href="?section=<?= $filter_section ?>&semester=<?= $filter_semester ?>"
                           style="font-size:12px;color:#8b82a7;text-decoration:none;
                                  display:block;text-align:right;margin-top:5px">
                           ✕ Clear
                        </a>
                    <?php endif; ?>
                </form>

            </div>

            <!-- SECTION HEADING -->
            <p class="db-sec-head">
                <?php if (!empty($search_query)): ?>
                    Results for "<?= htmlspecialchars($search_query) ?>"
                <?php else: ?>
                    Recommended for
                    <?= $filter_section=='ALL' ? 'All' : htmlspecialchars($filter_section) ?>
                    <?= $filter_semester ? 'Semester '.$filter_semester : '' ?>
                <?php endif; ?>
                <span class="db-count-pill"><?= count($notes) ?> Notes</span>
            </p>

            <!-- NOTES -->
            <?php if (empty($notes)): ?>
                <div class="db-empty">
                    <span class="db-empty-icon">📭</span>
                    <?php if (!empty($search_query)): ?>
                        <p>No notes found for "<?= htmlspecialchars($search_query) ?>"</p>
                    <?php else: ?>
                        <p>No notes yet — be the first to upload!</p>
                        <a href="upload.php"
                           style="display:inline-block;margin-top:16px;background:#7c3aed;
                                  color:white;padding:10px 24px;border-radius:50px;
                                  text-decoration:none;font-size:13px;font-weight:600">
                           + Upload Note
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="notes-grid-db">
                    <?php foreach ($notes as $note):
                        $stmt_f = $pdo->prepare("SELECT * FROM note_files WHERE note_id = ? LIMIT 1");
                        $stmt_f->execute([$note['id']]);
                        $first_file = $stmt_f->fetch();
                    ?>
                    <div class="note-card-db">
                        <div class="note-bookmark-db">
                            <form method="post" action="toggle_note_bookmark.php" class="note-bm-form">
                                <input type="hidden" name="note_id" value="<?= (int) $note['id'] ?>">
                                <input type="hidden" name="redirect" value="<?= htmlspecialchars($bookmark_redirect) ?>">
                                <?php if (!empty($note['user_is_pinned'])): ?>
                                    <input type="hidden" name="bookmark_action" value="unpin">
                                    <button type="submit" class="ns-note-action ns-note-action--pin is-active" title="Remove from top"><span class="ns-note-action-icon" aria-hidden="true">📌</span><span>Pinned</span></button>
                                <?php else: ?>
                                    <input type="hidden" name="bookmark_action" value="pin">
                                    <button type="submit" class="ns-note-action ns-note-action--pin" title="Keep at top of your list"><span class="ns-note-action-icon" aria-hidden="true">📌</span><span>Pin</span></button>
                                <?php endif; ?>
                            </form>
                            <form method="post" action="toggle_note_bookmark.php" class="note-bm-form">
                                <input type="hidden" name="note_id" value="<?= (int) $note['id'] ?>">
                                <input type="hidden" name="redirect" value="<?= htmlspecialchars($bookmark_redirect) ?>">
                                <?php if (!empty($note['user_is_favorite'])): ?>
                                    <input type="hidden" name="bookmark_action" value="unfavorite">
                                    <button type="submit" class="ns-note-action ns-note-action--save is-active" title="Remove from favorites"><span class="ns-note-action-icon" aria-hidden="true">⭐</span><span>Saved</span></button>
                                <?php else: ?>
                                    <input type="hidden" name="bookmark_action" value="favorite">
                                    <button type="submit" class="ns-note-action ns-note-action--save" title="Favorite for your account"><span class="ns-note-action-icon" aria-hidden="true">🔖</span><span>Save</span></button>
                                <?php endif; ?>
                            </form>
                        </div>
                        <span class="note-subj-db"><?= htmlspecialchars($note['subject']) ?></span>
                        <h3 class="note-title-db"><?= htmlspecialchars($note['title']) ?></h3>
                        <p class="note-desc-db"><?= htmlspecialchars($note['description']) ?></p>
                        <div class="note-foot-db">
                            <div class="note-upl-db">
                                <?php
$stmt_av = $pdo->prepare("SELECT avatar_seed FROM users WHERE name = ? LIMIT 1");
$stmt_av->execute([$note['uploader']]);
$upl_seed = $stmt_av->fetchColumn() ?? 'default';
$upl_av   = "https://api.dicebear.com/9.x/fun-emoji/svg?seed=" . urlencode($upl_seed);
?>
<img src="<?= $upl_av ?>" alt="avatar"
     style="width:26px;height:26px;border-radius:50%;object-fit:cover;flex-shrink:0">
                                <span class="note-author-db"><?= htmlspecialchars($note['uploader']) ?></span>
                            </div>
                            <div class="note-stats-db">
                                <span class="note-stat-db">👁 <?= $note['views'] ?? 0 ?></span>
                                <span class="note-stat-db">💬 <?= $note['comment_count'] ?? 0 ?></span>
                            </div>
                        </div>
                        <div class="note-acts-db">
                            <?php if ($first_file): ?>
                                <a href="uploads/<?= htmlspecialchars($first_file['filename']) ?>"
                                   download class="note-dl-btn">⬇ Download</a>
                            <?php endif; ?>
                            <a href="view_note.php?id=<?= $note['id'] ?>"
                               class="note-view-btn">👁 View Note</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- ACTIVITY -->
            <p class="act-heading">My Recent Activity</p>
            <div class="act-grid">
                <a href="my_notes.php" class="act-card">
                    <div class="act-icon">📤</div>
                    <div>
                        <div class="act-label">My Uploads</div>
                        <div class="act-sub"><?= $my_uploads_count ?> notes shared</div>
                    </div>
                    <span class="act-arrow">→</span>
                </a>
                <a href="messages.php" class="act-card">
                    <div class="act-icon">💬</div>
                    <div>
                        <div class="act-label">My Chats</div>
                        <div class="act-sub">
                            <?= $unread_count > 0 ? $unread_count.' unread' : 'No new messages' ?>
                        </div>
                    </div>
                    <span class="act-arrow">→</span>
                </a>
            </div>

        </div>
        <!-- END LEFT COLUMN -->

        <!-- RIGHT SIDEBAR -->
        <div class="db-sidebar">
            <div class="feed-card">
    <p class="feed-head">
        Community Feed
        <span style="font-size:10px;color:#8b82a7;font-weight:400;text-transform:none;letter-spacing:0;margin-left:4px">
            · <?= htmlspecialchars($feed_section) ?> Sem <?= $feed_semester ?>
        </span>
    </p>
    <?php if (empty($feed_notes)): ?>
        <p style="font-size:13px;padding:16px;color:#8b82a7">
            No uploads yet for your subjects.
        </p>
    <?php else: ?>
        <?php foreach ($feed_notes as $fn):
            $diff = time() - strtotime($fn['created_at']);
            if      ($diff < 3600)   $ago = round($diff/60).' min ago';
            elseif  ($diff < 86400)  $ago = round($diff/3600).' hrs ago';
            elseif  ($diff < 604800) $ago = round($diff/86400).' days ago';
            else                     $ago = date('d M', strtotime($fn['created_at']));
        ?>
        <?php
$fn_seed = $fn['avatar_seed'] ?? 'default';
$fn_av   = "https://api.dicebear.com/9.x/fun-emoji/svg?seed=" . urlencode($fn_seed);
?>
<a href="view_note.php?id=<?= $fn['id'] ?>" class="feed-row">
    <img src="<?= $fn_av ?>" alt="avatar"
         style="width:32px;height:32px;border-radius:50%;
                object-fit:cover;flex-shrink:0;border:2px solid #2e2a42">
            <div>
                <p class="feed-txt">
                    <strong><?= htmlspecialchars(explode(' ',$fn['uploader'])[0]) ?></strong>
                    uploaded
                    <span class="feed-nt">
                        <?= htmlspecialchars(mb_substr($fn['title'],0,22)) ?>
                        <?= mb_strlen($fn['title'])>22?'...':'' ?>
                    </span>
                </p>
                <span class="feed-time"><?= $ago ?></span>
            </div>
        </a>
        <?php endforeach; ?>

       <?php if ($feed_extra): ?>
        <!-- Hidden extra feed items -->
        <div id="feed-extra-items" style="display:none">
            <?php foreach (array_slice($all_feed_notes, 3) as $fn):
                $diff = time() - strtotime($fn['created_at']);
                if      ($diff < 3600)   $ago = round($diff/60).' min ago';
                elseif  ($diff < 86400)  $ago = round($diff/3600).' hrs ago';
                elseif  ($diff < 604800) $ago = round($diff/86400).' days ago';
                else                     $ago = date('d M', strtotime($fn['created_at']));
            ?>
            <?php
$fn_seed = $fn['avatar_seed'] ?? 'default';
$fn_av   = "https://api.dicebear.com/9.x/fun-emoji/svg?seed=" . urlencode($fn_seed);
?>
<a href="view_note.php?id=<?= $fn['id'] ?>" class="feed-row">
    <img src="<?= $fn_av ?>" alt="avatar"
         style="width:32px;height:32px;border-radius:50%;
                object-fit:cover;flex-shrink:0;border:2px solid #2e2a42">
                <div>
                    <p class="feed-txt">
                        <strong><?= htmlspecialchars(explode(' ',$fn['uploader'])[0]) ?></strong>
                        uploaded
                        <span class="feed-nt">
                            <?= htmlspecialchars(mb_substr($fn['title'],0,22)) ?>
                            <?= mb_strlen($fn['title'])>22?'...':'' ?>
                        </span>
                    </p>
                    <span class="feed-time"><?= $ago ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <a href="javascript:void(0)" onclick="showAllFeed()" class="feed-view-more">
            View <?= $feed_extra_count ?>+ more uploads →
        </a>
        <?php endif; ?>
    <?php endif; ?>
</div>
        </div>
        <!-- END SIDEBAR -->

    </div>
    <!-- END DB GRID -->

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
function showAllFeed() {
    document.getElementById('feed-extra-items').style.display = 'block';
    document.querySelector('.feed-view-more').style.display = 'none';
}
//toggle Menu
function toggleMenu() {
    const menu    = document.getElementById('side-menu');
    const overlay = document.getElementById('side-overlay');
    const btn     = document.getElementById('hamburger-btn');
    const isOpen  = menu.classList.contains('open');

    menu.classList.toggle('open', !isOpen);
    overlay.classList.toggle('open', !isOpen);
    btn.classList.toggle('open', !isOpen);
    document.body.style.overflow = isOpen ? '' : 'hidden';
}

// Close menu on Escape key
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        const menu = document.getElementById('side-menu');
        if (menu.classList.contains('open')) toggleMenu();
    }
});
</script>
</body>
</html>

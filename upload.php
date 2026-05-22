<?php
date_default_timezone_set('Asia/Karachi');
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Catch oversized uploads
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_LENGTH'])) {
    if ($_SERVER['CONTENT_LENGTH'] > 20 * 1024 * 1024) {
        session_start();
        header('Location: upload.php?error=filesize');
        exit();
    }
}

$error   = "";
$success = "";

if (isset($_GET['error']) && $_GET['error'] === 'filesize') {
    $error = "Total upload size exceeds 20MB. Please compress your files.";
}

$selected_section  = $_POST['section']  ?? ($_SESSION['user_section']  ?? 'BSCS');
$selected_semester = $_POST['semester'] ?? ($_SESSION['user_semester'] ?? 4);

// Fetch user avatar
$stmt = $pdo->prepare("SELECT avatar_seed, section, semester FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_row = $stmt->fetch();
$avatar_seed = $user_row['avatar_seed'] ?? 'default';
$avatar_url  = "https://api.dicebear.com/9.x/fun-emoji/svg?seed=" . urlencode($avatar_seed);
if (!isset($_SESSION['user_section'])) {
    $selected_section  = $_POST['section']  ?? ($user_row['section']  ?? 'BSCS');
    $selected_semester = $_POST['semester'] ?? ($user_row['semester'] ?? 4);
}

// Fetch subjects
$stmt = $pdo->prepare("SELECT subject FROM curriculum WHERE section = ? AND semester = ? ORDER BY subject ASC");
$stmt->execute([$selected_section, $selected_semester]);
$subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload'])) {
    $title       = trim($_POST["title"]);
    $description = trim($_POST["description"]);
    $subject     = trim($_POST["subject"]);
    $semester    = $_POST["semester"];
    $section     = $_POST["section"];
    $user_id     = $_SESSION['user_id'];
    $allowed     = ['pdf', 'png', 'jpg', 'jpeg', 'docx'];
    $upload_dir  = __DIR__ . "/uploads";
    $file_error  = "";
    $pending_files = [];

    if (empty($title) || empty($subject)) {
        $error = "Title and subject are required.";
    } else {
        if (!empty($_FILES['files']['name'][0])) {
            foreach ($_FILES['files']['tmp_name'] as $index => $tmp_name) {
                if ($_FILES['files']['error'][$index] !== UPLOAD_ERR_OK) {
                    $file_error = "One of your files could not be uploaded. Please try again.";
                    break;
                }

                $original = $_FILES['files']['name'][$index];
                $ext      = strtolower(pathinfo($original, PATHINFO_EXTENSION));
                $size     = $_FILES['files']['size'][$index];

                if (!in_array($ext, $allowed)) { $file_error = "Only PDF, Word, and image files allowed."; break; }
                if ($size > 5 * 1024 * 1024)   { $file_error = "Each file must be under 5MB."; break; }
                if (!is_uploaded_file($tmp_name)) { $file_error = "Upload temp file was not found. Please try again."; break; }

                $safe_original = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename($original));
                $safe_original = trim($safe_original, '._-');
                if ($safe_original === '') {
                    $safe_original = 'note_file.' . $ext;
                }
                $filename  = uniqid() . "_" . $safe_original;
                $file_type = in_array($ext, ['png','jpg','jpeg']) ? 'image' : 'file';
                $pending_files[] = [
                    'tmp_name' => $tmp_name,
                    'filename' => $filename,
                    'file_type' => $file_type
                ];
            }
        }

        if (!$file_error && !is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
            $file_error = "Uploads folder is missing and could not be created.";
        }

        if (!$file_error && !is_writable($upload_dir)) {
            $file_error = "Uploads folder is not writable.";
        }

        if ($file_error) {
            $error = $file_error;
        } else {
            $stmt = $pdo->prepare("INSERT INTO notes (user_id, title, description, subject, semester, section) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $title, $description, $subject, $semester, $section]);
            $note_id = $pdo->lastInsertId();
            $uploaded = 0;
            $moved_paths = [];

            foreach ($pending_files as $pending) {
                $target_path = $upload_dir . "/" . $pending['filename'];

                if (move_uploaded_file($pending['tmp_name'], $target_path) && is_file($target_path)) {
                    $moved_paths[] = $target_path;
                    $stmt2 = $pdo->prepare("INSERT INTO note_files (note_id, filename, file_type) VALUES (?, ?, ?)");
                    $stmt2->execute([$note_id, $pending['filename'], $pending['file_type']]);
                    $uploaded++;
                } else {
                    $file_error = "File upload failed before saving. Please upload the note again.";
                    break;
                }
            }

            if ($file_error) {
                $pdo->prepare("DELETE FROM note_files WHERE note_id = ?")->execute([$note_id]);
                $pdo->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?")->execute([$note_id, $user_id]);
                foreach ($moved_paths as $moved_path) {
                    if (is_file($moved_path)) {
                        @unlink($moved_path);
                    }
                }
                $error = $file_error;
            } else {
                $success = "Note uploaded successfully" . ($uploaded > 0 ? " with $uploaded file(s)!" : "!");
            }
        }
    }
}

// Subject icons map
$subject_icons = [
    'calculus' => '📐', 'algebra' => '📊', 'physics' => '⚡', 'chemistry' => '🧪',
    'programming' => '💻', 'data' => '🗄️', 'network' => '🌐', 'software' => '⚙️',
    'web' => '🌍', 'machine' => '🤖', 'artificial' => '🧠', 'database' => '💾',
    'operating' => '🖥️', 'computer' => '🖥️', 'digital' => '🔌', 'discrete' => '🔢',
    'statistics' => '📈', 'english' => '📝', 'islamic' => '☪️', 'quran' => '📖',
    'management' => '📋', 'linear' => '📉', 'theory' => '🔬', 'parallel' => '⚡',
    'compiler' => '⚙️', 'mobile' => '📱', 'cloud' => '☁️', 'security' => '🔒',
    'natural' => '🗣️', 'vision' => '👁️', 'graphics' => '🎨', 'writing' => '✍️',
    'entrepreneurship' => '💡', 'marketing' => '📣', 'analysis' => '🔍',
    'expository' => '✍️', 'multivariable' => '📐', 'information' => '📡',
    'pre-calculus' => '📐', 'object' => '🧩', 'functional' => '🗣️',
    'knowledge' => '🧠', 'neural' => '🤖', 'deep' => '🤖',
];

function getSubjectIcon($subject, $icons) {
    $lower = strtolower($subject);
    foreach ($icons as $keyword => $icon) {
        if (strpos($lower, $keyword) !== false) return $icon;
    }
    return '📚';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Upload Note — NoteSwap</title>
<script>const t=localStorage.getItem('theme')||'dark';document.documentElement.setAttribute('data-theme',t);</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --accent: #a855f7;
    --accent-bright: #c084fc;
    --accent-dim: rgba(168,85,247,0.15);
    --accent-glow: rgba(168,85,247,0.4);
    --font-main: 'Plus Jakarta Sans', sans-serif;
    --font-body: 'Inter', sans-serif;
}

[data-theme="dark"] {
    --bg-page:   #221f31;
    --bg-card:   rgba(30,27,46,0.75);
    --bg-input:  rgba(15,14,23,0.8);
    --bg-chip:   rgba(255,255,255,0.04);
    --border:    rgba(255,255,255,0.08);
    --border-md: rgba(255,255,255,0.12);
    --text-prim: #f3f0ff;
    --text-sec:  #9ca3af;
    --text-dim:  #6b7280;
}

[data-theme="light"] {
    --bg-page:   #efeef3;
    --bg-card:   rgba(255,255,255,0.7);
    --bg-input:  rgba(255,255,255,0.9);
    --bg-chip:   rgba(0,0,0,0.03);
    --border:    rgba(0,0,0,0.08);
    --border-md: rgba(0,0,0,0.12);
    --text-prim: #111827;
    --text-sec:  #6b7280;
    --text-dim:  #9ca3af;
}

body {
    font-family: var(--font-body);
    background: var(--bg-page);
    color: var(--text-prim);
    min-height: 100vh;
    transition: background 0.3s, color 0.3s;
}

/* NAVBAR */
.up-nav {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 32px;
    border-bottom: 1px solid var(--border);
    background: rgba(0,0,0,0.2);
    backdrop-filter: blur(12px);
    position: sticky; top: 0; z-index: 100;
}

.up-nav-brand {
    display: flex; align-items: center; gap: 8px;
    font-family: var(--font-main); font-size: 18px; font-weight: 700;
    text-decoration: none;
    background: linear-gradient(135deg, #a855f7, #f59e0b);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
}

.up-nav-right { display: flex; align-items: center; gap: 10px; }

.up-nav-user {
    display: flex; align-items: center; gap: 8px;
    font-size: 13px; font-weight: 600; color: var(--text-sec);
}

.up-nav-user img {
    width: 30px; height: 30px; border-radius: 50%;
    border: 2px solid var(--accent); object-fit: cover;
}

.up-nav-btn {
    padding: 7px 16px; border-radius: 50px;
    font-size: 12px; font-weight: 600;
    cursor: pointer; text-decoration: none;
    border: 1px solid var(--border-md);
    background: var(--bg-chip);
    color: var(--text-sec);
    font-family: var(--font-main);
    transition: all 0.2s;
}
.up-nav-btn:hover { border-color: var(--accent); color: var(--accent); }
.up-nav-btn-theme { background: none; }

/* PAGE */
.up-page {
    max-width: 820px; margin: 0 auto;
    padding: 36px 20px 60px;
}

/* HEADER */
.up-header {
    display: flex; justify-content: space-between; align-items: flex-start;
    margin-bottom: 32px;
}

.up-title {
    font-family: var(--font-main); font-size: 26px; font-weight: 800;
    color: var(--text-prim); margin-bottom: 4px;
}

.up-subtitle { font-size: 13px; color: var(--text-sec); }

.up-progress-wrap { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; }

.up-progress-label { font-size: 11px; color: var(--text-dim); font-weight: 600; }

.up-progress-bar {
    width: 140px; height: 4px;
    background: var(--border);
    border-radius: 4px; overflow: hidden;
}

.up-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #7c3aed, #a855f7);
    border-radius: 4px;
    transition: width 0.4s ease;
}

/* ALERT */
.up-alert {
    padding: 12px 18px; border-radius: 10px; margin-bottom: 20px;
    font-size: 13px; display: flex; align-items: center; gap: 8px;
    font-family: var(--font-body);
}
.up-alert-error   { background: rgba(239,68,68,0.1);   color: #f87171; border: 1px solid rgba(239,68,68,0.25); }
.up-alert-success { background: rgba(168,85,247,0.12); color: #c084fc; border: 1px solid rgba(168,85,247,0.3); }

/* PHASE BLOCKS */
.phase-block {
    border-radius: 16px;
    border: 1px solid var(--border);
    background: var(--bg-card);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    margin-bottom: 16px;
    overflow: hidden;
    transition: all 0.3s;
}

.phase-block.active {
    border-color: rgba(168,85,247,0.5);
    box-shadow: 0 0 0 1px rgba(168,85,247,0.2),
                0 0 24px rgba(168,85,247,0.35),
                0 8px 32px rgba(0,0,0,0.2);
}

.phase-block.inactive {
    opacity: 0.5;
    pointer-events: none;
}

.phase-header {
    display: flex; align-items: center; gap: 12px;
    padding: 18px 24px 16px;
}

.phase-badge {
    width: 28px; height: 28px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700; flex-shrink: 0;
    font-family: var(--font-main);
}

.phase-block.active   .phase-badge { background: var(--accent); color: white; }
.phase-block.inactive .phase-badge { background: var(--border); color: var(--text-dim); }
.phase-block.done     .phase-badge { background: rgba(168,85,247,0.2); color: var(--accent-bright); }

.phase-title {
    font-family: var(--font-main); font-size: 15px; font-weight: 700;
    color: var(--text-prim);
}

.phase-step {
    margin-left: auto; font-size: 11px; font-weight: 600;
    color: var(--text-dim);
}

.phase-body { padding: 0 24px 24px; }

/* FORM ROWS */
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 18px; }

.up-label {
    display: block; font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.8px;
    color: var(--text-dim); margin-bottom: 7px;
    font-family: var(--font-main);
}

.up-select, .up-input, .up-textarea {
    width: 100%; padding: 11px 14px;
    border-radius: 10px; border: 1px solid var(--border-md);
    background: var(--bg-input);
    color: var(--text-prim);
    font-size: 13px; font-family: var(--font-body);
    outline: none; transition: border-color 0.2s, box-shadow 0.2s;
    -webkit-appearance: none;
}

.up-select:focus, .up-input:focus, .up-textarea:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(168,85,247,0.15);
}

.up-select option { background: #1a1825; }
.up-textarea { resize: vertical; min-height: 90px; }

/* SUBJECT CHIPS */
.subject-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
    gap: 8px;
    margin-top: 4px;
}

.subject-chip {
    display: flex; align-items: center; gap: 8px;
    padding: 9px 14px;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: var(--bg-chip);
    cursor: pointer;
    transition: all 0.2s;
    font-size: 13px; font-weight: 500;
    color: var(--text-sec);
    font-family: var(--font-body);
    user-select: none;
}

.subject-chip:hover {
    border-color: rgba(168,85,247,0.4);
    background: var(--accent-dim);
    color: var(--text-prim);
}

.subject-chip.selected {
    border-color: var(--accent);
    background: rgba(168,85,247,0.12);
    color: var(--accent-bright);
    box-shadow: 0 0 0 1px rgba(168,85,247,0.2);
}

.chip-icon { font-size: 15px; flex-shrink: 0; }
.chip-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; }

/* HIDDEN SUBJECT INPUT */
#subject-hidden { display: none; }

/* INPUT GROUP */
.up-group { margin-bottom: 16px; }

/* DRAG DROP ZONE */
.drop-zone {
    border: 2px dashed var(--border-md);
    border-radius: 14px;
    padding: 40px 20px;
    text-align: center;
    position: relative;
    cursor: pointer;
    transition: all 0.25s;
    background: var(--bg-chip);
}

.drop-zone:hover, .drop-zone.drag-over {
    border-color: var(--accent);
    background: var(--accent-dim);
}

.drop-zone-icon {
    font-size: 40px; display: block; margin-bottom: 12px;
    opacity: 0.5;
}

.drop-zone-text {
    font-size: 14px; color: var(--text-sec); margin-bottom: 4px;
    font-family: var(--font-main); font-weight: 600;
}

.drop-zone-sub { font-size: 12px; color: var(--text-dim); }

.drop-zone-sub a { color: var(--accent); text-decoration: none; cursor: pointer; }
.drop-zone-sub a:hover { text-decoration: underline; }

/* FLOATING DOC ICONS */
.drop-floating-icons {
    position: absolute; right: 20px; top: 50%;
    transform: translateY(-50%);
    display: flex; flex-direction: column; gap: 8px;
}

.doc-float-icon {
    width: 44px; height: 52px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px;
    border: 1px solid var(--border-md);
    background: var(--bg-card);
    backdrop-filter: blur(8px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

/* FILE PREVIEW LIST */
.file-preview-list {
    margin-top: 12px; display: flex; flex-direction: column; gap: 8px;
}

.file-preview-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 9px 14px;
    background: var(--bg-chip);
    border: 1px solid var(--border);
    border-radius: 10px;
    font-size: 13px; color: var(--text-prim);
}

.file-preview-item .fp-remove {
    background: none; border: none; cursor: pointer;
    color: var(--text-dim); font-size: 16px; padding: 0 4px;
    transition: color 0.2s; line-height: 1;
}
.file-preview-item .fp-remove:hover { color: #f87171; }

.file-size-text { font-size: 11px; color: var(--text-dim); margin-left: 8px; }

/* UPLOAD BUTTON */
.up-btn {
    width: 100%; padding: 15px;
    border: none; border-radius: 12px;
    background: linear-gradient(90deg, #7c3aed 0%, #a855f7 50%, #c084fc 100%);
    color: white; font-size: 15px; font-weight: 700;
    font-family: var(--font-main);
    cursor: pointer; margin-top: 20px;
    transition: opacity 0.2s, transform 0.15s;
    letter-spacing: 0.3px;
    box-shadow: 0 4px 20px rgba(168,85,247,0.35);
}

.up-btn:hover { opacity: 0.92; transform: translateY(-1px); }
.up-btn:active { transform: translateY(0); }

/* SUCCESS STATE */
.success-screen {
    text-align: center; padding: 48px 20px;
    border-radius: 16px;
    border: 1px solid rgba(168,85,247,0.3);
    background: var(--bg-card);
    backdrop-filter: blur(16px);
    box-shadow: 0 0 24px rgba(168,85,247,0.2);
}

.success-icon { font-size: 56px; display: block; margin-bottom: 16px; }
.success-title { font-family: var(--font-main); font-size: 22px; font-weight: 700; color: var(--text-prim); margin-bottom: 8px; }
.success-sub { font-size: 14px; color: var(--text-sec); margin-bottom: 24px; }
.success-btns { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }

.s-btn {
    padding: 10px 24px; border-radius: 50px;
    font-size: 13px; font-weight: 600; font-family: var(--font-main);
    text-decoration: none; transition: all 0.2s; cursor: pointer; border: none;
}
.s-btn-primary { background: linear-gradient(90deg,#7c3aed,#a855f7); color: white; }
.s-btn-primary:hover { opacity: 0.9; }
.s-btn-secondary { background: var(--bg-chip); color: var(--text-sec); border: 1px solid var(--border-md); }
.s-btn-secondary:hover { border-color: var(--accent); color: var(--accent); }

@media (max-width: 600px) {
    .form-row-2 { grid-template-columns: 1fr; }
    .up-header { flex-direction: column; gap: 14px; }
    .up-nav { padding: 12px 16px; }
    .drop-floating-icons { display: none; }
    .subject-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="up-nav">
    <a href="dashboard.php" class="up-nav-brand">
        <svg width="22" height="22" viewBox="0 0 28 28" fill="none">
            <rect x="3" y="2" width="16" height="20" rx="3" stroke="#a855f7" stroke-width="2" fill="none"/>
            <path d="M7 8h8M7 12h6" stroke="#a855f7" stroke-width="2" stroke-linecap="round"/>
            <path d="M14 18c0-2.2 1.8-4 4-4s4 1.8 4 4-1.8 4-4 4-4-1.8-4-4z" stroke="#f59e0b" stroke-width="2" fill="none"/>
            <path d="M20.5 21.5l2.5 2.5" stroke="#f59e0b" stroke-width="2" stroke-linecap="round"/>
        </svg>
        NoteSwap
    </a>
    <div class="up-nav-right">
        <div class="up-nav-user">
            <img src="<?= $avatar_url ?>" alt="avatar">
            <?= htmlspecialchars($_SESSION['user_name']) ?>
        </div>
        <a href="dashboard.php" class="up-nav-btn">← Back</a>
        
        <button class="up-nav-btn up-nav-btn-theme" onclick="toggleDark()" id="theme-btn">🌙</button>
    </div>
</nav>

<div class="up-page">

    <!-- HEADER -->
    <div class="up-header">
        <div>
            <h1 class="up-title">Upload a Note</h1>
            <p class="up-subtitle">Share your knowledge with your classmates</p>
        </div>
        <div class="up-progress-wrap">
            <span class="up-progress-label" id="progress-label">1 of 3 complete</span>
            <div class="up-progress-bar">
                <div class="up-progress-fill" id="progress-fill" style="width:33%"></div>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="up-alert up-alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
    <!-- SUCCESS SCREEN -->
    <div class="success-screen">
        <span class="success-icon">🎉</span>
        <h2 class="success-title">Note Uploaded Successfully!</h2>
        <p class="success-sub"><?= htmlspecialchars($success) ?></p>
        <div class="success-btns">
            <a href="dashboard.php" class="s-btn s-btn-primary">Go to Dashboard</a>
            <a href="upload.php" class="s-btn s-btn-secondary">Upload Another</a>
        </div>
    </div>

    <?php else: ?>

    <form method="POST" enctype="multipart/form-data" id="upload-form">

        <!-- ── PHASE 1: SOURCE SELECTION ── -->
        <div class="phase-block active" id="phase1">
            <div class="phase-header">
                <div class="phase-badge">1</div>
                <span class="phase-title">Phase 1: Source Selection</span>
            </div>
            <div class="phase-body">
                <div class="form-row-2">
                    <div>
                        <label class="up-label">Section</label>
                        <select name="section" class="up-select" id="section-select">
                            <option value="BSCS" <?= $selected_section=='BSCS'?'selected':'' ?>>BSCS</option>
                            <option value="BSAI" <?= $selected_section=='BSAI'?'selected':'' ?>>BSAI</option>
                            <option value="BSSE" <?= $selected_section=='BSSE'?'selected':'' ?>>BSSE</option>
                        </select>
                    </div>
                    <div>
                        <label class="up-label">Semester</label>
                        <select name="semester" class="up-select" id="semester-select">
                            <?php for ($i=1;$i<=8;$i++): ?>
                                <option value="<?=$i?>" <?=$selected_semester==$i?'selected':''?>>
                                    Semester <?=$i?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <label class="up-label">Subject</label>
                <input type="hidden" name="subject" id="subject-hidden"
                       value="<?= !empty($subjects) ? htmlspecialchars($subjects[0]) : '' ?>">

                <div class="subject-grid" id="subject-grid">
                    <?php foreach ($subjects as $i => $subj): ?>
                    <div class="subject-chip <?= $i===0?'selected':'' ?>"
                         onclick="selectSubject('<?= htmlspecialchars(addslashes($subj)) ?>', this)">
                        <span class="chip-icon"><?= getSubjectIcon($subj, $subject_icons) ?></span>
                        <span class="chip-text"><?= htmlspecialchars($subj) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ── PHASE 2: NOTE DETAILS ── -->
        <div class="phase-block inactive" id="phase2">
            <div class="phase-header">
                <div class="phase-badge">2</div>
                <span class="phase-title">Phase 2: Add Note Details</span>
                <span class="phase-step">2/3</span>
            </div>
            <div class="phase-body">
                <div class="form-row-2">
                    <div class="up-group">
                        <label class="up-label">Note Title</label>
                        <input type="text" name="title" class="up-input"
                               placeholder="e.g. Chapter 5 — Linked Lists"
                               oninput="checkPhase2()">
                    </div>
                    <div class="up-group">
                        <label class="up-label">Description</label>
                        <input type="text" name="description" class="up-input"
                               placeholder="What does this note cover?">
                    </div>
                </div>
            </div>
        </div>

        <!-- ── PHASE 3: ATTACH FILES ── -->
        <div class="phase-block inactive" id="phase3">
            <div class="phase-header">
                <div class="phase-badge">3</div>
                <span class="phase-title">Phase 3: Attach Files</span>
            </div>
            <div class="phase-body">

                <div class="drop-zone" id="drop-zone"
                     onclick="document.getElementById('file-input').click()">
                    <span class="drop-zone-icon">📁</span>
                    <p class="drop-zone-text">Drag and drop files here or
                        <span style="color:var(--accent);cursor:pointer">browse</span>
                    </p>
                    <p class="drop-zone-sub">
                        Max 5MB per file &nbsp;|&nbsp; Total 20MB
                        &nbsp;|&nbsp; Need compression?
                        <a href="https://www.ilovepdf.com" target="_blank"
                           onclick="event.stopPropagation()">ilovepdf.com</a>
                    </p>

                    <!-- Floating doc icons -->
                    <div class="drop-floating-icons">
                        <div class="doc-float-icon">📄</div>
                        <div class="doc-float-icon">📝</div>
                    </div>
                </div>

                <input type="file" id="file-input" name="files[]" multiple
                       accept=".pdf,.docx,.png,.jpg,.jpeg" style="display:none"
                       onchange="handleFiles(this.files)">

                <div class="file-preview-list" id="file-preview"></div>

            </div>
        </div>

        <!-- UPLOAD BUTTON -->
        <button type="submit" name="upload" class="up-btn" id="upload-btn">
            Upload Note
        </button>

    </form>
    <?php endif; ?>

</div>

<script>
// ── DARK MODE ──
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

// ── SUBJECT SELECTION ──
function selectSubject(subj, el) {
    document.querySelectorAll('.subject-chip').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('subject-hidden').value = subj;
    unlockPhase2();
}

function unlockPhase2() {
    document.getElementById('phase1').classList.remove('active');
    document.getElementById('phase1').classList.add('done');
    document.getElementById('phase2').classList.remove('inactive');
    document.getElementById('phase2').classList.add('active');
    document.getElementById('progress-fill').style.width = '66%';
    document.getElementById('progress-label').textContent = '2 of 3 complete';
    // Focus title input
    setTimeout(() => {
        const t = document.querySelector('input[name="title"]');
        if (t) t.focus();
    }, 100);
}

function checkPhase2() {
    const title = document.querySelector('input[name="title"]').value.trim();
    if (title.length > 0) {
        document.getElementById('phase2').classList.remove('active');
        document.getElementById('phase2').classList.add('done');
        document.getElementById('phase3').classList.remove('inactive');
        document.getElementById('phase3').classList.add('active');
        document.getElementById('progress-fill').style.width = '100%';
        document.getElementById('progress-label').textContent = '3 of 3 complete';
    } else {
        document.getElementById('phase3').classList.add('inactive');
        document.getElementById('phase3').classList.remove('active');
        document.getElementById('phase2').classList.remove('done');
        document.getElementById('phase2').classList.add('active');
        document.getElementById('progress-fill').style.width = '66%';
        document.getElementById('progress-label').textContent = '2 of 3 complete';
    }
}

// ── SECTION/SEMESTER CHANGE → reload subjects ──
document.getElementById('section-select').addEventListener('change', reloadSubjects);
document.getElementById('semester-select').addEventListener('change', reloadSubjects);

function reloadSubjects() {
    const section  = document.getElementById('section-select').value;
    const semester = document.getElementById('semester-select').value;

    fetch(`get_subjects.php?section=${section}&semester=${semester}`)
        .then(r => r.json())
        .then(subjects => {
            const grid = document.getElementById('subject-grid');
            grid.innerHTML = '';
            document.getElementById('subject-hidden').value = subjects[0] || '';

            subjects.forEach((subj, i) => {
    const chip = document.createElement('div');
    chip.className = 'subject-chip' + (i === 0 ? ' selected' : '');
    chip.innerHTML = `<span class="chip-icon">${getSubjectIconJS(subj)}</span>
                      <span class="chip-text">${subj}</span>`;
    chip.onclick = () => selectSubject(subj, chip);
    grid.appendChild(chip);
});

            // Reset phases
            document.getElementById('phase1').classList.add('active');
            document.getElementById('phase1').classList.remove('done');
            document.getElementById('phase2').classList.add('inactive');
            document.getElementById('phase2').classList.remove('active','done');
            document.getElementById('phase3').classList.add('inactive');
            document.getElementById('phase3').classList.remove('active','done');
            document.getElementById('progress-fill').style.width = '33%';
            document.getElementById('progress-label').textContent = '1 of 3 complete';
        });
}

// ── FILE HANDLING ──
let selectedFiles = [];

function handleFiles(fileList) {
    const allowed = ['pdf','png','jpg','jpeg','docx'];
    Array.from(fileList).forEach(file => {
        const ext = file.name.split('.').pop().toLowerCase();
        if (!allowed.includes(ext)) {
            alert(`${file.name} — only PDF, Word, and image files are allowed.`);
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            alert(`${file.name} is over 5MB. Please compress it first.`);
            return;
        }
        selectedFiles.push(file);
    });
    renderFilePreviews();
    updateFileInput();
}

function renderFilePreviews() {
    const list = document.getElementById('file-preview');
    list.innerHTML = '';
    selectedFiles.forEach((file, idx) => {
        const ext  = file.name.split('.').pop().toLowerCase();
        const icon = ['png','jpg','jpeg'].includes(ext) ? '🖼️' : (ext==='pdf'?'📄':'📝');
        const div  = document.createElement('div');
        div.className = 'file-preview-item';
        div.innerHTML = `
            <span>${icon} ${file.name} <span class="file-size-text">${(file.size/1024).toFixed(1)} KB</span></span>
            <button type="button" class="fp-remove" onclick="removeFile(${idx})">✕</button>
        `;
        list.appendChild(div);
    });
}

function removeFile(idx) {
    selectedFiles.splice(idx, 1);
    renderFilePreviews();
    updateFileInput();
}

function updateFileInput() {
    const dt = new DataTransfer();
    selectedFiles.forEach(f => dt.items.add(f));
    document.getElementById('file-input').files = dt.files;
}

// Drag and drop
const dropZone = document.getElementById('drop-zone');
dropZone.addEventListener('dragover',  e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
    handleFiles(e.dataTransfer.files);
});
function getSubjectIconJS(subject) {
    const lower = subject.toLowerCase();
    const icons = {
        'calculus': '📐', 'algebra': '📊', 'physics': '⚡',
        'chemistry': '🧪', 'programming': '💻', 'data': '🗄️',
        'network': '🌐', 'software': '⚙️', 'web': '🌍',
        'machine': '🤖', 'artificial': '🧠', 'database': '💾',
        'operating': '🖥️', 'computer': '🖥️', 'digital': '🔌',
        'discrete': '🔢', 'statistics': '📈', 'english': '📝',
        'islamic': '☪️', 'quran': '📖', 'management': '📋',
        'linear': '📉', 'theory': '🔬', 'parallel': '⚡',
        'compiler': '⚙️', 'mobile': '📱', 'cloud': '☁️',
        'security': '🔒', 'natural': '🗣️', 'vision': '👁️',
        'graphics': '🎨', 'writing': '✍️', 'entrepreneurship': '💡',
        'marketing': '📣', 'analysis': '🔍', 'expository': '✍️',
        'multivariable': '📐', 'information': '📡', 'pre-calculus': '📐',
        'object': '🧩', 'functional': '🗣️', 'knowledge': '🧠',
        'neural': '🤖', 'deep': '🤖', 'cyber': '🔒',
        'architecture': '🏗️', 'numerical': '🔢', 'construction': '⚙️',
        'quality': '✅', 'project': '📋', 'language': '🗣️'
    };
    for (const [keyword, icon] of Object.entries(icons)) {
        if (lower.includes(keyword)) return icon;
    }
    return '📚';
}
</script>
</body>
</html>

<?php
require_once 'db.php';
require_once 'bookmark_helper.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['note_id'], $_POST['bookmark_action'])) {
    header('Location: dashboard.php');
    exit();
}

$user_id   = (int) $_SESSION['user_id'];
$note_id   = (int) $_POST['note_id'];
$action    = $_POST['bookmark_action'];
$redirect  = safe_bookmark_redirect((string) ($_POST['redirect'] ?? 'dashboard.php'));

if ($note_id <= 0) {
    header('Location: ' . $redirect);
    exit();
}

try {
    ensure_note_user_bookmarks_table($pdo);

    $chk = $pdo->prepare('SELECT id FROM notes WHERE id = ?');
    $chk->execute([$note_id]);
    if (!$chk->fetch()) {
        header('Location: ' . $redirect);
        exit();
    }

    $stmt = $pdo->prepare('SELECT is_pinned, is_favorite FROM note_user_bookmarks WHERE user_id = ? AND note_id = ?');
    $stmt->execute([$user_id, $note_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $p   = $row ? (int) $row['is_pinned'] : 0;
    $f   = $row ? (int) $row['is_favorite'] : 0;

    switch ($action) {
        case 'pin':
            $p = 1;
            break;
        case 'unpin':
            $p = 0;
            break;
        case 'favorite':
            $f = 1;
            break;
        case 'unfavorite':
            $f = 0;
            break;
        default:
            header('Location: ' . $redirect);
            exit();
    }

    if ($p === 0 && $f === 0) {
        $pdo->prepare('DELETE FROM note_user_bookmarks WHERE user_id = ? AND note_id = ?')
            ->execute([$user_id, $note_id]);
    } else {
        $ins = $pdo->prepare('
            INSERT INTO note_user_bookmarks (user_id, note_id, is_pinned, is_favorite)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE is_pinned = VALUES(is_pinned), is_favorite = VALUES(is_favorite)
        ');
        $ins->execute([$user_id, $note_id, $p, $f]);
    }
} catch (Exception $e) {
    // fall through to redirect
}

header('Location: ' . $redirect);
exit();

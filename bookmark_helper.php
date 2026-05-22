<?php

/**
 * Per-user pin/favorite for notes. Each row is unique (user_id, note_id).
 */
function ensure_note_user_bookmarks_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS note_user_bookmarks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            note_id INT NOT NULL,
            is_pinned TINYINT(1) NOT NULL DEFAULT 0,
            is_favorite TINYINT(1) NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_note_user_bookmarks (user_id, note_id),
            INDEX idx_nub_user (user_id),
            INDEX idx_nub_note (note_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function safe_bookmark_redirect(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return 'dashboard.php';
    }
    if (strlen($url) > 512 || strpos($url, '..') !== false) {
        return 'dashboard.php';
    }
    if (!preg_match('#^[a-zA-Z0-9_\-\./?=&%]+$#', $url)) {
        return 'dashboard.php';
    }
    return $url;
}

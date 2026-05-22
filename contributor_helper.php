<?php

/**
 * Contribution score: simple, transparent (10 pts per uploaded note).
 */
function ns_contribution_score(int $note_count): int
{
    return $note_count * 10;
}

/**
 * Per-user upload counts (only users who have at least one note appear, but we need all users with 0 too for counting).
 * Returns map user_id => note_count for every user in `users`.
 */
function ns_user_upload_counts(PDO $pdo): array
{
    $rows = $pdo->query('
        SELECT u.id AS user_id, COUNT(n.id) AS cnt
        FROM users u
        LEFT JOIN notes n ON n.user_id = u.id
        GROUP BY u.id
    ')->fetchAll(PDO::FETCH_ASSOC);

    $map = [];
    foreach ($rows as $r) {
        $map[(int) $r['user_id']] = (int) $r['cnt'];
    }
    return $map;
}

/**
 * Dynamic "Top Contributor" rule (no numeric ranks shown):
 * - By default you need strictly MORE than 4 notes (i.e. note_count > 4, same as >= 5).
 * - If 5 or more users satisfy the current bar, raise the bar by +2 on that boundary
 *   (e.g. >4 → >6 → >8 …), matching "tighten when too many qualify".
 *
 * @return array{t_exclusive: int, max_notes: int, eligible_user_ids: int[]}
 */
function ns_top_contributor_rule(PDO $pdo): array
{
    $counts = ns_user_upload_counts($pdo);
    $max_notes = empty($counts) ? 0 : max($counts);

    // t = exclusive lower bound: qualify iff note_count > t
    $t = 4;

    $count_eligible = function (int $threshold) use ($counts): int {
        $n = 0;
        foreach ($counts as $c) {
            if ($c > $threshold) {
                $n++;
            }
        }
        return $n;
    };

    $eligible = $count_eligible($t);
    while ($eligible >= 5) {
        $t += 2;
        $eligible = $count_eligible($t);
    }

    $eligible_ids = [];
    foreach ($counts as $uid => $c) {
        if ($c > $t) {
            $eligible_ids[] = (int) $uid;
        }
    }

    // If the raised bar excludes everyone, recognize users tied for max uploads — but only if
    // that tie group is ≤4 (avoids giving the tag to a huge flat tie).
    if (empty($eligible_ids) && $max_notes > 0) {
        $at_max = [];
        foreach ($counts as $uid => $c) {
            if ($c === $max_notes) {
                $at_max[] = (int) $uid;
            }
        }
        if (count($at_max) > 0 && count($at_max) <= 4) {
            $eligible_ids = $at_max;
        }
    }

    return [
        't_exclusive'       => $t,
        'max_notes'         => $max_notes,
        'eligible_user_ids' => $eligible_ids,
    ];
}

function ns_is_top_contributor(int $user_id, int $note_count, array $rule): bool
{
    foreach ($rule['eligible_user_ids'] as $id) {
        if ($id === $user_id) {
            return true;
        }
    }
    return false;
}

/**
 * Simple automatic tier label from score (optional display).
 */
function ns_contributor_tier_label(int $note_count): string
{
    if ($note_count <= 0) {
        return 'Newcomer';
    }
    if ($note_count < 3) {
        return 'Contributor';
    }
    if ($note_count < 8) {
        return 'Regular contributor';
    }
    if ($note_count < 15) {
        return 'Campus helper';
    }
    return 'NoteSwap champion';
}

/* ── Manual Top contributor (admin-granted; stacks with upload-based rule) ── */

function ns_ensure_manual_top_contributor_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS manual_top_contributors (
            user_id INT NOT NULL PRIMARY KEY,
            granted_by INT NULL,
            granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_mtc_granted_by (granted_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function ns_is_manual_top_contributor(PDO $pdo, int $user_id): bool
{
    try {
        ns_ensure_manual_top_contributor_table($pdo);
        $st = $pdo->prepare('SELECT 1 FROM manual_top_contributors WHERE user_id = ? LIMIT 1');
        $st->execute([$user_id]);
        return (bool) $st->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

function ns_grant_manual_top_contributor(PDO $pdo, int $user_id, int $admin_user_id): void
{
    ns_ensure_manual_top_contributor_table($pdo);
    $st = $pdo->prepare('
        INSERT INTO manual_top_contributors (user_id, granted_by)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE granted_by = VALUES(granted_by), granted_at = CURRENT_TIMESTAMP
    ');
    $st->execute([$user_id, $admin_user_id]);
}

function ns_revoke_manual_top_contributor(PDO $pdo, int $user_id): void
{
    ns_ensure_manual_top_contributor_table($pdo);
    $pdo->prepare('DELETE FROM manual_top_contributors WHERE user_id = ?')->execute([$user_id]);
}

/**
 * All user IDs with a manual Top contributor row (for admin lists).
 *
 * @return array<int, true>
 */
function ns_manual_top_contributor_id_set(PDO $pdo): array
{
    try {
        ns_ensure_manual_top_contributor_table($pdo);
        $ids = $pdo->query('SELECT user_id FROM manual_top_contributors')->fetchAll(PDO::FETCH_COLUMN);
        $set = [];
        foreach ($ids as $id) {
            $set[(int) $id] = true;
        }
        return $set;
    } catch (Exception $e) {
        return [];
    }
}

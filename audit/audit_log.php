<?php
// ============================================================
//  MediaVault - Audit Log
//  Module 4: Database Automation & Transaction Integrity
// ============================================================
require_once '../includes/session_guard.php';
require_once '../config/db.php';

// ── Pagination ────────────────────────────────────────────────
$per_page   = 20;
$page       = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page - 1) * $per_page;

// ── Filters ───────────────────────────────────────────────────
$filter_op  = $_GET['op']      ?? '';
$filter_out = $_GET['outcome'] ?? '';
$filter_uid = (int)($_GET['uid'] ?? 0);

$where_parts = [];
$bind_types  = '';
$bind_vals   = [];

if ($filter_op !== '') {
    $where_parts[] = 'a.operation_type = ?';
    $bind_types   .= 's';
    $bind_vals[]   = $filter_op;
}
if ($filter_out !== '') {
    $where_parts[] = 'a.outcome = ?';
    $bind_types   .= 's';
    $bind_vals[]   = $filter_out;
}
if ($filter_uid > 0) {
    $where_parts[] = 'a.user_id = ?';
    $bind_types   .= 'i';
    $bind_vals[]   = $filter_uid;
}

$where_sql = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

// ── Count ─────────────────────────────────────────────────────
$count_sql  = "SELECT COUNT(*) AS total
               FROM transaction_audit_log a
               $where_sql";
$total_rows = 0;
if ($stmt = mysqli_prepare($conn, $count_sql)) {
    if ($bind_types) {
        mysqli_stmt_bind_param($stmt, $bind_types, ...$bind_vals);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $total_rows = (int)(mysqli_fetch_assoc($res)['total'] ?? 0);
    mysqli_stmt_close($stmt);
}
$total_pages = max(1, (int)ceil($total_rows / $per_page));

// ── Fetch rows ────────────────────────────────────────────────
$rows = [];
$data_sql = "SELECT
                 a.log_id,
                 a.operation_type,
                 a.timestamp,
                 a.outcome,
                 a.user_id,
                 u.username,
                 a.file_id,
                 f.file_name,
                 f.file_type
             FROM transaction_audit_log a
             LEFT JOIN user_accounts      u ON u.user_id  = a.user_id
             LEFT JOIN multimedia_files   f ON f.file_id  = a.file_id
             $where_sql
             ORDER BY a.log_id DESC
             LIMIT ? OFFSET ?";

$full_bind_types = $bind_types . 'ii';
$full_bind_vals  = array_merge($bind_vals, [$per_page, $offset]);

if ($stmt = mysqli_prepare($conn, $data_sql)) {
    mysqli_stmt_bind_param($stmt, $full_bind_types, ...$full_bind_vals);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// ── Summary counts ────────────────────────────────────────────
$summary = ['INSERT' => 0, 'UPDATE' => 0, 'DELETE' => 0, 'SUCCESS' => 0, 'FAILED' => 0, 'TOTAL' => $total_rows];
$sum_sql = "SELECT operation_type, outcome, COUNT(*) AS cnt FROM transaction_audit_log GROUP BY operation_type, outcome";
if ($res2 = mysqli_query($conn, $sum_sql)) {
    while ($r = mysqli_fetch_assoc($res2)) {
        $summary[$r['operation_type']] = ($summary[$r['operation_type']] ?? 0) + (int)$r['cnt'];
        $summary[$r['outcome']]        = ($summary[$r['outcome']]        ?? 0) + (int)$r['cnt'];
    }
}

// ── Users list for filter dropdown ────────────────────────────
$users = [];
if ($res3 = mysqli_query($conn, "SELECT user_id, username FROM user_accounts ORDER BY username")) {
    while ($u = mysqli_fetch_assoc($res3)) $users[] = $u;
}

// helper: build URL with updated query param
function audit_url(array $overrides): string {
    $params = array_merge($_GET, $overrides);
    // always reset to page 1 when filters change
    if (array_key_exists('op', $overrides) || array_key_exists('outcome', $overrides) || array_key_exists('uid', $overrides)) {
        $params['page'] = 1;
    }
    return '?' . http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== '0' && $v !== 0));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log — MediaVault</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #eef1f6; min-height: 100vh; color: #1a1a2e; }

        /* ── NAVBAR (identical to dashboard) ── */
        .navbar {
            background: #0f3460;
            padding: 0 32px;
            height: 58px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky; top: 0; z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,0.25);
        }
        .nav-logo { font-size: 20px; font-weight: 800; color: #fff; letter-spacing: 1px; text-decoration: none; }
        .nav-logo span { color: #ffd700; }
        .nav-right { display: flex; align-items: center; gap: 10px; font-size: 13px; color: rgba(255,255,255,0.75); }
        .nav-role { background: #e94560; color: #fff; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .nav-role.admin  { background: #e94560; }
        .nav-role.user   { background: #2563eb; }
        .nav-role.viewer { background: #64748b; }
        .btn-logout { color: #fff; text-decoration: none; background: rgba(255,255,255,0.12); padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; }
        .btn-logout:hover { background: rgba(255,255,255,0.22); }
        .btn-back { color: rgba(255,255,255,0.85); text-decoration: none; font-size: 13px; display: flex; align-items: center; gap: 5px; }
        .btn-back:hover { color: #fff; }

        /* ── PAGE ── */
        .page { max-width: 1200px; margin: 0 auto; padding: 32px 24px; }

        /* ── BREADCRUMB ── */
        .breadcrumb { font-size: 12px; color: #94a3b8; margin-bottom: 18px; display: flex; align-items: center; gap: 6px; }
        .breadcrumb a { color: #0f3460; text-decoration: none; font-weight: 600; }
        .breadcrumb a:hover { text-decoration: underline; }

        /* ── PAGE HEADER ── */
        .page-header {
            background: linear-gradient(120deg, #0f3460 0%, #1a5276 60%, #1f618d 100%);
            border-radius: 14px; padding: 24px 28px; color: #fff;
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 22px;
            box-shadow: 0 4px 16px rgba(15,52,96,0.18);
        }
        .page-header h1 { font-size: 20px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .page-header p  { font-size: 12.5px; opacity: 0.75; margin-top: 4px; }
        .header-badge { background: rgba(255,255,255,0.15); padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; white-space: nowrap; }

        /* ── SUMMARY TILES ── */
        .summary-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 22px; }
        .s-tile { background: #fff; border-radius: 10px; padding: 16px 18px; box-shadow: 0 1px 5px rgba(0,0,0,0.06); border-top: 3px solid #ccc; text-align: center; }
        .s-tile.total  { border-top-color: #0f3460; }
        .s-tile.insert { border-top-color: #10b981; }
        .s-tile.update { border-top-color: #f59e0b; }
        .s-tile.delete { border-top-color: #e94560; }
        .s-tile.success{ border-top-color: #2563eb; }
        .s-tile.failed { border-top-color: #dc2626; }
        .s-tile .num   { font-size: 26px; font-weight: 800; color: #0f172a; line-height: 1; }
        .s-tile label  { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: 0.8px; color: #94a3b8; margin-top: 5px; font-weight: 600; }

        /* ── FILTER BAR ── */
        .filter-bar {
            background: #fff;
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 18px;
            display: flex; flex-wrap: wrap; align-items: center; gap: 10px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.06);
        }
        .filter-bar label { font-size: 12px; color: #64748b; font-weight: 600; white-space: nowrap; }
        .filter-bar select {
            border: 1px solid #e2e8f0;
            border-radius: 7px;
            padding: 6px 10px;
            font-size: 12px;
            color: #1e293b;
            background: #f8fafc;
            cursor: pointer;
        }
        .filter-bar select:focus { outline: 2px solid #0f3460; }
        .btn-filter { padding: 7px 16px; border-radius: 7px; font-size: 12px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; }
        .btn-apply  { background: #0f3460; color: #fff; }
        .btn-apply:hover { background: #163870; }
        .btn-reset  { background: #f1f5f9; color: #475569; }
        .btn-reset:hover { background: #e2e8f0; }
        .filter-spacer { flex: 1; }
        .result-count { font-size: 12px; color: #94a3b8; white-space: nowrap; }

        /* ── TABLE ── */
        .table-wrap {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 6px rgba(0,0,0,0.07);
            overflow: hidden;
            margin-bottom: 20px;
        }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        thead tr { background: #0f3460; color: #fff; }
        thead th { padding: 12px 14px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; white-space: nowrap; }
        tbody tr { border-bottom: 1px solid #f1f5f9; transition: background 0.1s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #f8fafc; }
        tbody td { padding: 11px 14px; vertical-align: middle; color: #334155; }
        .td-mono { font-family: 'Courier New', monospace; font-size: 12px; color: #64748b; }
        .td-dim  { color: #94a3b8; font-size: 12px; }

        /* ── OPERATION BADGES ── */
        .badge { display: inline-block; padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 700; letter-spacing: 0.3px; }
        .badge-INSERT  { background: #d1fae5; color: #065f46; }
        .badge-UPDATE  { background: #fef3cd; color: #92600a; }
        .badge-DELETE  { background: #fdedf0; color: #c0152a; }
        .badge-SUCCESS { background: #dbeafe; color: #1d4ed8; }
        .badge-FAILED  { background: #fee2e2; color: #b91c1c; }
        .badge-UNKNOWN { background: #f1f5f9; color: #64748b; }

        /* ── EMPTY STATE ── */
        .empty-state { text-align: center; padding: 52px 24px; color: #94a3b8; }
        .empty-state .icon { font-size: 42px; margin-bottom: 12px; }
        .empty-state p { font-size: 14px; }

        /* ── PAGINATION ── */
        .pagination { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .pg-btn { display: inline-block; padding: 6px 12px; border-radius: 7px; font-size: 12px; font-weight: 600; text-decoration: none; background: #fff; color: #334155; border: 1px solid #e2e8f0; }
        .pg-btn:hover { background: #f1f5f9; }
        .pg-btn.active { background: #0f3460; color: #fff; border-color: #0f3460; }
        .pg-btn.disabled { pointer-events: none; opacity: 0.4; }

        /* ── FOOTER ── */
        footer { text-align: center; padding: 20px 0 28px; font-size: 11.5px; color: #a0aec0; }
    </style>
</head>
<body>

<!-- NAVBAR -->
<div class="navbar">
    <a href="../index_admin.php" class="nav-logo">Media<span>Vault</span></a>
    <div class="nav-right">
        <a href="../index_admin.php" class="btn-back">← Dashboard</a>
        <span>👤 <?= htmlspecialchars($_SESSION['username'] ?? 'Guest') ?></span>
        <span class="nav-role <?= strtolower($_SESSION['role'] ?? 'user') ?>"><?= htmlspecialchars($_SESSION['role'] ?? 'User') ?></span>
        <a href="../authentication/logout.php" class="btn-logout">Sign Out</a>
    </div>
</div>

<div class="page">

    <!-- BREADCRUMB -->
    <div class="breadcrumb">
        <a href="../index_admin.php">Dashboard</a>
        <span>›</span>
        <span>Audit Log</span>
    </div>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1>📋 Transaction Audit Log</h1>
            <p>Automatic records written by AFTER INSERT / UPDATE / DELETE triggers on multimedia_files.</p>
        </div>
        <div class="header-badge"><?= number_format($total_rows) ?> total entries</div>
    </div>

    <!-- SUMMARY TILES -->
    <div class="summary-row">
        <div class="s-tile total">
            <div class="num"><?= number_format($summary['TOTAL']) ?></div>
            <label>Total Events</label>
        </div>
        <div class="s-tile insert">
            <div class="num"><?= number_format($summary['INSERT'] ?? 0) ?></div>
            <label>Inserts</label>
        </div>
        <div class="s-tile update">
            <div class="num"><?= number_format($summary['UPDATE'] ?? 0) ?></div>
            <label>Updates</label>
        </div>
        <div class="s-tile delete">
            <div class="num"><?= number_format($summary['DELETE'] ?? 0) ?></div>
            <label>Deletes</label>
        </div>
        <div class="s-tile success">
            <div class="num"><?= number_format($summary['SUCCESS'] ?? 0) ?></div>
            <label>Success</label>
        </div>
        <div class="s-tile failed">
            <div class="num"><?= number_format($summary['FAILED'] ?? 0) ?></div>
            <label>Failed</label>
        </div>
    </div>

    <!-- FILTER BAR -->
    <form method="GET" action="">
        <div class="filter-bar">
            <label>Operation</label>
            <select name="op">
                <option value="">All</option>
                <?php foreach (['INSERT','UPDATE','DELETE'] as $op): ?>
                    <option value="<?= $op ?>" <?= $filter_op === $op ? 'selected' : '' ?>><?= $op ?></option>
                <?php endforeach; ?>
            </select>

            <label>Outcome</label>
            <select name="outcome">
                <option value="">All</option>
                <?php foreach (['SUCCESS','FAILED'] as $out): ?>
                    <option value="<?= $out ?>" <?= $filter_out === $out ? 'selected' : '' ?>><?= $out ?></option>
                <?php endforeach; ?>
            </select>

            <label>User</label>
            <select name="uid">
                <option value="">All Users</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= $u['user_id'] ?>" <?= $filter_uid === (int)$u['user_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['username']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn-filter btn-apply">Apply</button>
            <a href="audit_log.php" class="btn-filter btn-reset">Reset</a>

            <span class="filter-spacer"></span>
            <span class="result-count">
                Showing <?= number_format(count($rows)) ?> of <?= number_format($total_rows) ?> entries
                &nbsp;·&nbsp; Page <?= $page ?> / <?= $total_pages ?>
            </span>
        </div>
    </form>

    <!-- TABLE -->
    <div class="table-wrap">
        <?php if (empty($rows)): ?>
            <div class="empty-state">
                <div class="icon">📭</div>
                <p>No audit entries match your filters.</p>
            </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Log ID</th>
                    <th>Timestamp</th>
                    <th>Operation</th>
                    <th>Outcome</th>
                    <th>User</th>
                    <th>File ID</th>
                    <th>File Name</th>
                    <th>File Type</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $r): ?>
                <tr>
                    <td class="td-dim"><?= $offset + $i + 1 ?></td>
                    <td class="td-mono"><?= (int)$r['log_id'] ?></td>
                    <td class="td-mono"><?= htmlspecialchars($r['timestamp'] ?? '—') ?></td>
                    <td><span class="badge badge-<?= htmlspecialchars($r['operation_type'] ?? 'UNKNOWN') ?>"><?= htmlspecialchars($r['operation_type'] ?? '—') ?></span></td>
                    <td><span class="badge badge-<?= htmlspecialchars($r['outcome'] ?? 'UNKNOWN') ?>"><?= htmlspecialchars($r['outcome'] ?? '—') ?></span></td>
                    <td>
                        <?php if ($r['username']): ?>
                            <a href="<?= audit_url(['uid' => $r['user_id']]) ?>" style="color:#0f3460;font-weight:600;text-decoration:none;">
                                <?= htmlspecialchars($r['username']) ?>
                            </a>
                        <?php else: ?>
                            <span class="td-dim">ID <?= (int)$r['user_id'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="td-mono"><?= $r['file_id'] !== null ? (int)$r['file_id'] : '<span class="td-dim">—</span>' ?></td>
                    <td><?= $r['file_name'] ? htmlspecialchars($r['file_name']) : '<span class="td-dim">deleted / N/A</span>' ?></td>
                    <td>
                        <?php if ($r['file_type']): ?>
                            <span class="badge badge-UNKNOWN"><?= htmlspecialchars($r['file_type']) ?></span>
                        <?php else: ?>
                            <span class="td-dim">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- PAGINATION -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <a href="<?= audit_url(['page' => $page - 1]) ?>" class="pg-btn <?= $page <= 1 ? 'disabled' : '' ?>">‹ Prev</a>
        <?php
        $start = max(1, $page - 2);
        $end   = min($total_pages, $page + 2);
        if ($start > 1) echo '<a href="' . audit_url(['page' => 1]) . '" class="pg-btn">1</a>';
        if ($start > 2) echo '<span style="padding:0 4px;color:#94a3b8;">…</span>';
        for ($p = $start; $p <= $end; $p++):
        ?>
            <a href="<?= audit_url(['page' => $p]) ?>" class="pg-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor;
        if ($end < $total_pages - 1) echo '<span style="padding:0 4px;color:#94a3b8;">…</span>';
        if ($end < $total_pages) echo '<a href="' . audit_url(['page' => $total_pages]) . '" class="pg-btn">' . $total_pages . '</a>';
        ?>
        <a href="<?= audit_url(['page' => $page + 1]) ?>" class="pg-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">Next ›</a>
    </div>
    <?php endif; ?>

</div>

<footer>MediaVault &nbsp;·&nbsp; BITP3353 Multimedia Database &nbsp;·&nbsp; FTMK UTeM &nbsp;·&nbsp; 2025/2026</footer>

</body>
</html>
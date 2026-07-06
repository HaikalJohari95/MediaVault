<?php
require_once __DIR__ . '/../includes/session_guard.php';
require_once __DIR__ . '/../config/db.php';

function ensure_file_storage_columns(mysqli $conn): void
{
    $columns = [];
    $result = mysqli_query($conn, "SHOW COLUMNS FROM multimedia_files");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $columns[$row['Field']] = true;
        }
    }

    if (!isset($columns['stored_path'])) {
        mysqli_query($conn, "ALTER TABLE multimedia_files ADD COLUMN stored_path VARCHAR(500) DEFAULT NULL AFTER size_kb");
    }
    if (!isset($columns['mime_type'])) {
        mysqli_query($conn, "ALTER TABLE multimedia_files ADD COLUMN mime_type VARCHAR(120) DEFAULT NULL AFTER stored_path");
    }
}

$search = trim((string) ($_GET['search'] ?? ''));
$type = trim((string) ($_GET['type'] ?? ''));
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$sort = trim((string) ($_GET['sort'] ?? 'newest'));
$direction = strtoupper(trim((string) ($_GET['direction'] ?? 'DESC')));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$sortOptions = [
    'newest' => 'm.file_id',
    'name' => 'm.file_name',
    'type' => 'm.file_type',
    'size' => 'm.size_kb',
    'date' => 'm.upload_timestamp',
];

if (!isset($sortOptions[$sort])) {
    $sort = 'newest';
}
if (!in_array($direction, ['ASC', 'DESC'], true)) {
    $direction = 'DESC';
}

ensure_file_storage_columns($conn);

$where = ['m.user_id = ?'];
$params = [$currentUserId];
$types = 'i';

if ($search !== '') {
    $where[] = 'm.file_name LIKE ?';
    $params[] = '%' . $search . '%';
    $types .= 's';
}
if (in_array($type, ['Document', 'Audio', 'Video'], true)) {
    $where[] = 'm.file_type = ?';
    $params[] = $type;
    $types .= 's';
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$countSql = 'SELECT COUNT(*) AS total FROM multimedia_files m' . $whereSql;
$countStmt = mysqli_prepare($conn, $countSql);
if ($params) {
    mysqli_stmt_bind_param($countStmt, $types, ...$params);
}
mysqli_stmt_execute($countStmt);
$countResult = mysqli_stmt_get_result($countStmt);
$totalRows = (int) (mysqli_fetch_assoc($countResult)['total'] ?? 0);
mysqli_stmt_close($countStmt);

$typeCounts = ['Document' => 0, 'Audio' => 0, 'Video' => 0];
$typeStmt = mysqli_prepare($conn, "SELECT file_type, COUNT(*) AS total FROM multimedia_files WHERE user_id = ? GROUP BY file_type");
mysqli_stmt_bind_param($typeStmt, 'i', $currentUserId);
mysqli_stmt_execute($typeStmt);
$typeResult = mysqli_stmt_get_result($typeStmt);
if ($typeResult) {
    while ($typeRow = mysqli_fetch_assoc($typeResult)) {
        $typeCounts[$typeRow['file_type']] = (int) $typeRow['total'];
    }
}
mysqli_stmt_close($typeStmt);

$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$queryParams = [
    'search' => $search,
    'type' => $type,
    'sort' => $sort,
    'direction' => $direction,
];

$sql = 'SELECT file_id, file_name, file_type, size_kb, stored_path, upload_timestamp FROM multimedia_files m';
$sql .= $whereSql;
$sql .= ' ORDER BY ' . $sortOptions[$sort] . ' ' . $direction . ', m.file_id DESC';
$sql .= ' LIMIT ? OFFSET ?';

$stmt = mysqli_prepare($conn, $sql);
$pageParams = array_merge($params, [$perPage, $offset]);
$pageTypes = $types . 'ii';
mysqli_stmt_bind_param($stmt, $pageTypes, ...$pageParams);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Files - MediaVault</title>
    <style>
        :root {
            --bg: #eef2f7;
            --surface: #ffffff;
            --surface-soft: #f8fafc;
            --line: #d9e2ec;
            --line-strong: #c6d3e1;
            --text: #162033;
            --muted: #66758a;
            --primary: #1d4ed8;
            --primary-dark: #173ea8;
            --primary-soft: #e8f0ff;
            --shadow: 0 18px 45px rgba(31, 41, 55, 0.08);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: linear-gradient(180deg, #f7f9fc 0%, var(--bg) 100%);
            color: var(--text);
            font-family: Arial, sans-serif;
            min-height: 100vh;
        }
        .container { max-width: 1120px; margin: 0 auto; padding: 34px 18px 42px; }
        .page-header {
            background: #102a43;
            border-radius: 8px;
            box-shadow: var(--shadow);
            color: #fff;
            margin-bottom: 18px;
            padding: 24px;
        }
        .header-row { align-items: flex-start; display: flex; gap: 16px; justify-content: space-between; }
        .brand-mark { color: #9fbdfc; font-size: 12px; font-weight: 800; letter-spacing: 0.9px; margin: 0 0 7px; text-transform: uppercase; }
        h1, h2 { margin: 0; }
        h1 { color: #fff; font-size: 31px; line-height: 1.15; }
        h2 { color: #152238; font-size: 21px; margin-bottom: 8px; }
        .page-header .muted { color: #d8e2f0; margin-bottom: 0; }
        .panel {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 24px;
        }
        .filters {
            align-items: stretch;
            background: var(--surface-soft);
            border: 1px solid var(--line);
            border-radius: 8px;
            display: grid;
            gap: 10px;
            grid-template-columns: minmax(190px, 1.2fr) repeat(3, minmax(145px, 0.8fr)) auto auto;
            margin: 18px 0;
            padding: 12px;
        }
        input, select {
            background: #fff;
            border: 1px solid var(--line-strong);
            border-radius: 8px;
            color: var(--text);
            min-height: 42px;
            padding: 10px 11px;
            width: 100%;
        }
        input:focus, select:focus { border-color: var(--primary); outline: 3px solid rgba(29, 78, 216, 0.16); }
        .btn {
            background: var(--primary);
            border: 0;
            border-radius: 8px;
            color: #fff;
            cursor: pointer;
            display: inline-block;
            font-weight: 800;
            min-height: 42px;
            padding: 11px 15px;
            text-align: center;
            text-decoration: none;
            transition: background 0.15s, box-shadow 0.15s, transform 0.15s;
            white-space: nowrap;
        }
        .btn:hover { background: var(--primary-dark); box-shadow: 0 10px 22px rgba(29, 78, 216, 0.22); transform: translateY(-1px); }
        .btn.secondary { background: #edf2f7; color: #1f2d3d; }
        .btn.secondary:hover { background: #dbe5ef; box-shadow: 0 10px 20px rgba(15, 23, 42, 0.12); }
        .btn.active { background: #152238; }
        .table-wrap {
            border: 1px solid var(--line);
            border-radius: 8px;
            overflow-x: auto;
        }
        table { border-collapse: collapse; min-width: 740px; width: 100%; }
        th, td { border-bottom: 1px solid var(--line); padding: 13px 14px; text-align: left; vertical-align: middle; }
        th { background: #f2f6fb; color: #526174; font-size: 11px; font-weight: 800; letter-spacing: 0.6px; text-transform: uppercase; }
        tbody tr { transition: background 0.12s; }
        tbody tr:hover { background: #f9fbfd; }
        tr:last-child td { border-bottom: 0; }
        .badge { background: var(--primary-soft); border: 1px solid #c9d9ff; border-radius: 999px; color: #1e40af; display: inline-block; font-size: 12px; font-weight: 800; padding: 5px 10px; }
        .badge.missing { background: #fff7ed; border-color: #fed7aa; color: #9a3412; }
        .type-summary {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            margin: 18px 0;
        }
        .type-card {
            background: var(--surface-soft);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 15px;
        }
        .type-card span {
            color: var(--muted);
            display: block;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        .type-card strong { color: #152238; display: block; font-size: 24px; }
        .muted { color: var(--muted); line-height: 1.55; }
        .pagination { align-items: center; display: flex; flex-wrap: wrap; gap: 10px; justify-content: space-between; margin-top: 16px; }
        .page-links { display: flex; flex-wrap: wrap; gap: 6px; }
        @media (max-width: 920px) {
            .filters { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .type-summary { grid-template-columns: 1fr; }
        }
        @media (max-width: 720px) {
            .container { padding: 18px 12px 30px; }
            .filters { grid-template-columns: 1fr; }
            .filters .btn, .header-row .btn { width: 100%; }
            .header-row { display: block; }
            .header-row .btn { margin-top: 14px; text-align: center; }
            .panel, .page-header { padding: 18px; }
            h1 { font-size: 26px; }
        }
    </style>
</head>
<body>
    <main class="container">
        <header class="page-header">
            <div class="header-row">
                <div>
                <p class="brand-mark">MediaVault Multimedia</p>
                <h1>View Files</h1>
                <p class="muted">Browse, filter, and inspect multimedia records stored in the system.</p>
                </div>
                <a class="btn secondary" href="../index.php">Back to Dashboard</a>
            </div>
        </header>

        <section class="panel">
            <h2>Multimedia Files</h2>
            <p class="muted">Browse your uploaded file metadata by name and type.</p>

            <div class="type-summary">
                <a class="type-card" href="view_files.php?type=Document" style="text-decoration:none;">
                    <span>Documents Uploaded</span>
                    <strong><?php echo $typeCounts['Document']; ?></strong>
                </a>
                <a class="type-card" href="view_files.php?type=Audio" style="text-decoration:none;">
                    <span>Audio Uploaded</span>
                    <strong><?php echo $typeCounts['Audio']; ?></strong>
                </a>
                <a class="type-card" href="view_files.php?type=Video" style="text-decoration:none;">
                    <span>Videos Uploaded</span>
                    <strong><?php echo $typeCounts['Video']; ?></strong>
                </a>
            </div>

            <form class="filters" method="get">
                <input type="search" name="search" placeholder="Search file name" value="<?php echo htmlspecialchars($search); ?>">
                <select name="type">
                    <option value="">All types</option>
                    <option value="Document" <?php echo $type === 'Document' ? 'selected' : ''; ?>>Document</option>
                    <option value="Audio" <?php echo $type === 'Audio' ? 'selected' : ''; ?>>Audio</option>
                    <option value="Video" <?php echo $type === 'Video' ? 'selected' : ''; ?>>Video</option>
                </select>
                <select name="sort">
                    <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Sort by newest</option>
                    <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Sort by file name</option>
                    <option value="type" <?php echo $sort === 'type' ? 'selected' : ''; ?>>Sort by type</option>
                    <option value="size" <?php echo $sort === 'size' ? 'selected' : ''; ?>>Sort by size</option>
                    <option value="date" <?php echo $sort === 'date' ? 'selected' : ''; ?>>Sort by upload date</option>
                </select>
                <select name="direction">
                    <option value="DESC" <?php echo $direction === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                    <option value="ASC" <?php echo $direction === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                </select>
                <button class="btn" type="submit">Filter</button>
                <a class="btn secondary" href="view_files.php">Reset</a>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>File Name</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th>Upload Date</th>
                            <th>File</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><?php echo (int) $row['file_id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['file_name']); ?></td>
                                    <td><span class="badge"><?php echo htmlspecialchars($row['file_type']); ?></span></td>
                                    <td><?php echo number_format((float) $row['size_kb'], 2); ?> KB</td>
                                    <td><?php echo htmlspecialchars(substr((string) $row['upload_timestamp'], 0, 10)); ?></td>
                                    <td>
                                        <?php if (!empty($row['stored_path'])): ?>
                                            <span class="badge">Saved</span>
                                        <?php else: ?>
                                            <span class="badge missing">Metadata only</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><a class="btn secondary" href="file_details.php?id=<?php echo (int) $row['file_id']; ?>">Details</a></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="muted">No multimedia files found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalRows > $perPage): ?>
                <div class="pagination">
                    <span class="muted">
                        Page <?php echo $page; ?> of <?php echo $totalPages; ?>,
                        showing <?php echo min($totalRows, $offset + 1); ?>-<?php echo min($totalRows, $offset + $perPage); ?>
                        of <?php echo $totalRows; ?> files
                    </span>
                    <div class="page-links">
                        <?php if ($page > 1): ?>
                            <?php $queryParams['page'] = $page - 1; ?>
                            <a class="btn secondary" href="view_files.php?<?php echo htmlspecialchars(http_build_query($queryParams)); ?>">Previous</a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php $queryParams['page'] = $i; ?>
                            <a class="btn <?php echo $i === $page ? 'active' : 'secondary'; ?>" href="view_files.php?<?php echo htmlspecialchars(http_build_query($queryParams)); ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <?php $queryParams['page'] = $page + 1; ?>
                            <a class="btn secondary" href="view_files.php?<?php echo htmlspecialchars(http_build_query($queryParams)); ?>">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
<?php mysqli_stmt_close($stmt); ?>
<?php
include '../config/db.php';
include '../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../authentication/login.php");
    exit();
}
?>

<h1>📊 Analytics Dashboard</h1>
<p>Welcome to MediaVault Analytics & Reporting System, <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>!</p>

<style>
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 20px;
    margin-top: 20px;
}
.card {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.card h2 {
    margin-top: 0;
    color: #2c3e50;
    font-size: 1.3rem;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}
th, td {
    padding: 8px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}
th {
    background: #2c3e50;
    color: white;
}
tr:hover {
    background: #f1f1f1;
}
</style>

<div class="dashboard-grid">

    <!-- Report 1: Storage Usage by File Type -->
    <div class="card">
        <h2>💾 Storage Usage by File Type</h2>
        <?php
        $stmt = $pdo->query("
            SELECT 
                file_type,
                COUNT(*) AS total_files,
                SUM(size_kb) AS total_size_kb,
                AVG(size_kb) AS avg_size_kb
            FROM multimedia_files
            GROUP BY file_type
            ORDER BY total_size_kb DESC
        ");
        ?>
        <table>
            <tr><th>File Type</th><th>Total Files</th><th>Total (KB)</th><th>Avg (KB)</th></tr>
            <?php while($row = $stmt->fetch()): ?>
            <tr>
                <td><?= htmlspecialchars($row['file_type']) ?></td>
                <td><?= $row['total_files'] ?></td>
                <td><?= number_format($row['total_size_kb'], 2) ?></td>
                <td><?= number_format($row['avg_size_kb'], 2) ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>

    <!-- Report 2: Top 5 Uploaders -->
    <div class="card">
        <h2>🏆 Most Active Uploaders (Top 5)</h2>
        <?php
        $stmt = $pdo->query("
            SELECT 
                u.username,
                COUNT(m.file_id) AS upload_count
            FROM user_accounts u
            JOIN multimedia_files m ON u.user_id = m.user_id
            GROUP BY u.user_id
            ORDER BY upload_count DESC
            LIMIT 5
        ");
        ?>
        <table>
            <tr><th>Username</th><th>Uploads</th></tr>
            <?php while($row = $stmt->fetch()): ?>
            <tr>
                <td><?= htmlspecialchars($row['username']) ?></td>
                <td><?= $row['upload_count'] ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>

    <!-- Report 3: Daily Transaction Volume -->
    <div class="card">
        <h2>📆 Daily Transaction Volume</h2>
        <?php
        $stmt = $pdo->query("
            SELECT 
                DATE(timestamp) AS tdate,
                operation_type,
                COUNT(*) AS total
            FROM transaction_audit_log
            WHERE operation_type IN ('INSERT','UPDATE','DELETE')
            GROUP BY tdate, operation_type
            ORDER BY tdate DESC
            LIMIT 10
        ");
        ?>
        <table>
            <tr><th>Date</th><th>Operation</th><th>Count</th></tr>
            <?php while($row = $stmt->fetch()): ?>
            <tr>
                <td><?= $row['tdate'] ?></td>
                <td><?= $row['operation_type'] ?></td>
                <td><?= $row['total'] ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>

    <!-- Report 4: Demographic Distribution -->
    <div class="card">
        <h2>🌏 Demographic (State of Origin)</h2>
        <?php
        $stmt = $pdo->query("
            SELECT 
                state_of_origin,
                COUNT(*) AS total_users
            FROM demographic_parsing_store
            WHERE state_of_origin IS NOT NULL AND state_of_origin != ''
            GROUP BY state_of_origin
            ORDER BY total_users DESC
        ");
        ?>
        <table>
            <tr><th>State</th><th>Users</th></tr>
            <?php while($row = $stmt->fetch()): ?>
            <tr>
                <td><?= htmlspecialchars($row['state_of_origin']) ?></td>
                <td><?= $row['total_users'] ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>

    <!-- Report 5: Audio Quality Summary -->
    <div class="card">
        <h2>🎧 Audio Quality Summary</h2>
        <?php
        $stmt = $pdo->query("
            SELECT 
                COALESCE(genre_tag, 'No Genre') AS genre,
                COUNT(*) AS total,
                AVG(bitrate_kbps) AS avg_bitrate,
                AVG(duration_seconds) AS avg_duration
            FROM audio_metadata
            GROUP BY genre_tag
            ORDER BY total DESC
        ");
        ?>
        <table>
            <tr><th>Genre</th><th>Files</th><th>Avg Bitrate</th><th>Avg Duration (sec)</th></tr>
            <?php while($row = $stmt->fetch()): ?>
            <tr>
                <td><?= htmlspecialchars($row['genre']) ?></td>
                <td><?= $row['total'] ?></td>
                <td><?= round($row['avg_bitrate'], 2) ?></td>
                <td><?= round($row['avg_duration'], 2) ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
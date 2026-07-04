<?php
// ============================================================
//  MediaVault - Storage Usage Report (Fixed for MySQLi & UI)
// ============================================================
require_once '../includes/session_guard.php';
require_once '../config/db.php';


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MediaVault - Storage Usage Report</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #eef1f6; color: #1a1a2e; padding: 24px; }
        .report-container { max-width: 1100px; margin: 0 auto; background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,0.05); }
        h1 { font-size: 20px; color: #0f3460; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13.5px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #0f3460; color: white; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
        tr:hover { background: #f8fafc; }
        /* Gaya Butang Kembali */
        .btn-back { display: inline-flex; align-items: center; gap: 8px; color: #0f3460; text-decoration: none; font-size: 13.5px; font-weight: 600; margin-bottom: 20px; transition: color 0.2s; }
        .btn-back:hover { color: #e94560; }
    </style>
</head>
<body>

<div class="report-container">
     <a href="../index_admin.php" class="btn-back">← Back to Dashboard</a>
    <h1>💾 Storage Usage Report by File Type</h1>

    <?php
    $query = "SELECT 
                file_type,
                COUNT(*) AS total_files,
                SUM(size_kb) AS total_size_kb,
                AVG(size_kb) AS avg_size_kb,
                MIN(size_kb) AS min_size_kb,
                MAX(size_kb) AS max_size_kb
              FROM multimedia_files
              GROUP BY file_type
              ORDER BY total_size_kb DESC";
    $result = mysqli_query($conn, $query);
    ?>

    <table>
        <thead>
            <tr>
                <th>File Type</th>
                <th>Total Files</th>
                <th>Total Size (KB)</th>
                <th>Average Size (KB)</th>
                <th>Min (KB)</th>
                <th>Max (KB)</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <?php while($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td style="font-weight: 600; color: #0f3460;"><?= htmlspecialchars($row['file_type']) ?></td>
                    <td><?= $row['total_files'] ?></td>
                    <td><?= number_format($row['total_size_kb'], 2) ?> KB</td>
                    <td><?= number_format($row['avg_size_kb'], 2) ?> KB</td>
                    <td><?= number_format($row['min_size_kb'], 2) ?> KB</td>
                    <td><?= number_format($row['max_size_kb'], 2) ?> KB</td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: #64748b; padding: 20px;">No multimedia assets registered in storage yet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>

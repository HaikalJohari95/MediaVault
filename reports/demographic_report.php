<?php
// ============================================================
//  MediaVault - Demographic Report (Fixed for MySQLi)
// ============================================================
require_once '../includes/session_guard.php';
require_once '../config/db.php';


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MediaVault - Demographic Report</title>
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
    <h1>🌍 Demographic Distribution by State of Origin</h1>

    <?php
    // Mendapatkan total users menggunakan MySQLi
    $totalQuery = mysqli_query($conn, "SELECT COUNT(*) AS total FROM demographic_parsing_store");
    $totalRow = mysqli_fetch_assoc($totalQuery);
    $totalUsers = $totalRow['total'] ?? 0;

    $query = "SELECT 
                state_of_origin,
                COUNT(*) AS total_users,
                MIN(date_of_birth) AS oldest_dob,
                MAX(date_of_birth) AS youngest_dob
              FROM demographic_parsing_store
              GROUP BY state_of_origin
              ORDER BY total_users DESC";
    $result = mysqli_query($conn, $query);
    ?>

    <table>
        <thead>
            <tr>
                <th>State of Origin</th>
                <th>Number of Users</th>
                <th>Percentage (%)</th>
                <th>Oldest User Birthdate</th>
                <th>Youngest User Birthdate</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($totalUsers > 0 && $result): ?>
                <?php while($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td style="font-weight: 600; color: #0f3460;"><?= htmlspecialchars($row['state_of_origin'] ?: 'Unknown') ?></td>
                    <td><?= $row['total_users'] ?></td>
                    <td><span style="background: #d1fae5; color: #065f46; padding: 2px 6px; border-radius: 4px; font-weight: 600;"><?= round(($row['total_users'] / $totalUsers) * 100, 2) ?>%</span></td>
                    <td><?= $row['oldest_dob'] ? date('Y-m-d', strtotime($row['oldest_dob'])) : '—' ?></td>
                    <td><?= $row['youngest_dob'] ? date('Y-m-d', strtotime($row['youngest_dob'])) : '—' ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: #64748b; padding: 20px;">No demographic parsing records logged in the database yet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>

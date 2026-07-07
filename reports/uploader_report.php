<?php
// ============================================================
//  MediaVault - Most Active Uploaders (Optimized & Fixed)
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/session_guard.php';
require_once '../config/db.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediaVault - Most Active Uploaders</title>
    <style>
        body { 
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, sans-serif; 
            background: #f1f5f9; 
            color: #1e293b; 
            padding: 40px 24px; 
            margin: 0;
        }
        .report-container { 
            max-width: 1100px; 
            margin: 0 auto; 
            background: #ffffff; 
            padding: 32px; 
            border-radius: 16px; 
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05); 
        }
        h1 { 
            font-size: 22px; 
            color: #0f3460; 
            margin-bottom: 24px; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }
        table { 
            width: 100%; 
            border-collapse: separate; 
            border-spacing: 0;
            margin-top: 10px; 
            font-size: 14px; 
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }
        th, td { 
            padding: 14px 18px; 
            text-align: left; 
            border-bottom: 1px solid #e2e8f0; 
        }
        th { 
            background: #0f3460; 
            color: #ffffff; 
            font-weight: 600; 
            text-transform: uppercase; 
            font-size: 11px; 
            letter-spacing: 0.75px; 
        }
        tr:last-child td {
            border-bottom: none;
        }
        tr:hover { 
            background: #f8fafc; 
        }
        .badge {
            background: #e2e8f0;
            color: #334155;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            text-transform: capitalize;
        }
        .badge-admin { background: #fee2e2; color: #991b1b; }
        .badge-user { background: #dbeafe; color: #1e40af; }
        .badge-viewer { background: #fef3c7; color: #92400e; }
        
        .btn-back { 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            color: #0f3460; 
            text-decoration: none; 
            font-size: 14px; 
            font-weight: 600; 
            margin-bottom: 24px; 
            transition: all 0.2s; 
        }
        .btn-back:hover { 
            color: #e94560; 
            transform: translateX(-4px);
        }
    </style>
</head>
<body>

<div class="report-container">
    <a href="../index1.php" class="btn-back">← Back to Dashboard</a>
    <h1>👤 Most Active Uploaders (Top 10)</h1>

    <?php
    /**
     * BENTUK PENAMBAHBAIKAN QUERY (ANTI-GROUP BY ERROR):
     * 1. Menyertakan semua lajur SELECT bukan agregat ke dalam GROUP BY bagi mematuhi standard MySQL 8.0 Laragon.
     * 2. Menggunakan IFNULL pada fungsi SUM supaya pengguna baru memaparkan '0.00 KB' dan bukannya ruang kosong.
     */
    $query = "SELECT 
                u.username,
                u.email,
                u.department,
                u.access_role AS role,
                COUNT(m.file_id) AS upload_count,
                IFNULL(SUM(m.size_kb), 0) AS total_uploaded_kb
              FROM user_accounts u
              LEFT JOIN multimedia_files m ON u.user_id = m.user_id
              GROUP BY u.user_id, u.username, u.email, u.department, u.access_role
              ORDER BY upload_count DESC, total_uploaded_kb DESC
              LIMIT 10";
              
    $result = mysqli_query($conn, $query);
    ?>

    <table>
        <thead>
            <tr>
                <th style="width: 80px;">Rank</th>
                <th>Username</th>
                <th>Email</th>
                <th>Department</th>
                <th>Role</th>
                <th>Upload Count</th>
                <th>Total Uploaded (KB)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $rank = 1;
            if ($result && mysqli_num_rows($result) > 0):
                while($row = mysqli_fetch_assoc($result)): 
                    // Menentukan kelas reka bentuk lencana berdasarkan peranan akses
                    $role_class = 'badge-user';
                    if (strtolower($row['role']) === 'admin') $role_class = 'badge-admin';
                    if (strtolower($row['role']) === 'viewer') $role_class = 'badge-viewer';
            ?>
            <tr>
                <td><strong>#<?= $rank++ ?></strong></td>
                <td style="font-weight: 600; color: #0f3460;"><?= htmlspecialchars($row['username']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= htmlspecialchars($row['department'] ?? '-') ?></td>
                <td><span class="badge <?= $role_class ?>"><?= htmlspecialchars($row['role'] ?? 'User') ?></span></td>
                <td><strong><?= number_format($row['upload_count']) ?></strong> assets</td>
                <td><?= number_format($row['total_uploaded_kb'], 2) ?> KB</td>
            </tr>
            <?php 
                endwhile;
            else:
            ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: #64748b; padding: 30px;">No registered user accounts found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
<?php
// ============================================================
//  MediaVault - Transaction Audit Report (Fixed for MySQLi)
// ============================================================
require_once '../includes/session_guard.php';
require_once '../config/db.php';


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MediaVault - Transaction Audit Report</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #eef1f6; color: #1a1a2e; padding: 24px; }
        .report-container { max-width: 1100px; margin: 0 auto; background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,0.05); }
        h1 { font-size: 20px; color: #0f3460; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13.5px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #0f3460; color: white; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
        tr:hover { background: #f8fafc; }
        .badge { padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; }
        .b-ins { background: #d1fae5; color: #065f46; }
        .b-upd { background: #fef3cd; color: #92600a; }
        .b-del { background: #fdedf0; color: #c0152a; }
        /* Gaya Butang Kembali */
        .btn-back { display: inline-flex; align-items: center; gap: 8px; color: #0f3460; text-decoration: none; font-size: 13.5px; font-weight: 600; margin-bottom: 20px; transition: color 0.2s; }
        .btn-back:hover { color: #e94560; }
    </style>
</head>
<body>

<div class="report-container">
     <a href="../index1.php" class="btn-back">← Back to Dashboard</a>
    <h1>📈 Daily Transaction Volume (INSERT, UPDATE, DELETE)</h1>

    <?php
    $query = "SELECT 
                DATE(timestamp) AS trans_date,
                operation_type,
                COUNT(*) AS total_ops,
                COUNT(DISTINCT user_id) AS unique_users,
                SUM(CASE WHEN outcome = 'SUCCESS' THEN 1 ELSE 0 END) AS successful_ops
              FROM transaction_audit_log
              WHERE operation_type IN ('INSERT','UPDATE','DELETE')
              GROUP BY trans_date, operation_type
              ORDER BY trans_date DESC
              LIMIT 30";
    $result = mysqli_query($conn, $query);
    ?>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Operation Type</th>
                <th>Total Transactions</th>
                <th>Successful Deliveries</th>
                <th>Unique Active Operators</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $hasData = false;
            if ($result && mysqli_num_rows($result) > 0):
                while($row = mysqli_fetch_assoc($result)): 
                    $hasData = true;
                    $opClass = 'b-ins';
                    if($row['operation_type'] === 'UPDATE') $opClass = 'b-upd';
                    if($row['operation_type'] === 'DELETE') $opClass = 'b-del';
            ?>
            <tr>
                <td><?= htmlspecialchars($row['trans_date']) ?></td>
                <td><span class="badge <?= $opClass ?>"><?= htmlspecialchars($row['operation_type']) ?></span></td>
                <td style="font-weight: 600;"><?= $row['total_ops'] ?></td>
                <td>
                    <span style="color: #065f46; font-weight: 600;"><?= $row['successful_ops'] ?></span> 
                    <span style="color: #94a3b8;">/ <?= $row['total_ops'] ?></span>
                </td>
                <td>👤 <?= $row['unique_users'] ?> user(s)</td>
            </tr>
            <?php 
                endwhile;
            endif; 
            
            if (!$hasData): 
            ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: #64748b; padding: 20px;">No mutation operations recorded in system logs yet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>

<?php
// ============================================================
//  MediaVault - Audio Quality Report
// ============================================================

// Memanggil session_guard untuk kawalan keselamatan sesi & perlindungan laluan subfolder
require_once '../includes/session_guard.php'; 
require_once '../config/db.php'; // Membawa masuk sambungan $conn (MySQLi)

// Memastikan fungsi semakan peranan jika diperlukan (Contoh: Hanya User & Admin boleh lihat laporan)
// requireRole('User'); 


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediaVault - Audio Quality Report</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #eef1f6; color: #1a1a2e; padding: 24px; }
        .report-container { max-width: 1100px; margin: 0 auto; background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,0.05); }
        h1 { font-size: 20px; color: #0f3460; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13.5px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #0f3460; color: white; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
        tr:hover { background: #f8fafc; }
        .badge-count { background: #e8eef7; color: #0f3460; padding: 3px 8px; border-radius: 12px; font-weight: bold; }
        .no-data { text-align: center; padding: 30px; color: #64748b; font-style: italic; }
        /* Gaya Butang Kembali */
        .btn-back { display: inline-flex; align-items: center; gap: 8px; color: #0f3460; text-decoration: none; font-size: 13.5px; font-weight: 600; margin-bottom: 20px; transition: color 0.2s; }
        .btn-back:hover { color: #e94560; }
    </style>
</head>
<body>

<div class="report-container">
    <a href="../index_admin.php" class="btn-back">← Back to Dashboard</a>
    <h1>🎵 Audio Quality Summary Report</h1>

    <?php
    // Membina SQL Query agregasi dinamik untuk data multimedia audio
    $query = "SELECT 
                COALESCE(genre_tag, 'No Genre') AS genre,
                COUNT(*) AS total_audio_files,
                AVG(bitrate_kbps) AS avg_bitrate,
                AVG(duration_seconds) AS avg_duration_sec,
                MIN(bitrate_kbps) AS min_bitrate,
                MAX(bitrate_kbps) AS max_bitrate,
                MIN(duration_seconds) AS min_duration,
                MAX(duration_seconds) AS max_duration
              FROM audio_metadata
              GROUP BY genre_tag
              ORDER BY total_audio_files DESC";
              
    $result = mysqli_query($conn, $query);
    ?>

    <table>
        <thead>
            <tr>
                <th>Genre</th>
                <th>Total Files</th>
                <th>Avg Bitrate (kbps)</th>
                <th>Min Bitrate</th>
                <th>Max Bitrate</th>
                <th>Avg Duration (sec)</th>
                <th>Min Duration</th>
                <th>Max Duration</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            if ($result && mysqli_num_rows($result) > 0):
                while($row = mysqli_fetch_assoc($result)): 
            ?>
                <tr>
                    <td style="font-weight: 600; color: #0f3460;"><?= htmlspecialchars($row['genre']) ?></td>
                    <td><span class="badge-count"><?= $row['total_audio_files'] ?></span></td>
                    <td><?= round($row['avg_bitrate'] ?? 0, 2) ?> kbps</td>
                    <td><?= $row['min_bitrate'] ?? 0 ?> kbps</td>
                    <td><?= $row['max_bitrate'] ?? 0 ?> kbps</td>
                    <td><?= round($row['avg_duration_sec'] ?? 0, 2) ?>s</td>
                    <td><?= $row['min_duration'] ?? 0 ?> sec</td>
                    <td><?= $row['max_duration'] ?? 0 ?> sec</td>
                </tr>
            <?php 
                endwhile; 
            else:
            ?>
                <tr>
                    <td colspan="8" class="no-data">⚠️ No audio database records found in table 'audio_metadata'.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>

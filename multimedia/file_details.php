<?php
// ============================================================
//  MediaVault - Detailed Metadata & Media Preview Control
// ============================================================

require_once __DIR__ . '/../includes/session_guard.php';
require_once __DIR__ . '/../config/db.php';

// Normalize role structures to ensure authorization enforcement
$currentUserRole = strtolower(trim((string) ($_SESSION['access_role'] ?? $_SESSION['role'] ?? 'User')));

// Capture the active logged-in user's ID from session state
$currentUserId   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// Guard: Ensure user is properly authenticated
if ($currentUserId <= 0) {
    die("Access Denied: Invalid user session context.");
}

// Extract and clean requested file identifier from URL query string
$fileId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($fileId <= 0) {
    die("Error: Invalid or missing multimedia file identification parameter.");
}

/* |--------------------------------------------------------------------------
| Role-Based Query Isolation Matrix (Standard User Constraint)
|--------------------------------------------------------------------------
*/
// Users and Viewers are strictly bound to matches matching their current session ID
$sql = 'SELECT m.file_id, m.file_name, m.file_type, m.size_kb, m.stored_path, m.upload_timestamp, m.mime_type, u.username, u.department  
        FROM multimedia_files m
        LEFT JOIN user_accounts u ON m.user_id = u.user_id
        WHERE m.file_id = ? AND m.user_id = ?  
        LIMIT 1';
        
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'ii', $fileId, $currentUserId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$fileData = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Guard: If empty or unauthorized, provide a safe non-descriptive error
if (!$fileData) {
    die("Error: The requested file profile does not exist, or you lack permission to view it.");
}

$cleanPath = htmlspecialchars($fileData['stored_path'] ?? '');
$fileType = $fileData['file_type'] ?? '';
$fileName = htmlspecialchars($fileData['file_name'] ?? '');
$mimeType = htmlspecialchars($fileData['mime_type'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inspect File Profile #<?php echo $fileId; ?> - MediaVault</title>
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
            --accent-red: #991b1b;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: linear-gradient(180deg, #f7f9fc 0%, var(--bg) 100%);
            color: var(--text);
            font-family: Arial, sans-serif;
            min-height: 100vh;
        }
        .container { max-width: 900px; margin: 0 auto; padding: 34px 18px 42px; }
        .page-header {
            background: #111827;
            border-left: 5px solid #1d4ed8; /* Swapped red badge alert layout to workspace blue */
            border-radius: 8px;
            box-shadow: var(--shadow);
            color: #fff;
            margin-bottom: 18px;
            padding: 24px;
        }
        .header-row { align-items: flex-start; display: flex; gap: 16px; justify-content: space-between; }
        .brand-mark { color: #93c5fd; font-size: 12px; font-weight: 800; letter-spacing: 0.9px; margin: 0 0 7px; text-transform: uppercase; }
        h1, h2 { margin: 0; }
        h1 { color: #fff; font-size: 26px; line-height: 1.2; word-break: break-all; }
        h2 { color: #152238; font-size: 21px; margin-bottom: 16px; }
        .page-header .muted { color: #e5e7eb; margin-top: 6px; }
        .panel {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 24px;
            margin-bottom: 20px;
        }
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
        .btn.success { background: #10b981; }
        .btn.success:hover { background: #059669; }
        
        .badge { background: var(--primary-soft); border: 1px solid #c9d9ff; border-radius: 999px; color: #1e40af; display: inline-block; font-size: 12px; font-weight: 800; padding: 5px 10px; }
        .badge.missing { background: #fff5f5; border-color: #fca5a5; color: var(--accent-red); }
        .badge.admin-user { background: #f3f4f6; border-color: #e5e7eb; color: #374151; }
        .muted { color: var(--muted); line-height: 1.55; }

        /* Media Screen Player Layout Configuration */
        .player-stage {
            background: #090d16;
            border-radius: 8px;
            margin-bottom: 24px;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            border: 1px solid #1f2937;
        }
        .player-stage video {
            width: 100%;
            max-height: 480px;
            border-radius: 6px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            background: #000;
        }
        .player-stage audio {
            width: 100%;
            padding: 10px 0;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-top: 14px;
        }
        .meta-item {
            background: var(--surface-soft);
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 14px;
        }
        .meta-label {
            font-size: 11px;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .meta-value {
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
        }
        @media (max-width: 640px) {
            .meta-grid { grid-template-columns: 1fr; }
            .header-row { flex-direction: column; gap: 12px; }
        }
    </style>
</head>
<body>
    <main class="container">
        <header class="page-header">
            <div class="header-row">
                <div>
                    <p class="brand-mark">File Inspector Asset Window</p>
                    <h1><?php echo $fileName; ?></h1>
                    <p class="muted">Asset Identity Reference Pointer: #<?php echo (int)$fileData['file_id']; ?></p>
                </div>
                <a class="btn secondary" href="../index.php">Return to Dashboard</a>
            </div>
        </header>

        <section class="panel">
            <h2>Multimedia Live Rendering Window</h2>
            
            <?php if (!empty($cleanPath)): ?>
                <div class="player-stage">
                    <?php if ($fileType === 'Video'): ?>
                        <video controls preload="auto">
                            <source src="<?php echo $cleanPath; ?>" type="<?php echo $mimeType ?: 'video/mp4'; ?>">
                            Browser engine lacks capability for native HTML5 video stream decoding.
                        </video>
                    <?php elseif ($fileType === 'Audio'): ?>
                        <audio controls preload="auto">
                            <source src="<?php echo $cleanPath; ?>" type="<?php echo $mimeType ?: 'audio/mpeg'; ?>">
                            Browser engine lacks capability for native HTML5 audio stream decoding.
                        </audio>
                    <?php else: ?>
                        <div style="text-align: center; color: #94a3b8; padding: 20px 0;">
                            <p style="margin: 0 0 14px 0;">Document assets cannot be parsed inside active audio/video players.</p>
                            <a class="btn success" href="<?php echo $cleanPath; ?>" download>💾 Download File Blueprint</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="player-stage" style="padding: 40px; text-align: center;">
                    <span class="badge missing" style="font-size: 14px; padding: 8px 16px;">CRITICAL ERR: Virtual Disk Storage Reference Missing</span>
                </div>
            <?php endif; ?>

            <h2>Structural Database Manifest Data</h2>
            <div class="meta-grid">
                <div class="meta-item">
                    <div class="meta-label">Assigned Media Category</div>
                    <div class="meta-value"><span class="badge"><?php echo htmlspecialchars($fileType); ?></span></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Calculated Storage Weight</div>
                    <div class="meta-value"><?php echo number_format((float) $fileData['size_kb'], 2); ?> KB</div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">System File Extension / Mime-Type</div>
                    <div class="meta-value"><?php echo $mimeType ? $mimeType : 'Unknown File Engine Stream Signature'; ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Server Ingestion Timestamp</div>
                    <div class="meta-value"><?php echo htmlspecialchars((string)$fileData['upload_timestamp']); ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Responsible System Account Owner</div>
                    <div class="meta-value"><span class="badge admin-user"><?php echo htmlspecialchars($fileData['username'] ?? 'System / Profile Reference'); ?></span></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Corporate Department Workspace</div>
                    <div class="meta-value"><?php echo htmlspecialchars($fileData['department'] ?? 'General Corporate Instance Space'); ?></div>
                </div>
            </div>

            </section>
    </main>
</body>
</html>
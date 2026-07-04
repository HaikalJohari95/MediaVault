<?php
// ============================================================
//      MediaVault - Main Dashboard
// ============================================================
require_once 'includes/session_guard.php';
require_once 'includes/ic_parser.php';
require_once 'config/db.php'; // Memastikan sambungan ke database GS05DB sedia ada

$ic_data = null;
$db_ic = '';

// Mengambil IC terus dari database berdasarkan session user_id yang sedang aktif
if (isset($_SESSION['user_id']) && isset($conn)) {
    $current_user_id = $_SESSION['user_id'];
    
    // SQL Query menggunakan INNER JOIN berpandukan ERD yang disediakan
    $query = "SELECT d.ic_number 
              FROM user_accounts u
              INNER JOIN demographic_parsing_store d ON u.user_id = d.user_id 
              WHERE u.user_id = ? 
              LIMIT 1";
              
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, "i", $current_user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            $db_ic = $row['ic_number'];
        }
        mysqli_stmt_close($stmt);
    }
}

// Parse string nombor IC yang diambil dari pangkalan data secara automatik
$user_ic = preg_replace('/[^0-9]/', '', $db_ic);
if (!empty($user_ic) && strlen($user_ic) === 12) {
    $ic_data = parseMyKadIC($user_ic);
}

// Check if user is Admin
$isAdmin = (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediaVault - Dashboard</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #eef1f6; min-height: 100vh; color: #1a1a2e; }

        /* NAVBAR */
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
        .nav-logo { font-size: 20px; font-weight: 800; color: #fff; letter-spacing: 1px; }
        .nav-logo span { color: #ffd700; }
        .nav-right { display: flex; align-items: center; gap: 10px; font-size: 13px; color: rgba(255,255,255,0.75); }
        .nav-role { color: #fff; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .nav-role.admin  { background: #e94560; }
        .nav-role.user   { background: #2563eb; }
        .nav-role.viewer { background: #64748b; }
        .btn-logout { color: #fff; text-decoration: none; background: rgba(255,255,255,0.12); padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; }
        .btn-logout:hover { background: rgba(255,255,255,0.22); }
        .btn-admin-panel { color: #fff; text-decoration: none; background: #e94560; padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; }
        .btn-admin-panel:hover { background: #cc3b54; }

        /* LAYOUT */
        .page { max-width: 1100px; margin: 0 auto; padding: 32px 24px; }

        /* BANNER */
        .banner {
            background: linear-gradient(120deg, #0f3460 0%, #1a5276 60%, #1f618d 100%);
            border-radius: 14px; padding: 28px 32px; color: #fff;
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 24px;
            box-shadow: 0 4px 16px rgba(15,52,96,0.18);
        }
        .banner-left h2 { font-size: 22px; font-weight: 700; margin-bottom: 4px; }
        .banner-left p  { font-size: 13px; opacity: 0.75; }
        .banner-meta { display: flex; gap: 24px; }
        .meta-item { text-align: right; }
        .meta-item label { display: block; font-size: 10px; opacity: 0.6; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
        .meta-item span  { font-weight: 600; font-size: 13px; }

        /* SECTION LABEL */
        .section-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #64748b; margin-bottom: 14px; }

        /* INFO TILES */
        .info-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 24px; }
        .info-tile { background: #fff; border-radius: 10px; padding: 16px 18px; box-shadow: 0 1px 5px rgba(0,0,0,0.06); }
        .info-tile label { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: 0.7px; color: #94a3b8; margin-bottom: 5px; }
        .info-tile span  { font-size: 14px; font-weight: 700; color: #1e293b; }

        /* MODULES GRID */
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .card { background: #fff; border-radius: 12px; padding: 22px; box-shadow: 0 1px 6px rgba(0,0,0,0.07); display: flex; flex-direction: column; gap: 8px; border-left: 4px solid #ccc; transition: box-shadow 0.2s, transform 0.2s; }
        .card:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.10); transform: translateY(-2px); }
        .c1 { border-left-color: #0f3460; }
        .c2 { border-left-color: #e94560; }
        .c3 { border-left-color: #f59e0b; }
        .card-header { display: flex; align-items: center; gap: 12px; }
        .card-icon { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .ic1{background:#e8eef7;} .ic2{background:#fdedf0;} .ic3{background:#fef3cd;}
        .card-title { font-size: 15px; font-weight: 700; color: #0f172a; line-height: 1.3; }
        .card-desc  { font-size: 12.5px; color: #64748b; line-height: 1.6; }
        .card-actions { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 4px; }
        .btn { padding: 6px 13px; border-radius: 7px; font-size: 12px; font-weight: 600; text-decoration: none; transition: opacity 0.15s; }
        .btn:hover { opacity: 0.78; }
        .b1{background:#e8eef7;color:#0f3460;} .b2{background:#fdedf0;color:#c0152a;} .b3{background:#fef3cd;color:#92600a;}

        /* —— IC PARSER DISPLAY —— */
        .ic-demo-card {
            background: #fff; border-radius: 12px; padding: 26px 28px; box-shadow: 0 1px 6px rgba(0,0,0,0.07); border-left: 4px solid #0f3460; margin-bottom: 24px;
        }
        .ic-demo-card h3 {
            font-size: 15px; font-weight: 700; color: #0f3460; margin-bottom: 6px; display: flex; align-items: center; gap: 8px;
        }
        .ic-demo-card p { font-size: 12.5px; color: #64748b; margin-bottom: 16px; line-height: 1.5; }
        .ic-display-number {
            font-size: 16px; font-weight: 700; color: #0f3460; background: #f1f5f9; padding: 8px 14px; border-radius: 6px; display: inline-block; font-family: 'Courier New', monospace; letter-spacing: 1px; margin-bottom: 16px;
        }
        .ic-result { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; }
        .ic-tile { background: #f0f7ff; border-radius: 9px; padding: 14px 16px; border: 1px solid #bfdbfe; }
        .ic-tile label { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: 0.7px; color: #3b82f6; margin-bottom: 5px; font-weight: 700; }
        .ic-tile span { font-size: 15px; font-weight: 700; color: #0f3460; }
        .ic-badge-m { background: #dbeafe; color: #1d4ed8; }
        .ic-badge-f { background: #fce7f3; color: #be185d; }
        .ic-gender-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 13px; font-weight: 700; }
        .ic-error { background: #fef2f2; border: 1px solid #fca5a5; border-radius: 8px; padding: 10px 14px; font-size: 13px; color: #b91c1c; }
        footer { text-align: center; padding: 20px 0 28px; font-size: 11.5px; color: #a0aec0; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="nav-logo">Media<span>Vault</span></div>
    <div class="nav-right">
        <span>👤 <?= htmlspecialchars($_SESSION['username'] ?? 'Guest') ?></span>
        <span class="nav-role <?= strtolower($_SESSION['role'] ?? 'user') ?>"><?= htmlspecialchars($_SESSION['role'] ?? 'User') ?></span>
        
        <?php if ($isAdmin): ?>
            <a href="index_admin.php" class="btn-admin-panel">Admin Panel</a>
        <?php endif; ?>
        
        <a href="authentication/logout.php" class="btn-logout">Sign Out</a>
    </div>
</div>

<div class="page">

    <div class="banner">
        <div class="banner-left">
            <h2>Welcome back, <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?> 👋</h2>
            <p>MediaVault Multimedia Database System &nbsp;·&nbsp; BITP3353</p>
        </div>
        <div class="banner-meta">
            <div class="meta-item">
                <label>Department</label>
                <span><?= htmlspecialchars($_SESSION['department'] ?? '—') ?: '—' ?></span>
            </div>
            <div class="meta-item">
                <label>Email</label>
                <span><?= htmlspecialchars($_SESSION['email'] ?? '—') ?></span>
            </div>
        </div>
    </div>

    <div class="section-label">Account Overview</div>
    <div class="info-row">
        <div class="info-tile"><label>Username</label><span><?= htmlspecialchars($_SESSION['username'] ?? '—') ?></span></div>
        <div class="info-tile"><label>Email</label><span><?= htmlspecialchars($_SESSION['email'] ?? '—') ?></span></div>
        <div class="info-tile"><label>Department</label><span><?= htmlspecialchars($_SESSION['department'] ?? 'Not set') ?: 'Not set' ?></span></div>
        <div class="info-tile"><label>Access Role</label><span><?= htmlspecialchars($_SESSION['role'] ?? '—') ?></span></div>
    </div>

    <div class="section-label">🧬 Member 1 Feature — Malaysian IC Parser</div>
    <div class="ic-demo-card">
        <h3>🪪 Your IC Information</h3>
        <p>The system has extracted your profile information from the database securely.</p>

        <?php if ($ic_data && !isset($ic_data['error'])): ?>
            <div class="ic-display-number">IC Number: <?= htmlspecialchars($db_ic) ?></div>
            
            <div class="ic-result">
                <div class="ic-tile">
                    <label>📅 Date of Birth</label>
                    <span><?= htmlspecialchars($ic_data['dob']) ?></span>
                </div>
                <div class="ic-tile">
                    <label>🎂 Age</label>
                    <span><?= htmlspecialchars($ic_data['age']) ?> years old</span>
                </div>
                <div class="ic-tile">
                    <label>📍 State of Origin</label>
                    <span><?= htmlspecialchars($ic_data['state']) ?></span>
                </div>
                <div class="ic-tile">
                    <label>🧬 Gender</label>
                    <span>
                        <?php if ($ic_data['gender'] === 'Male'): ?>
                            <span class="ic-gender-badge ic-badge-m">♂ Male</span>
                        <?php else: ?>
                            <span class="ic-gender-badge ic-badge-f">♀ Female</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        <?php else: ?>
            <div class="ic-error">⚠️ No valid IC data found in the database for this account. Please ensure your demographic records exist.</div>
        <?php endif; ?>
    </div>

    <div class="section-label">System Modules</div>
    <div class="grid">

        <div class="card c2">
            <div class="card-header">
                <div class="card-icon ic2">📁</div>
                <div class="card-title">Multimedia Asset Management</div>
            </div>
            <div class="card-desc">Upload and manage multimedia files — PDF, DOCX, MP3, WAV, MP4 — with automatic metadata routing to document, audio, and video sub-tables.</div>
            <div class="card-actions">
                <a href="multimedia/upload_file.php"  class="btn b2">⬆️ Upload</a>
                <a href="multimedia/view_files.php"   class="btn b2">📂 View Files</a>
            </div>
        </div>

        <div class="card c3">
            <div class="card-header">
                <div class="card-icon ic3">🔎</div>
                <div class="card-title">Hybrid Search Query Engine</div>
            </div>
            <div class="card-desc">Search across all assets using three strategies: Attribute-Based (ABR), Text-Based (TBR), and Content-Based Retrieval (CBR).</div>
            <div class="card-actions">
                <a href="search/search.php?tab=abr" class="btn b3">📊 ABR Search</a>
                <a href="search/search.php?tab=tbr" class="btn b3">📝 TBR Search</a>
                <a href="search/search.php?tab=cbr" class="btn b3">🎨 CBR Search</a>
            </div>
        </div>
    </div>
</div>

<footer>MediaVault &nbsp;·&nbsp; BITP3353 Multimedia Database &nbsp;·&nbsp; FTMK UTeM &nbsp;·&nbsp; 2025/2026</footer>

</body>
</html>
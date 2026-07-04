<?php
// ============================================================
//  MediaVault - Centralized Unified Dashboard Portal
//  Handles: Centralized metrics, user workspace, and root telemetries
// ============================================================

// 1. Force error reporting on to diagnose any silent issues
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Load dependencies
require_once 'includes/session_guard.php';
require_once 'config/db.php';

$ic_data = null;
$db_ic = '';

/**
 * Parses a 12-digit Malaysian MyKad (IC) number to extract metadata safely.
 * Implements age tracking based on the current context year.
 */
function parseMyKadIC($ic) {
    if (empty($ic) || strlen($ic) !== 12) {
        return ['error' => 'Invalid IC length'];
    }

    $yearPart  = substr($ic, 0, 2);
    $monthPart = substr($ic, 2, 2);
    $dayPart   = substr($ic, 4, 2);

    $currentCenturyShort = (int)date('y'); // e.g., 26 for 2026
    if ((int)$yearPart <= $currentCenturyShort) {
        $fullYear = 2000 + (int)$yearPart;
    } else {
        $fullYear = 1900 + (int)$yearPart;
    }

    $dobString = sprintf("%04d-%02d-%02d", $fullYear, $monthPart, $dayPart);
    $dobFormatted = date("d-M-Y", strtotime($dobString));

    // Calculate dynamic age
    $birthDate = new DateTime($dobString);
    $today     = new DateTime();
    $age       = $today->diff($birthDate)->y;

    // Place of Birth (7th and 8th digit state codes)
    $stateCode = substr($ic, 6, 2);
    $stateMapping = [
        '01' => 'Johor', '21' => 'Johor', '22' => 'Johor', '23' => 'Johor', '24' => 'Johor',
        '02' => 'Kedah', '25' => 'Kedah', '26' => 'Kedah', '27' => 'Kedah',
        '03' => 'Kelantan', '28' => 'Kelantan', '29' => 'Kelantan',
        '04' => 'Melaka', '30' => 'Melaka',
        '05' => 'Negeri Sembilan', '31' => 'Negeri Sembilan', '59' => 'Negeri Sembilan',
        '06' => 'Pahang', '32' => 'Pahang', '33' => 'Pahang',
        '07' => 'Pulau Pinang', '34' => 'Pulau Pinang', '35' => 'Pulau Pinang',
        '08' => 'Perak', '36' => 'Perak', '37' => 'Perak', '38' => 'Perak', '39' => 'Perak',
        '09' => 'Perlis', '40' => 'Perlis',
        '10' => 'Selangor', '41' => 'Selangor', '42' => 'Selangor', '43' => 'Selangor', '44' => 'Selangor',
        '11' => 'Terengganu', '45' => 'Terengganu', '46' => 'Terengganu',
        '12' => 'Sabah', '47' => 'Sabah', '48' => 'Sabah', '49' => 'Sabah',
        '13' => 'Sarawak', '50' => 'Sarawak', '51' => 'Sarawak', '52' => 'Sarawak', '53' => 'Sarawak',
        '14' => 'Kuala Lumpur', '54' => 'Kuala Lumpur', '55' => 'Kuala Lumpur', '56' => 'Kuala Lumpur', '57' => 'Kuala Lumpur',
        '15' => 'Labuan', '58' => 'Labuan', '16' => 'Putrajaya'
    ];
    
    $state = $stateMapping[$stateCode] ?? 'Unknown / Foreign Born';

    // Logical Gender mapping via last digit tracking
    $lastDigit = (int)substr($ic, -1);
    $gender = ($lastDigit % 2 !== 0) ? 'Male' : 'Female';

    return [
        'dob' => $dobFormatted,
        'age' => $age,
        'state' => $state,
        'gender' => $gender
    ];
}

// Check roles explicitly
$userRole = isset($_SESSION['role']) ? strtolower($_SESSION['role']) : 'user';
$isAdmin  = ($userRole === 'admin');

// Default metric state array for Admin View
$system_stats = [
    'total_users' => 0,
    'total_assets' => 0,
    'total_storage_mb' => 0,
    'audit_alerts' => 0
];

$recent_users = [];
$recent_logs = [];
$db_error_messages = [];

if (isset($conn) && $conn) {
    
    // --- PART A: Cache optimization for IC number lookup (All verified roles) ---
    if (!isset($_SESSION['cached_ic']) && isset($_SESSION['user_id'])) {
        $current_user_id = $_SESSION['user_id'];
        
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
                $_SESSION['cached_ic'] = $row['ic_number'];
            }
            mysqli_stmt_close($stmt);
        }
    }

    $db_ic = $_SESSION['cached_ic'] ?? '';
    $user_ic = preg_replace('/[^0-9]/', '', $db_ic);
    if (!empty($user_ic) && strlen($user_ic) === 12) {
        $ic_data = parseMyKadIC($user_ic);
    }

    // --- PART B: Administrative Core System Aggregations (Admin View Only) ---
    if ($isAdmin) {
        // Query 1: Count Active Users
        $res1 = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM user_accounts");
        if ($res1) {
            $row = mysqli_fetch_assoc($res1);
            $system_stats['total_users'] = $row['cnt'];
        } else {
            $db_error_messages[] = "User accounts error: " . mysqli_error($conn);
        }

        // Query 2: Count Assets 
        $res2 = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM multimedia_files");
        if ($res2) {
            $row = mysqli_fetch_assoc($res2);
            $system_stats['total_assets'] = $row['cnt'];
        } else {
            $db_error_messages[] = "Multimedia files error: " . mysqli_error($conn);
        }

        // Query 3: Calculate Storage Volume
        $res3 = mysqli_query($conn, "SELECT ROUND(SUM(size_kb) / 1024, 2) as size_mb FROM multimedia_files"); 
        if ($res3) {
            $row = mysqli_fetch_assoc($res3);
            $system_stats['total_storage_mb'] = $row['size_mb'] ?? 0.00;
        } else {
            $db_error_messages[] = "Storage calc error: " . mysqli_error($conn);
        }

        // Query 4: Fetch Today's Action Triggers
        $res4 = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM transaction_audit_log WHERE DATE(`timestamp`) = CURRENT_DATE");
        if ($res4) {
            $row = mysqli_fetch_assoc($res4);
            $system_stats['audit_alerts'] = $row['cnt'];
        } else {
            $db_error_messages[] = "Transaction log error: " . mysqli_error($conn);
        }

        // Query 5: Populate Recent Users Grid
        $u_query = "SELECT u.user_id, u.username, u.email, u.access_role, d.ic_number 
                    FROM user_accounts u
                    LEFT JOIN demographic_parsing_store d ON u.user_id = d.user_id 
                    ORDER BY u.user_id DESC LIMIT 5";
                    
        if ($u_res = mysqli_query($conn, $u_query)) {
            while ($row = mysqli_fetch_assoc($u_res)) {
                $recent_users[] = $row;
            }
        } else {
            $db_error_messages[] = "Recent users query error: " . mysqli_error($conn);
        }

        // Query 6: Populate Recent Logs Grid
        $l_query = "SELECT log_id, operation_type AS action_type, 'multimedia_files' AS table_name, user_id AS executed_by, `timestamp` AS action_timestamp 
                    FROM transaction_audit_log 
                    ORDER BY `timestamp` DESC LIMIT 5";
                    
        if ($l_res = mysqli_query($conn, $l_query)) {
            while ($row = mysqli_fetch_assoc($l_res)) {
                $recent_logs[] = $row;
            }
        } else {
            $db_error_messages[] = "Audit logs query error: " . mysqli_error($conn);
        }
    }
} else {
    $db_error_messages[] = "Critical Error: Database connection link (\$conn) is missing.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediaVault - <?= $isAdmin ? 'Administrative Control Center' : 'Workspace Dashboard' ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #eef1f6; min-height: 100vh; color: #1a1a2e; }

        /* NAVBAR */
        .navbar {
            background: #0f3460; padding: 0 32px; height: 58px;
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 8px rgba(0,0,0,0.25);
        }
        .nav-logo { font-size: 20px; font-weight: 800; color: #fff; letter-spacing: 1px; }
        .nav-logo span { color: #ffd700; }
        .nav-right { display: flex; align-items: center; gap: 10px; font-size: 13px; color: rgba(255,255,255,0.75); }
        .nav-role { color: #fff; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .nav-role.admin { background: #e94560; }
        .nav-role.user   { background: #2563eb; }
        .btn-logout { color: #fff; text-decoration: none; background: rgba(255,255,255,0.12); padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; }
        .btn-logout:hover { background: rgba(255,255,255,0.22); }

        /* LAYOUT CONTAINER */
        .page { max-width: 1200px; margin: 0 auto; padding: 32px 24px; display: grid; gap: 24px; }

        /* DB ALERTS SCREEN */
        .db-warning-box {
            background: #fff5f5; border-left: 4px solid #e53e3e; padding: 16px; 
            border-radius: 8px; color: #c53030; font-size: 13px;
        }
        .db-warning-box h4 { margin-bottom: 6px; font-weight: 700; }
        .db-warning-box ul { padding-left: 16px; }

        /* BANNER */
        .banner {
            background: <?= $isAdmin ? 'linear-gradient(120deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%)' : 'linear-gradient(120deg, #0f3460 0%, #1f618d 100%)' ?>;
            border-radius: 14px; padding: 28px 32px; color: #fff;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 4px 16px rgba(15,52,96,0.18);
        }
        .banner-left h2 { font-size: 22px; font-weight: 700; margin-bottom: 4px; }
        .banner-left p  { font-size: 13px; opacity: 0.75; }
        .banner-left p.admin-accent { color: #ffd700; font-weight: 600; opacity: 1; }
        .banner-meta { display: flex; gap: 24px; }
        .meta-item { text-align: right; }
        .meta-item label { display: block; font-size: 10px; opacity: 0.6; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
        .meta-item span  { font-weight: 600; font-size: 13px; }

        .section-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #64748b; margin-bottom: -10px; }
        .section-label.spaced { margin-bottom: 0px; margin-top: 8px; }

        /* ACCOUNT OVERVIEW GRID */
        .dashboard-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .info-tile { background: #fff; border-radius: 12px; padding: 18px; box-shadow: 0 1px 6px rgba(0,0,0,0.05); border-top: 3px solid #0f3460; }
        .info-tile.alert-tile { border-top-color: #e94560; }
        .info-tile label { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: 0.7px; color: #94a3b8; margin-bottom: 5px; font-weight: 600; }
        .info-tile span  { font-size: 14px; font-weight: 700; color: #1e293b; }
        .info-tile.metric span { font-size: 20px; font-weight: 800; }

        /* TABLES & SPLIT GRID */
        .split-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 16px; }
        @media (max-width: 600px) { .split-grid { grid-template-columns: 1fr; } }
        
        .panel-card { background: #fff; border-radius: 12px; padding: 22px; box-shadow: 0 1px 6px rgba(0,0,0,0.07); }
        .panel-title { font-size: 14px; font-weight: 700; color: #0f3460; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; }
        
        .admin-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 12.5px; }
        .admin-table th { color: #64748b; font-weight: 600; padding: 8px 10px; border-bottom: 2px solid #e2e8f0; font-size: 11px; text-transform: uppercase; }
        .admin-table td { padding: 10px; border-bottom: 1px solid #f1f5f9; color: #334155; }
        
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; }
        .badge.admin { background: #fdedf0; color: #c0152a; }
        .badge.user  { background: #e8eef7; color: #2563eb; }
        .badge.viewer{ background: #f1f5f9; color: #64748b; }
        .action-link { font-size: 11px; font-weight: 600; color: #0f3460; text-decoration: none; }

        /* MODULES GRID */
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; }
        .card { background: #fff; border-radius: 12px; padding: 22px; box-shadow: 0 1px 6px rgba(0,0,0,0.07); display: flex; flex-direction: column; gap: 8px; border-left: 4px solid #ccc; transition: box-shadow 0.2s, transform 0.2s; }
        .card:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.10); transform: translateY(-2px); }
        .c2 { border-left-color: #0f3460; }
        .c3 { border-left-color: #f59e0b; }
        .c4 { border-left-color: #e94560; }
        .c5 { border-left-color: #8b5cf6; }
        .c-system { border-left-color: #10b981; }
        
        .card-header { display: flex; align-items: center; gap: 12px; }
        .card-icon { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .ic2{background:#e8eef7;} .ic3{background:#fef3cd;} .ic4{background:#fdedf0;} .ic5{background:#ede9fe;} .ic-green{background:#d1fae5;}
        .card-title { font-size: 15px; font-weight: 700; color: #0f172a; line-height: 1.3; }
        .card-desc  { font-size: 12.5px; color: #64748b; line-height: 1.6; min-height: 45px; }
        .card-actions { display: flex; flex-wrap: wrap; gap: 7px; margin-top: auto; padding-top: 12px; }
        .btn { padding: 6px 13px; border-radius: 7px; font-size: 12px; font-weight: 600; text-decoration: none; }
        .btn:hover { opacity: 0.85; }
        .b2{background:#e8eef7;color:#0f3460;} .b3{background:#fef3cd;color:#92600a;} .b4{background:#fdedf0;color:#c0152a;} .b5{background:#ede9fe;color:#5b21b6;} .b-green{background:#d1fae5;color:#065f46;}

        /* DEMOGRAPHIC CARD */
        .ic-demo-card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 1px 6px rgba(0,0,0,0.07); border-left: 4px solid #0f3460; }
        .ic-demo-card h3 { font-size: 15px; font-weight: 700; color: #0f3460; margin-bottom: 4px; display: flex; align-items: center; gap: 8px; }
        .ic-demo-card p { font-size: 12.5px; color: #64748b; margin-bottom: 16px; }
        .ic-display-number { font-size: 14px; font-weight: 700; color: #0f3460; background: #f1f5f9; padding: 6px 12px; border-radius: 6px; display: inline-block; font-family: monospace; margin-bottom: 16px; }
        .ic-result { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
        .ic-tile { background: #f0f7ff; border-radius: 9px; padding: 14px; border: 1px solid #bfdbfe; }
        .ic-tile label { display: block; font-size: 10px; text-transform: uppercase; color: #3b82f6; margin-bottom: 5px; font-weight: 700; }
        .ic-tile span { font-size: 14px; font-weight: 700; color: #0f3460; }
        .ic-gender-badge { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 12px; font-weight: 700; }
        .ic-badge-m { background: #dbeafe; color: #1d4ed8; }
        .ic-badge-f { background: #fce7f3; color: #be185d; }
        .ic-error { background: #fef2f2; border: 1px solid #fca5a5; border-radius: 8px; padding: 12px; font-size: 13px; color: #b91c1c; }
        
        footer { text-align: center; padding: 8px 0 20px; font-size: 11.5px; color: #a0aec0; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="nav-logo">Media<span>Vault</span></div>
    <div class="nav-right">
        <span>👤 <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
        <span class="nav-role <?= $isAdmin ? 'admin' : 'user' ?>"><?= htmlspecialchars($_SESSION['role'] ?? 'User') ?></span>
        <a href="authentication/logout.php" class="btn-logout">Sign Out</a>
    </div>
</div>

<div class="page">

    <?php if ($isAdmin && !empty($db_error_messages)): ?>
        <div class="db-warning-box">
            <h4>⚠️ Database Dynamic Query Warning:</h4>
            <ul>
                <?php foreach ($db_error_messages as $msg): ?>
                    <li><?= htmlspecialchars($msg) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="banner">
        <div class="banner-left">
            <?php if ($isAdmin): ?>
                <h2>Administrative Command Center 🛠️</h2>
                <p class="admin-accent">Root Privileges Active &nbsp;·&nbsp; gs05db central store</p>
            <?php else: ?>
                <h2>Welcome back, <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?> 👋</h2>
                <p>Personal Media Management Repository &nbsp;·&nbsp; BITP3353</p>
            <?php endif; ?>
        </div>
        <div class="banner-meta">
            <div class="meta-item">
                <label><?= $isAdmin ? 'Server Status' : 'Department' ?></label>
                <?php if ($isAdmin): ?>
                    <span style="color: #10b981;">● Operational</span>
                <?php else: ?>
                    <span><?= htmlspecialchars($_SESSION['department'] ?? '—') ?: '—' ?></span>
                <?php endif; ?>
            </div>
            <div class="meta-item">
                <label><?= $isAdmin ? 'Admin Email' : 'Clearance Level' ?></label>
                <span style="color: <?= $isAdmin ? '#ffd700' : '#fff' ?>; font-weight: bold;">
                    <?= $isAdmin ? 'ROOT ADMIN' : 'STANDARD' ?>
                </span>
            </div>
        </div>
    </div>

    <?php if ($isAdmin): ?>
        <div class="section-label">System Aggregations &amp; Telemetry</div>
        <div class="info-row dashboard-row">
            <div class="info-tile metric"><label>👥 Registered Users</label><span><?= number_format($system_stats['total_users']) ?></span></div>
            <div class="info-tile metric"><label>📁 Managed Files</label><span><?= number_format($system_stats['total_assets']) ?></span></div>
            <div class="info-tile metric"><label>💾 Storage Volume</label><span><?= $system_stats['total_storage_mb'] ?> MB</span></div>
            <div class="info-tile alert-tile metric"><label>⚡ System Actions (Today)</label><span><?= number_format($system_stats['audit_alerts']) ?></span></div>
        </div>

        <div class="split-grid">
            <div class="panel-card">
                <div class="panel-title">
                    <span>👥 Recent User Registrations</span>
                    <a href="admin/manage_users.php" class="action-link">View All →</a>
                </div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Malaysian IC Linked</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_users)): ?>
                            <tr><td colspan="3">No user records detected.</td></tr>
                        <?php else: ?>
                            <?php foreach($recent_users as $user): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($user['username']) ?></strong><br><small style="color:#64748b"><?= htmlspecialchars($user['email']) ?></small></td>
                                    <td><span class="badge <?= strtolower($user['access_role'] ?? 'user') ?>"><?= htmlspecialchars($user['access_role'] ?? 'User') ?></span></td>
                                    <td style="font-family: monospace; letter-spacing:0.5px;"><?= htmlspecialchars($user['ic_number'] ?? 'Not Parsed') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="panel-card">
                <div class="panel-title">
                    <span>📋 Database Automated Trigger Logs</span>
                    <a href="audit/audit_log.php" class="action-link">View Logs →</a>
                </div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Table Source</th>
                            <th>Operator ID</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_logs)): ?>
                            <tr><td colspan="4">No operational logs caught.</td></tr>
                        <?php else: ?>
                            <?php foreach($recent_logs as $log): ?>
                                <tr>
                                    <td style="color:#e94560; font-weight:600;"><?= htmlspecialchars($log['action_type']) ?></td>
                                    <td><code><?= htmlspecialchars($log['table_name']) ?></code></td>
                                    <td>User #<?= htmlspecialchars($log['executed_by'] ?? '0') ?></td>
                                    <td style="font-size:11px; color:#64748b;"><?= htmlspecialchars($log['action_timestamp']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <div class="section-label <?= $isAdmin ? 'spaced' : '' ?>">Account Identification Context</div>
    <div class="dashboard-row">
        <div class="info-tile"><label>👤 Account Username</label><span><?= htmlspecialchars($_SESSION['username'] ?? '—') ?></span></div>
        <div class="info-tile"><label>✉️ Primary Mailbox</label><span><?= htmlspecialchars($_SESSION['email'] ?? '—') ?></span></div>
        <div class="info-tile"><label>🏢 Department Assignment</label><span><?= htmlspecialchars($_SESSION['department'] ?? 'Not set') ?: 'Not set' ?></span></div>
    </div>

    <div class="section-label">🧬 Identity Parsing Store</div>
    <div class="ic-demo-card">
        <h3>🪪 Identity Attributes Breakdown</h3>
        <p>Decrypted metadata extracted securely via DB-level relational mapping constraints.</p>

        <?php if ($ic_data && !isset($ic_data['error'])): ?>
            <div class="ic-display-number">MyKad Target: <?= htmlspecialchars($db_ic) ?></div>
            <div class="ic-result">
                <div class="ic-tile"><label>📅 Year/Date of Birth</label><span><?= htmlspecialchars($ic_data['dob']) ?></span></div>
                <div class="ic-tile"><label>🎂 Calculated Age</label><span><?= htmlspecialchars($ic_data['age']) ?> Y/O</span></div>
                <div class="ic-tile"><label>📍 Native Home State</label><span><?= htmlspecialchars($ic_data['state']) ?></span></div>
                <div class="ic-tile">
                    <label>🧬 Logical Gender Mapping</label>
                    <span>
                        <?php if (isset($ic_data['gender']) && strtolower($ic_data['gender']) === 'male'): ?>
                            <span class="ic-gender-badge ic-badge-m">♂ Male</span>
                        <?php else: ?>
                            <span class="ic-gender-badge ic-badge-f">♀ Female</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        <?php else: ?>
            <div class="ic-error">⚠️ Identity binding error: No verifiable MyKad entity linked to this unique user mapping context.</div>
        <?php endif; ?>
    </div>

    <div class="section-label">Accessible Functional Elements</div>
    <div class="grid">

        <div class="card c2">
            <div class="card-header">
                <div class="card-icon ic2">📁</div>
                <div class="card-title"><?= $isAdmin ? 'Global Media Repositories' : 'Personal Document Vault' ?></div>
            </div>
            <div class="card-desc">
                <?= $isAdmin ? 'Inspect, review, and flag cross-sub-table media artifacts, files, and relational attributes.' : 'Upload and safely catalog your PDF, images, audio strings, and video binaries.' ?>
            </div>
            <div class="card-actions">
                <?php if (!$isAdmin): ?>
                    <a href="multimedia/upload_file.php" class="btn b2">⬆️ Upload File</a>
                <?php endif; ?>
                <a href="multimedia/view_files_admin.php" class="btn b2">📂 Browse Archives</a>
            </div>
        </div>

        <div class="card c3">
            <div class="card-header">
                <div class="card-icon ic3">🔎</div>
                <div class="card-title">Hybrid Optimization Search Core</div>
            </div>
            <div class="card-desc">Execute query patterns via dynamic text-based indexing or content-attribute retrieval parameters.</div>
            <div class="card-actions">
                <a href="search/search_admin.php" class="btn b3">Index (ABR)</a>
                <a href="search/search_admin.php" class="btn b3">String (TBR)</a>
                <a href="search/search_admin.php" class="btn b3">Content (CBR)</a>
            </div>
        </div>

        <?php if ($isAdmin): ?>

            <div class="card c5">
                <div class="card-header">
                    <div class="card-icon ic5">⚙️</div>
                    <div class="card-title">System Triggers &amp; Operational Logs</div>
                </div>
                <div class="card-desc">Review automated schema audit records written via system-level transaction triggers (<code>AFTER INSERT/UPDATE/DELETE</code>).</div>
                <div class="card-actions">
                    <a href="audit/audit_log.php" class="btn b5">📋 Audit Ledger</a>
                </div>
            </div>

            <div class="card c-system">
                <div class="card-header">
                    <div class="card-icon ic-green">📈</div>
                    <div class="card-title">Global Aggregations & Telemetry</div>
                </div>
                <div class="card-desc">Compute structural disk usage footprints, uploader distribution frequencies, and regional target groupings.</div>
                <div class="card-actions">
                    <a href="reports/storage_report.php" class="btn b-green">💾 Disk Footprint</a>
                    <a href="reports/audio_report.php" class="btn b-green">🎵 Audio Vectors</a>
                    <a href="reports/demographic_report.php" class="btn b-green">👥 Identity Matrices</a>
                </div>
            </div>
        <?php endif; ?>

    </div>
    
    <footer>MediaVault &nbsp;·&nbsp; Advanced Database Systems &nbsp;·&nbsp; FTMK UTeM</footer>
</div>
</body>
</html>
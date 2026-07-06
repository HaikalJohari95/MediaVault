<?php
// ============================================================
//  MediaVault - Session Guard (Fixed Pathing)
//  Member 1: NURHANIM NABILA BINTI AB RAZAK
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // FIX: Mengesan kedudukan root directory secara dinamik supaya pautan tidak pecah 
    // sama ada dipanggil dari fail root atau dari dalam sub-folder.
    $script_dir = dirname($_SERVER['SCRIPT_NAME']);
    $base_url = ($script_dir === '/' || $script_dir === '\\') ? '' : $script_dir;
    
    // Jika dipanggil dari subfolder (seperti /audit atau /reports), kita potong hujungnya
    if (preg_match('/(?:\/audit|\/reports|\/multimedia|\/search|\/includes)$/', $base_url)) {
        $base_url = preg_replace('/(?:\/audit|\/reports|\/multimedia|\/search|\/includes)$/', '', $base_url);
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>MediaVault - Welcome</title>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                font-family: 'Segoe UI', sans-serif;
                background: linear-gradient(135deg, #0f3460 0%, #1a5276 50%, #e94560 100%);
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 24px;
            }
            .logo {
                font-size: 36px;
                font-weight: 900;
                color: #fff;
                letter-spacing: 2px;
                margin-bottom: 6px;
            }
            .logo span { color: #ffd700; }
            .tagline {
                font-size: 13px;
                color: rgba(255,255,255,0.65);
                margin-bottom: 40px;
                letter-spacing: 0.5px;
            }
            .card {
                background: #fff;
                border-radius: 16px;
                padding: 40px 44px;
                width: 100%;
                max-width: 420px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                text-align: center;
            }
            .card h2 {
                font-size: 18px;
                color: #0f3460;
                margin-bottom: 8px;
                font-weight: 700;
            }
            .card p {
                font-size: 13px;
                color: #94a3b8;
                margin-bottom: 30px;
                line-height: 1.6;
            }
            .btn-login {
                display: block;
                width: 100%;
                padding: 13px;
                background: #0f3460;
                color: #fff;
                border-radius: 10px;
                text-decoration: none;
                font-size: 15px;
                font-weight: 700;
                margin-bottom: 12px;
                transition: background 0.2s;
            }
            .btn-login:hover { background: #1a4a80; }
            .btn-register {
                display: block;
                width: 100%;
                padding: 13px;
                background: #fff;
                color: #e94560;
                border-radius: 10px;
                text-decoration: none;
                font-size: 15px;
                font-weight: 700;
                border: 2px solid #e94560;
                transition: background 0.2s, color 0.2s;
            }
            .btn-register:hover { background: #e94560; color: #fff; }
            .divider {
                display: flex;
                align-items: center;
                gap: 10px;
                margin: 18px 0;
                color: #cbd5e1;
                font-size: 12px;
            }
            .divider::before, .divider::after {
                content: '';
                flex: 1;
                border-top: 1px solid #e2e8f0;
            }
            footer {
                margin-top: 28px;
                font-size: 11.5px;
                color: rgba(255,255,255,0.4);
                text-align: center;
            }
        </style>
    </head>
    <body>
        <div class="logo">Media<span>Vault</span></div>
        <div class="tagline">BITP3353 Multimedia Database System &nbsp;·&nbsp; UTeM FTMK</div>

        <div class="card">
            <h2>Welcome to MediaVault</h2>
            <p>Please sign in to access the system, or create a new account to get started.</p>

            <a href="<?= $base_url ?>/authentication/login.php" class="btn-login">🔑 Sign In</a>

            <div class="divider">or</div>

            <a href="<?= $base_url ?>/authentication/register.php" class="btn-register">📝 Create Account</a>
        </div>

        <footer>MediaVault &nbsp;·&nbsp; 2025/2026</footer>
    </body>
    </html>
    <?php
    exit();
}

// Helper: Check if user has required role
function requireRole($required_role) {
    $hierarchy = ['Viewer' => 1, 'User' => 2, 'Admin' => 3];
    $user_level = $hierarchy[$_SESSION['role']] ?? 0;
    $req_level  = $hierarchy[$required_role] ?? 99;

    if ($user_level < $req_level) {
        // FIX: Menggunakan sistem dinamik path juga untuk butang Back to Dashboard
        $script_dir = dirname($_SERVER['SCRIPT_NAME']);
        $base_url = ($script_dir === '/' || $script_dir === '\\') ? '' : $script_dir;
        if (preg_match('/(?:\/audit|\/reports|\/multimedia|\/search|\/includes)$/', $base_url)) {
            $base_url = preg_replace('/(?:\/audit|\/reports|\/multimedia|\/search|\/includes)$/', '', $base_url);
        }

        die("<div style='font-family:sans-serif;padding:40px;text-align:center;color:#e94560;'>
             <h2>⛔ Access Denied</h2>
             <p style='color:#64748b;margin-top:10px;'>You need <strong>$required_role</strong> privileges to view this page.</p>
             <a href='" . $base_url . "/index.php' style='display:inline-block;margin-top:20px;padding:10px 24px;background:#0f3460;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;'>← Back to Dashboard</a>
             </div>");
    }
}
?>
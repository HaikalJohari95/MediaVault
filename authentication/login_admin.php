<?php
// ============================================================
//  MediaVault - Admin Only Login Page
//  Member 1: NURHANIM NABILA BINTI AB RAZAK
//  Handles: Plain-text password validation + Strict Admin Enforcement
// ============================================================

session_start();

// Lencongkan jika admin sudah sedia log masuk
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin') {
    header("Location: ../index_admin.php");
    exit();
}

require_once '../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';

    // Validasi asas form input
    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {

        // Dapatkan maklumat akaun berdasarkan username ATAU email
        $stmt = mysqli_prepare($conn,
            "SELECT user_id, username, email, password_hash, department, access_role
             FROM user_accounts
             WHERE username = ? OR email = ?
             LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, 'ss', $username, $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user   = mysqli_fetch_assoc($result);

        // Semakan padanan kata laluan secara Plain-Text (Bukan password_verify)
        if ($user && $password === $user['password_hash']) {
            
            // ── SEKATAN KESELAMATAN: ENFORCE ADMIN ONLY ──
            if (strtolower($user['access_role']) !== 'admin') {
                $error = "Access Denied: This terminal is strictly reserved for Administrators only.";
            } else {
                // Log masuk berjaya: Cipta data sesi (Session Variables)
                session_regenerate_id(true); // Elak serangan Session Fixation

                $_SESSION['user_id']    = $user['user_id'];
                $_SESSION['username']   = $user['username'];
                $_SESSION['email']      = $user['email'];
                $_SESSION['role']       = $user['access_role'];
                $_SESSION['department'] = $user['department'];
                $_SESSION['logged_in']  = true;

                // Hala terus ke halaman panel pentadbir utama
                header("Location: ../index_admin.php");
                exit();
            }
        } else {
            $error = "Invalid username/email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - MediaVault</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
    <style>
        /* Gaya visual tambahan bagi membezakan portal log masuk Admin */
        .auth-card { border-top: 5px solid #e94560; }
        .admin-badge {
            background: #fdedf0; color: #c0152a;
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            padding: 4px 10px; border-radius: 20px;
            display: inline-block; margin-bottom: 15px; letter-spacing: 0.5px;
        }
    </style>
</head>
<body>

<div class="auth-card">
    <div class="auth-logo">
        <h1>Media<span>Vault</span></h1>
        <p>Multimedia Database Management System</p>
    </div>

    <center><span class="admin-badge">🔒 Administrative Terminal</span></center>
    <h2 class="form-title" style="margin-top: 5px;">Sign In</h2>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger" style="background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; padding: 12px; border-radius: 6px; font-size: 13px; margin-bottom: 16px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="username">Admin Username or Email</label>
            <input type="text" id="username" name="username"
                   placeholder="Enter admin credentials"
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                   required>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password"
                   placeholder="Enter your password"
                   required>
        </div>

        <button type="submit" class="btn-primary" style="background: #e94560;">Secure Login</button>
    </form>

    <div class="auth-link">
        <a href="../index_admin.php" style="color: #64748b; text-decoration: none;">← Back to Main System</a>
    </div>
</div>

</body>
</html>
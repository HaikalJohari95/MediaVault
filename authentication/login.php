<?php
// ============================================================
//  MediaVault - Login Page (Standard User Access)
//  Member 1: NURHANIM NABILA BINTI AB RAZAK
//  Handles: Plain-text password validation + Standard session management
// ============================================================

session_start();

// Lencongkan pengguna ke index jika sudah sedia log masuk
if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
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
            
            // Log masuk berjaya: Cipta data sesi (Session Variables)
            session_regenerate_id(true); // Elak serangan Session Fixation

            $_SESSION['user_id']    = $user['user_id'];
            $_SESSION['username']   = $user['username'];
            $_SESSION['email']      = $user['email'];
            $_SESSION['role']       = $user['access_role'];
            $_SESSION['department'] = $user['department'];
            $_SESSION['logged_in']  = true;

            // Hala terus ke halaman utama (Main Dashboard / Index)
            header("Location: ../index.php");
            exit();
            
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
    <title>Login - MediaVault</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>

<div class="auth-card">
    <div class="auth-logo">
        <h1>Media<span>Vault</span></h1>
        <p>Multimedia Database Management System</p>
    </div>

    <h2 class="form-title">Sign In</h2>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger" style="background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; padding: 12px; border-radius: 6px; font-size: 13px; margin-bottom: 16px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="username">Username or Email</label>
            <input type="text" id="username" name="username"
                   placeholder="Enter your username or email"
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                   required>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password"
                   placeholder="Enter your password"
                   required>
        </div>

        <button type="submit" class="btn-primary">Login</button>
    </form>

    <div class="auth-link">
        Don't have an account? <a href="register.php">Register here</a>
    </div>
</div>

</body>
</html>
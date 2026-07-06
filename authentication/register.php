<?php
// ============================================================
//  MediaVault - Registration Page
//  Member 1: NURHANIM NABILA BINTI AB RAZAK
//  Handles: Account creation + IC Parsing + Plain-text Password
// ============================================================

session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../config/db.php';
require_once '../includes/ic_parser.php';

$error   = '';
$success = '';

// AJAX: Return parsed IC data as JSON
if (isset($_GET['parse_ic'])) {
    header('Content-Type: application/json');
    $ic = preg_replace('/[^0-9]/', '', $_GET['parse_ic']);
    $result = parseMyKadIC($ic);
    echo json_encode($result ?? ['error' => 'Invalid IC']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Collect & Sanitize Input
    $username   = trim($_POST['username']   ?? '');
    $email      = trim($_POST['email']      ?? '');
    $password   = $_POST['password']        ?? ''; // Ambil direct tanpa hash
    $confirm_pw = $_POST['confirm_password'] ?? '';
    $department = trim($_POST['department'] ?? '');
    $ic_number  = preg_replace('/[^0-9]/', '', $_POST['ic_number'] ?? '');
    $role       = $_POST['access_role'] ?? 'User';

    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($ic_number)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm_pw) {
        $error = "Passwords do not match.";
    } elseif (strlen($ic_number) !== 12) {
        $error = "IC number must be exactly 12 digits.";
    } else {
        // IC Parsing
        $parsed = parseMyKadIC($ic_number);

        if (!$parsed || isset($parsed['error'])) {
            $error = "Invalid IC number format.";
        } else {
            // Check Duplicate IC Number
            $check_ic = mysqli_prepare($conn, "SELECT record_id FROM demographic_parsing_store WHERE ic_number = ? LIMIT 1");
            mysqli_stmt_bind_param($check_ic, 's', $ic_number);
            mysqli_stmt_execute($check_ic);
            mysqli_stmt_store_result($check_ic);
            $ic_exists = mysqli_stmt_num_rows($check_ic) > 0;
            mysqli_stmt_close($check_ic);

            if ($ic_exists) {
                $error = "IC number is already registered.";
            } else {
                // Check Duplicate Username atau Email
                $check = mysqli_prepare($conn, "SELECT user_id FROM user_accounts WHERE username = ? OR email = ? LIMIT 1");
                mysqli_stmt_bind_param($check, 'ss', $username, $email);
                mysqli_stmt_execute($check);
                mysqli_stmt_store_result($check);
                $user_exists = mysqli_stmt_num_rows($check) > 0;
                mysqli_stmt_close($check);

                if ($user_exists) {
                    $error = "Username or email already registered.";
                } else {
                    
                    // Memulakan Database Transaction untuk jaminan ACID
                    mysqli_begin_transaction($conn);

                    try {
                        // 1. Insert ke dalam table: user_accounts (Menyimpan $password secara teks biasa)
                        $insert_user = mysqli_prepare($conn,
                            "INSERT INTO user_accounts (username, email, password_hash, department, access_role)
                             VALUES (?, ?, ?, ?, ?)"
                        );
                        // Nota: Nama column 'password_hash' dikekalkan mengikut schema asal database anda, tetapi datanya adalah teks biasa
                        mysqli_stmt_bind_param($insert_user, 'sssss', $username, $email, $password, $department, $role);
                        mysqli_stmt_execute($insert_user);
                        
                        // Dapatkan ID auto-increment yang baru dijana
                        $new_user_id = mysqli_insert_id($conn);
                        mysqli_stmt_close($insert_user);

                        // 2. Insert ke dalam table: demographic_parsing_store
                        $insert_ic = mysqli_prepare($conn,
                            "INSERT INTO demographic_parsing_store (user_id, ic_number, date_of_birth, state_of_origin)
                             VALUES (?, ?, ?, ?)"
                        );
                        $dob_db = $parsed['dob_db'] ?? null; 
                        mysqli_stmt_bind_param($insert_ic, 'isss', $new_user_id, $ic_number, $dob_db, $parsed['state']);
                        mysqli_stmt_execute($insert_ic);
                        mysqli_stmt_close($insert_ic);

                        // Jika kedua-dua proses INSERT berjaya, sahkan transaksi
                        mysqli_commit($conn);
                        $success = "Registration successful! You can now <a href='login.php'>login here</a>.";
                        
                        // Kosongkan semula form input
                        $_POST = array();
                        
                    } catch (Exception $e) {
                        // Jika ada ralat, batalkan semua perubahan (Rollback)
                        mysqli_rollback($conn);
                        $error = "Registration failed due to a system error. Please try again.";
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MediaVault</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
    <style>
        .ic-parsed-info {
            display: none;
            margin-top: 10px;
            padding: 12px;
            background: #f0f7ff;
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            font-size: 13px;
            color: #1e3a8a;
            line-height: 1.6;
        }
        .divider {
            margin: 20px 0;
            border: 0;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>

<div class="auth-card" style="max-width:540px; margin: 40px auto;">
    <div class="auth-logo">
        <h1>Media<span>Vault</span></h1>
        <p>Create Your Account</p>
    </div>

    <h2 class="form-title">Register</h2>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger" style="color: #b91c1c; background: #fef2f2; padding: 10px; border-radius: 6px; margin-bottom: 15px; border: 1px solid #fca5a5;"><?= $error ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success" style="color: #15803d; background: #f0fdf4; padding: 10px; border-radius: 6px; margin-bottom: 15px; border: 1px solid #bbf7d0;"><?= $success ?></div>
    <?php endif; ?>

    <form method="POST" action="">

        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" placeholder="Choose a username"
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" placeholder="your@email.com"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label>Malaysian IC Number (No Dashes)</label>
            <input type="text" name="ic_number" id="ic_input"
                   placeholder="e.g. 021115101234"
                   maxlength="12"
                   value="<?= htmlspecialchars($_POST['ic_number'] ?? '') ?>" required>

            <div class="ic-parsed-info" id="ic_parsed_box">
                <p>
                    📅 <strong>Date of Birth:</strong> <span id="ic_dob">-</span><br>
                    🎂 <strong>Age:</strong> <span id="ic_age">-</span><br>
                    📍 <strong>State of Origin:</strong> <span id="ic_state">-</span><br>
                    🧬 <strong>Gender:</strong> <span id="ic_gender">-</span>
                </p>
            </div>
        </div>

        <div class="form-group">
            <label>Department</label>
            <input type="text" name="department" placeholder="e.g. IT, Marketing, Finance"
                   value="<?= htmlspecialchars($_POST['department'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label>Access Role</label>
            <select name="access_role">
                <option value="User"   <?= (($_POST['access_role'] ?? '') === 'User')   ? 'selected' : '' ?>>User</option>
                <option value="Viewer" <?= (($_POST['access_role'] ?? '') === 'Viewer') ? 'selected' : '' ?>>Viewer</option>
            </select>
        </div>

        <hr class="divider">

        <div class="form-group">
            <label>Password <small>(min. 8 characters)</small></label>
            <input type="password" name="password" placeholder="Create your password" required>
        </div>

        <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" placeholder="Re-enter your password" required>
        </div>

        <button type="submit" class="btn-primary">Create Account</button>
    </form>

    <div class="auth-link" style="margin-top: 15px; text-align: center;">
        Already have an account? <a href="login.php">Login here</a>
    </div>
</div>

<script>
const icInput   = document.getElementById('ic_input');
const parsedBox = document.getElementById('ic_parsed_box');

icInput.addEventListener('input', function () {
    const ic = this.value.replace(/[^0-9]/g, '');
    this.value = ic; 
    
    if (ic.length === 12) {
        fetch(`register.php?parse_ic=${ic}`)
            .then(r => r.json())
            .then(data => {
                if (!data.error) {
                    document.getElementById('ic_dob').textContent    = data.dob;
                    document.getElementById('ic_age').textContent    = data.age + ' years old';
                    document.getElementById('ic_state').textContent  = data.state;
                    document.getElementById('ic_gender').textContent = data.gender;
                    parsedBox.style.display = 'block';
                } else {
                    parsedBox.style.display = 'none';
                }
            })
            .catch(() => {
                parsedBox.style.display = 'none';
            });
    } else {
        parsedBox.style.display = 'none';
    }
});
</script>

</body>
</html>
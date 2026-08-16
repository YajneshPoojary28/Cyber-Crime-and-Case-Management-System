<?php
require_once 'config/database.php';

// No session_start() here - it's already in database.php

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

// Regular login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($email && $password) {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT id, name, email, password FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid password!";
            }
        } else {
            $error = "Email not found!";
        }
        $stmt->close();
        $conn->close();
    } else {
        $error = "Please fill all fields!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - CyberShield</title>
<link rel="stylesheet" href="css/style.css">
<style>
/* Password field with forgot link on right */
.password-wrapper {
    position: relative;
    margin-bottom: 5px;
}
.password-input {
    width: 100%;
    padding-right: 70px;
}
.forgot-link-right {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 11px;
    white-space: nowrap;
}
.forgot-link-right a {
    color: var(--text3);
    text-decoration: none;
}
.forgot-link-right a:hover {
    color: var(--accent2);
    text-decoration: underline;
}
</style>
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-logo">
            <div style="font-size:48px;margin-bottom:10px">🛡️</div>
            <div class="auth-title">Welcome Back</div>
            <div class="auth-sub">Login to your account</div>
        </div>
        
        <?php if ($error): ?>
            <div style="background:rgba(244,63,94,0.1);border:1px solid rgba(244,63,94,0.3);border-radius:10px;padding:12px;margin-bottom:20px;color:var(--danger);">❌ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group" style="margin-bottom:20px">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-input" placeholder="your@email.com" required>
            </div>
            
            <div class="form-group" style="margin-bottom:25px">
                <label class="form-label">Password</label>
                <div class="password-wrapper">
                    <input type="password" name="password" class="form-input password-input" placeholder="••••••••" required>
                    <div class="forgot-link-right">
                        <a href="reset-password.php">Forgot?</a>
                    </div>
                </div>
            </div>
            
            <button type="submit" name="login" class="btn btn-primary" style="width:100%;padding:14px">Sign In →</button>
        </form>
        
        <div style="text-align:center;margin-top:20px">
            <a href="register.php" style="color:var(--accent2);text-decoration:none">Don't have an account? Register</a>
        </div>
        <div style="text-align:center;margin-top:15px">
            <a href="index.php" style="color:var(--text3);text-decoration:none;font-size:12px">🏠 Back to Home</a>
        </div>
    </div>
</div>
</body>
</html>
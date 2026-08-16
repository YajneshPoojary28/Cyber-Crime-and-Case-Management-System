<?php
require_once 'config/database.php';

if (isAdminLoggedIn()) {
    header("Location: admin-dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT id, username, full_name, role, password FROM admins WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($admin = $result->fetch_assoc()) {
            if (password_verify($password, $admin['password'])) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['full_name'];
                $_SESSION['admin_role'] = $admin['role'];
                addActivityLog($admin['id'], "Logged in to admin console", $_SERVER['REMOTE_ADDR']);
                header("Location: admin-dashboard.php");
                exit();
            } else {
                $error = "Invalid password!";
            }
        } else {
            $error = "Username not found!";
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
<title>Admin Login - CyberShield</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-logo">
            <div style="font-size:48px;margin-bottom:10px">🔐</div>
            <div class="auth-title">Admin Console</div>
            <div class="auth-sub">Authorized Personnel Only</div>
        </div>
        <div style="background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);border-radius:10px;padding:12px;margin-bottom:20px;color:var(--warning);font-size:12px">
            ⚠️ Authorized cyber crime officers only. Unauthorized access is a criminal offence.
        </div>
        <?php if ($error): ?>
            <div style="background:rgba(244,63,94,0.1);border:1px solid rgba(244,63,94,0.3);border-radius:10px;padding:12px;margin-bottom:20px;color:var(--danger);">❌ <?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group" style="margin-bottom:20px">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-input" placeholder="Enter username" required>
            </div>
            <div class="form-group" style="margin-bottom:25px">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-input" placeholder="Enter password" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;padding:14px">Access Console →</button>
        </form>
        <div style="text-align:center;margin-top:20px">
            <a href="login.php" style="color:var(--text3);text-decoration:none;font-size:12px">← Back to User Portal</a>
        </div>
    </div>
</div>
</body>
</html>
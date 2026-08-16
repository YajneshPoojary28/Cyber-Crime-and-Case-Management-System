<?php
require_once 'config/database.php';

if (isOfficerLoggedIn()) {
    header("Location: officer-dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = "Email and password are required";
    } else {
        $conn = getConnection();
        // Use 'email' instead of 'username' and check 'is_active'
        $stmt = $conn->prepare("SELECT * FROM investigation_officers WHERE email = ? AND is_active = 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                $_SESSION['officer_id'] = $row['id'];
                $_SESSION['officer_name'] = $row['full_name'];
                $_SESSION['officer_email'] = $row['email'];
                $_SESSION['officer_badge'] = $row['badge_number'];
                $_SESSION['officer_department'] = $row['department'];
                
                // Update last login (if column exists)
                $update = $conn->prepare("UPDATE investigation_officers SET updated_at = NOW() WHERE id = ?");
                $update->bind_param("i", $row['id']);
                $update->execute();
                $update->close();
                
                header("Location: officer-dashboard.php");
                exit();
            } else {
                $error = "Invalid password";
            }
        } else {
            $error = "Officer not found or account inactive";
        }
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Login - CyberShield</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            width: 100%;
            max-width: 440px;
            margin: 20px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            text-align: center;
            padding: 30px;
        }
        .card-header i {
            font-size: 48px;
            margin-bottom: 15px;
        }
        .card-header h3 {
            font-family: var(--display);
            font-size: 24px;
            margin-bottom: 5px;
        }
        .card-header p {
            font-size: 13px;
            opacity: 0.8;
            font-family: var(--mono);
        }
        .card-body {
            padding: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            font-size: 12px;
            font-family: var(--mono);
            color: var(--text3);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            display: block;
        }
        .input-group {
            display: flex;
            align-items: center;
            background: var(--bg4);
            border: 1px solid var(--border);
            border-radius: 10px;
            transition: all 0.15s;
        }
        .input-group:focus-within {
            border-color: var(--accent);
        }
        .input-group-text {
            background: transparent;
            border: none;
            color: var(--text3);
            padding: 0 0 0 14px;
            font-size: 14px;
        }
        .form-control {
            background: transparent;
            border: none;
            padding: 12px 14px;
            color: var(--text);
            font-size: 14px;
            width: 100%;
            outline: none;
            font-family: var(--sans);
        }
        .form-control::placeholder {
            color: var(--text3);
        }
        .btn-officer {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            width: 100%;
            color: white;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-officer:hover {
            transform: translateY(-1px);
            filter: brightness(1.05);
        }
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-danger {
            background: rgba(244, 63, 94, 0.15);
            border: 1px solid rgba(244, 63, 94, 0.3);
            color: var(--danger);
        }
        .text-center {
            text-align: center;
        }
        .mt-3 {
            margin-top: 16px;
        }
        .back-link {
            color: var(--text3);
            text-decoration: none;
            font-size: 13px;
            transition: color 0.15s;
        }
        .back-link:hover {
            color: var(--accent);
        }
        .officer-badge {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }
        .badge-info {
            font-size: 11px;
            font-family: var(--mono);
            color: var(--text3);
        }
        .credentials-info {
            background: rgba(108, 99, 255, 0.1);
            border-radius: 10px;
            padding: 12px;
            margin-top: 20px;
            text-align: center;
        }
        .credentials-info p {
            font-size: 12px;
            margin: 5px 0;
            color: var(--text2);
        }
        .credentials-info strong {
            color: var(--accent);
            font-family: var(--mono);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-user-shield"></i>
                <h3>Investigation Officer</h3>
                <p>Access your dashboard</p>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email" class="form-control" required placeholder="Enter your email">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" id="password" class="form-control" required placeholder="Enter your password">
                        </div>
                    </div>
                    <button type="submit" class="btn-officer">
                        <i class="fas fa-sign-in-alt"></i> Login as Officer
                    </button>
                </form>
            
                
                <div class="officer-badge">
                    <div class="badge-info">
                        <i class="fas fa-id-card"></i> Authorized Personnel Only
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <a href="index.php" class="back-link">
                        <i class="fas fa-arrow-left"></i> Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
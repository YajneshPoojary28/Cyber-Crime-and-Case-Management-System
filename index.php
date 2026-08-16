<?php
require_once 'config/database.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
} elseif (isAdminLoggedIn()) {
    header("Location: admin-dashboard.php");
    exit();
} elseif (isOfficerLoggedIn()) {
    header("Location: officer-dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CyberShield - Cyber Crime Reporting</title>
<link rel="stylesheet" href="css/style.css">
<style>
    /* Additional styles matching your dark theme */
    .auth-wrap {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background: var(--bg);
    }
    .auth-card {
        width: 100%;
        max-width: 440px;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 20px;
        padding: 40px;
        backdrop-filter: blur(10px);
    }
    .auth-logo {
        text-align: center;
        margin-bottom: 32px;
    }
    .auth-logo div:first-child {
        font-size: 64px !important;
        margin-bottom: 16px !important;
    }
    .auth-title {
        font-family: var(--display);
        font-size: 28px;
        font-weight: 800;
        color: var(--text);
        margin-bottom: 8px;
    }
    .auth-sub {
        font-size: 14px;
        color: var(--text3);
        font-family: var(--sans);
    }
    .btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 20px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        border: none;
        transition: all 0.15s;
        font-family: var(--sans);
        text-decoration: none;
        width: 100%;
    }
    .btn-primary {
        background: var(--accent);
        color: white;
    }
    .btn-primary:hover {
        background: #5a52e8;
        transform: translateY(-1px);
    }
    .btn-ghost {
        background: transparent;
        border: 1px solid var(--border2);
        color: var(--text2);
    }
    .btn-ghost:hover {
        background: var(--bg4);
        color: var(--text);
    }
    .btn-outline {
        background: transparent;
        border: 1px solid var(--accent);
        color: var(--accent);
    }
    .btn-outline:hover {
        background: var(--accent);
        color: white;
    }
    hr {
        margin: 20px 0;
        border: none;
        border-top: 1px solid var(--border);
    }
    .officer-section {
        background: rgba(108, 99, 255, 0.1);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 16px;
        margin-top: 8px;
    }
    .officer-section .btn-ghost {
        background: rgba(108, 99, 255, 0.15);
        border-color: rgba(108, 99, 255, 0.3);
    }
    .text-muted {
        color: var(--text3);
        font-size: 11px;
        font-family: var(--mono);
        text-align: center;
        display: block;
        margin-top: 12px;
    }
</style>
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-logo">
            <div>🛡️</div>
            <div class="auth-title">CyberShield</div>
            <div class="auth-sub">Cyber Crime Reporting Portal</div>
        </div>
        <div style="display:flex;flex-direction:column;gap:12px">
            <a href="login.php" class="btn btn-primary">
                <i class="fas fa-user"></i> Citizen Login →
            </a>
            <a href="register.php" class="btn btn-ghost">
                <i class="fas fa-user-plus"></i> Create Account →
            </a>
            <hr>
            <a href="admin-login.php" class="btn btn-outline">
                <i class="fas fa-shield-alt"></i> 🔐 Admin Login
            </a>
            <div class="officer-section">
                <a href="officer-login.php" class="btn btn-ghost">
                    <i class="fas fa-user-shield"></i> 👮 Investigation Officer Login
                </a>
                <small class="text-muted">For law enforcement personnel only</small>
            </div>
        </div>
    </div>
</div>
</body>
</html>
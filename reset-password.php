<?php
require_once 'config/database.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($email)) {
        $error = "Please enter your email address!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address!";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long!";
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $error = "Password must contain at least one uppercase letter!";
    } elseif (!preg_match('/[a-z]/', $new_password)) {
        $error = "Password must contain at least one lowercase letter!";
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $error = "Password must contain at least one number!";
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new_password)) {
        $error = "Password must contain at least one special character!";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        $conn = getConnection();
        
        // Check if email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $update = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            $update->bind_param("ss", $hashed_password, $email);
            
            if ($update->execute()) {
                $success = "Password reset successfully! You can now login with your new password.";
            } else {
                $error = "Failed to reset password. Please try again.";
            }
            $update->close();
        } else {
            $error = "Email address not found in our records!";
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
<title>Reset Password - CyberShield</title>
<link rel="stylesheet" href="css/style.css">
<style>
.password-wrapper {
    position: relative;
}

.password-strength-container {
    margin-top: 10px;
}

.strength-meter {
    height: 6px;
    background: var(--bg4);
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 8px;
}

.strength-bar {
    height: 100%;
    width: 0%;
    transition: width 0.3s ease, background 0.3s ease;
    border-radius: 3px;
}

.strength-text {
    font-size: 11px;
    font-family: var(--mono);
    margin-bottom: 10px;
}

/* Strength levels */
.strength-very-weak { background: #f43f5e; width: 20%; }
.strength-weak { background: #fb923c; width: 40%; }
.strength-fair { background: #f59e0b; width: 60%; }
.strength-good { background: #3b82f6; width: 80%; }
.strength-strong { background: #10b981; width: 100%; }

.text-very-weak { color: #f43f5e; }
.text-weak { color: #fb923c; }
.text-fair { color: #f59e0b; }
.text-good { color: #3b82f6; }
.text-strong { color: #10b981; }

/* Password hint */
.password-hint {
    font-size: 11px;
    color: var(--text3);
    margin-top: 5px;
}

.password-hint ul {
    margin: 5px 0 0 20px;
    padding: 0;
}

.password-hint li {
    margin-bottom: 3px;
}

.password-hint li.valid {
    color: var(--success);
    text-decoration: line-through;
    opacity: 0.7;
}

.match-message {
    font-size: 11px;
    margin-top: 5px;
}

.match-success {
    color: var(--success);
}

.match-error {
    color: var(--danger);
}

.submit-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card" style="max-width: 480px;">
        <div class="auth-logo">
            <div style="font-size:48px;margin-bottom:10px">🔑</div>
            <div class="auth-title">Reset Password</div>
            <div class="auth-sub">Enter your email and new password</div>
        </div>
        
        <?php if ($error): ?>
            <div style="background:rgba(244,63,94,0.1);border:1px solid rgba(244,63,94,0.3);border-radius:10px;padding:12px;margin-bottom:20px;color:var(--danger);">❌ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);border-radius:10px;padding:12px;margin-bottom:20px;color:var(--success);">✅ <?php echo $success; ?>
                <div style="margin-top: 15px;"><a href="login.php" class="btn btn-primary" style="display:inline-block;padding:10px 20px;text-decoration:none;">Go to Login →</a></div>
            </div>
        <?php else: ?>
            <form method="POST" id="resetForm">
                <div class="form-group" style="margin-bottom:20px">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" id="email" class="form-input" placeholder="your@email.com" required>
                </div>
                
                <div class="form-group" style="margin-bottom:15px">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" id="new_password" class="form-input" placeholder="Enter new password" required onkeyup="checkPasswordStrength()">
                    
                    <!-- Password hint -->
                    <div class="password-hint" id="passwordHint">
                        Password must be at least 8 characters long, contain uppercase, lowercase, number and special character.
                    </div>
                    
                    <!-- Password Strength Meter -->
                    <div class="password-strength-container" id="strengthContainer" style="display: none;">
                        <div class="strength-meter">
                            <div class="strength-bar" id="strengthBar"></div>
                        </div>
                        <div class="strength-text" id="strengthText"></div>
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom:25px">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-input" placeholder="Re-enter new password" required onkeyup="checkPasswordMatch()">
                    <div class="match-message" id="matchMessage"></div>
                </div>
                
                <button type="submit" id="submitBtn" class="btn btn-primary submit-btn" style="width:100%;padding:14px" disabled>Reset Password →</button>
            </form>
            <div style="text-align:center;margin-top:20px">
                <a href="login.php" style="color:var(--accent2);text-decoration:none">← Back to Login</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function checkPasswordStrength() {
    const password = document.getElementById('new_password').value;
    const strengthContainer = document.getElementById('strengthContainer');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    const passwordHint = document.getElementById('passwordHint');
    
    if (password.length === 0) {
        strengthContainer.style.display = 'none';
        passwordHint.style.display = 'block';
        return false;
    }
    
    strengthContainer.style.display = 'block';
    passwordHint.style.display = 'none';
    
    // Check criteria
    const hasLength = password.length >= 8;
    const hasUpper = /[A-Z]/.test(password);
    const hasLower = /[a-z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
    
    // Calculate strength score (0-5)
    let score = 0;
    if (hasLength) score++;
    if (hasUpper) score++;
    if (hasLower) score++;
    if (hasNumber) score++;
    if (hasSpecial) score++;
    
    // Update strength meter and text
    strengthBar.className = 'strength-bar';
    
    if (score <= 1) {
        strengthBar.classList.add('strength-very-weak');
        strengthText.innerHTML = 'Very Weak Password';
        strengthText.className = 'strength-text text-very-weak';
    } else if (score <= 2) {
        strengthBar.classList.add('strength-weak');
        strengthText.innerHTML = 'Weak Password';
        strengthText.className = 'strength-text text-weak';
    } else if (score <= 3) {
        strengthBar.classList.add('strength-fair');
        strengthText.innerHTML = 'Fair Password';
        strengthText.className = 'strength-text text-fair';
    } else if (score <= 4) {
        strengthBar.classList.add('strength-good');
        strengthText.innerHTML = 'Good Password';
        strengthText.className = 'strength-text text-good';
    } else {
        strengthBar.classList.add('strength-strong');
        strengthText.innerHTML = 'Strong Password';
        strengthText.className = 'strength-text text-strong';
    }
    
    // Update hint with checkmarks
    updateHint(hasLength, 'Length: at least 8 characters');
    updateHint(hasUpper, 'Uppercase letter (A-Z)');
    updateHint(hasLower, 'Lowercase letter (a-z)');
    updateHint(hasNumber, 'Number (0-9)');
    updateHint(hasSpecial, 'Special character (!@#$%^&*)');
    
    // Check if all criteria are met
    const isValid = hasLength && hasUpper && hasLower && hasNumber && hasSpecial;
    
    // Check password match
    checkPasswordMatch();
    
    return isValid;
}

function updateHint(isValid, text) {
    const hint = document.getElementById('passwordHint');
    if (!hint) return;
    
    // Find or create hint item
    let hintItem = Array.from(hint.querySelectorAll('li')).find(li => li.textContent.includes(text.split(':')[0]));
    
    if (!hintItem && !isValid) {
        // Add new hint item if not exists
        if (hint.innerHTML.includes('Password must be')) {
            hint.innerHTML = '';
            hint.style.padding = '0';
        }
        hintItem = document.createElement('li');
        hintItem.textContent = text;
        hint.appendChild(hintItem);
    }
    
    if (hintItem) {
        if (isValid) {
            hintItem.classList.add('valid');
            hintItem.innerHTML = `✓ ${text}`;
        } else {
            hintItem.classList.remove('valid');
            hintItem.innerHTML = `○ ${text}`;
        }
    }
}

function checkPasswordMatch() {
    const password = document.getElementById('new_password').value;
    const confirm = document.getElementById('confirm_password').value;
    const matchMessage = document.getElementById('matchMessage');
    const submitBtn = document.getElementById('submitBtn');
    const email = document.getElementById('email').value;
    
    // Check if password meets all criteria
    const hasLength = password.length >= 8;
    const hasUpper = /[A-Z]/.test(password);
    const hasLower = /[a-z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
    const isPasswordValid = hasLength && hasUpper && hasLower && hasNumber && hasSpecial;
    
    // Check email
    const isEmailValid = email.length > 0 && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    
    if (confirm.length > 0) {
        if (password === confirm) {
            matchMessage.innerHTML = '✓ Passwords match';
            matchMessage.className = 'match-message match-success';
            if (isPasswordValid && isEmailValid) {
                submitBtn.disabled = false;
            } else {
                submitBtn.disabled = true;
            }
        } else {
            matchMessage.innerHTML = '✗ Passwords do not match';
            matchMessage.className = 'match-message match-error';
            submitBtn.disabled = true;
        }
    } else {
        matchMessage.innerHTML = '';
        submitBtn.disabled = true;
    }
}

// Real-time validation
document.getElementById('new_password').addEventListener('input', function() {
    checkPasswordStrength();
});

document.getElementById('confirm_password').addEventListener('input', function() {
    checkPasswordMatch();
});

document.getElementById('email').addEventListener('input', function() {
    const email = this.value;
    const isEmailValid = email.length > 0 && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    const password = document.getElementById('new_password').value;
    const confirm = document.getElementById('confirm_password').value;
    const isPasswordValid = checkPasswordStrength();
    
    if (isEmailValid && isPasswordValid && password === confirm && password.length > 0) {
        document.getElementById('submitBtn').disabled = false;
    } else {
        document.getElementById('submitBtn').disabled = true;
    }
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn) submitBtn.disabled = true;
});
</script>

</body>
</html>
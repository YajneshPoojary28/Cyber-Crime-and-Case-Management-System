<?php
require_once 'config/database.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $terms = isset($_POST['terms']) ? true : false;
    
    // Validation
    if (!$name || !$email || !$password || !$phone) {
        $error = "Please fill all required fields!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format!";
    } elseif (!preg_match('/^[6-9][0-9]{9}$/', $phone)) {
        $error = "Invalid phone number! Must be 10 digits starting with 6-9.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long!";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = "Password must contain at least one uppercase letter!";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = "Password must contain at least one lowercase letter!";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = "Password must contain at least one number!";
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $error = "Password must contain at least one special character!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (!$terms) {
        $error = "You must accept the Terms & Conditions and Privacy Policy!";
    } else {
        $conn = getConnection();
        
        // Check if email already exists
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            $error = "Email already registered!";
        } else {
            // Check if phone already exists
            $checkPhone = $conn->prepare("SELECT id FROM users WHERE phone = ?");
            $checkPhone->bind_param("s", $phone);
            $checkPhone->execute();
            if ($checkPhone->get_result()->num_rows > 0) {
                $error = "Phone number already registered!";
            } else {
                // Create user account
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $name, $email, $phone, $hashed_password);
                
                if ($stmt->execute()) {
                    $success = "Account created successfully! Please login.";
                } else {
                    $error = "Registration failed. Please try again.";
                }
                $stmt->close();
            }
            $checkPhone->close();
        }
        $checkStmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register - CyberShield</title>
<link rel="stylesheet" href="css/style.css">
<style>
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.9);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}
.modal-content {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 20px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    animation: modalSlideIn 0.3s ease;
}
@keyframes modalSlideIn {
    from { transform: translateY(-50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    position: sticky;
    top: 0;
    background: var(--card);
    z-index: 1;
}
.modal-header h3 { font-family: var(--display); font-size: 20px; color: var(--text); }
.modal-close { background: none; border: none; font-size: 28px; cursor: pointer; color: var(--text3); }
.modal-close:hover { color: var(--danger); }
.modal-body { padding: 24px; }
.modal-body h4 { color: var(--accent2); margin-top: 20px; margin-bottom: 10px; font-family: var(--display); }
.modal-body p { color: var(--text2); font-size: 13px; line-height: 1.6; margin-bottom: 15px; }
.modal-body ul { color: var(--text2); font-size: 13px; line-height: 1.6; margin-left: 20px; margin-bottom: 15px; }
.modal-body li { margin-bottom: 8px; }
.modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; position: sticky; bottom: 0; background: var(--card); }
.terms-link { color: var(--accent2); cursor: pointer; text-decoration: underline; }
.terms-link:hover { color: var(--accent3); }
.checkbox-wrapper {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-top: 20px;
    padding: 12px;
    background: var(--bg4);
    border-radius: 10px;
}
.checkbox-wrapper input[type="checkbox"] { width: 18px; height: 18px; margin-top: 2px; cursor: pointer; accent-color: var(--accent); }
.checkbox-wrapper label { flex: 1; font-size: 13px; color: var(--text2); line-height: 1.5; cursor: pointer; }
.checkbox-wrapper label a { color: var(--accent2); text-decoration: none; }
.checkbox-wrapper label a:hover { text-decoration: underline; }

/* Password Strength Meter */
.password-strength-container { margin-top: 8px; }
.strength-meter { height: 4px; background: var(--bg4); border-radius: 4px; overflow: hidden; margin-top: 5px; }
.strength-bar { height: 100%; width: 0%; transition: width 0.3s ease, background 0.3s ease; border-radius: 4px; }
.strength-text { font-size: 10px; margin-top: 5px; font-family: var(--mono); }
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

/* Validation styles */
.validation-message { font-size: 11px; margin-top: 4px; }
.validation-error { color: var(--danger); }
.validation-success { color: var(--success); }
.help-tip { font-size: 11px; color: var(--text3); margin-top: 4px; }
</style>
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card" style="max-width:520px">
        <div class="auth-logo">
            <div style="font-size:40px;margin-bottom:10px">📝</div>
            <div class="auth-title">Create Account</div>
            <div class="auth-sub">Join CyberShield today</div>
        </div>
        
        <?php if ($error): ?>
            <div style="background:rgba(244,63,94,0.1);border:1px solid rgba(244,63,94,0.3);border-radius:10px;padding:12px;margin-bottom:20px;color:var(--danger);">❌ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);border-radius:10px;padding:12px;margin-bottom:20px;color:var(--success);">✅ <?php echo $success; ?>
                <div style="margin-top: 15px;"><a href="login.php" class="btn btn-primary" style="display:inline-block;padding:10px 20px;text-decoration:none;">Go to Login →</a></div>
            </div>
        <?php else: ?>
            <!-- Registration Form -->
            <form method="POST" id="registerForm">
                <div class="form-group" style="margin-bottom:15px">
                    <label class="form-label">Full Name <span class="req">*</span></label>
                    <input type="text" name="name" id="name" class="form-input" placeholder="Enter your full name" required>
                </div>
                
                <div class="form-group" style="margin-bottom:15px">
                    <label class="form-label">Email Address <span class="req">*</span></label>
                    <input type="email" name="email" id="email" class="form-input" placeholder="you@example.com" required>
                    <div id="emailError" class="validation-message"></div>
                </div>
                
                <div class="form-group" style="margin-bottom:15px">
                    <label class="form-label">Phone Number <span class="req">*</span></label>
                    <input type="tel" name="phone" id="phone" class="form-input" placeholder="10-digit mobile number" required>
                    <div id="phoneError" class="validation-message"></div>
                    <div class="help-tip">Enter 10-digit mobile number starting with 6,7,8,9</div>
                </div>
                
                <div class="form-group" style="margin-bottom:15px">
                    <label class="form-label">Password <span class="req">*</span></label>
                    <input type="password" name="password" id="password" class="form-input" placeholder="Min 8 characters" required>
                    
                    <div class="password-strength-container" id="strengthContainer" style="display: none;">
                        <div class="strength-meter">
                            <div class="strength-bar" id="strengthBar"></div>
                        </div>
                        <div class="strength-text" id="strengthText"></div>
                    </div>
                    
                    <div id="passwordError" class="validation-message"></div>
                    <div class="help-tip">Password must contain: 8+ chars, uppercase, lowercase, number, special character</div>
                </div>
                
                <div class="form-group" style="margin-bottom:20px">
                    <label class="form-label">Confirm Password <span class="req">*</span></label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-input" placeholder="Re-enter password" required>
                    <div id="confirmError" class="validation-message"></div>
                </div>
                
                <div class="checkbox-wrapper">
                    <input type="checkbox" name="terms" id="terms" required>
                    <label for="terms">
                        I agree to the <a href="javascript:void(0)" onclick="showTermsModal()" class="terms-link">Terms & Conditions</a> 
                        and <a href="javascript:void(0)" onclick="showPrivacyModal()" class="terms-link">Privacy Policy</a>
                    </label>
                </div>
                
                <button type="submit" name="register" id="submitBtn" class="btn btn-primary" style="width:100%;padding:14px;margin-top:20px" disabled>Register →</button>
            </form>
            
            <div style="text-align:center;margin-top:20px">
                <a href="login.php" style="color:var(--accent2);text-decoration:none">Already have an account? Sign in</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Terms & Conditions Modal -->
<div id="termsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>📜 Terms & Conditions</h3>
            <button class="modal-close" onclick="closeTermsModal()">&times;</button>
        </div>
        <div class="modal-body">
            <h4>1. Acceptance of Terms</h4>
            <p>By registering and using CyberShield, you agree to comply with these Terms & Conditions...</p>
            <h4>2. Accurate Information</h4>
            <p>You agree to provide accurate, current, and complete information...</p>
            <h4>3. Privacy & Data Protection</h4>
            <p>Your personal information will be handled according to our Privacy Policy...</p>
            <h4>4. Complaint Filing</h4>
            <p>All complaints filed must be genuine. False complaints may lead to legal action...</p>
            <h4>5. Account Security</h4>
            <p>You are responsible for maintaining the confidentiality of your account credentials...</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="closeTermsModal()">I Understand</button>
        </div>
    </div>
</div>

<!-- Privacy Policy Modal -->
<div id="privacyModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>🔒 Privacy Policy</h3>
            <button class="modal-close" onclick="closePrivacyModal()">&times;</button>
        </div>
        <div class="modal-body">
            <h4>1. Information We Collect</h4>
            <p>We collect personal information including name, email, phone...</p>
            <h4>2. How We Use Your Information</h4>
            <p>Process complaints, verify identity, communicate updates...</p>
            <h4>3. Data Protection</h4>
            <p>Industry-standard security measures including password hashing...</p>
            <h4>4. Data Sharing</h4>
            <p>We do not sell your personal information. Data shared only with law enforcement...</p>
            <h4>5. Your Rights</h4>
            <p>You can request data deletion or correction by contacting support...</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="closePrivacyModal()">I Understand</button>
        </div>
    </div>
</div>

<script>
// Validation functions
function validateEmail() {
    const email = document.getElementById('email').value;
    const emailError = document.getElementById('emailError');
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (email && !emailRegex.test(email)) {
        emailError.innerHTML = '✗ Invalid email format';
        emailError.className = 'validation-message validation-error';
        return false;
    } else if (email) {
        emailError.innerHTML = '✓ Valid email';
        emailError.className = 'validation-message validation-success';
        return true;
    }
    emailError.innerHTML = '';
    return false;
}

function validatePhone() {
    const phone = document.getElementById('phone').value;
    const phoneError = document.getElementById('phoneError');
    const phoneRegex = /^[6-9][0-9]{9}$/;
    
    if (phone && !phoneRegex.test(phone)) {
        phoneError.innerHTML = '✗ Invalid phone number (must be 10 digits starting with 6-9)';
        phoneError.className = 'validation-message validation-error';
        return false;
    } else if (phone) {
        phoneError.innerHTML = '✓ Valid phone number';
        phoneError.className = 'validation-message validation-success';
        return true;
    }
    phoneError.innerHTML = '';
    return false;
}

function checkPasswordStrength() {
    const password = document.getElementById('password').value;
    const strengthContainer = document.getElementById('strengthContainer');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    const passwordError = document.getElementById('passwordError');
    
    if (password.length === 0) {
        strengthContainer.style.display = 'none';
        passwordError.innerHTML = '';
        return false;
    }
    
    strengthContainer.style.display = 'block';
    
    const hasLength = password.length >= 8;
    const hasUpper = /[A-Z]/.test(password);
    const hasLower = /[a-z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
    
    let score = 0;
    if (hasLength) score++;
    if (hasUpper) score++;
    if (hasLower) score++;
    if (hasNumber) score++;
    if (hasSpecial) score++;
    
    strengthBar.className = 'strength-bar';
    
    if (score <= 1) {
        strengthBar.classList.add('strength-very-weak');
        strengthText.innerHTML = 'Very Weak Password';
        strengthText.className = 'strength-text text-very-weak';
        passwordError.innerHTML = '✗ Password is too weak';
        passwordError.className = 'validation-message validation-error';
    } else if (score <= 2) {
        strengthBar.classList.add('strength-weak');
        strengthText.innerHTML = 'Weak Password';
        strengthText.className = 'strength-text text-weak';
        passwordError.innerHTML = '✗ Password is weak';
        passwordError.className = 'validation-message validation-error';
    } else if (score <= 3) {
        strengthBar.classList.add('strength-fair');
        strengthText.innerHTML = 'Fair Password';
        strengthText.className = 'strength-text text-fair';
        passwordError.innerHTML = '⚠ Password is fair';
        passwordError.className = 'validation-message text-fair';
    } else if (score <= 4) {
        strengthBar.classList.add('strength-good');
        strengthText.innerHTML = 'Good Password';
        strengthText.className = 'strength-text text-good';
        passwordError.innerHTML = '✓ Password is good';
        passwordError.className = 'validation-message validation-success';
    } else {
        strengthBar.classList.add('strength-strong');
        strengthText.innerHTML = 'Strong Password';
        strengthText.className = 'strength-text text-strong';
        passwordError.innerHTML = '✓ Password is strong';
        passwordError.className = 'validation-message validation-success';
    }
    
    const isValid = hasLength && hasUpper && hasLower && hasNumber && hasSpecial;
    return isValid;
}

function validateConfirmPassword() {
    const password = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;
    const confirmError = document.getElementById('confirmError');
    
    if (confirm && password !== confirm) {
        confirmError.innerHTML = '✗ Passwords do not match';
        confirmError.className = 'validation-message validation-error';
        return false;
    } else if (confirm && password === confirm) {
        confirmError.innerHTML = '✓ Passwords match';
        confirmError.className = 'validation-message validation-success';
        return true;
    }
    confirmError.innerHTML = '';
    return false;
}

function validateForm() {
    const name = document.getElementById('name').value;
    const emailValid = validateEmail();
    const phoneValid = validatePhone();
    const passwordValid = checkPasswordStrength();
    const confirmValid = validateConfirmPassword();
    const terms = document.getElementById('terms').checked;
    const submitBtn = document.getElementById('submitBtn');
    
    if (name && emailValid && phoneValid && passwordValid && confirmValid && terms) {
        submitBtn.disabled = false;
    } else {
        submitBtn.disabled = true;
    }
}

// Event listeners
document.getElementById('email').addEventListener('input', function() { validateEmail(); validateForm(); });
document.getElementById('phone').addEventListener('input', function() { validatePhone(); validateForm(); });
document.getElementById('password').addEventListener('input', function() { checkPasswordStrength(); validateConfirmPassword(); validateForm(); });
document.getElementById('confirm_password').addEventListener('input', function() { validateConfirmPassword(); validateForm(); });
document.getElementById('name').addEventListener('input', function() { validateForm(); });
document.getElementById('terms').addEventListener('change', function() { validateForm(); });

// Modal functions
function showTermsModal() { document.getElementById('termsModal').style.display = 'flex'; }
function closeTermsModal() { document.getElementById('termsModal').style.display = 'none'; }
function showPrivacyModal() { document.getElementById('privacyModal').style.display = 'flex'; }
function closePrivacyModal() { document.getElementById('privacyModal').style.display = 'none'; }

// Close modals when clicking outside
window.addEventListener('click', function(e) {
    if (e.target === document.getElementById('termsModal')) closeTermsModal();
    if (e.target === document.getElementById('privacyModal')) closePrivacyModal();
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn) submitBtn.disabled = true;
});
</script>

</body>
</html>
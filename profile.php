<?php
require_once 'config/database.php';
redirectIfNotLoggedIn();

$pageTitle = 'My Profile';
$conn = getConnection();
$userId = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$complaintCount = $conn->prepare("SELECT COUNT(*) as count FROM complaints WHERE user_id = ?");
$complaintCount->bind_param("i", $userId);
$complaintCount->execute();
$complaintCount = $complaintCount->get_result()->fetch_assoc()['count'];

$resolvedCount = $conn->prepare("SELECT COUNT(*) as count FROM complaints WHERE user_id = ? AND status = 'resolved'");
$resolvedCount->bind_param("i", $userId);
$resolvedCount->execute();
$resolvedCount = $resolvedCount->get_result()->fetch_assoc()['count'];

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    
    if ($name) {
        if ($new_password) {
            if (strlen($new_password) < 8) {
                $error = "Password must be at least 8 characters!";
            } else {
                $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
                $updateStmt = $conn->prepare("UPDATE users SET name = ?, phone = ?, password = ? WHERE id = ?");
                $updateStmt->bind_param("sssi", $name, $phone, $hashedPassword, $userId);
                if ($updateStmt->execute()) {
                    $_SESSION['user_name'] = $name;
                    $message = "Profile updated successfully!";
                    $user['name'] = $name;
                    $user['phone'] = $phone;
                } else {
                    $error = "Failed to update profile.";
                }
                $updateStmt->close();
            }
        } else {
            $updateStmt = $conn->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
            $updateStmt->bind_param("ssi", $name, $phone, $userId);
            if ($updateStmt->execute()) {
                $_SESSION['user_name'] = $name;
                $message = "Profile updated successfully!";
                $user['name'] = $name;
                $user['phone'] = $phone;
            } else {
                $error = "Failed to update profile.";
            }
            $updateStmt->close();
        }
    }
}
$conn->close();
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div style="display: grid; grid-template-columns: 300px 1fr; gap: 24px;">
    <div class="card" style="padding: 24px; text-align: center;">
        <div class="avatar" style="width: 80px; height: 80px; font-size: 32px; border-radius: 50%; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--accent), var(--accent3));"><?php echo strtoupper(substr($user['name'], 0, 2)); ?></div>
        <div style="font-size: 18px; font-weight: 700;"><?php echo htmlspecialchars($user['name']); ?></div>
        <div style="display: inline-block; background: rgba(108,99,255,0.15); padding: 4px 12px; border-radius: 20px; font-size: 11px; margin-top: 8px;">🛡️ Verified Citizen</div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 20px 0; padding: 16px 0; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);"><div><div style="font-size: 24px; font-weight: 800;"><?php echo $complaintCount; ?></div><div style="font-size: 10px; color: var(--text3);">Total</div></div><div><div style="font-size: 24px; font-weight: 800;"><?php echo $resolvedCount; ?></div><div style="font-size: 10px; color: var(--text3);">Resolved</div></div></div>
        <div style="text-align: left; background: var(--bg4); border-radius: 10px; padding: 12px; margin-bottom: 16px;"><div style="display: flex; justify-content: space-between; padding: 8px 0;"><span style="font-size: 11px; color: var(--text3);">📧 Email</span><span style="font-size: 12px;"><?php echo htmlspecialchars($user['email']); ?></span></div><div style="display: flex; justify-content: space-between; padding: 8px 0;"><span style="font-size: 11px; color: var(--text3);">📱 Phone</span><span style="font-size: 12px;"><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></span></div><div style="display: flex; justify-content: space-between; padding: 8px 0;"><span style="font-size: 11px; color: var(--text3);">📅 Member Since</span><span style="font-size: 12px;"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span></div></div>
        <button onclick="location.href='logout.php'" class="btn btn-danger" style="width: 100%;">🚪 Logout</button>
    </div>
    
    <div class="card" style="padding: 32px;">
        <div class="section-title" style="margin-bottom: 8px;">Edit Profile Information</div>
        <div class="section-subtitle" style="margin-bottom: 24px;">Update your personal details</div>
        <?php if ($message): ?><div style="background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.3); border-radius:10px; padding:12px; margin-bottom:20px; color:var(--success);">✅ <?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div style="background:rgba(244,63,94,0.1); border:1px solid rgba(244,63,94,0.3); border-radius:10px; padding:12px; margin-bottom:20px; color:var(--danger);">❌ <?php echo $error; ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group" style="margin-bottom: 20px;"><label class="form-label">Full Name</label><input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($user['name']); ?>" required></div>
            <div class="form-group" style="margin-bottom: 20px;"><label class="form-label">Email Address</label><input type="email" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" disabled style="opacity: 0.6;"><div class="help-tip">Email cannot be changed</div></div>
            <div class="form-group" style="margin-bottom: 20px;"><label class="form-label">Phone Number</label><input type="tel" name="phone" class="form-input" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"></div>
            <div class="form-group" style="margin-bottom: 24px;"><label class="form-label">Change Password (Leave blank to keep current)</label><input type="password" name="new_password" class="form-input" placeholder="New password (min 8 characters)"></div>
            <div style="display: flex; gap: 12px;"><button type="submit" class="btn btn-primary">💾 Save Changes</button><button type="button" class="btn btn-ghost" onclick="location.href='dashboard.php'">Cancel</button></div>
        </form>
        <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--border);"><div style="font-size: 12px; color: var(--text3);">Need help? Contact support: <span style="color: var(--accent2);">support@cybershield.gov.in</span></div><div style="font-size: 12px; color: var(--text3); margin-top: 8px;">Emergency Helpline: <span style="color: var(--warning); font-weight: 600;">1930</span></div></div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
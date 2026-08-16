<?php
require_once 'config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit();
}

$pageTitle = 'Admin Notifications';
$conn = getConnection();

// Mark notification as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notifId = $_GET['mark_read'];
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $stmt->bind_param("i", $notifId);
    $stmt->execute();
    $stmt->close();
    header("Location: admin-notifications.php");
    exit();
}

// Mark all as read
if (isset($_POST['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = 1");
    $stmt->execute();
    $stmt->close();
    header("Location: admin-notifications.php");
    exit();
}

// Delete notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notifId = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
    $stmt->bind_param("i", $notifId);
    $stmt->execute();
    $stmt->close();
    header("Location: admin-notifications.php");
    exit();
}

// First, check what columns exist in notifications table
$columns_result = $conn->query("SHOW COLUMNS FROM notifications");
$columns = [];
while ($col = $columns_result->fetch_assoc()) {
    $columns[] = $col['Field'];
}

// Get all admin notifications (user_id = 1 is admin)
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = 1 ORDER BY created_at DESC");
$stmt->execute();
$notifications = $stmt->get_result();
$stmt->close();

// Get unread count
$unreadQuery = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = 1 AND is_read = 0");
$unreadQuery->execute();
$unreadResult = $unreadQuery->get_result();
$unreadCount = $unreadResult->fetch_assoc()['unread'];
$unreadQuery->close();

$conn->close();

// Check if complaint_id column exists
$hasComplaintId = in_array('complaint_id', $columns);
$hasComplaintNum = in_array('complaint_num', $columns);
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="notification-page" style="max-width: 800px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <div class="section-title">Admin Notifications</div>
            <div class="section-subtitle">
                Track officer actions and complaint resolutions
                <?php if ($unreadCount > 0): ?>
                    <span class="badge" style="background: var(--danger); margin-left: 10px;"><?php echo $unreadCount; ?> unread</span>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($unreadCount > 0): ?>
            <form method="POST">
                <button type="submit" name="mark_all_read" class="btn btn-ghost btn-sm">✓ Mark all as read</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($notifications->num_rows == 0): ?>
        <div class="card" style="text-align: center; padding: 60px 20px;">
            <div style="font-size: 64px; margin-bottom: 16px;">🔔</div>
            <div class="section-title" style="margin-bottom: 8px;">No notifications yet</div>
            <div class="section-subtitle">When officers resolve complaints, you'll see them here</div>
        </div>
    <?php else: ?>
        <?php while ($notif = $notifications->fetch_assoc()): ?>
            <div class="card" style="margin-bottom: 12px; <?php echo !$notif['is_read'] ? 'border-left: 3px solid var(--accent);' : ''; ?>">
                <div style="padding: 20px; display: flex; gap: 16px;">
                    <div style="font-size: 32px;">
                        <?php 
                        if (strpos($notif['title'] ?? '', 'Resolved') !== false) echo '✅';
                        elseif (strpos($notif['title'] ?? '', 'Status') !== false) echo '📋';
                        elseif (strpos($notif['title'] ?? '', 'Alert') !== false) echo '⚠️';
                        else echo '🔔';
                        ?>
                    </div>
                    <div style="flex: 1;">
                        <div class="notification-title" style="font-weight: <?php echo !$notif['is_read'] ? '600' : '400'; ?>">
                            <?php echo htmlspecialchars($notif['title'] ?? 'Notification'); ?>
                        </div>
                        <div class="notification-message" style="color: var(--text2); margin: 8px 0;">
                            <?php echo htmlspecialchars($notif['message'] ?? ''); ?>
                        </div>
                        <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap; margin-top: 8px;">
                            <span style="font-size: 11px; color: var(--text3);">
                                📅 <?php echo date('F d, Y \a\t h:i A', strtotime($notif['created_at'])); ?>
                            </span>
                            <?php if ($hasComplaintId && !empty($notif['complaint_id'])): ?>
                                <a href="complaint-detail.php?id=<?php echo $notif['complaint_id']; ?>" class="cid" style="text-decoration: none;">
                                    🆔 View Complaint #<?php echo $notif['complaint_id']; ?>
                                </a>
                            <?php elseif ($hasComplaintNum && !empty($notif['complaint_num'])): ?>
                                <span class="cid">🆔 <?php echo htmlspecialchars($notif['complaint_num']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div style="display: flex; gap: 8px; margin-top: 12px;">
                            <?php if (!$notif['is_read']): ?>
                                <a href="?mark_read=<?php echo $notif['id']; ?>" class="btn btn-ghost btn-sm">✓ Mark as read</a>
                            <?php endif; ?>
                            <a href="?delete=<?php echo $notif['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this notification?')">🗑 Delete</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
<?php
require_once 'config/database.php';
requireLogin();

$pageTitle = 'Notifications';
$conn = getConnection();
$userId = $_SESSION['user_id'];

// Mark single notification as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notifId = $_GET['mark_read'];
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notifId, $userId);
    $stmt->execute();
    $stmt->close();
    header("Location: notifications.php");
    exit();
}

// Mark all as read
if (isset($_POST['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();
    header("Location: notifications.php");
    exit();
}

// Delete notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notifId = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notifId, $userId);
    $stmt->execute();
    $stmt->close();
    header("Location: notifications.php");
    exit();
}

// Get all notifications for the user
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$notifications = $stmt->get_result();
$stmt->close();

// Get unread count
$unreadQuery = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
$unreadQuery->bind_param("i", $userId);
$unreadQuery->execute();
$unreadResult = $unreadQuery->get_result();
$unreadCount = $unreadResult->fetch_assoc()['unread'];
$unreadQuery->close();

$conn->close();
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="notification-page" style="max-width: 800px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <div class="section-title">All Notifications</div>
            <div class="section-subtitle">
                Stay updated with your complaint status
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
            <div class="section-subtitle">You'll receive notifications about your complaint status here</div>
            <button class="btn btn-primary btn-sm" onclick="location.href='file-complaint.php'" style="margin-top: 20px;">📝 File a Complaint</button>
        </div>
    <?php else: ?>
        <?php while ($notif = $notifications->fetch_assoc()): ?>
            <div class="card" style="margin-bottom: 12px; <?php echo !$notif['is_read'] ? 'border-left: 3px solid var(--accent);' : ''; ?>">
                <div style="padding: 20px; display: flex; gap: 16px;">
                    <div style="font-size: 32px;">
                        <?php 
                        if (strpos($notif['title'], 'Resolved') !== false) echo '✅';
                        elseif (strpos($notif['title'], 'Fake') !== false) echo '⚠️';
                        elseif (strpos($notif['title'], 'Assigned') !== false) echo '👮';
                        elseif (strpos($notif['title'], 'Status') !== false) echo '📋';
                        elseif (strpos($notif['title'], 'Alert') !== false) echo '⚠️';
                        else echo '🔔';
                        ?>
                    </div>
                    <div style="flex: 1;">
                        <div class="notification-title" style="font-weight: <?php echo !$notif['is_read'] ? '600' : '400'; ?>">
                            <?php echo htmlspecialchars($notif['title']); ?>
                        </div>
                        <div class="notification-message" style="color: var(--text2); margin: 8px 0;">
                            <?php echo htmlspecialchars($notif['message']); ?>
                        </div>
                        <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                            <span style="font-size: 11px; color: var(--text3);">
                                📅 <?php echo date('F d, Y \a\t h:i A', strtotime($notif['created_at'])); ?>
                            </span>
                            <?php if ($notif['complaint_id']): ?>
                                <a href="complaint-detail.php?id=<?php echo $notif['complaint_id']; ?>" class="cid" style="text-decoration: none;">
                                    🆔 View Complaint
                                </a>
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

<style>
.notification-page {
    margin: 0 auto;
    padding: 20px;
}

.section-title {
    font-family: var(--display);
    font-size: 24px;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 4px;
}

.section-subtitle {
    font-size: 13px;
    color: var(--text3);
    font-family: var(--mono);
}

.badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
}

.card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.2s;
}

.card:hover {
    border-color: var(--border2);
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}

.btn-sm {
    padding: 5px 12px;
    font-size: 11px;
}

.btn-ghost {
    background: var(--bg4);
    color: var(--text2);
    border: 1px solid var(--border);
}

.btn-ghost:hover {
    background: var(--bg3);
    color: var(--text);
}

.btn-primary {
    background: var(--accent);
    color: white;
    border: none;
}

.btn-primary:hover {
    background: #5a52e8;
    transform: translateY(-1px);
}

.btn-danger {
    background: rgba(244, 63, 94, 0.15);
    color: #f43f5e;
    border: 1px solid rgba(244, 63, 94, 0.3);
}

.btn-danger:hover {
    background: rgba(244, 63, 94, 0.25);
}

.notification-title {
    font-size: 15px;
    color: var(--text);
    margin-bottom: 4px;
}

.notification-message {
    font-size: 13px;
    line-height: 1.5;
}

.cid {
    font-family: var(--mono);
    font-size: 11px;
    color: var(--accent2);
    background: rgba(108, 99, 255, 0.1);
    padding: 4px 10px;
    border-radius: 6px;
    display: inline-block;
}

.cid:hover {
    background: rgba(108, 99, 255, 0.2);
}

@media (max-width: 640px) {
    .notification-page {
        padding: 16px;
    }
    
    .section-title {
        font-size: 20px;
    }
    
    .card > div {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
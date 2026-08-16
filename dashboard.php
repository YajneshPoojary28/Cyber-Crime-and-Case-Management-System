<?php
require_once 'config/database.php';
redirectIfNotLoggedIn();

$pageTitle = 'Dashboard';
$conn = getConnection();
$userId = $_SESSION['user_id'];

// Get user statistics
$totalQuery = $conn->prepare("SELECT COUNT(*) as total FROM complaints WHERE user_id = ?");
$totalQuery->bind_param("i", $userId);
$totalQuery->execute();
$total = $totalQuery->get_result()->fetch_assoc()['total'];

$pendingQuery = $conn->prepare("SELECT COUNT(*) as pending FROM complaints WHERE user_id = ? AND status = 'pending'");
$pendingQuery->bind_param("i", $userId);
$pendingQuery->execute();
$pending = $pendingQuery->get_result()->fetch_assoc()['pending'];

$resolvedQuery = $conn->prepare("SELECT COUNT(*) as resolved FROM complaints WHERE user_id = ? AND status = 'resolved'");
$resolvedQuery->bind_param("i", $userId);
$resolvedQuery->execute();
$resolved = $resolvedQuery->get_result()->fetch_assoc()['resolved'];

// Get recent complaints
$recentQuery = $conn->prepare("SELECT id, complaint_num, category, incident_date, priority, status FROM complaints WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$recentQuery->bind_param("i", $userId);
$recentQuery->execute();
$recentComplaints = $recentQuery->get_result();

// Get unread notifications count
$notifQuery = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
$notifQuery->bind_param("i", $userId);
$notifQuery->execute();
$unreadCount = $notifQuery->get_result()->fetch_assoc()['unread'];

// Get recent notifications
$notifListQuery = $conn->prepare("SELECT id, complaint_num, title, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$notifListQuery->bind_param("i", $userId);
$notifListQuery->execute();
$notifications = $notifListQuery->get_result();

$conn->close();
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<!-- Main Dashboard Content -->
<div class="dashboard-container">
    <!-- Welcome Hero Section -->
    <div class="welcome-hero">
        <div class="welcome-badge">🛡️ CyberShield Portal</div>
        <div class="welcome-title">Welcome back, <span><?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?></span>!</div>
        <div class="welcome-text">Track your cyber crime complaints, submit new reports, and stay informed about the latest updates on your cases. Our AI-powered system helps prioritize and flag suspicious activities for faster resolution.</div>
        <div class="welcome-actions">
            <button class="btn btn-primary" onclick="location.href='file-complaint.php'">📝 File New Complaint</button>
            <button class="btn btn-ghost" onclick="location.href='my-complaints.php'">📋 View All Complaints</button>
        </div>
    </div>

    <!-- Statistics Cards - Removed In Progress -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-header">
                <div class="stat-label">Total Complaints</div>
                <div class="stat-icon">📁</div>
            </div>
            <div class="stat-value"><?php echo $total; ?></div>
            <div class="stat-delta">↗ All time records</div>
        </div>
        <div class="stat-card yellow">
            <div class="stat-header">
                <div class="stat-label">Pending Review</div>
                <div class="stat-icon">⏳</div>
            </div>
            <div class="stat-value"><?php echo $pending; ?></div>
            <div class="stat-delta <?php echo $pending > 0 ? 'neg' : ''; ?>"><?php echo $pending > 0 ? '⚠ Awaiting officer' : '✓ All processed'; ?></div>
        </div>
        <div class="stat-card green">
            <div class="stat-header">
                <div class="stat-label">Resolved</div>
                <div class="stat-icon">✅</div>
            </div>
            <div class="stat-value"><?php echo $resolved; ?></div>
            <div class="stat-delta">Cases closed successfully</div>
        </div>
    </div>

    <!-- Two Column Grid -->
    <div class="two-column-grid">
        <!-- Recent Complaints -->
        <div class="recent-section">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Recent Complaints</h2>
                    <p class="section-subtitle">Your last 5 submitted complaints</p>
                </div>
                <button class="btn-link" onclick="location.href='my-complaints.php'">View All →</button>
            </div>
            <div class="card">
                <?php if ($recentComplaints->num_rows == 0): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <div class="empty-state-text">No complaints filed yet</div>
                        <div class="empty-state-sub">Click "File New Complaint" to get started</div>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Complaint #</th>
                                <th>Category</th>
                                <th>Date</th>
                                <th>Priority</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($complaint = $recentComplaints->fetch_assoc()): ?>
                            <tr onclick="location.href='complaint-detail.php?id=<?php echo $complaint['id']; ?>'">
                                <td><span class="cid"><?php echo htmlspecialchars($complaint['complaint_num']); ?></span></td>
                                <td><?php echo htmlspecialchars($complaint['category']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($complaint['incident_date'])); ?></td>
                                <td><span class="pill pill-<?php echo $complaint['priority']; ?>"><?php echo ucfirst($complaint['priority']); ?></span></td>
                                <td><span class="pill pill-<?php 
                                    // Fix: Convert status to proper CSS class
                                    $statusClass = $complaint['status'];
                                    if ($statusClass == 'in-progress') $statusClass = 'progress';
                                    echo $statusClass; 
                                ?>"><?php echo ucfirst(str_replace('-', ' ', $complaint['status'])); ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notifications -->
        <div class="notifications-section">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Notifications</h2>
                    <p class="section-subtitle"><?php echo $unreadCount; ?> unread notification<?php echo $unreadCount != 1 ? 's' : ''; ?></p>
                </div>
                <?php if ($unreadCount > 0): ?>
                    <button class="btn-link" onclick="markAllNotificationsRead()">Mark all read</button>
                <?php endif; ?>
            </div>
            <div class="card">
                <?php if ($notifications->num_rows == 0): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">🔔</div>
                        <div class="empty-state-text">No notifications</div>
                        <div class="empty-state-sub">You're all caught up!</div>
                    </div>
                <?php else: ?>
                    <div class="notifications-list">
                        <?php while ($notif = $notifications->fetch_assoc()): ?>
                        <div class="notification-item">
                            <div class="notification-dot <?php echo $notif['is_read'] ? 'read' : 'unread'; ?>"></div>
                            <div class="notification-content">
                                <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                                <div class="notification-time">
                                    <?php echo date('M d, Y H:i', strtotime($notif['created_at'])); ?>
                                    <?php if ($notif['complaint_num']): ?>
                                        · <span class="cid"><?php echo htmlspecialchars($notif['complaint_num']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.dashboard-container {
    max-width: 1400px;
    margin: 0 auto;
}

/* Welcome Hero Section */
.welcome-hero {
    background: linear-gradient(135deg, var(--bg3) 0%, var(--bg4) 100%);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 40px;
    margin-bottom: 32px;
    position: relative;
    overflow: hidden;
}

.welcome-hero::before {
    content: '';
    position: absolute;
    top: -50px;
    right: -50px;
    width: 250px;
    height: 250px;
    background: radial-gradient(circle, rgba(108,99,255,0.15) 0%, transparent 70%);
}

.welcome-badge {
    display: inline-block;
    background: rgba(108,99,255,0.15);
    border: 1px solid rgba(108,99,255,0.3);
    border-radius: 20px;
    padding: 5px 14px;
    font-size: 11px;
    font-family: var(--mono);
    color: var(--accent2);
    margin-bottom: 16px;
}

.welcome-title {
    font-family: var(--display);
    font-size: 32px;
    font-weight: 800;
    color: var(--text);
    margin-bottom: 12px;
}

.welcome-title span {
    background: linear-gradient(135deg, var(--accent), var(--accent3));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.welcome-text {
    font-size: 14px;
    color: var(--text2);
    line-height: 1.6;
    max-width: 600px;
    margin-bottom: 28px;
}

.welcome-actions {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}

/* Statistics Cards - Updated for 3 cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 32px;
}

.stat-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 20px;
    position: relative;
    overflow: hidden;
    transition: all 0.2s;
}

.stat-card:hover {
    border-color: var(--border2);
    transform: translateY(-2px);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
}

.stat-card.blue::before { background: linear-gradient(90deg, var(--accent), var(--accent3)); }
.stat-card.yellow::before { background: linear-gradient(90deg, var(--warning), #fcd34d); }
.stat-card.green::before { background: linear-gradient(90deg, var(--success), #34d399); }

.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.stat-label {
    font-size: 11px;
    font-family: var(--mono);
    color: var(--text3);
    text-transform: uppercase;
    letter-spacing: 1px;
}

.stat-icon {
    font-size: 32px;
    opacity: 0.3;
}

.stat-value {
    font-family: var(--display);
    font-size: 36px;
    font-weight: 800;
    color: var(--text);
    margin-bottom: 8px;
}

.stat-delta {
    font-size: 11px;
    font-family: var(--mono);
    color: var(--success);
}

.stat-delta.neg {
    color: var(--danger);
}

/* Two Column Grid */
.two-column-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

/* Section Styles */
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 12px;
}

.section-title {
    font-family: var(--display);
    font-size: 18px;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 4px;
}

.section-subtitle {
    font-size: 12px;
    color: var(--text3);
    font-family: var(--mono);
}

.btn-link {
    background: none;
    border: none;
    color: var(--accent2);
    cursor: pointer;
    font-size: 12px;
    font-family: var(--mono);
    padding: 8px 12px;
    border-radius: 8px;
    transition: all 0.2s;
}

.btn-link:hover {
    background: var(--bg4);
    color: var(--accent3);
}

/* Card Styles */
.card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
}

/* Data Table */
.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    text-align: left;
    padding: 14px 16px;
    background: var(--bg4);
    color: var(--text3);
    font-size: 11px;
    font-family: var(--mono);
    font-weight: 500;
    border-bottom: 1px solid var(--border);
}

.data-table td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    color: var(--text2);
    font-size: 13px;
}

.data-table tr {
    cursor: pointer;
    transition: background 0.2s;
}

.data-table tr:hover td {
    background: var(--bg4);
}

.data-table tr:last-child td {
    border-bottom: none;
}

/* Notifications List */
.notifications-list {
    max-height: 450px;
    overflow-y: auto;
}

.notification-item {
    display: flex;
    gap: 14px;
    padding: 16px;
    border-bottom: 1px solid var(--border);
    transition: background 0.2s;
}

.notification-item:hover {
    background: var(--bg4);
}

.notification-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-top: 6px;
    flex-shrink: 0;
}

.notification-dot.unread {
    background: var(--accent);
    box-shadow: 0 0 8px var(--accent);
}

.notification-dot.read {
    background: var(--text3);
}

.notification-content {
    flex: 1;
}

.notification-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 4px;
}

.notification-message {
    font-size: 12px;
    color: var(--text2);
    margin-bottom: 6px;
    line-height: 1.5;
}

.notification-time {
    font-size: 10px;
    font-family: var(--mono);
    color: var(--text3);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 24px;
}

.empty-state-icon {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.5;
}

.empty-state-text {
    font-size: 14px;
    color: var(--text2);
    margin-bottom: 8px;
}

.empty-state-sub {
    font-size: 12px;
    color: var(--text3);
}

/* CID Styling */
.cid {
    font-family: var(--mono);
    font-size: 11px;
    color: var(--accent2);
    background: rgba(108,99,255,0.1);
    padding: 4px 10px;
    border-radius: 6px;
}

/* Pill Styling - Updated with proper classes */
.pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-family: var(--mono);
    font-weight: 500;
}

.pill::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

/* Status pills */
.pill-pending { background: rgba(245,158,11,0.15); color: var(--warning); }
.pill-progress { background: rgba(56,189,248,0.15); color: var(--accent3); }
.pill-resolved { background: rgba(16,185,129,0.15); color: var(--success); }
.pill-closed { background: rgba(100,100,120,0.15); color: var(--text3); }

/* Priority pills */
.pill-high { background: rgba(244,63,94,0.15); color: var(--danger); }
.pill-medium { background: rgba(245,158,11,0.15); color: var(--warning); }
.pill-low { background: rgba(16,185,129,0.15); color: var(--success); }
.pill-critical { background: rgba(139,0,0,0.2); color: #ff4060; }

/* Responsive */
@media (max-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .two-column-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    .welcome-title {
        font-size: 24px;
    }
    .welcome-hero {
        padding: 28px 20px;
    }
}
</style>

<script>
function markAllNotificationsRead() {
    fetch('mark-notifications-read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}
</script>

<?php include 'includes/footer.php'; ?>
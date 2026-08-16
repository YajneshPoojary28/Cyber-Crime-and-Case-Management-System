<?php
require_once 'config/database.php';
redirectIfNotAdmin();

$pageTitle = 'Activity Logs';
$conn = getConnection();

// Handle Clear Logs
if (isset($_POST['clear_logs']) && isset($_POST['confirm'])) {
    $delete_stmt = $conn->prepare("DELETE FROM activity_logs");
    if ($delete_stmt->execute()) {
        $message = "All activity logs have been cleared successfully!";
        addActivityLog($_SESSION['admin_id'], "Cleared all activity logs", $_SERVER['REMOTE_ADDR']);
    } else {
        $message = "Failed to clear activity logs.";
    }
    $delete_stmt->close();
    
    header("Location: admin-logs.php?msg=" . urlencode($message));
    exit();
}

// Get message from URL if any
$message = $_GET['msg'] ?? '';
$logs = $conn->query("SELECT l.*, a.full_name as admin_name FROM activity_logs l JOIN admins a ON l.admin_id = a.id ORDER BY l.created_at DESC LIMIT 100");
$totalLogs = $conn->query("SELECT COUNT(*) as total FROM activity_logs")->fetch_assoc()['total'];
$conn->close();
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="logs-container">
    <div class="logs-header">
        <div class="section-header">
            <div class="section-title">
                📋 Admin Activity Logs
                <span class="log-count">(<?php echo $totalLogs; ?> records)</span>
            </div>
            <?php if ($totalLogs > 0): ?>
                <button class="btn-clear-logs" onclick="showClearModal()">
                    🗑️ Clear All Logs
                </button>
            <?php endif; ?>
        </div>
        
        <?php if ($message): ?>
            <div class="success-message" id="successMessage">
                ✅ <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="logs-table-container">
        <table class="logs-data-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Admin</th>
                    <th>Action</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs->num_rows == 0): ?>
                    <tr>
                        <td colspan="4" class="empty-table">
                            <div class="empty-state">
                                <div class="empty-icon">📭</div>
                                <div class="empty-text">No activity logs found</div>
                                <div class="empty-sub">Activity logs will appear here when admins perform actions</div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php while($log = $logs->fetch_assoc()): ?>
                    <tr class="log-row">
                        <td class="log-time">
                            <span class="time-icon">🕐</span>
                            <?php echo date('d M Y, H:i:s', strtotime($log['created_at'])); ?>
                        </td>
                        <td class="log-admin">
                            <div class="admin-info">
                                <span class="admin-avatar"><?php echo strtoupper(substr($log['admin_name'], 0, 2)); ?></span>
                                <?php echo htmlspecialchars($log['admin_name']); ?>
                            </div>
                        </td>
                        <td class="log-action">
                            <div class="action-badge <?php 
                                if (strpos($log['action'], 'login') !== false) echo 'action-login';
                                elseif (strpos($log['action'], 'delete') !== false) echo 'action-delete';
                                elseif (strpos($log['action'], 'update') !== false) echo 'action-update';
                                elseif (strpos($log['action'], 'clear') !== false) echo 'action-clear';
                                else echo 'action-default';
                            ?>">
                                <?php echo htmlspecialchars($log['action']); ?>
                            </div>
                        </td>
                        <td class="log-ip">
                            <code class="ip-address"><?php echo htmlspecialchars($log['ip_address']); ?></code>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Clear Logs Confirmation Modal -->
<div id="clearModal" class="modal">
    <div class="modal-content clear-modal">
        <div class="modal-icon">⚠️</div>
        <div class="modal-title">Clear All Activity Logs</div>
        <div class="modal-text">
            Are you sure you want to clear all activity logs?<br>
            This action <strong>cannot be undone</strong> and will permanently delete all log records.
        </div>
        <div class="modal-buttons">
            <button class="btn btn-ghost" onclick="closeClearModal()">Cancel</button>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="clear_logs" value="1">
                <input type="hidden" name="confirm" value="1">
                <button type="submit" class="btn btn-danger">Yes, Clear All Logs</button>
            </form>
        </div>
    </div>
</div>

<style>
.logs-container {
    max-width: 1400px;
    margin: 0 auto;
}

.logs-header {
    margin-bottom: 24px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 16px;
}

.section-title {
    font-family: var(--display);
    font-size: 20px;
    font-weight: 700;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.log-count {
    font-size: 13px;
    font-family: var(--mono);
    color: var(--accent2);
    background: rgba(108,99,255,0.15);
    padding: 4px 12px;
    border-radius: 20px;
    font-weight: normal;
}

.btn-clear-logs {
    background: rgba(244,63,94,0.15);
    border: 1px solid rgba(244,63,94,0.3);
    border-radius: 10px;
    padding: 10px 20px;
    color: var(--danger);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-clear-logs:hover {
    background: rgba(244,63,94,0.25);
    transform: translateY(-1px);
}

.success-message {
    background: rgba(16,185,129,0.1);
    border: 1px solid rgba(16,185,129,0.3);
    border-radius: 12px;
    padding: 14px 18px;
    color: var(--success);
    font-size: 13px;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Logs Table */
.logs-table-container {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow-x: auto;
}

.logs-data-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}

.logs-data-table th {
    text-align: left;
    padding: 16px 20px;
    background: var(--bg4);
    color: var(--text3);
    font-size: 11px;
    font-family: var(--mono);
    font-weight: 600;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    border-bottom: 1px solid var(--border);
}

.logs-data-table td {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    color: var(--text2);
    font-size: 13px;
    vertical-align: middle;
}

.log-row {
    transition: background 0.2s;
}

.log-row:hover {
    background: var(--bg4);
}

.log-row:last-child td {
    border-bottom: none;
}

/* Log Time */
.log-time {
    font-family: var(--mono);
    font-size: 12px;
    color: var(--text2);
    white-space: nowrap;
}

.time-icon {
    margin-right: 8px;
    opacity: 0.7;
}

/* Admin Info */
.admin-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.admin-avatar {
    width: 28px;
    height: 28px;
    background: linear-gradient(135deg, var(--accent), var(--accent3));
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 600;
    color: white;
}

/* Action Badges */
.action-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-family: var(--mono);
    font-weight: 500;
}

.action-login {
    background: rgba(56,189,248,0.15);
    color: var(--accent3);
}

.action-delete {
    background: rgba(244,63,94,0.15);
    color: var(--danger);
}

.action-update {
    background: rgba(245,158,11,0.15);
    color: var(--warning);
}

.action-clear {
    background: rgba(139,0,0,0.15);
    color: #ff4060;
}

.action-default {
    background: rgba(108,99,255,0.15);
    color: var(--accent2);
}

/* IP Address */
.ip-address {
    font-family: var(--mono);
    font-size: 11px;
    background: var(--bg4);
    padding: 4px 8px;
    border-radius: 6px;
    color: var(--accent2);
}

/* Empty State */
.empty-table {
    text-align: center;
    padding: 60px 20px !important;
}

.empty-state {
    text-align: center;
}

.empty-icon {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.5;
}

.empty-text {
    font-size: 16px;
    color: var(--text2);
    margin-bottom: 8px;
}

.empty-sub {
    font-size: 13px;
    color: var(--text3);
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.8);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 20px;
    max-width: 450px;
    width: 90%;
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-icon {
    text-align: center;
    font-size: 56px;
    margin-top: 24px;
}

.modal-title {
    text-align: center;
    font-family: var(--display);
    font-size: 22px;
    font-weight: 700;
    color: var(--text);
    margin: 16px 0 8px;
}

.modal-text {
    text-align: center;
    color: var(--text2);
    font-size: 14px;
    margin: 8px 24px 24px;
    line-height: 1.6;
}

.modal-buttons {
    display: flex;
    gap: 12px;
    justify-content: center;
    padding: 0 24px 24px;
}

/* Responsive */
@media (max-width: 768px) {
    .logs-data-table th {
        display: none;
    }
    
    .logs-data-table td {
        display: block;
        padding: 12px 16px;
        text-align: right;
        position: relative;
        border-bottom: none;
    }
    
    .logs-data-table td::before {
        content: attr(data-label);
        position: absolute;
        left: 16px;
        top: 12px;
        font-size: 11px;
        font-family: var(--mono);
        color: var(--text3);
        font-weight: 500;
    }
    
    .logs-data-table tr {
        display: block;
        margin-bottom: 16px;
        border: 1px solid var(--border);
        border-radius: 12px;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .admin-info {
        justify-content: flex-end;
    }
}
</style>

<script>
// Add data labels to table cells for responsive view
document.querySelectorAll('.logs-data-table td').forEach(td => {
    const th = td.parentElement.parentElement.querySelectorAll('th')[td.cellIndex];
    if (th) {
        td.setAttribute('data-label', th.textContent);
    }
});

function showClearModal() {
    document.getElementById('clearModal').style.display = 'flex';
}

function closeClearModal() {
    document.getElementById('clearModal').style.display = 'none';
}

// Auto-hide success message after 5 seconds
setTimeout(function() {
    const msg = document.getElementById('successMessage');
    if (msg) {
        msg.style.opacity = '0';
        setTimeout(function() {
            msg.style.display = 'none';
        }, 500);
    }
}, 5000);

// Close modal when clicking outside
window.addEventListener('click', function(e) {
    const modal = document.getElementById('clearModal');
    if (e.target === modal) {
        closeClearModal();
    }
});
</script>

<?php include 'includes/footer.php'; ?>
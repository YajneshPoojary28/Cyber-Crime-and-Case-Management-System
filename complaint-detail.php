<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in (either citizen, admin, or officer)
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id']) && !isset($_SESSION['officer_id'])) {
    header("Location: login.php");
    exit();
}

$complaint_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$complaint_id) {
    header("Location: " . (isset($_SESSION['admin_id']) ? "admin-complaints.php" : (isset($_SESSION['officer_id']) ? "officer-dashboard.php" : "my-complaints.php")));
    exit();
}

$conn = getConnection();
$user_id = $_SESSION['user_id'] ?? 0;
$isAdmin = isset($_SESSION['admin_id']);
$isOfficer = isset($_SESSION['officer_id']);

// Fetch complaint with user details including phone number
if ($isAdmin) {
    $stmt = $conn->prepare("SELECT c.*, u.name as user_name, u.email as user_email, u.phone as user_phone FROM complaints c JOIN users u ON c.user_id = u.id WHERE c.id = ?");
    $stmt->bind_param("i", $complaint_id);
} elseif ($isOfficer) {
    // Officer can only view complaints assigned to them
    $officer_id = $_SESSION['officer_id'];
    $stmt = $conn->prepare("SELECT c.*, u.name as user_name, u.email as user_email, u.phone as user_phone FROM complaints c JOIN users u ON c.user_id = u.id WHERE c.id = ? AND c.assigned_to = ?");
    $stmt->bind_param("ii", $complaint_id, $officer_id);
} else {
    // Citizen can only view their own complaints
    $stmt = $conn->prepare("SELECT c.*, u.name as user_name, u.email as user_email, u.phone as user_phone FROM complaints c JOIN users u ON c.user_id = u.id WHERE c.id = ? AND c.user_id = ?");
    $stmt->bind_param("ii", $complaint_id, $user_id);
}
$stmt->execute();
$complaint = $stmt->get_result()->fetch_assoc();

if (!$complaint) {
    header("Location: " . ($isAdmin ? "admin-complaints.php" : ($isOfficer ? "officer-dashboard.php" : "my-complaints.php")));
    exit();
}

// Handle status update (Admin only)
if ($isAdmin && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'] ?? $complaint['status'];
    $new_priority = $_POST['priority'] ?? $complaint['priority'];
    $admin_remarks = $_POST['admin_remarks'] ?? '';
    $public_response = $_POST['public_response'] ?? '';
    
    $update_stmt = $conn->prepare("UPDATE complaints SET status = ?, priority = ?, admin_remarks = ?, public_response = ? WHERE id = ?");
    $update_stmt->bind_param("ssssi", $new_status, $new_priority, $admin_remarks, $public_response, $complaint_id);
    
    if ($update_stmt->execute()) {
        // Add to timeline if status changed
        if ($new_status != $complaint['status']) {
            $timeline_note = "Status changed from {$complaint['status']} to {$new_status}";
            $timeline_update = $conn->prepare("INSERT INTO complaint_timeline (complaint_id, action, action_by, note) VALUES (?, ?, ?, ?)");
            $action_by = $_SESSION['admin_name'] ?? 'Admin';
            $timeline_update->bind_param("isss", $complaint_id, $timeline_note, $action_by, $timeline_note);
            $timeline_update->execute();
            $timeline_update->close();
        }
        
        // Send notification to citizen about status update
        $citizen_message = "Your complaint #{$complaint['complaint_num']} status has been updated to: " . ucfirst(str_replace('-', ' ', $new_status));
        $citizen_notification = $conn->prepare("INSERT INTO notifications (user_id, complaint_id, title, message, is_read, created_at) VALUES (?, ?, 'Status Update', ?, 0, NOW())");
        $citizen_notification->bind_param("iis", $complaint['user_id'], $complaint_id, $citizen_message);
        $citizen_notification->execute();
        $citizen_notification->close();
        
        // Redirect to refresh page
        header("Location: complaint-detail.php?id=$complaint_id");
        exit();
    }
    $update_stmt->close();
}

// Handle resolve by officer
if ($isOfficer && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['resolve_complaint'])) {
    $resolution_notes = $_POST['resolution_notes'] ?? '';
    
    if (!empty($resolution_notes)) {
        $update_stmt = $conn->prepare("UPDATE complaints SET status = 'resolved', resolved_date = NOW(), resolution_notes = ? WHERE id = ?");
        $update_stmt->bind_param("si", $resolution_notes, $complaint_id);
        
        if ($update_stmt->execute()) {
            $timeline_note = "Complaint resolved by officer: " . $_SESSION['officer_name'];
            $timeline_update = $conn->prepare("INSERT INTO complaint_timeline (complaint_id, action, action_by, note) VALUES (?, ?, ?, ?)");
            $action_by = $_SESSION['officer_name'] ?? 'Officer';
            $timeline_update->bind_param("isss", $complaint_id, $timeline_note, $action_by, $resolution_notes);
            $timeline_update->execute();
            $timeline_update->close();
            
            // Send notification to ADMIN
            $admin_message = "Complaint #{$complaint['complaint_num']} has been resolved by Officer {$_SESSION['officer_name']}.";
            $admin_notification = $conn->prepare("INSERT INTO notifications (user_id, complaint_id, title, message, is_read, created_at) VALUES (1, ?, 'Complaint Resolved', ?, 0, NOW())");
            $admin_notification->bind_param("is", $complaint_id, $admin_message);
            $admin_notification->execute();
            $admin_notification->close();
            
            // Send notification to CITIZEN
            $citizen_message = "Your complaint #{$complaint['complaint_num']} has been successfully resolved. Thank you for your patience.";
            $citizen_notification = $conn->prepare("INSERT INTO notifications (user_id, complaint_id, title, message, is_read, created_at) VALUES (?, ?, 'Complaint Resolved', ?, 0, NOW())");
            $citizen_notification->bind_param("iis", $complaint['user_id'], $complaint_id, $citizen_message);
            $citizen_notification->execute();
            $citizen_notification->close();
            
            header("Location: complaint-detail.php?id=$complaint_id");
            exit();
        }
        $update_stmt->close();
    }
}

// Get timeline
$timeline_stmt = $conn->prepare("SELECT * FROM complaint_timeline WHERE complaint_id = ? ORDER BY created_at ASC");
$timeline_stmt->bind_param("i", $complaint_id);
$timeline_stmt->execute();
$timeline = $timeline_stmt->get_result();

$conn->close();
$pageTitle = 'Complaint Details';
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div style="margin-bottom: 16px;">
    <button class="btn btn-ghost btn-sm" onclick="history.back()">← Back</button>
</div>

<div class="detail-grid">
    <div>
        <div class="card" style="padding: 24px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px;">
                <div>
                    <span class="cid" style="font-size: 14px;"><?php echo $complaint['complaint_num']; ?></span>
                    <div class="section-title" style="margin-top: 8px;"><?php echo htmlspecialchars($complaint['category']); ?></div>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <span class="pill pill-<?php echo str_replace('-', '', $complaint['status']); ?>"><?php echo ucfirst($complaint['status']); ?></span>
                    <span class="pill pill-<?php echo $complaint['priority']; ?>"><?php echo ucfirst($complaint['priority']); ?></span>
                    <?php if ($complaint['suspicious']): ?>
                        <span class="pill pill-suspicious">⚠️ Suspicious</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                <div>
                    <div class="detail-key">Filed By</div>
                    <div class="detail-val"><?php echo htmlspecialchars($complaint['user_name']); ?></div>
                </div>
                <div>
                    <div class="detail-key">Email</div>
                    <div class="detail-val"><?php echo htmlspecialchars($complaint['user_email']); ?></div>
                </div>
                <div>
                    <div class="detail-key">Phone Number</div>
                    <div class="detail-val">
                        <?php 
                        if (!empty($complaint['user_phone'])) {
                            echo htmlspecialchars($complaint['user_phone']);
                        } else {
                            echo '<span class="text-muted">Not provided</span>';
                        }
                        ?>
                    </div>
                </div>
                <div>
                    <div class="detail-key">Incident Date & Time</div>
                    <div class="detail-val"><?php echo date('d M Y', strtotime($complaint['incident_date'])); ?> at <?php echo substr($complaint['incident_time'], 0, 5); ?></div>
                </div>
                <div>
                    <div class="detail-key">Financial Loss</div>
                    <div class="detail-val" style="color: <?php echo $complaint['financial_loss'] > 0 ? 'var(--danger)' : 'var(--text3)'; ?>">
                        <?php echo $complaint['financial_loss'] > 0 ? '₹' . number_format($complaint['financial_loss'], 2) : 'None reported'; ?>
                    </div>
                </div>
                <div>
                    <div class="detail-key">Location/URL</div>
                    <div class="detail-val" style="word-break: break-all;"><?php echo htmlspecialchars($complaint['location'] ?: 'Not provided'); ?></div>
                </div>
                <div>
                    <div class="detail-key">Suspect Information</div>
                    <div class="detail-val"><?php echo htmlspecialchars($complaint['suspect_info'] ?: 'Not provided'); ?></div>
                </div>
                <?php if ($isAdmin || $isOfficer): ?>
                <div>
                    <div class="detail-key">Assigned Officer ID</div>
                    <div class="detail-val"><?php echo $complaint['assigned_to'] ? 'Officer #' . $complaint['assigned_to'] : 'Not assigned'; ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="detail-field">
                <div class="detail-key">Full Description</div>
                <div style="background: var(--bg4); border-radius: 10px; padding: 16px; margin-top: 8px;">
                    <?php echo nl2br(htmlspecialchars($complaint['description'])); ?>
                </div>
            </div>
            
            <?php if ($complaint['public_response']): ?>
            <div class="detail-field" style="margin-top: 16px;">
                <div class="detail-key">Official Response</div>
                <div style="background: rgba(56,189,248,0.1); border-left: 3px solid var(--accent3); border-radius: 10px; padding: 16px; margin-top: 8px;">
                    <?php echo nl2br(htmlspecialchars($complaint['public_response'])); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($complaint['resolution_notes']): ?>
            <div class="detail-field" style="margin-top: 16px;">
                <div class="detail-key">Resolution Notes</div>
                <div style="background: rgba(16,185,129,0.1); border-left: 3px solid var(--success); border-radius: 10px; padding: 16px; margin-top: 8px;">
                    <?php echo nl2br(htmlspecialchars($complaint['resolution_notes'])); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Admin Actions Panel -->
        <?php if ($isAdmin): ?>
        <div class="card" style="padding: 24px;">
            <div class="section-title" style="margin-bottom: 16px;">Admin Actions</div>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Update Status</label>
                        <select name="status" class="form-select">
                            <option value="pending" <?php echo $complaint['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="in-progress" <?php echo $complaint['status'] == 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="resolved" <?php echo $complaint['status'] == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            <option value="closed" <?php echo $complaint['status'] == 'closed' ? 'selected' : ''; ?>>Closed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Update Priority</label>
                        <select name="priority" class="form-select">
                            <option value="low" <?php echo $complaint['priority'] == 'low' ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo $complaint['priority'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo $complaint['priority'] == 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="critical" <?php echo $complaint['priority'] == 'critical' ? 'selected' : ''; ?>>Critical</option>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Admin Remarks (Internal)</label>
                        <textarea name="admin_remarks" class="form-textarea" style="min-height: 80px;"><?php echo htmlspecialchars($complaint['admin_remarks'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Public Response</label>
                        <textarea name="public_response" class="form-textarea" style="min-height: 80px;"><?php echo htmlspecialchars($complaint['public_response'] ?? ''); ?></textarea>
                    </div>
                </div>
                <button type="submit" name="update_status" class="btn btn-primary" style="margin-top: 16px;">💾 Update Complaint</button>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Officer Actions Panel -->
        <?php if ($isOfficer && $complaint['status'] != 'resolved' && $complaint['status'] != 'closed'): ?>
        <div class="card" style="padding: 24px; margin-top: 20px;">
            <div class="section-title" style="margin-bottom: 16px;">Officer Actions</div>
            <form method="POST">
                <div class="form-group full">
                    <label class="form-label">Resolution Notes *</label>
                    <textarea name="resolution_notes" class="form-textarea" style="min-height: 100px;" required placeholder="Describe how the complaint was resolved, actions taken, and any recommendations..."></textarea>
                    <small class="text-muted">These notes will be shared with the admin and citizen.</small>
                </div>
                <button type="submit" name="resolve_complaint" class="btn btn-success" style="margin-top: 16px;">✅ Mark as Resolved</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    
    <div>
        <div class="card" style="padding: 20px;">
            <div class="section-title" style="margin-bottom: 16px;">Status Timeline</div>
            <?php if ($timeline && $timeline->num_rows > 0): ?>
            <ul class="timeline">
                <?php while ($event = $timeline->fetch_assoc()): ?>
                <li class="timeline-item">
                    <div class="tl-dot">
                        <?php 
                        if (strpos($event['action'], 'Filed') !== false) echo '📝';
                        elseif (strpos($event['action'], 'Suspicious') !== false) echo '🤖';
                        elseif (strpos($event['action'], 'resolved') !== false) echo '✅';
                        else echo '🔄';
                        ?>
                    </div>
                    <div class="tl-content">
                        <div class="tl-title"><?php echo htmlspecialchars($event['action']); ?></div>
                        <div class="tl-meta"><?php echo date('M d, Y H:i', strtotime($event['created_at'])); ?> · <?php echo htmlspecialchars($event['action_by']); ?></div>
                        <?php if ($event['note']): ?>
                            <div class="tl-note"><?php echo htmlspecialchars($event['note']); ?></div>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endwhile; ?>
            </ul>
            <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-history fa-2x text-muted mb-2 d-block"></i>
                <p class="text-muted">No timeline events yet.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.detail-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
}
.detail-field {
    margin-top: 16px;
}
.detail-key {
    font-size: 11px;
    font-family: var(--mono);
    color: var(--text3);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}
.detail-val {
    font-size: 14px;
    color: var(--text);
}
.text-muted {
    color: var(--text3);
}
.cid {
    font-family: var(--mono);
    font-size: 12px;
    color: var(--accent2);
    background: rgba(108,99,255,0.1);
    padding: 4px 10px;
    border-radius: 6px;
}
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
.pill-pending { background: rgba(245,158,11,0.15); color: var(--warning); }
.pill-in-progress { background: rgba(56,189,248,0.15); color: var(--accent3); }
.pill-resolved { background: rgba(16,185,129,0.15); color: var(--success); }
.pill-closed { background: rgba(100,100,120,0.15); color: var(--text3); }
.pill-low { background: rgba(16,185,129,0.15); color: var(--success); }
.pill-medium { background: rgba(245,158,11,0.15); color: var(--warning); }
.pill-high { background: rgba(244,63,94,0.15); color: var(--danger); }
.pill-critical { background: rgba(139,0,0,0.2); color: #ff4060; }
.pill-suspicious { background: rgba(244,63,94,0.2); color: var(--danger); border: 1px solid rgba(244,63,94,0.3); }
.timeline { list-style: none; padding: 0; margin: 0; }
.timeline-item { display: flex; gap: 16px; padding-bottom: 20px; position: relative; }
.timeline-item::before { content: ''; position: absolute; left: 14px; top: 28px; bottom: 0; width: 1px; background: var(--border); }
.timeline-item:last-child::before { display: none; }
.tl-dot { width: 28px; height: 28px; border-radius: 50%; border: 2px solid var(--border2); display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; background: var(--bg3); }
.tl-content { flex: 1; }
.tl-title { font-size: 14px; font-weight: 600; color: var(--text); }
.tl-meta { font-size: 11px; color: var(--text3); font-family: var(--mono); margin-top: 4px; }
.tl-note { font-size: 12px; color: var(--text2); margin-top: 8px; background: var(--bg4); padding: 10px 14px; border-radius: 8px; border-left: 3px solid var(--accent); }
.btn-ghost { background: var(--bg3); color: var(--text2); border: 1px solid var(--border); padding: 8px 16px; border-radius: 8px; cursor: pointer; }
.btn-ghost:hover { background: var(--bg2); transform: translateY(-1px); }
.btn-primary { background: var(--accent); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
.btn-primary:hover { transform: translateY(-1px); filter: brightness(1.05); }
.btn-success { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
.btn-success:hover { transform: translateY(-1px); filter: brightness(1.05); }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group { display: flex; flex-direction: column; gap: 8px; }
.form-group.full { grid-column: 1/-1; }
.form-label { font-size: 11px; font-family: var(--mono); color: var(--text3); text-transform: uppercase; letter-spacing: 0.5px; }
.form-select, .form-textarea { background: var(--bg4); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; color: var(--text); font-size: 13px; outline: none; }
.form-select:focus, .form-textarea:focus { border-color: var(--accent); }
@media (max-width: 768px) {
    .detail-grid { grid-template-columns: 1fr; }
    .form-grid { grid-template-columns: 1fr; }
}
</style>

<?php include 'includes/footer.php'; ?>
<?php
require_once 'config/database.php';
redirectIfNotAdmin();

$pageTitle = 'Complaint Management';
$conn = getConnection();

$success_msg = '';
$error_msg = '';

// Handle assign to officer
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_officer'])) {
    $complaint_id = $_POST['complaint_id'];
    $officer_id = $_POST['officer_id'];
    
    if ($officer_id) {
        // Check which column exists
        $columns = $conn->query("SHOW COLUMNS FROM complaints");
        $col_names = [];
        while ($col = $columns->fetch_assoc()) {
            $col_names[] = $col['Field'];
        }
        
        // Determine correct column name
        if (in_array('assigned_to', $col_names)) {
            $assign_col = 'assigned_to';
        } elseif (in_array('officer_id', $col_names)) {
            $assign_col = 'officer_id';
        } else {
            $conn->query("ALTER TABLE complaints ADD COLUMN assigned_to INT NULL");
            $conn->query("ALTER TABLE complaints ADD COLUMN assigned_date DATETIME NULL");
            $assign_col = 'assigned_to';
        }
        
        // Get complaint details for notification
        $complaint_stmt = $conn->prepare("SELECT user_id, complaint_num FROM complaints WHERE id = ?");
        $complaint_stmt->bind_param("i", $complaint_id);
        $complaint_stmt->execute();
        $complaint_data = $complaint_stmt->get_result()->fetch_assoc();
        $user_id = $complaint_data['user_id'];
        $complaint_num = $complaint_data['complaint_num'];
        $complaint_stmt->close();
        
        // Get officer name for notification
        $officer_stmt = $conn->prepare("SELECT full_name FROM investigation_officers WHERE id = ?");
        $officer_stmt->bind_param("i", $officer_id);
        $officer_stmt->execute();
        $officer_data = $officer_stmt->get_result()->fetch_assoc();
        $officer_name = $officer_data['full_name'] ?? 'Investigation Officer';
        $officer_stmt->close();
        
        // Keep status as 'pending' when assigning
        $assign_stmt = $conn->prepare("UPDATE complaints SET $assign_col = ?, assigned_date = NOW(), status = 'pending' WHERE id = ?");
        $assign_stmt->bind_param("ii", $officer_id, $complaint_id);
        
        if ($assign_stmt->execute()) {
            // Send notification to USER (citizen)
            $notification_title = "Officer Assigned to Your Complaint";
            $notification_message = "Good news! Your complaint #{$complaint_num} has been assigned to Officer {$officer_name}. They will review your case and contact you soon. You can track the status of your complaint in your dashboard.";
            
            $user_notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, complaint_id, title, message, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
            $user_notif_stmt->bind_param("iiss", $user_id, $complaint_id, $notification_title, $notification_message);
            $user_notif_stmt->execute();
            $user_notif_stmt->close();
            
            // Send notification to OFFICER
            $officer_notif_title = "New Complaint Assigned";
            $officer_notif_message = "You have been assigned a new complaint #{$complaint_num}. Please review and take necessary action.";
            
            $officer_notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, complaint_id, title, message, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
            $officer_notif_stmt->bind_param("iiss", $officer_id, $complaint_id, $officer_notif_title, $officer_notif_message);
            $officer_notif_stmt->execute();
            $officer_notif_stmt->close();
            
            $success_msg = "✅ Complaint assigned to officer successfully! Notification sent to citizen and officer.";
        } else {
            $error_msg = "❌ Error: " . $assign_stmt->error;
        }
        $assign_stmt->close();
    }
}

$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';

// Check what columns exist in complaints table
$columns = $conn->query("SHOW COLUMNS FROM complaints");
$col_names = [];
while ($col = $columns->fetch_assoc()) {
    $col_names[] = $col['Field'];
}

// Determine the correct assignment column for display
$assign_col = in_array('assigned_to', $col_names) ? 'assigned_to' : (in_array('officer_id', $col_names) ? 'officer_id' : null);

// Build query with correct column names
$query = "SELECT c.*, u.name as user_name FROM complaints c JOIN users u ON c.user_id = u.id WHERE 1=1";
$params = []; 
$types = "";

$search_col1 = in_array('complaint_num', $col_names) ? 'c.complaint_num' : 'c.id';
$search_col2 = 'u.name';
$search_col3 = in_array('type', $col_names) ? 'c.type' : (in_array('category', $col_names) ? 'c.category' : 'c.id');

if ($search) { 
    $query .= " AND ($search_col1 LIKE ? OR $search_col2 LIKE ? OR $search_col3 LIKE ?)"; 
    $p="%$search%"; 
    $params=array_merge($params,[$p,$p,$p]); 
    $types.="sss"; 
}
if ($status_filter) { 
    $query .= " AND c.status = ?"; 
    $params[]=$status_filter; 
    $types.="s"; 
}
if ($priority_filter) { 
    $query .= " AND c.priority = ?"; 
    $params[]=$priority_filter; 
    $types.="s"; 
}
$query .= " ORDER BY c.created_at DESC";

$stmt = $conn->prepare($query);
if(!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$complaints = $stmt->get_result();

// Get ALL active officers (removed badge_number filter)
$officers_query = $conn->query("SELECT id, full_name as name, badge_number FROM investigation_officers WHERE is_active = 1");
$officers = [];
while ($off = $officers_query->fetch_assoc()) {
    $officers[] = $off;
}

// Fetch files for each complaint
$complaints_with_files = [];
while ($c = $complaints->fetch_assoc()) {
    $proofs_exists = $conn->query("SHOW TABLES LIKE 'complaint_proofs'")->num_rows > 0;
    $files = [];
    
    if ($proofs_exists) {
        $file_stmt = $conn->prepare("SELECT id, filename, file_path, file_type FROM complaint_proofs WHERE complaint_id = ?");
        $file_stmt->bind_param("i", $c['id']);
        $file_stmt->execute();
        $files_result = $file_stmt->get_result();
        while ($file = $files_result->fetch_assoc()) {
            $clean_path = str_replace('\\', '/', $file['file_path']);
            // Remove leading slash if exists
            $clean_path = ltrim($clean_path, '/');
            $files[] = [
                'id' => $file['id'],
                'name' => $file['filename'],
                'path' => $clean_path,
                'type' => $file['file_type']
            ];
        }
        $file_stmt->close();
    }
    $c['files'] = $files;
    
    $c['display_type'] = $c['type'] ?? ($c['category'] ?? 'N/A');
    $c['is_suspicious'] = $c['is_suspicious'] ?? ($c['suspicious'] ?? 0);
    $c['assigned_value'] = $assign_col ? ($c[$assign_col] ?? null) : null;
    
    $complaints_with_files[] = $c;
}
$conn->close();
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<!-- Success Message Popup at Top -->
<?php if($success_msg): ?>
<div class="top-message success-message" id="topMessage">
    <div class="message-content">
        <i class="fas fa-check-circle"></i>
        <span><?php echo $success_msg; ?></span>
    </div>
</div>
<script>
    setTimeout(function() {
        var msg = document.getElementById('topMessage');
        if(msg) {
            msg.style.animation = 'slideUp 0.3s ease';
            setTimeout(function() {
                msg.style.display = 'none';
            }, 300);
        }
    }, 3000);
</script>
<?php endif; ?>

<?php if($error_msg): ?>
<div class="top-message error-message">
    <div class="message-content">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo $error_msg; ?></span>
    </div>
</div>
<script>
    setTimeout(function() {
        var msg = document.getElementById('topMessage');
        if(msg) {
            msg.style.animation = 'slideUp 0.3s ease';
            setTimeout(function() {
                msg.style.display = 'none';
            }, 300);
        }
    }, 3000);
</script>
<?php endif; ?>

<div class="complaints-management-container">
    <!-- Search and Filter Bar -->
    <div class="search-filter-bar">
        <form method="GET" class="filter-form">
            <div class="search-wrapper">
                <span class="search-icon">🔍</span>
                <input type="text" name="search" class="search-input-field" placeholder="Search by complaint #, user name, or category..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <select name="status" class="filter-select">
                <option value="">All Status</option>
                <option value="pending" <?php echo $status_filter=='pending'?'selected':''; ?>>Pending</option>
                <option value="in-progress" <?php echo $status_filter=='in-progress'?'selected':''; ?>>In Progress</option>
                <option value="resolved" <?php echo $status_filter=='resolved'?'selected':''; ?>>Resolved</option>
            </select>
            <select name="priority" class="filter-select">
                <option value="">All Priority</option>
                <option value="low" <?php echo $priority_filter=='low'?'selected':''; ?>>Low</option>
                <option value="medium" <?php echo $priority_filter=='medium'?'selected':''; ?>>Medium</option>
                <option value="high" <?php echo $priority_filter=='high'?'selected':''; ?>>High</option>
            </select>
            <button type="submit" class="btn-filter">Apply Filters</button>
            <?php if($search || $status_filter || $priority_filter): ?>
                <a href="admin-complaints.php" class="btn-clear">Clear All</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Complaints Table -->
    <div class="complaints-table-container">
        <table class="complaints-data-table">
            <thead>
                <tr>
                    <th>Complaint #</th>
                    <th>User</th>
                    <th>Category</th>
                    <th>Date</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Flag</th>
                    <th>Files</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($complaints_with_files) == 0): ?>
                    <tr class="empty-row">
                        <td colspan="9">
                            <div class="empty-state">
                                <div class="empty-icon">📋</div>
                                <div class="empty-text">No complaints found</div>
                                <div class="empty-sub">Try adjusting your search or filter criteria</div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach($complaints_with_files as $c): ?>
                    <tr class="complaint-row" data-id="<?php echo $c['id']; ?>">
                        <td data-label="Complaint #">
                            <span class="complaint-number"><?php echo htmlspecialchars($c['complaint_num'] ?? '#' . $c['id']); ?></span>
                        </td>
                        <td data-label="User"><?php echo htmlspecialchars($c['user_name']); ?></td>
                        <td data-label="Category"><?php echo htmlspecialchars($c['display_type']); ?></td>
                        <td data-label="Date"><?php echo date('d M Y', strtotime($c['created_at'])); ?></td>
                        <td data-label="Priority">
                            <span class="priority-badge priority-<?php echo strtolower($c['priority'] ?? 'low'); ?>">
                                <?php echo ucfirst($c['priority'] ?? 'Low'); ?>
                            </span>
                        </td>
                        <td data-label="Status">
                            <span class="status-badge status-<?php echo strtolower(str_replace('-', '', $c['status'] ?? 'pending')); ?>">
                                <?php 
                                $status_display = $c['status'] ?? 'pending';
                                if ($status_display == 'pending') echo 'Pending';
                                elseif ($status_display == 'in-progress') echo 'In Progress';
                                elseif ($status_display == 'resolved') echo 'Resolved';
                                else echo ucfirst($status_display);
                                ?>
                            </span>
                        </td>
                        <td data-label="Flag">
                            <?php if($c['is_suspicious'] == 1): ?>
                                <span class="flag-badge suspicious">⚠️ Flagged</span>
                            <?php else: ?>
                                <span class="flag-badge normal">—</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Files">
                            <?php if(count($c['files']) > 0): ?>
                                <button class="view-files-btn" onclick="event.stopPropagation(); showFiles(<?php echo htmlspecialchars(json_encode($c['files'])); ?>, '<?php echo htmlspecialchars($c['complaint_num'] ?? '#' . $c['id']); ?>')">
                                    📎 View (<?php echo count($c['files']); ?>)
                                </button>
                            <?php else: ?>
                                <span class="no-files">—</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Action">
                            <div class="action-buttons">
                                <?php if(empty($c['assigned_value']) || $c['assigned_value'] == 0): ?>
                                    <button class="btn-assign" onclick="event.stopPropagation(); showAssignModal(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars($c['complaint_num'] ?? '#' . $c['id']); ?>')">
                                        👮 Assign
                                    </button>
                                <?php else: ?>
                                    <span class="assigned-badge">✅ Assigned</span>
                                <?php endif; ?>
                                <button class="btn-view" onclick="viewComplaint(<?php echo $c['id']; ?>)">
                                    👁️ View
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Assign Modal -->
<div id="assignModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-icon">👮</div>
        <div class="modal-title">Assign to Officer</div>
        <div id="assignModalText" class="modal-text"></div>
        <form method="POST" id="assignForm">
            <input type="hidden" name="complaint_id" id="assign_complaint_id">
            <select name="officer_id" id="officer_select" class="filter-select" style="width: 100%; margin-bottom: 20px;" required>
                <option value="">-- Select Officer --</option>
                <?php foreach($officers as $officer): ?>
                    <option value="<?php echo $officer['id']; ?>"><?php echo htmlspecialchars($officer['name']); ?> (Badge: <?php echo htmlspecialchars($officer['badge_number']); ?>)</option>
                <?php endforeach; ?>
            </select>
            <div class="modal-buttons">
                <button type="button" onclick="closeAssignModal()" class="btn btn-ghost">Cancel</button>
                <button type="submit" name="assign_officer" class="btn btn-primary" id="assignBtn">Assign</button>
            </div>
        </form>
    </div>
</div>

<!-- Files Modal -->
<div id="filesModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-icon">📎</div>
        <div class="modal-title" id="filesModalTitle">Evidence Files</div>
        <div id="filesList" class="files-list"></div>
        <div class="modal-buttons">
            <button onclick="closeFilesModal()" class="btn btn-primary">Close</button>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div id="previewModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 90%;">
        <div class="modal-icon">👁️</div>
        <div class="modal-title">File Preview</div>
        <div id="previewContent" class="preview-content"></div>
        <div class="modal-buttons">
            <button onclick="closePreviewModal()" class="btn btn-primary">Close</button>
        </div>
    </div>
</div>

<style>
.complaints-management-container {
    max-width: 100%;
    padding: 20px;
}

/* Top Message Styles */
.top-message {
    position: fixed;
    top: 80px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 10001;
    min-width: 350px;
    max-width: 500px;
    animation: slideDown 0.3s ease;
}

.message-content {
    background: #10b981;
    color: white;
    padding: 14px 24px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
    font-weight: 500;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.error-message .message-content {
    background: #ef4444;
}

.message-content i {
    font-size: 20px;
}

@keyframes slideDown {
    from {
        transform: translate(-50%, -100%);
        opacity: 0;
    }
    to {
        transform: translate(-50%, 0);
        opacity: 1;
    }
}

@keyframes slideUp {
    from {
        transform: translate(-50%, 0);
        opacity: 1;
    }
    to {
        transform: translate(-50%, -100%);
        opacity: 0;
    }
}

/* Search and Filter Bar */
.search-filter-bar {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 24px;
}

.filter-form {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: flex-end;
}

.search-wrapper {
    position: relative;
    flex: 2;
    min-width: 250px;
}

.search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 16px;
    opacity: 0.6;
}

.search-input-field {
    width: 100%;
    background: var(--bg4);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 12px 14px 12px 42px;
    color: var(--text);
    font-size: 14px;
    outline: none;
}

.search-input-field:focus {
    border-color: var(--accent);
}

.filter-select {
    background: var(--bg4);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 12px 14px;
    color: var(--text2);
    font-size: 13px;
    min-width: 140px;
    cursor: pointer;
    outline: none;
}

.filter-select:focus {
    border-color: var(--accent);
}

.btn-filter {
    background: var(--accent);
    color: white;
    border: none;
    border-radius: 10px;
    padding: 12px 24px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-filter:hover {
    background: #5a52e8;
    transform: translateY(-1px);
}

.btn-clear {
    background: rgba(244, 63, 94, 0.15);
    border: 1px solid rgba(244, 63, 94, 0.3);
    border-radius: 10px;
    padding: 11px 20px;
    color: #f43f5e;
    font-size: 13px;
    text-decoration: none;
    transition: all 0.2s;
    display: inline-block;
}

.btn-clear:hover {
    background: rgba(244, 63, 94, 0.25);
}

/* Complaints Table */
.complaints-table-container {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow-x: auto;
}

.complaints-data-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1000px;
}

.complaints-data-table th {
    padding: 16px 20px;
    background: var(--bg4);
    color: var(--text3);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--border);
    text-align: left;
}

.complaints-data-table td {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    color: var(--text2);
    font-size: 13px;
    vertical-align: middle;
}

.complaint-row {
    cursor: pointer;
    transition: background 0.2s;
}

.complaint-row:hover {
    background: var(--bg4);
}

.complaint-number {
    font-family: monospace;
    font-size: 12px;
    color: var(--accent2);
    background: rgba(108, 99, 255, 0.1);
    padding: 4px 10px;
    border-radius: 6px;
    display: inline-block;
}

/* Priority Badges */
.priority-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
}

.priority-low {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.priority-medium {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

.priority-high {
    background: rgba(244, 63, 94, 0.15);
    color: #f43f5e;
}

/* Status Badges */
.status-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
}

.status-pending {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

.status-inprogress {
    background: rgba(56, 189, 248, 0.15);
    color: #38bdf8;
}

.status-resolved {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

/* Flag Badges */
.flag-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 500;
}

.flag-badge.suspicious {
    background: rgba(244, 63, 94, 0.15);
    color: #f43f5e;
    border: 1px solid rgba(244, 63, 94, 0.3);
}

.flag-badge.normal {
    color: var(--text3);
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn-assign {
    background: rgba(16, 185, 129, 0.15);
    border: 1px solid rgba(16, 185, 129, 0.3);
    border-radius: 8px;
    padding: 6px 14px;
    color: #10b981;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-assign:hover {
    background: rgba(16, 185, 129, 0.25);
    transform: translateY(-1px);
}

.assigned-badge {
    display: inline-block;
    padding: 5px 10px;
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 500;
}

.btn-view {
    background: rgba(108, 99, 255, 0.15);
    border: 1px solid rgba(108, 99, 255, 0.3);
    border-radius: 8px;
    padding: 6px 14px;
    color: #a78bfa;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-view:hover {
    background: rgba(108, 99, 255, 0.25);
    transform: translateY(-1px);
}

.view-files-btn {
    background: #10b981;
    color: white;
    border: none;
    padding: 5px 12px;
    border-radius: 6px;
    font-size: 11px;
    cursor: pointer;
    transition: all 0.2s;
}

.view-files-btn:hover {
    background: #059669;
    transform: translateY(-1px);
}

.no-files {
    color: var(--text3);
}

/* Empty State */
.empty-row td {
    padding: 60px 20px !important;
    text-align: center;
}

.empty-state {
    text-align: center;
}

.empty-icon {
    font-size: 64px;
    margin-bottom: 16px;
    opacity: 0.5;
}

.empty-text {
    font-size: 18px;
    font-weight: 600;
    color: var(--text);
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
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 32px;
    max-width: 600px;
    margin: 20px;
}

.modal-icon {
    font-size: 48px;
    text-align: center;
    margin-bottom: 16px;
}

.modal-title {
    font-size: 22px;
    font-weight: 700;
    text-align: center;
    margin-bottom: 12px;
    color: var(--text);
}

.modal-text {
    color: var(--text2);
    font-size: 14px;
    text-align: center;
    margin-bottom: 20px;
}

.modal-buttons {
    display: flex;
    gap: 12px;
    justify-content: center;
}

.btn-ghost {
    background: var(--bg3);
    color: var(--text2);
    border: 1px solid var(--border);
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
}

.btn-primary {
    background: var(--accent);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
}

.files-list {
    max-height: 400px;
    overflow-y: auto;
    margin: 20px 0;
}

.file-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    background: var(--bg3);
    border-radius: 8px;
    margin-bottom: 8px;
}

.view-file-btn {
    background: var(--accent);
    color: white;
    border: none;
    padding: 4px 12px;
    border-radius: 6px;
    cursor: pointer;
}

.preview-content {
    margin: 20px 0;
    text-align: center;
}

.preview-content img {
    max-width: 100%;
    max-height: 70vh;
    border-radius: 8px;
}

/* Responsive */
@media (max-width: 768px) {
    .filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-wrapper {
        width: 100%;
    }
    
    .filter-select {
        width: 100%;
    }
    
    .btn-filter, .btn-clear {
        text-align: center;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .complaints-data-table th {
        display: none;
    }
    
    .complaints-data-table td {
        display: block;
        padding: 12px 16px;
        text-align: right;
        position: relative;
    }
    
    .complaints-data-table td::before {
        content: attr(data-label);
        position: absolute;
        left: 16px;
        top: 12px;
        font-size: 11px;
        font-weight: 600;
        color: var(--text3);
    }
    
    .complaints-data-table tr {
        display: block;
        margin-bottom: 16px;
        border: 1px solid var(--border);
        border-radius: 12px;
    }
    
    .top-message {
        min-width: 280px;
        max-width: 90%;
    }
}
</style>

<script>
// Set the base URL for your project
const BASE_URL = '/a1/';

function viewComplaint(id) { 
    window.location.href = 'complaint-detail.php?id=' + id; 
}

function showAssignModal(id, num) {
    document.getElementById('assign_complaint_id').value = id;
    document.getElementById('assignModalText').innerHTML = `Assign complaint <strong>${num}</strong> to an investigation officer:`;
    document.getElementById('assignModal').style.display = 'flex';
}

function closeAssignModal() { 
    document.getElementById('assignModal').style.display = 'none'; 
}

function showFiles(files, num) {
    document.getElementById('filesModalTitle').innerHTML = `Evidence Files - ${num}`;
    let html = '';
    files.forEach(f => {
        let icon = f.type && f.type.startsWith('image/') ? '🖼️' : (f.type === 'application/pdf' ? '📕' : '📄');
        html += `<div class="file-item">
                    <div><span>${icon}</span> ${escapeHtml(f.name)}</div>
                    <button class="view-file-btn" onclick="previewFile('${f.path}', '${f.type}')">👁️ View</button>
                </div>`;
    });
    document.getElementById('filesList').innerHTML = html;
    document.getElementById('filesModal').style.display = 'flex';
}

function previewFile(path, type) {
    // Remove any leading slashes
    let cleanPath = path.replace(/^\/+/, '');
    
    // Build URL with base path
    let url = BASE_URL + cleanPath;
    
    // Remove any double slashes
    url = url.replace(/\/\//g, '/');
    
    // Log for debugging
    console.log('File path from DB:', path);
    console.log('Full URL:', url);
    
    let content = '';
    if (type && type.startsWith('image/')) {
        content = `<img src="${url}" style="max-width:100%;max-height:70vh;border-radius:8px;" 
                         onerror="console.error('Image load failed:', '${url}');this.style.display='none';this.parentElement.innerHTML='<div style=\\'padding:40px;text-align:center;color:var(--text2);\\'>❌ Image could not be loaded.<br><small style=\\'color:var(--text3);\\'>Path: ${url}</small><br><a href=\\'${url}\\' download style=\\'color:var(--accent);\\'>📥 Download file instead</a></div>'">`;
    } else if (type === 'application/pdf') {
        content = `<iframe src="${url}#toolbar=0" style="width:100%;height:70vh;border:none;"></iframe>`;
    } else {
        content = `<div style="padding:40px;text-align:center;">
                    <div style="font-size:48px;margin-bottom:16px;">📄</div>
                    <p style="color:var(--text2);margin-bottom:16px;">Preview not available for this file type</p>
                    <a href="${url}" download class="btn btn-primary">📥 Download File</a>
                </div>`;
    }
    document.getElementById('previewContent').innerHTML = content;
    closeFilesModal();
    document.getElementById('previewModal').style.display = 'flex';
}

function closeFilesModal() { 
    document.getElementById('filesModal').style.display = 'none'; 
}

function closePreviewModal() { 
    document.getElementById('previewModal').style.display = 'none'; 
    document.getElementById('previewContent').innerHTML = ''; 
}

function escapeHtml(t) { 
    let d = document.createElement('div'); 
    d.textContent = t; 
    return d.innerHTML; 
}

// Close modals when clicking outside
document.addEventListener('click', function(e) {
    if (e.target === document.getElementById('filesModal')) closeFilesModal();
    if (e.target === document.getElementById('previewModal')) closePreviewModal();
    if (e.target === document.getElementById('assignModal')) closeAssignModal();
});

// Close modals with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeFilesModal();
        closePreviewModal();
        closeAssignModal();
    }
});

// Make entire row clickable for view
document.querySelectorAll('.complaint-row').forEach(row => {
    const viewBtn = row.querySelector('.btn-view');
    const assignBtn = row.querySelector('.btn-assign');
    const filesBtn = row.querySelector('.view-files-btn');
    
    row.addEventListener('click', function(e) {
        if (e.target.closest('.btn-view')) return;
        if (e.target.closest('.btn-assign')) return;
        if (e.target.closest('.view-files-btn')) return;
        
        const complaintId = this.getAttribute('data-id');
        if (complaintId) {
            window.location.href = 'complaint-detail.php?id=' + complaintId;
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
<?php
require_once 'config/database.php';
redirectIfNotAdmin();

$pageTitle = 'User Management';
$conn = getConnection();

// Handle Delete User
if (isset($_GET['action']) && isset($_GET['id']) && $_GET['action'] == 'delete') {
    $user_id = intval($_GET['id']);
    
    // Delete user and their complaints
    $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $delete_stmt->bind_param("i", $user_id);
    if ($delete_stmt->execute()) {
        $message = "User deleted successfully!";
        addActivityLog($_SESSION['admin_id'], "Deleted user ID: $user_id", $_SERVER['REMOTE_ADDR']);
    } else {
        $message = "Failed to delete user.";
    }
    $delete_stmt->close();
    
    // Refresh the page to show updated data
    header("Location: admin-users.php?msg=" . urlencode($message));
    exit();
}

// Handle Export (Excel only)
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    $users_data = $conn->query("SELECT u.*, COUNT(c.id) as complaint_count FROM users u LEFT JOIN complaints c ON u.id = c.user_id GROUP BY u.id ORDER BY u.created_at DESC");
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d') . '.xls"');
    
    echo '<table border="1">';
    echo '<tr><th>ID</th><th>Full Name</th><th>Email</th><th>Phone</th><th>Complaints</th><th>Joined Date</th></tr>';
    
    while ($row = $users_data->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . $row['id'] . '</td>';
        echo '<td>' . $row['name'] . '</td>';
        echo '<td>' . $row['email'] . '</td>';
        echo '<td>' . ($row['phone'] ?: 'Not provided') . '</td>';
        echo '<td>' . $row['complaint_count'] . '</td>';
        echo '<td>' . date('Y-m-d', strtotime($row['created_at'])) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    exit();
}

// Get message from URL if any
$message = $_GET['msg'] ?? '';
$users = $conn->query("SELECT u.*, COUNT(c.id) as complaint_count FROM users u LEFT JOIN complaints c ON u.id = c.user_id GROUP BY u.id ORDER BY u.created_at DESC");
$conn->close();
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="users-management-container">
    <div class="users-header">
        <div class="section-header">
            <div class="section-title">👥 Registered Users</div>
            <div class="header-actions">
                <button class="btn btn-ghost btn-sm" onclick="exportUsers()">📈 Export Excel</button>
            </div>
        </div>
    </div>

    <div class="users-table-container">
        <table class="users-data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Complaints</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($users->num_rows == 0): ?>
                    <tr>
                        <td colspan="7" class="empty-table">
                            <div class="empty-state">
                                <div class="empty-icon">👥</div>
                                <div class="empty-text">No users found</div>
                            </div>
                          </td>
                      </tr>
                <?php else: ?>
                    <?php while($u = $users->fetch_assoc()): ?>
                    <tr class="user-row" data-user-id="<?php echo $u['id']; ?>">
                        <td data-label="ID">
                            <span class="user-id">#<?php echo $u['id']; ?></span>
                        </td>
                        <td data-label="Full Name">
                            <div class="user-name">
                                <span class="user-avatar"><?php echo strtoupper(substr($u['name'], 0, 2)); ?></span>
                                <?php echo htmlspecialchars($u['name']); ?>
                            </div>
                        </td>
                        <td data-label="Email"><?php echo htmlspecialchars($u['email']); ?></td>
                        <td data-label="Phone"><?php echo htmlspecialchars($u['phone']) ?: '—'; ?></td>
                        <td data-label="Complaints">
                            <span class="complaint-count-badge <?php echo $u['complaint_count'] > 0 ? 'has-complaints' : 'no-complaints'; ?>">
                                <?php echo $u['complaint_count']; ?> complaint<?php echo $u['complaint_count'] != 1 ? 's' : ''; ?>
                            </span>
                        </td>
                        <td data-label="Joined"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                        <td data-label="Actions">
                            <div class="action-buttons">
                                <button class="btn-view-user" onclick="viewUser(<?php echo $u['id']; ?>, '<?php echo addslashes($u['name']); ?>', '<?php echo addslashes($u['email']); ?>', '<?php echo addslashes($u['phone']); ?>', <?php echo $u['complaint_count']; ?>, '<?php echo $u['created_at']; ?>')">
                                    👁️ View
                                </button>
                                <button class="btn-delete-user" onclick="deleteUser(<?php echo $u['id']; ?>, '<?php echo addslashes($u['name']); ?>')">
                                    🗑️ Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- View User Modal -->
<div id="viewUserModal" class="modal">
    <div class="modal-content user-detail-modal">
        <div class="modal-header">
            <h3>👤 User Details</h3>
            <button class="modal-close" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="modal-body" id="userDetailsContent">
            <!-- User details will be loaded here -->
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeViewModal()">Close</button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content delete-modal">
        <div class="modal-icon">🗑️</div>
        <div class="modal-title">Confirm Deletion</div>
        <div class="modal-text" id="deleteMessage">Are you sure you want to permanently delete this user? This action cannot be undone.</div>
        <div class="modal-buttons">
            <button class="btn btn-ghost" onclick="closeDeleteModal()">Cancel</button>
            <button class="btn btn-danger" id="confirmDeleteBtn">Yes, Delete</button>
        </div>
    </div>
</div>

<!-- Success Toast Notification -->
<div id="successToast" class="toast-notification">
    <div class="toast-content">
        <span class="toast-icon">✅</span>
        <span class="toast-message" id="toastMessage"></span>
    </div>
</div>

<style>
.users-management-container {
    max-width: 1400px;
    margin: 0 auto;
}

.users-header {
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
}

.header-actions {
    display: flex;
    gap: 12px;
}

/* Users Table */
.users-table-container {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow-x: auto;
}

.users-data-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 900px;
}

.users-data-table th {
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

.users-data-table td {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    color: var(--text2);
    font-size: 13px;
    vertical-align: middle;
}

.user-row {
    transition: background 0.2s;
}

.user-row:hover {
    background: var(--bg4);
}

.user-row:last-child td {
    border-bottom: none;
}

/* User ID */
.user-id {
    font-family: var(--mono);
    font-size: 12px;
    color: var(--accent2);
    background: rgba(108,99,255,0.1);
    padding: 4px 10px;
    border-radius: 6px;
    display: inline-block;
}

/* User Name with Avatar */
.user-name {
    display: flex;
    align-items: center;
    gap: 10px;
}

.user-avatar {
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, var(--accent), var(--accent3));
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
    color: white;
}

/* Complaint Count Badge */
.complaint-count-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-family: var(--mono);
    font-weight: 500;
}

.complaint-count-badge.has-complaints {
    background: rgba(108,99,255,0.15);
    color: var(--accent2);
}

.complaint-count-badge.no-complaints {
    background: rgba(100,100,120,0.15);
    color: var(--text3);
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn-view-user {
    background: rgba(108,99,255,0.15);
    border: 1px solid rgba(108,99,255,0.3);
    border-radius: 6px;
    padding: 6px 12px;
    color: var(--accent2);
    font-size: 11px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-view-user:hover {
    background: rgba(108,99,255,0.25);
    transform: translateY(-1px);
}

.btn-delete-user {
    background: rgba(244,63,94,0.15);
    border: 1px solid rgba(244,63,94,0.3);
    border-radius: 6px;
    padding: 6px 12px;
    color: var(--danger);
    font-size: 11px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-delete-user:hover {
    background: rgba(244,63,94,0.25);
    transform: translateY(-1px);
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
    max-width: 500px;
    width: 90%;
    animation: modalSlideIn 0.3s ease;
}

.user-detail-modal {
    max-width: 600px;
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

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
}

.modal-header h3 {
    font-family: var(--display);
    font-size: 18px;
    color: var(--text);
}

.modal-close {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: var(--text3);
}

.modal-close:hover {
    color: var(--danger);
}

.modal-body {
    padding: 24px;
}

.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: flex-end;
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

/* User Detail Styles */
.user-detail-row {
    display: flex;
    padding: 12px 0;
    border-bottom: 1px solid var(--border);
}

.user-detail-row:last-child {
    border-bottom: none;
}

.user-detail-label {
    width: 120px;
    font-size: 11px;
    font-family: var(--mono);
    color: var(--text3);
    text-transform: uppercase;
}

.user-detail-value {
    flex: 1;
    color: var(--text);
    font-size: 14px;
}

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
    font-size: 14px;
    color: var(--text2);
}

/* Toast Notification */
.toast-notification {
    visibility: hidden;
    min-width: 300px;
    background: var(--card);
    border: 1px solid var(--success);
    border-radius: 12px;
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 10001;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    animation: slideInRight 0.3s ease;
}

.toast-notification.show {
    visibility: visible;
    animation: slideInRight 0.3s ease;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.toast-content {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 20px;
}

.toast-icon {
    font-size: 20px;
}

.toast-message {
    color: var(--text);
    font-size: 14px;
    font-weight: 500;
}

/* Responsive */
@media (max-width: 768px) {
    .users-data-table th {
        display: none;
    }
    
    .users-data-table td {
        display: block;
        padding: 12px 16px;
        text-align: right;
        position: relative;
        border-bottom: none;
    }
    
    .users-data-table td::before {
        content: attr(data-label);
        position: absolute;
        left: 16px;
        top: 12px;
        font-size: 11px;
        font-family: var(--mono);
        color: var(--text3);
        font-weight: 500;
    }
    
    .users-data-table tr {
        display: block;
        margin-bottom: 16px;
        border: 1px solid var(--border);
        border-radius: 12px;
    }
    
    .action-buttons {
        justify-content: flex-end;
    }
    
    .header-actions {
        width: 100%;
    }
    
    .toast-notification {
        left: 20px;
        right: 20px;
        min-width: auto;
    }
}
</style>

<script>
let currentUserId = null;
let currentUserName = '';

function showSuccessToast(message) {
    const toast = document.getElementById('successToast');
    const toastMessage = document.getElementById('toastMessage');
    toastMessage.textContent = message;
    toast.classList.add('show');
    
    setTimeout(function() {
        toast.classList.remove('show');
    }, 3000);
}

function exportUsers() {
    window.location.href = 'admin-users.php?export=excel';
}

function viewUser(id, name, email, phone, complaints, joined) {
    const modal = document.getElementById('viewUserModal');
    const content = document.getElementById('userDetailsContent');
    
    content.innerHTML = `
        <div class="user-detail-row">
            <div class="user-detail-label">User ID</div>
            <div class="user-detail-value">#${id}</div>
        </div>
        <div class="user-detail-row">
            <div class="user-detail-label">Full Name</div>
            <div class="user-detail-value">${name}</div>
        </div>
        <div class="user-detail-row">
            <div class="user-detail-label">Email</div>
            <div class="user-detail-value">${email}</div>
        </div>
        <div class="user-detail-row">
            <div class="user-detail-label">Phone</div>
            <div class="user-detail-value">${phone || 'Not provided'}</div>
        </div>
        <div class="user-detail-row">
            <div class="user-detail-label">Total Complaints</div>
            <div class="user-detail-value">${complaints}</div>
        </div>
        <div class="user-detail-row">
            <div class="user-detail-label">Member Since</div>
            <div class="user-detail-value">${new Date(joined).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</div>
        </div>
    `;
    
    modal.style.display = 'flex';
}

function closeViewModal() {
    document.getElementById('viewUserModal').style.display = 'none';
}

function deleteUser(id, name) {
    currentUserId = id;
    currentUserName = name;
    const modal = document.getElementById('deleteModal');
    const message = document.getElementById('deleteMessage');
    message.innerHTML = `Are you sure you want to permanently delete <strong>${name}</strong>? This will also remove all their complaints and cannot be undone.`;
    modal.style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

// Check for success message from URL and show toast
window.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const msg = urlParams.get('msg');
    if (msg) {
        showSuccessToast(msg);
        // Remove the message from URL without refreshing
        const newUrl = window.location.pathname;
        window.history.replaceState({}, document.title, newUrl);
    }
});

// Confirm Delete
document.getElementById('confirmDeleteBtn')?.addEventListener('click', function() {
    if (currentUserId) {
        window.location.href = `admin-users.php?action=delete&id=${currentUserId}`;
    }
});

// Close modals when clicking outside
window.addEventListener('click', function(e) {
    const viewModal = document.getElementById('viewUserModal');
    const deleteModal = document.getElementById('deleteModal');
    
    if (e.target === viewModal) closeViewModal();
    if (e.target === deleteModal) closeDeleteModal();
});
</script>

<?php include 'includes/footer.php'; ?>
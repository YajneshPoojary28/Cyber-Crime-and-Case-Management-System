<?php
// Make sure database.php is included before using getConnection()
// This should already be included in your main files

$isAdmin = isset($_SESSION['admin_id']);
$isOfficer = isset($_SESSION['officer_id']);
$isCitizen = isset($_SESSION['user_id']) && !$isAdmin && !$isOfficer;

// Set user name and role based on login type
if ($isAdmin) {
    $userName = $_SESSION['admin_name'] ?? 'Admin';
    $userRole = $_SESSION['admin_role'] ?? 'Super Admin';
    $initials = strtoupper(substr($userName, 0, 2));
} elseif ($isOfficer) {
    $userName = $_SESSION['officer_name'] ?? 'Police Officer';
    $userRole = $_SESSION['officer_rank'] ?? 'Investigation Officer';
    $initials = strtoupper(substr($userName, 0, 2));
} else {
    $userName = $_SESSION['user_name'] ?? 'Citizen';
    $userRole = 'Citizen';
    $initials = strtoupper(substr($userName, 0, 2));
}

$currentPage = basename($_SERVER['PHP_SELF']);

// Get unread notifications count for citizens
$unreadCount = 0;
if ($isCitizen && isset($_SESSION['user_id'])) {
    if (function_exists('getConnection')) {
        try {
            $conn = getConnection();
            $userId = $_SESSION['user_id'];
            
            // Check if notifications table has user_id column
            $checkColumns = $conn->query("SHOW COLUMNS FROM notifications");
            $hasUserId = false;
            $hasUserType = false;
            while ($col = $checkColumns->fetch_assoc()) {
                if ($col['Field'] == 'user_id') $hasUserId = true;
                if ($col['Field'] == 'user_type') $hasUserType = true;
            }
            
            if ($hasUserId) {
                $notifQuery = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
                $notifQuery->bind_param("i", $userId);
                $notifQuery->execute();
                $result = $notifQuery->get_result();
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $unreadCount = $row['unread'];
                }
                $notifQuery->close();
            }
            $conn->close();
        } catch (Exception $e) {
            // Table might not exist yet
            $unreadCount = 0;
        }
    }
}

// Get unread notifications count for admin
$adminUnreadCount = 0;
if ($isAdmin) {
    if (function_exists('getConnection')) {
        try {
            $conn = getConnection();
            // Try different possible column structures
            $notifQuery = $conn->query("SELECT COUNT(*) as unread FROM notifications WHERE is_read = 0");
            if ($notifQuery) {
                $row = $notifQuery->fetch_assoc();
                $adminUnreadCount = $row['unread'];
            }
            $conn->close();
        } catch (Exception $e) {
            $adminUnreadCount = 0;
        }
    }
}

// Get theme preference from cookie
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'dark';
?>
<aside class="sidebar">
    <div class="logo">
        <div class="logo-mark">
            <div class="logo-icon">🛡️</div>
            <div>
                <div class="logo-text">CyberShield</div>
                <div class="logo-sub">
                    <?php 
                    if ($isAdmin) {
                        echo 'Admin Console';
                    } elseif ($isOfficer) {
                        echo 'Officer Portal';
                    } else {
                        echo 'Reporting Portal';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <nav class="nav-section">
        <div class="nav-label">
            <?php 
            if ($isAdmin) {
                echo 'Admin';
            } elseif ($isOfficer) {
                echo 'Officer';
            } else {
                echo 'Navigation';
            }
            ?>
        </div>
        
        <?php if ($isCitizen): ?>
            <!-- Citizen Navigation -->
            <button class="nav-item <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>" onclick="location.href='dashboard.php'">
                <span class="nav-icon">🏠</span>
                <span>Dashboard</span>
            </button>
            
            <button class="nav-item <?php echo $currentPage == 'file-complaint.php' ? 'active' : ''; ?>" onclick="location.href='file-complaint.php'">
                <span class="nav-icon">📝</span>
                <span>File Complaint</span>
            </button>
            
            <button class="nav-item <?php echo $currentPage == 'my-complaints.php' ? 'active' : ''; ?>" onclick="location.href='my-complaints.php'">
                <span class="nav-icon">📋</span>
                <span>My Complaints</span>
            </button>
            
            <button class="nav-item <?php echo $currentPage == 'notifications.php' ? 'active' : ''; ?>" onclick="location.href='notifications.php'">
                <span class="nav-icon">🔔</span>
                <span>Notifications</span>
                <?php if ($unreadCount > 0): ?>
                    <span class="badge"><?php echo $unreadCount; ?></span>
                <?php endif; ?>
            </button>
            
            <button class="nav-item <?php echo $currentPage == 'guidelines.php' ? 'active' : ''; ?>" onclick="location.href='guidelines.php'">
                <span class="nav-icon">📖</span>
                <span>Guidelines</span>
            </button>
            
            <button class="nav-item <?php echo $currentPage == 'profile.php' ? 'active' : ''; ?>" onclick="location.href='profile.php'">
                <span class="nav-icon">👤</span>
                <span>My Profile</span>
            </button>
            
            <button class="nav-item logout-nav-item" onclick="showLogoutModal()">
                <span class="nav-icon">🚪</span>
                <span>Logout</span>
            </button>
            
        <?php elseif ($isOfficer): ?>
            <!-- Officer Navigation -->
            <button class="nav-item <?php echo $currentPage == 'officer-dashboard.php' ? 'active' : ''; ?>" onclick="location.href='officer-dashboard.php'">
                <span class="nav-icon">📊</span>
                <span>Dashboard</span>
            </button>
            
            <button class="nav-item <?php echo $currentPage == 'officer-complaints.php' ? 'active' : ''; ?>" onclick="location.href='officer-complaints.php'">
                <span class="nav-icon">📋</span>
                <span>My Complaints</span>
            </button>
            
            <button class="nav-item logout-nav-item" onclick="showLogoutModal()">
                <span class="nav-icon">🚪</span>
                <span>Logout</span>
            </button>
            
        <?php elseif ($isAdmin): ?>
            <!-- Admin Navigation -->
            <button class="nav-item <?php echo $currentPage == 'admin-dashboard.php' ? 'active' : ''; ?>" onclick="location.href='admin-dashboard.php'">
                <span class="nav-icon">📊</span>
                <span>Dashboard</span>
            </button>
            
            <button class="nav-item <?php echo $currentPage == 'admin-complaints.php' ? 'active' : ''; ?>" onclick="location.href='admin-complaints.php'">
                <span class="nav-icon">📁</span>
                <span>All Complaints</span>
            </button>
            
            <button class="nav-item <?php echo $currentPage == 'admin-users.php' ? 'active' : ''; ?>" onclick="location.href='admin-users.php'">
                <span class="nav-icon">👥</span>
                <span>Users</span>
            </button>
            
            <button class="nav-item <?php echo $currentPage == 'admin-reports.php' ? 'active' : ''; ?>" onclick="location.href='admin-reports.php'">
                <span class="nav-icon">📈</span>
                <span>Reports</span>
            </button>
            
            <button class="nav-item <?php echo $currentPage == 'admin-notifications.php' ? 'active' : ''; ?>" onclick="location.href='admin-notifications.php'">
                <span class="nav-icon">🔔</span>
                <span>Notifications</span>
                <?php if ($adminUnreadCount > 0): ?>
                    <span class="badge"><?php echo $adminUnreadCount; ?></span>
                <?php endif; ?>
            </button>
            
            <button class="nav-item <?php echo $currentPage == 'admin-logs.php' ? 'active' : ''; ?>" onclick="location.href='admin-logs.php'">
                <span class="nav-icon">🔐</span>
                <span>Activity Logs</span>
            </button>
            
            <button class="nav-item <?php echo $currentPage == 'guidelines.php' ? 'active' : ''; ?>" onclick="location.href='guidelines.php'">
                <span class="nav-icon">📖</span>
                <span>Guidelines</span>
            </button>
            
            <button class="nav-item logout-nav-item" onclick="showLogoutModal()">
                <span class="nav-icon">🚪</span>
                <span>Logout</span>
            </button>
        <?php endif; ?>
    </nav>
    
    <!-- Admin/ Officer Login Links for Citizens -->
    <?php if ($isCitizen): ?>
        <div class="nav-section" style="margin-top: auto;">
            <div class="nav-label">Other</div>
            <button class="nav-item" onclick="location.href='admin-login.php'">
                <span class="nav-icon">🔑</span>
                <span>Admin Login</span>
            </button>
            <button class="nav-item" onclick="location.href='officer-login.php'">
                <span class="nav-icon">👮</span>
                <span>Officer Login</span>
            </button>
        </div>
    <?php endif; ?>
    
    <!-- Sidebar Footer with User Info -->
    <div class="sidebar-footer">
        <div class="user-chip" onclick="location.href='<?php 
            if ($isAdmin) {
                echo 'admin-dashboard.php';
            } elseif ($isOfficer) {
                echo 'officer-dashboard.php';
            } else {
                echo 'profile.php';
            } 
        ?>'">
            <div class="avatar"><?php echo $initials; ?></div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($userName); ?></div>
                <div class="user-role"><?php echo $userRole; ?></div>
            </div>
        </div>
    </div>
</aside>

<div class="main">
    <div class="topbar">
        <div class="topbar-left">
            <div class="topbar-title"><?php echo $pageTitle ?? 'Dashboard'; ?></div>
            <div class="topbar-breadcrumb">CyberShield / <?php echo $pageTitle ?? 'Dashboard'; ?></div>
        </div>
        <div class="topbar-right">
            <!-- Theme Toggle Button -->
            <button class="theme-toggle-btn" onclick="toggleTheme()" title="Toggle Theme">
                <span id="themeIcon"><?php echo $theme == 'dark' ? '🌙' : '☀️'; ?></span>
                <span><?php echo $theme == 'dark' ? 'Dark' : 'Light'; ?></span>
            </button>
        </div>
    </div>
    <div class="content">

<style>
/* Logout Nav Item Styling */
.logout-nav-item {
    margin-top: 16px !important;
    border-top: 1px solid var(--border);
    border-radius: 0 !important;
    color: var(--danger) !important;
}

.logout-nav-item:hover {
    background: rgba(244, 63, 94, 0.1) !important;
    color: var(--danger) !important;
}

.logout-nav-item .nav-icon {
    color: var(--danger);
}

/* Topbar Layout */
.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.topbar-left {
    display: flex;
    align-items: center;
    gap: 16px;
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

/* Theme Toggle Button */
.theme-toggle-btn {
    background: var(--bg4);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 8px 16px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 500;
    color: var(--text2);
}

.theme-toggle-btn:hover {
    background: var(--bg3);
    transform: translateY(-1px);
    border-color: var(--accent);
}

.theme-toggle-btn span:first-child {
    font-size: 16px;
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
    padding: 32px;
    max-width: 400px;
    margin: 20px;
    text-align: center;
    animation: modalSlideIn 0.3s ease;
}
@keyframes modalSlideIn {
    from { transform: translateY(-50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
.modal-icon { font-size: 56px; margin-bottom: 16px; }
.modal-title { font-family: var(--display); font-size: 22px; font-weight: 700; color: var(--text); margin-bottom: 12px; }
.modal-text { color: var(--text2); font-size: 14px; line-height: 1.6; margin-bottom: 24px; }
.modal-buttons { display: flex; gap: 12px; justify-content: center; }
.modal-buttons .btn { padding: 10px 24px; }

/* User chip styling */
.user-chip {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: var(--bg4);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
}
.user-chip:hover {
    background: var(--bg3);
    transform: translateX(2px);
}
.avatar {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--accent), var(--accent3));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: 700;
    font-family: var(--display);
    color: white;
    flex-shrink: 0;
}
.user-info {
    flex: 1;
    min-width: 0;
}
.user-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.user-role {
    font-size: 11px;
    color: var(--text3);
    font-family: var(--mono);
    margin-top: 2px;
}

/* Button Styles */
.btn {
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    font-size: 13px;
}

.btn-ghost {
    background: var(--bg3);
    color: var(--text2);
    border: 1px solid var(--border);
}

.btn-ghost:hover {
    background: var(--bg2);
}

.btn-danger {
    background: #f43f5e;
    color: white;
}

.btn-danger:hover {
    background: #e11d48;
    transform: translateY(-1px);
}
</style>

<script>
function showLogoutModal() {
    document.getElementById('logoutModal').style.display = 'flex';
}
function closeLogoutModal() {
    document.getElementById('logoutModal').style.display = 'none';
}
function proceedLogout() {
    <?php 
    if ($isAdmin) {
        echo "window.location.href = 'admin-logout.php';";
    } elseif ($isOfficer) {
        echo "window.location.href = 'logout.php';";
    } else {
        echo "window.location.href = 'logout.php';";
    }
    ?>
}

// Theme switching function
function setTheme(theme) {
    document.cookie = "theme=" + theme + "; path=/; max-age=" + (365 * 24 * 60 * 60);
    applyTheme(theme);
    
    const themeIcon = document.getElementById('themeIcon');
    const themeText = document.querySelector('.theme-toggle-btn span:last-child');
    if (themeIcon) {
        themeIcon.textContent = theme === 'dark' ? '🌙' : '☀️';
    }
    if (themeText) {
        themeText.textContent = theme === 'dark' ? 'Dark' : 'Light';
    }
}

function toggleTheme() {
    const currentTheme = getCookie('theme') || 'dark';
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    setTheme(newTheme);
}

function applyTheme(theme) {
    const root = document.documentElement;
    
    if (theme === 'dark') {
        root.style.setProperty('--bg', '#09090f');
        root.style.setProperty('--bg2', '#0f0f1a');
        root.style.setProperty('--bg3', '#14141f');
        root.style.setProperty('--bg4', '#1a1a2a');
        root.style.setProperty('--border', 'rgba(120,120,255,0.12)');
        root.style.setProperty('--border2', 'rgba(120,120,255,0.22)');
        root.style.setProperty('--text', '#e8e8f0');
        root.style.setProperty('--text2', '#9090b0');
        root.style.setProperty('--text3', '#606080');
        root.style.setProperty('--card', '#111120');
        root.style.setProperty('--card2', '#161628');
    } else {
        root.style.setProperty('--bg', '#fef9f0');
        root.style.setProperty('--bg2', '#fdf5e6');
        root.style.setProperty('--bg3', '#faf0e1');
        root.style.setProperty('--bg4', '#f5e8d9');
        root.style.setProperty('--border', 'rgba(139,69,19,0.12)');
        root.style.setProperty('--border2', 'rgba(139,69,19,0.22)');
        root.style.setProperty('--text', '#4a3728');
        root.style.setProperty('--text2', '#6b5340');
        root.style.setProperty('--text3', '#8b6b50');
        root.style.setProperty('--card', '#fffaf5');
        root.style.setProperty('--card2', '#fff5e8');
    }
}

// Load theme on page load
window.addEventListener('DOMContentLoaded', function() {
    const theme = getCookie('theme');
    if (theme && (theme === 'dark' || theme === 'light')) {
        applyTheme(theme);
        const themeIcon = document.getElementById('themeIcon');
        const themeText = document.querySelector('.theme-toggle-btn span:last-child');
        if (themeIcon) {
            themeIcon.textContent = theme === 'dark' ? '🌙' : '☀️';
        }
        if (themeText) {
            themeText.textContent = theme === 'dark' ? 'Dark' : 'Light';
        }
    }
});

function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    const modal = document.getElementById('logoutModal');
    if (e.target === modal) {
        closeLogoutModal();
    }
});

// File preview functions
function showFiles(files) {
    const filesListDiv = document.getElementById('filesList');
    if (!filesListDiv) return;
    
    filesListDiv.innerHTML = '';
    
    files.forEach((file) => {
        const fileIcon = file.type && file.type.startsWith('image/') ? '🖼️' : (file.type === 'application/pdf' ? '📕' : '📄');
        
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';
        fileItem.innerHTML = `
            <div class="file-info">
                <span class="file-icon">${fileIcon}</span>
                <span class="file-name">${escapeHtml(file.name)}</span>
            </div>
            <button class="view-file-btn" onclick="previewFile('${file.path}', '${file.type}')">
                👁️ View
            </button>
        `;
        filesListDiv.appendChild(fileItem);
    });
    
    document.getElementById('filesModal').style.display = 'flex';
}

function previewFile(filePath, fileType) {
    const previewContent = document.getElementById('previewContent');
    if (!previewContent) return;
    
    const fullUrl = '/D1/' + filePath;
    
    if (fileType && fileType.startsWith('image/')) {
        previewContent.innerHTML = `<img src="${fullUrl}" alt="Evidence" style="max-width: 100%; max-height: 70vh;">`;
    } else if (fileType === 'application/pdf') {
        previewContent.innerHTML = `<iframe src="${fullUrl}#toolbar=0" style="width: 100%; height: 70vh; border: none;"></iframe>`;
    } else {
        previewContent.innerHTML = `<div style="padding: 40px; text-align: center;">
            <a href="${fullUrl}" download class="btn btn-primary">Download File</a>
        </div>`;
    }
    
    const filesModal = document.getElementById('filesModal');
    if (filesModal) filesModal.style.display = 'none';
    
    const previewModal = document.getElementById('previewModal');
    if (previewModal) previewModal.style.display = 'flex';
}

function closeFilesModal() { 
    const modal = document.getElementById('filesModal');
    if (modal) modal.style.display = 'none'; 
}

function closePreviewModal() { 
    const modal = document.getElementById('previewModal');
    if (modal) {
        modal.style.display = 'none';
        const previewContent = document.getElementById('previewContent');
        if (previewContent) previewContent.innerHTML = '';
    }
}

function escapeHtml(text) { 
    const div = document.createElement('div'); 
    div.textContent = text; 
    return div.innerHTML; 
}

// Close modals when clicking outside
document.addEventListener('click', function(e) {
    const filesModal = document.getElementById('filesModal');
    const previewModal = document.getElementById('previewModal');
    
    if (e.target === filesModal) closeFilesModal();
    if (e.target === previewModal) closePreviewModal();
});
</script>

<div id="logoutModal" class="modal">
    <div class="modal-content">
        <div class="modal-icon">🚪</div>
        <div class="modal-title">Confirm Logout</div>
        <div class="modal-text">Are you sure you want to logout from <?php 
            if ($isAdmin) {
                echo 'Admin Console';
            } elseif ($isOfficer) {
                echo 'Officer Portal';
            } else {
                echo 'CyberShield';
            } 
        ?>?<br>You will need to login again to access your account.</div>
        <div class="modal-buttons">
            <button onclick="closeLogoutModal()" class="btn btn-ghost">Cancel</button>
            <button onclick="proceedLogout()" class="btn btn-danger">Yes, Logout</button>
        </div>
    </div>
</div>
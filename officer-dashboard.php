<?php
// Remove ALL session_start() code - config/database.php already handles it
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if officer is logged in
if (!isset($_SESSION['officer_id'])) {
    header("Location: officer-login.php");
    exit();
}

$officer_id = $_SESSION['officer_id'];
$conn = getConnection();

// Get officer details
$officer_stmt = $conn->prepare("SELECT full_name, badge_number FROM investigation_officers WHERE id = ?");
$officer_stmt->bind_param("i", $officer_id);
$officer_stmt->execute();
$officer_info = $officer_stmt->get_result()->fetch_assoc();

// If officer not found in database, logout
if (!$officer_info) {
    session_destroy();
    header("Location: officer-login.php");
    exit();
}

// Get all complaints (no LIMIT)
$sql = "SELECT c.*, u.name as citizen_name 
        FROM complaints c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.assigned_to = ? 
        ORDER BY c.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $officer_id);
$stmt->execute();
$result_set = $stmt->get_result();

// Store in array
$assigned_complaints = array();
while ($row = $result_set->fetch_assoc()) {
    $assigned_complaints[] = $row;
}
$total_assigned = count($assigned_complaints);

// Get statistics
$stats = array();

$total_query = $conn->prepare("SELECT COUNT(*) as count FROM complaints WHERE assigned_to = ?");
$total_query->bind_param("i", $officer_id);
$total_query->execute();
$stats['total'] = $total_query->get_result()->fetch_assoc()['count'];

// PENDING complaints (officer needs to review)
$pending_query = $conn->prepare("SELECT COUNT(*) as count FROM complaints WHERE assigned_to = ? AND status = 'pending'");
$pending_query->bind_param("i", $officer_id);
$pending_query->execute();
$stats['pending'] = $pending_query->get_result()->fetch_assoc()['count'];

// FAKE complaints (officer marked as fake)
$fake_query = $conn->prepare("SELECT COUNT(*) as count FROM complaints WHERE assigned_to = ? AND status = 'fake'");
$fake_query->bind_param("i", $officer_id);
$fake_query->execute();
$stats['fake'] = $fake_query->get_result()->fetch_assoc()['count'];

// RESOLVED complaints (real complaints resolved)
$resolved_query = $conn->prepare("SELECT COUNT(*) as count FROM complaints WHERE assigned_to = ? AND status = 'resolved'");
$resolved_query->bind_param("i", $officer_id);
$resolved_query->execute();
$stats['resolved'] = $resolved_query->get_result()->fetch_assoc()['count'];

// Get category data for pie chart
$cat_query = $conn->prepare("SELECT category, COUNT(*) as count FROM complaints WHERE assigned_to = ? GROUP BY category");
$cat_query->bind_param("i", $officer_id);
$cat_query->execute();
$cat_result = $cat_query->get_result();
$categories = array();
$category_counts = array();
while ($row = $cat_result->fetch_assoc()) {
    $categories[] = $row['category'];
    $category_counts[] = $row['count'];
}

// Get monthly trend data
$trend_query = $conn->prepare("SELECT DATE_FORMAT(created_at, '%b %Y') as month, COUNT(*) as count FROM complaints WHERE assigned_to = ? GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY created_at ASC LIMIT 6");
$trend_query->bind_param("i", $officer_id);
$trend_query->execute();
$trend_result = $trend_query->get_result();
$months = array();
$monthly_counts = array();
while ($row = $trend_result->fetch_assoc()) {
    $months[] = $row['month'];
    $monthly_counts[] = $row['count'];
}

$conn->close();

$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'dark';
$officer_name = $officer_info['full_name'] ?? 'Police Officer';
$name_parts = explode(' ', $officer_name);
$initials = '';
if (count($name_parts) >= 2) {
    $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
} else {
    $initials = strtoupper(substr($officer_name, 0, 2));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Dashboard - CyberShield</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="css/style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--bg); font-family: var(--sans); color: var(--text); overflow-x: hidden; }
        
        .sidebar {
            position: fixed; left: 0; top: 0; height: 100vh; width: 260px;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            padding: 20px; color: white; display: flex; flex-direction: column; z-index: 100;
            overflow-y: auto;
        }
        .sidebar h4 { margin-bottom: 30px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); font-size: 18px; }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8); padding: 12px 15px; margin: 5px 0;
            border-radius: 10px; transition: all 0.3s; display: flex;
            align-items: center; gap: 10px; text-decoration: none;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,0.1); color: white; }
        
        .officer-badge { margin-top: auto; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); text-align: center; }
        .officer-avatar { width: 48px; height: 48px; background: rgba(255,255,255,0.15); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-size: 18px; font-weight: 600; color: white; }
        .officer-name { font-size: 14px; font-weight: 600; margin-bottom: 3px; }
        .officer-role { font-size: 10px; opacity: 0.7; font-family: monospace; text-transform: uppercase; letter-spacing: 1px; }
        
        .main-content { margin-left: 260px; padding: 20px; min-height: 100vh; }
        
        .top-bar { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 16px 24px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
        .page-title h2 { font-size: 18px; font-weight: 600; margin: 0; color: var(--text); }
        .page-title p { font-size: 12px; color: var(--text3); margin: 4px 0 0 0; }
        .theme-toggle-btn { background: var(--bg4); border: 1px solid var(--border); border-radius: 30px; padding: 6px 16px; cursor: pointer; display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text2); }
        
        .stat-card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); transition: transform 0.3s; height: 100%; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card .card-body { padding: 20px; }
        .stat-card i { font-size: 2rem; opacity: 0.3; float: right; }
        .stat-card h3 { font-size: 28px; font-weight: 700; margin-bottom: 5px; }
        .stat-card p { font-size: 13px; margin: 0; opacity: 0.9; }
        
        .charts-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -12px 24px -12px;
        }
        .chart-col {
            flex: 0 0 50%;
            max-width: 50%;
            padding: 0 12px;
        }
        .chart-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .chart-card .card-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
            font-family: var(--display);
            flex-shrink: 0;
        }
        .chart-card .card-title i {
            margin-right: 8px;
            color: var(--accent);
        }
        .chart-card canvas {
            max-height: 280px;
            width: 100% !important;
            height: auto !important;
            flex-shrink: 1;
        }
        
        .main-card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; margin-top: 24px; }
        .main-card .card-header { background: var(--bg3); border-bottom: 1px solid var(--border); padding: 15px 20px; }
        .main-card .card-header h5 { margin: 0; color: var(--text); font-size: 16px; font-weight: 600; }
        .main-card .card-header h5 i { margin-right: 8px; color: var(--accent); }
        
        .table { color: var(--text2); margin-bottom: 0; width: 100%; }
        .table thead th { 
            background: var(--bg4); 
            color: var(--text3); 
            font-size: 11px; 
            font-weight: 600; 
            text-transform: uppercase; 
            padding: 14px 12px; 
            border-bottom: 1px solid var(--border);
            text-align: left;
        }
        .table tbody td { 
            padding: 12px; 
            vertical-align: middle; 
            border-bottom: 1px solid var(--border);
            text-align: left;
        }
        .table tbody tr:hover td { background: var(--bg3); }
        
        .btn-sm { 
            padding: 5px 10px; 
            font-size: 11px; 
            border-radius: 6px; 
            margin: 2px; 
            text-decoration: none; 
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-fake { background: #dc3545; color: white; border: none; cursor: pointer; }
        .btn-fake:hover { background: #c82333; }
        .btn-resolve { background: #28a745; color: white; border: none; cursor: pointer; }
        .btn-resolve:hover { background: #1e7e34; }
        .btn-view { background: #17a2b8; color: white; border: none; cursor: pointer; }
        .btn-view:hover { background: #138496; }
        
        .badge-pending { background: #ffc107; color: #333; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; }
        .badge-fake { background: #dc3545; color: white; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; }
        .badge-resolved { background: #28a745; color: white; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; }
        .badge-inprogress { background: #17a2b8; color: white; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; }
        
        .priority-high { background: #dc3545; color: white; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; }
        .priority-medium { background: #fd7e14; color: white; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; }
        .priority-low { background: #28a745; color: white; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; }
        
        .complaint-num {
            font-family: monospace;
            font-size: 12px;
            font-weight: 500;
            color: var(--accent2);
        }
        
        .alert-info {
            background: rgba(56, 189, 248, 0.15);
            border: 1px solid rgba(56, 189, 248, 0.3);
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 20px;
            color: #38bdf8;
            font-size: 13px;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.active { transform: translateX(0); width: 260px; }
            .main-content { margin-left: 0; width: 100%; }
            .mobile-toggle { display: block; position: fixed; top: 20px; left: 20px; z-index: 101; background: var(--accent); border: none; color: white; padding: 10px 15px; border-radius: 10px; cursor: pointer; }
            .chart-col { flex: 0 0 100%; max-width: 100%; margin-bottom: 20px; }
            .charts-row { flex-direction: column; }
        }
        @media (min-width: 769px) { 
            .mobile-toggle { display: none; }
            .table-responsive { overflow-x: auto; }
        }
    </style>
</head>
<body>
    <button class="mobile-toggle" id="mobileToggle"><i class="fas fa-bars"></i></button>

    <div class="sidebar" id="sidebar">
        <h4 class="text-center"><i class="fas fa-shield-alt"></i> CyberShield</h4>
        <div class="nav flex-column">
           <a href="officer-dashboard.php" class="nav-link active"><i class="fas fa-tachometer-alt"></i> 📊 Dashboard</a>
           <a href="officer-complaints.php" class="nav-link"><i class="fas fa-list"></i> 📋 My Complaints</a>
           <a href="officer-reports.php" class="nav-link"><i class="fas fa-chart-line"></i> 📈 Reports</a>
           <a href="logout.php" class="nav-link text-danger mt-2"><i class="fas fa-sign-out-alt"></i> 🚪 Logout</a>
        </div>
        <div class="officer-badge">
            <div class="officer-avatar"><?php echo $initials; ?></div>
            <div class="officer-name"><?php echo htmlspecialchars($officer_name); ?></div>
            <div class="officer-role">Police Officer</div>
        </div>
    </div>
    
    <div class="main-content">
        <div class="alert-info">
            <i class="fas fa-info-circle"></i> 
            <strong>Officer ID:</strong> <?php echo $officer_id; ?> | 
            <strong>Total Assigned:</strong> <?php echo $stats['total']; ?> complaints
        </div>
        
        <div class="top-bar">
            <div class="page-title">
                <h2>Officer Dashboard</h2>
                <p>CyberShield / Dashboard</p>
            </div>
            <button class="theme-toggle-btn" onclick="toggleTheme()">
                <span id="themeIcon"><?php echo $theme == 'dark' ? '🌙' : '☀️'; ?></span>
                <span><?php echo $theme == 'dark' ? 'Dark' : 'Light'; ?></span>
            </button>
        </div>
        
        <!-- Statistics Cards Row -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body">
                        <i class="fas fa-tasks fa-2x float-end"></i>
                        <h3><?php echo $stats['total']; ?></h3>
                        <p>Total Assigned</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body">
                        <i class="fas fa-clock fa-2x float-end"></i>
                        <h3><?php echo $stats['pending']; ?></h3>
                        <p>Pending Review</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-danger text-white">
                    <div class="card-body">
                        <i class="fas fa-exclamation-triangle fa-2x float-end"></i>
                        <h3><?php echo $stats['fake']; ?></h3>
                        <p>Fake Complaints</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <i class="fas fa-check-circle fa-2x float-end"></i>
                        <h3><?php echo $stats['resolved']; ?></h3>
                        <p>Resolved</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="charts-row">
            <div class="chart-col">
                <div class="chart-card">
                    <div class="card-title"><i class="fas fa-chart-pie"></i> Complaint by Category</div>
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
            <div class="chart-col">
                <div class="chart-card">
                    <div class="card-title"><i class="fas fa-chart-line"></i> Monthly Complaint Trend</div>
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Assigned Complaints Table -->
        <div class="main-card">
            <div class="card-header">
                <h5><i class="fas fa-clipboard-list"></i> Assigned Complaints</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Complaint Number</th>
                                <th>Citizen Name</th>
                                <th>Crime Type</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Assigned Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($total_assigned > 0): ?>
                                <?php foreach ($assigned_complaints as $complaint): ?>
                                <tr class="complaint-row" data-id="<?php echo $complaint['id']; ?>">
                                    <td data-label="ID">#<?php echo $complaint['id']; ?></td>
                                    <td data-label="Complaint Number" class="complaint-num"><?php echo htmlspecialchars($complaint['complaint_num']); ?></td>
                                    <td data-label="Citizen Name"><?php echo htmlspecialchars($complaint['citizen_name']); ?></td>
                                    <td data-label="Crime Type"><?php echo htmlspecialchars($complaint['category']); ?></td>
                                    <td data-label="Status">
                                        <?php 
                                        $status = $complaint['status'];
                                        if ($status == 'pending'): ?>
                                            <span class="badge-pending"><i class="fas fa-clock"></i> Pending</span>
                                        <?php elseif ($status == 'fake'): ?>
                                            <span class="badge-fake"><i class="fas fa-exclamation-triangle"></i> Fake</span>
                                        <?php elseif ($status == 'resolved'): ?>
                                            <span class="badge-resolved"><i class="fas fa-check-circle"></i> Resolved</span>
                                        <?php elseif ($status == 'in-progress'): ?>
                                            <span class="badge-inprogress"><i class="fas fa-spinner fa-spin"></i> In Progress</span>
                                        <?php else: ?>
                                            <span class="badge-pending"><?php echo ucfirst($status); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Priority">
                                        <?php 
                                        $priority = $complaint['priority'];
                                        if ($priority == 'high'): ?>
                                            <span class="priority-high">High</span>
                                        <?php elseif ($priority == 'medium'): ?>
                                            <span class="priority-medium">Medium</span>
                                        <?php elseif ($priority == 'low'): ?>
                                            <span class="priority-low">Low</span>
                                        <?php else: ?>
                                            <span class="priority-medium"><?php echo ucfirst($priority); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Assigned Date"><?php echo date('d M Y', strtotime($complaint['assigned_date'] ?? $complaint['created_at'])); ?></td>
                                    <td data-label="Action">
                                        <div class="action-buttons">
                                            <?php if ($complaint['status'] == 'pending'): ?>
                                                <!-- FAKE BUTTON - Red color for fake -->
                                                <a href="resolve-fake-complaint.php?id=<?php echo $complaint['id']; ?>" class="btn-sm btn-fake" onclick="event.stopPropagation(); return confirm('⚠️ WARNING: Marking this as FAKE will notify the admin and citizen. Are you sure?')">
                                                    <i class="fas fa-exclamation-triangle"></i> 🚫 Fake
                                                </a>
                                                <!-- RESOLVE BUTTON - Green color for resolve -->
                                                <a href="complaint-detail.php?id=<?php echo $complaint['id']; ?>" class="btn-sm btn-resolve" onclick="event.stopPropagation()">
                                                    <i class="fas fa-check-circle"></i> ✓ Resolve
                                                </a>
                                            <?php elseif ($complaint['status'] == 'fake'): ?>
                                                <span class="text-muted"><i class="fas fa-ban"></i> Marked Fake</span>
                                                <a href="complaint-detail.php?id=<?php echo $complaint['id']; ?>" class="btn-sm btn-view" onclick="event.stopPropagation()">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            <?php elseif ($complaint['status'] == 'resolved'): ?>
                                                <span class="text-muted"><i class="fas fa-check"></i> Resolved</span>
                                                <a href="complaint-detail.php?id=<?php echo $complaint['id']; ?>" class="btn-sm btn-view" onclick="event.stopPropagation()">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            <?php elseif ($complaint['status'] == 'in-progress'): ?>
                                                <a href="complaint-detail.php?id=<?php echo $complaint['id']; ?>" class="btn-sm btn-view" onclick="event.stopPropagation()">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            <?php else: ?>
                                                <a href="complaint-detail.php?id=<?php echo $complaint['id']; ?>" class="btn-sm btn-view" onclick="event.stopPropagation()">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                                        <p class="text-muted">No complaints assigned yet.</p>
                                        <small class="text-muted">Complaints will appear here once assigned by admin.</small>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        const mobileToggle = document.getElementById('mobileToggle');
        const sidebar = document.getElementById('sidebar');
        if (mobileToggle) {
            mobileToggle.addEventListener('click', () => sidebar.classList.toggle('active'));
        }

        function setTheme(theme) {
            document.cookie = "theme=" + theme + "; path=/; max-age=" + (365 * 24 * 60 * 60);
            applyTheme(theme);
            document.getElementById('themeIcon').textContent = theme === 'dark' ? '🌙' : '☀️';
            const themeText = document.querySelector('.theme-toggle-btn span:last-child');
            if (themeText) themeText.textContent = theme === 'dark' ? 'Dark' : 'Light';
        }

        function toggleTheme() {
            const currentTheme = document.cookie.includes('theme=dark') ? 'dark' : 'light';
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
                root.style.setProperty('--text', '#e8e8f0');
                root.style.setProperty('--text2', '#9090b0');
                root.style.setProperty('--text3', '#606080');
                root.style.setProperty('--card', '#111120');
            } else {
                root.style.setProperty('--bg', '#fef9f0');
                root.style.setProperty('--bg2', '#fdf5e6');
                root.style.setProperty('--bg3', '#faf0e1');
                root.style.setProperty('--bg4', '#f5e8d9');
                root.style.setProperty('--border', 'rgba(139,69,19,0.12)');
                root.style.setProperty('--text', '#4a3728');
                root.style.setProperty('--text2', '#6b5340');
                root.style.setProperty('--text3', '#8b6b50');
                root.style.setProperty('--card', '#fffaf5');
            }
        }

        // Make entire row clickable
        document.querySelectorAll('.complaint-row').forEach(row => {
            row.addEventListener('click', function(e) {
                if (e.target.closest('.btn-fake')) return;
                if (e.target.closest('.btn-resolve')) return;
                if (e.target.closest('.btn-view')) return;
                
                const complaintId = this.getAttribute('data-id');
                if (complaintId) {
                    window.location.href = 'complaint-detail.php?id=' + complaintId;
                }
            });
        });

        // Initialize Charts
        window.addEventListener('DOMContentLoaded', function() {
            const theme = getCookie('theme') || 'dark';
            applyTheme(theme);
            
            const textColor = theme === 'dark' ? '#e8e8f0' : '#4a3728';
            const gridColor = theme === 'dark' ? 'rgba(120,120,255,0.1)' : 'rgba(139,69,19,0.1)';
            
            // Pie Chart
            const categoryCtx = document.getElementById('categoryChart');
            if (categoryCtx && <?php echo count($categories); ?> > 0) {
                new Chart(categoryCtx.getContext('2d'), {
                    type: 'pie',
                    data: {
                        labels: <?php echo json_encode($categories); ?>,
                        datasets: [{
                            data: <?php echo json_encode($category_counts); ?>,
                            backgroundColor: ['#6c63ff', '#f59e0b', '#10b981', '#f43f5e', '#38bdf8', '#a78bfa', '#ec4899', '#14b8a6'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { 
                                position: 'bottom', 
                                labels: { color: textColor, font: { size: 11 }, boxWidth: 12 } 
                            }
                        }
                    }
                });
            }
            
            // Line Chart
            const trendCtx = document.getElementById('trendChart');
            if (trendCtx && <?php echo count($months); ?> > 0) {
                new Chart(trendCtx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($months); ?>,
                        datasets: [{
                            label: 'Number of Complaints',
                            data: <?php echo json_encode($monthly_counts); ?>,
                            borderColor: '#6c63ff',
                            backgroundColor: 'rgba(108, 99, 255, 0.1)',
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#6c63ff',
                            pointBorderColor: '#fff',
                            pointRadius: 5,
                            pointHoverRadius: 7,
                            pointBorderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { labels: { color: textColor } }
                        },
                        scales: {
                            y: { 
                                beginAtZero: true, 
                                grid: { color: gridColor },
                                ticks: { color: textColor, stepSize: 1 }
                            },
                            x: { 
                                grid: { display: false },
                                ticks: { color: textColor }
                            }
                        }
                    }
                });
            }
        });

        function getCookie(name) {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) return parts.pop().split(';').shift();
            return null;
        }
    </script>
</body>
</html>
<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if officer is logged in
if (!isset($_SESSION['officer_id'])) {
    header("Location: officer-login.php");
    exit();
}

$officer_id = $_SESSION['officer_id'];
$conn = getConnection();

// Get ALL assigned complaints - removed the status filter
$complaints_query = $conn->prepare("SELECT c.*, u.name as citizen_name FROM complaints c JOIN users u ON c.user_id = u.id WHERE c.assigned_to = ? ORDER BY c.created_at DESC");
$complaints_query->bind_param("i", $officer_id);
$complaints_query->execute();
$complaints = $complaints_query->get_result();

// Get statistics - Count ALL complaints
$stats = [];
$total_query = $conn->prepare("SELECT COUNT(*) as count FROM complaints WHERE assigned_to = ?");
$total_query->bind_param("i", $officer_id);
$total_query->execute();
$stats['total'] = $total_query->get_result()->fetch_assoc()['count'];

$pending_query = $conn->prepare("SELECT COUNT(*) as count FROM complaints WHERE assigned_to = ? AND status = 'pending'");
$pending_query->bind_param("i", $officer_id);
$pending_query->execute();
$stats['pending'] = $pending_query->get_result()->fetch_assoc()['count'];

$fake_query = $conn->prepare("SELECT COUNT(*) as count FROM complaints WHERE assigned_to = ? AND status = 'fake'");
$fake_query->bind_param("i", $officer_id);
$fake_query->execute();
$stats['fake'] = $fake_query->get_result()->fetch_assoc()['count'];

$resolved_query = $conn->prepare("SELECT COUNT(*) as count FROM complaints WHERE assigned_to = ? AND status = 'resolved'");
$resolved_query->bind_param("i", $officer_id);
$resolved_query->execute();
$stats['resolved'] = $resolved_query->get_result()->fetch_assoc()['count'];

$conn->close();

// Get theme preference from cookie
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'dark';

// Get officer name from session or database
$officer_name = $_SESSION['officer_name'] ?? 'Police Officer';
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
    <title>My Assigned Complaints - CyberShield</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        
        /* Statistics Cards */
        .stats-row { display: flex; gap: 20px; margin-bottom: 24px; flex-wrap: wrap; }
        .stat-card { flex: 1; min-width: 150px; background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 20px; text-align: center; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card .stat-number { font-size: 32px; font-weight: 700; color: var(--text); }
        .stat-card .stat-label { font-size: 12px; color: var(--text3); margin-top: 5px; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card.primary .stat-number { color: var(--accent); }
        .stat-card.warning .stat-number { color: #f59e0b; }
        .stat-card.danger .stat-number { color: #dc3545; }
        .stat-card.success .stat-number { color: #10b981; }
        
        .main-card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; }
        .main-card .card-header { background: var(--bg3); border-bottom: 1px solid var(--border); padding: 15px 20px; }
        .main-card .card-header h5 { margin: 0; color: var(--text); font-size: 16px; font-weight: 600; }
        .table { color: var(--text2); margin-bottom: 0; width: 100%; border-collapse: collapse; }
        .table thead th { 
            background: var(--bg4); 
            color: var(--text3); 
            font-size: 11px; 
            font-weight: 600; 
            text-transform: uppercase; 
            padding: 12px 15px; 
            border-bottom: 1px solid var(--border);
            text-align: left;
        }
        .table tbody td { 
            padding: 12px 15px; 
            vertical-align: middle; 
            border-bottom: 1px solid var(--border);
            text-align: left;
        }
        .table tbody tr:hover td { 
            background: var(--bg3); 
            cursor: pointer;
        }
        
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
        .btn-success { background: #28a745; color: white; border: none; cursor: pointer; }
        .btn-info { background: #17a2b8; color: white; border: none; cursor: pointer; }
        .btn-danger { background: #dc3545; color: white; border: none; cursor: pointer; }
        
        /* Status Badges */
        .badge-pending { background: #ffc107; color: #333; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; }
        .badge-resolved { background: #28a745; color: white; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; }
        .badge-fake { background: #dc3545; color: white; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; }
        
        /* Priority Badges */
        .priority-high { background: #dc3545; color: white; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; }
        .priority-medium { background: #fd7e14; color: white; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; }
        .priority-low { background: #28a745; color: white; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; }
        
        .action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .complaint-num {
            font-family: monospace;
            font-size: 12px;
            font-weight: 500;
            color: var(--accent2);
            background: rgba(108,99,255,0.1);
            padding: 4px 8px;
            border-radius: 6px;
            display: inline-block;
        }
        
        .text-center {
            text-align: center;
        }
        
        .py-5 {
            padding-top: 3rem;
            padding-bottom: 3rem;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .filter-btn {
            background: var(--bg4);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 6px 16px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
            color: var(--text2);
        }
        .filter-btn.active, .filter-btn:hover {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }
        
        /* Table column widths */
        .col-id { width: 5%; }
        .col-complaint { width: 18%; }
        .col-citizen { width: 15%; }
        .col-crime { width: 15%; }
        .col-status { width: 12%; }
        .col-priority { width: 10%; }
        .col-date { width: 13%; }
        .col-action { width: 12%; }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-toggle { display: block; position: fixed; top: 20px; left: 20px; z-index: 101; background: var(--accent); border: none; color: white; padding: 10px 15px; border-radius: 10px; cursor: pointer; }
            .stats-row { flex-direction: column; }
            .table thead { display: none; }
            .table tbody td { 
                display: block; 
                text-align: right; 
                position: relative; 
                padding-left: 50%;
                white-space: normal;
            }
            .table tbody td::before { 
                content: attr(data-label); 
                position: absolute; 
                left: 15px; 
                top: 12px; 
                font-size: 11px; 
                font-weight: 600; 
                color: var(--text3); 
                text-align: left; 
            }
            .table tbody tr { 
                display: block; 
                margin-bottom: 16px; 
                border: 1px solid var(--border); 
                border-radius: 12px;
            }
            .table tbody td:last-child {
                border-bottom: none;
            }
            .action-buttons {
                justify-content: flex-end;
            }
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
            <a href="officer-dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> 📊 Dashboard</a>
            <a href="officer-complaints.php" class="nav-link active"><i class="fas fa-list"></i> 📋 My Complaints</a>
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
        <div class="top-bar">
            <div class="page-title">
                <h2>My Assigned Complaints</h2>
                <p>CyberShield / My Complaints</p>
            </div>
            <button class="theme-toggle-btn" onclick="toggleTheme()">
                <span id="themeIcon"><?php echo $theme == 'dark' ? '🌙' : '☀️'; ?></span>
                <span><?php echo $theme == 'dark' ? 'Dark' : 'Light'; ?></span>
            </button>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-row">
            <div class="stat-card primary">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Assigned</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-number"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card danger">
                <div class="stat-number"><?php echo $stats['fake'] ?? 0; ?></div>
                <div class="stat-label">Fake</div>
            </div>
            <div class="stat-card success">
                <div class="stat-number"><?php echo $stats['resolved']; ?></div>
                <div class="stat-label">Resolved</div>
            </div>
        </div>
        
        <!-- Filter Buttons -->
        <div class="filter-buttons">
            <button class="filter-btn active" data-filter="all">All Complaints</button>
            <button class="filter-btn" data-filter="pending">Pending</button>
            <button class="filter-btn" data-filter="fake">Fake</button>
            <button class="filter-btn" data-filter="resolved">Resolved</button>
        </div>
        
        <div class="main-card">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> Assigned Complaints</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="complaintsTable">
                        <thead>
                            <tr>
                                <th class="col-id">ID</th>
                                <th class="col-complaint">Complaint #</th>
                                <th class="col-citizen">Citizen</th>
                                <th class="col-crime">Crime Type</th>
                                <th class="col-status">Status</th>
                                <th class="col-priority">Priority</th>
                                <th class="col-date">Assigned Date</th>
                                <th class="col-action">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($complaints && $complaints->num_rows > 0): ?>
                                <?php while ($complaint = $complaints->fetch_assoc()): ?>
                                <tr class="complaint-row" data-id="<?php echo $complaint['id']; ?>" data-status="<?php echo $complaint['status']; ?>">
                                    <td data-label="ID">#<?php echo $complaint['id']; ?></td>
                                    <td data-label="Complaint #"><span class="complaint-num"><?php echo htmlspecialchars($complaint['complaint_num'] ?? 'N/A'); ?></span></td>
                                    <td data-label="Citizen"><?php echo htmlspecialchars($complaint['citizen_name']); ?></td>
                                    <td data-label="Crime Type"><?php echo htmlspecialchars($complaint['category'] ?? $complaint['type'] ?? 'N/A'); ?></td>
                                    <td data-label="Status">
                                        <?php 
                                        $status = $complaint['status'];
                                        if ($status == 'pending'): ?>
                                            <span class="badge-pending"><i class="fas fa-clock"></i> Pending</span>
                                        <?php elseif ($status == 'fake'): ?>
                                            <span class="badge-fake"><i class="fas fa-exclamation-triangle"></i> Fake</span>
                                        <?php elseif ($status == 'resolved'): ?>
                                            <span class="badge-resolved"><i class="fas fa-check-circle"></i> Resolved</span>
                                        <?php else: ?>
                                            <span class="badge-pending"><?php echo ucfirst($status); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Priority">
                                        <?php 
                                        $priority = $complaint['priority'];
                                        if ($priority == 'high'): ?>
                                            <span class="priority-high"><i class="fas fa-exclamation-triangle"></i> High</span>
                                        <?php elseif ($priority == 'medium'): ?>
                                            <span class="priority-medium"><i class="fas fa-chart-line"></i> Medium</span>
                                        <?php elseif ($priority == 'low'): ?>
                                            <span class="priority-low"><i class="fas fa-arrow-down"></i> Low</span>
                                        <?php else: ?>
                                            <span class="priority-low"><?php echo ucfirst($priority); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Assigned Date"><?php echo date('d M Y', strtotime($complaint['assigned_date'] ?? $complaint['created_at'])); ?></td>
                                    <td data-label="Action">
                                        <div class="action-buttons">
                                            <?php if ($complaint['status'] == 'pending'): ?>
                                                <a href="resolve-fake-complaint.php?id=<?php echo $complaint['id']; ?>" class="btn btn-sm btn-success" onclick="event.stopPropagation(); return confirm('⚠️ WARNING: Marking this as FAKE will notify the admin and citizen. Are you sure?')">
                                                    <i class="fas fa-exclamation-triangle"></i> 🚫 Fake
                                                </a>
                                                <a href="complaint-detail.php?id=<?php echo $complaint['id']; ?>" class="btn btn-sm btn-info" onclick="event.stopPropagation()">
                                                    <i class="fas fa-check-circle"></i> ✓ Resolve
                                                </a>
                                            <?php elseif ($complaint['status'] == 'fake'): ?>
                                                <span class="text-muted"><i class="fas fa-ban"></i> Marked Fake</span>
                                                <a href="complaint-detail.php?id=<?php echo $complaint['id']; ?>" class="btn btn-sm btn-info" onclick="event.stopPropagation()">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            <?php elseif ($complaint['status'] == 'resolved'): ?>
                                                <span class="text-muted"><i class="fas fa-check"></i> Resolved</span>
                                                <a href="complaint-detail.php?id=<?php echo $complaint['id']; ?>" class="btn btn-sm btn-info" onclick="event.stopPropagation()">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            <?php else: ?>
                                                <a href="complaint-detail.php?id=<?php echo $complaint['id']; ?>" class="btn btn-sm btn-info" onclick="event.stopPropagation()">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
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

        // Filter functionality
        const filterButtons = document.querySelectorAll('.filter-btn');
        const tableRows = document.querySelectorAll('.complaint-row');
        
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                filterButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                const filterValue = this.getAttribute('data-filter');
                
                tableRows.forEach(row => {
                    if (filterValue === 'all') {
                        row.style.display = '';
                    } else {
                        const rowStatus = row.getAttribute('data-status');
                        if (rowStatus === filterValue) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    }
                });
            });
        });

        // Make entire row clickable
        document.querySelectorAll('.complaint-row').forEach(row => {
            row.addEventListener('click', function(e) {
                if (e.target.closest('.btn-sm')) return;
                if (e.target.closest('.action-buttons')) return;
                
                const complaintId = this.getAttribute('data-id');
                if (complaintId) {
                    window.location.href = 'complaint-detail.php?id=' + complaintId;
                }
            });
        });
    </script>
</body>
</html>
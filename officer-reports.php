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

// Get filter parameters
$filter_type = $_GET['filter_type'] ?? 'monthly';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$export = isset($_GET['export']) ? $_GET['export'] : '';

// Set date range based on filter type
if ($filter_type == 'weekly') {
    $start_date = date('Y-m-d', strtotime('-7 days'));
    $end_date = date('Y-m-d');
} elseif ($filter_type == 'monthly') {
    $start_date = date('Y-m-d', strtotime('-30 days'));
    $end_date = date('Y-m-d');
} elseif ($filter_type == 'yearly') {
    $start_date = date('Y-m-d', strtotime('-365 days'));
    $end_date = date('Y-m-d');
}

// Build query with proper prepared statement
$query = "SELECT c.*, u.name as citizen_name 
          FROM complaints c 
          JOIN users u ON c.user_id = u.id 
          WHERE c.assigned_to = ?";

$params = [$officer_id];
$types = "i";

if ($start_date && $end_date && $filter_type != 'custom') {
    $query .= " AND DATE(c.created_at) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= "ss";
} elseif ($start_date && $end_date && $filter_type == 'custom') {
    $query .= " AND DATE(c.created_at) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= "ss";
}

$query .= " ORDER BY c.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$complaints = [];
while ($row = $result->fetch_assoc()) {
    $complaints[] = $row;
}
$stmt->close();

// Get statistics
$total_complaints = count($complaints);
$resolved_count = count(array_filter($complaints, function($c) { return $c['status'] == 'resolved'; }));
$pending_count = count(array_filter($complaints, function($c) { return $c['status'] == 'pending'; }));
$fake_count = count(array_filter($complaints, function($c) { return $c['status'] == 'fake'; }));

// Get category wise statistics
$category_stats = [];
foreach ($complaints as $c) {
    $cat = $c['category'];
    if (!isset($category_stats[$cat])) {
        $category_stats[$cat] = 0;
    }
    $category_stats[$cat]++;
}

// Get priority wise statistics
$priority_stats = ['high' => 0, 'medium' => 0, 'low' => 0, 'critical' => 0];
foreach ($complaints as $c) {
    $priority = $c['priority'];
    if (isset($priority_stats[$priority])) {
        $priority_stats[$priority]++;
    }
}

// Get monthly data for chart
$monthly_data = [];
foreach ($complaints as $c) {
    $month = date('Y-m', strtotime($c['created_at']));
    if (!isset($monthly_data[$month])) {
        $monthly_data[$month] = 0;
    }
    $monthly_data[$month]++;
}
ksort($monthly_data);

$conn->close();

// =====================================================
// EXPORT FUNCTIONALITY - EXCEL
// =====================================================
if ($export == 'excel') {
    // Set headers for Excel download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="complaints_report_' . date('Y-m-d') . '.xls"');
    
    echo '<html>';
    echo '<head><meta charset="UTF-8"><title>Complaints Report</title></head>';
    echo '<body>';
    echo '<h2>Complaints Report - Officer ' . htmlspecialchars($_SESSION['officer_name']) . '</h2>';
    echo '<p>Generated: ' . date('Y-m-d H:i:s') . '</p>';
    echo '<p>Period: ' . ($start_date ? $start_date . ' to ' . $end_date : 'All Time') . '</p>';
    echo '<hr>';
    
    // Statistics
    echo '<h3>Statistics</h3>';
    echo '<table border="1" cellpadding="5">';
    echo '<tr><th>Total</th><th>Resolved</th><th>Pending</th><th>Fake</th></tr>';
    echo '<tr>';
    echo '<td>' . $total_complaints . '</td>';
    echo '<td>' . $resolved_count . '</td>';
    echo '<td>' . $pending_count . '</td>';
    echo '<td>' . $fake_count . '</td>';
    echo '</tr>';
    echo '</table><br>';
    
    // Complaints List
    echo '<h3>Complaints List</h3>';
    echo '<table border="1" cellpadding="5">';
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>Complaint #</th>';
    echo '<th>Citizen</th>';
    echo '<th>Category</th>';
    echo '<th>Status</th>';
    echo '<th>Priority</th>';
    echo '<th>Date</th>';
    echo '<th>Loss</th>';
    echo '</tr>';
    
    if (count($complaints) > 0) {
        foreach ($complaints as $c) {
            echo '<tr>';
            echo '<td>' . $c['id'] . '</td>';
            echo '<td>' . htmlspecialchars($c['complaint_num']) . '</td>';
            echo '<td>' . htmlspecialchars($c['citizen_name']) . '</td>';
            echo '<td>' . htmlspecialchars($c['category']) . '</td>';
            echo '<td>' . ucfirst($c['status']) . '</td>';
            echo '<td>' . ucfirst($c['priority']) . '</td>';
            echo '<td>' . date('d M Y', strtotime($c['created_at'])) . '</td>';
            echo '<td>' . ($c['financial_loss'] > 0 ? '₹' . number_format($c['financial_loss'], 2) : '0') . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="8" align="center">No complaints found</td></tr>';
    }
    
    echo '</table>';
    echo '<br><hr>';
    echo '<p>Generated by CyberShield - Cyber Crime Reporting System</p>';
    echo '</body></html>';
    exit();
}

// =====================================================
// EXPORT FUNCTIONALITY - PDF (using HTML/CSS)
// =====================================================
if ($export == 'pdf') {
    // Set headers for PDF download (using print styles)
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="complaints_report_' . date('Y-m-d') . '.html"');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Complaints Report - CyberShield</title>
        <style>
            @media print {
                body { margin: 0; padding: 20px; }
                .page-break { page-break-before: always; }
            }
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: Arial, Helvetica, sans-serif; 
                background: white; 
                color: #1a1a2e; 
                padding: 30px;
                font-size: 12px;
            }
            .report-container {
                max-width: 1100px;
                margin: 0 auto;
            }
            .report-header {
                text-align: center;
                margin-bottom: 30px;
                padding-bottom: 20px;
                border-bottom: 3px solid #1e3c72;
            }
            .report-title {
                font-size: 28px;
                font-weight: 700;
                color: #1e3c72;
                margin-bottom: 5px;
            }
            .report-subtitle {
                font-size: 14px;
                color: #666;
                margin-bottom: 10px;
            }
            .report-meta {
                font-size: 11px;
                color: #888;
                font-family: monospace;
            }
            .report-meta span {
                margin: 0 10px;
            }
            
            /* Stats Grid */
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 15px;
                margin-bottom: 30px;
            }
            .stat-box {
                background: #f5f5f5;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                padding: 15px;
                text-align: center;
            }
            .stat-number {
                font-size: 28px;
                font-weight: 700;
                color: #1e3c72;
            }
            .stat-label {
                font-size: 11px;
                color: #666;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-top: 5px;
            }
            .stat-number.green { color: #10b981; }
            .stat-number.orange { color: #f59e0b; }
            .stat-number.red { color: #dc3545; }
            
            /* Charts Section */
            .charts-section {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin-bottom: 30px;
            }
            .chart-box {
                background: #fafafa;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                padding: 15px;
            }
            .chart-box h4 {
                font-size: 14px;
                margin-bottom: 15px;
                padding-bottom: 10px;
                border-bottom: 2px solid #e0e0e0;
                color: #333;
            }
            
            /* Simple Bar Chart */
            .bar-chart { margin-top: 10px; }
            .bar-item { margin-bottom: 10px; }
            .bar-label {
                display: flex;
                justify-content: space-between;
                font-size: 12px;
                margin-bottom: 3px;
            }
            .bar-track {
                height: 8px;
                background: #e0e0e0;
                border-radius: 4px;
                overflow: hidden;
            }
            .bar-fill {
                height: 100%;
                border-radius: 4px;
                transition: width 0.3s;
            }
            .bar-fill.status-pending { background: #f59e0b; }
            .bar-fill.status-resolved { background: #10b981; }
            .bar-fill.status-fake { background: #dc3545; }
            .bar-fill.priority-high { background: #dc3545; }
            .bar-fill.priority-medium { background: #f59e0b; }
            .bar-fill.priority-low { background: #10b981; }
            .bar-fill.priority-critical { background: #6f42c1; }
            
            /* Table */
            .table-section { margin-top: 20px; }
            .table-section h4 {
                font-size: 16px;
                margin-bottom: 15px;
                padding-bottom: 10px;
                border-bottom: 2px solid #1e3c72;
                color: #1e3c72;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 11px;
            }
            table th {
                background: #1e3c72;
                color: white;
                padding: 10px 12px;
                text-align: left;
                font-weight: 600;
                text-transform: uppercase;
                font-size: 10px;
                letter-spacing: 0.5px;
            }
            table td {
                padding: 8px 12px;
                border-bottom: 1px solid #eee;
                color: #555;
            }
            table tr:nth-child(even) td {
                background: #f9f9f9;
            }
            table tr:hover td {
                background: #f0f0f0;
            }
            
            .badge {
                display: inline-block;
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 10px;
                font-weight: 500;
            }
            .badge-resolved { background: #d1fae5; color: #059669; }
            .badge-pending { background: #fef3c7; color: #d97706; }
            .badge-fake { background: #fee2e2; color: #dc2626; }
            .badge-inprogress { background: #dbeafe; color: #2563eb; }
            
            .badge-high { background: #fee2e2; color: #dc2626; }
            .badge-medium { background: #fef3c7; color: #d97706; }
            .badge-low { background: #d1fae5; color: #059669; }
            .badge-critical { background: #fce4ec; color: #c62828; }
            
            .complaint-num {
                font-family: monospace;
                font-size: 11px;
                color: #667eea;
                background: #eef2ff;
                padding: 2px 8px;
                border-radius: 4px;
            }
            
            .footer {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 2px solid #e0e0e0;
                text-align: center;
                font-size: 10px;
                color: #999;
                font-family: monospace;
            }
            
            @media print {
                body { padding: 15px; }
                .stat-box { break-inside: avoid; }
                .chart-box { break-inside: avoid; }
                table tr { break-inside: avoid; }
                .stats-grid { page-break-after: avoid; }
            }
        </style>
    </head>
    <body>
        <div class="report-container">
            <!-- Header -->
            <div class="report-header">
                <div class="report-title">🛡️ CyberShield - Complaints Report</div>
                <div class="report-subtitle">Cyber Crime Reporting & Case Management System</div>
                <div class="report-meta">
                    <span>👮 Officer: <?php echo htmlspecialchars($_SESSION['officer_name']); ?></span>
                    <span>📅 Generated: <?php echo date('F d, Y \a\t h:i A'); ?></span>
                    <span>📊 Period: <?php echo ($start_date ? $start_date . ' to ' . $end_date : 'All Time'); ?></span>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-number"><?php echo $total_complaints; ?></div>
                    <div class="stat-label">Total Complaints</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number green"><?php echo $resolved_count; ?></div>
                    <div class="stat-label">Resolved</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number orange"><?php echo $pending_count; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number red"><?php echo $fake_count; ?></div>
                    <div class="stat-label">Fake</div>
                </div>
            </div>
            
            <!-- Charts -->
            <div class="charts-section">
                <div class="chart-box">
                    <h4>📊 Status Distribution</h4>
                    <div class="bar-chart">
                        <?php
                        $status_data = [
                            'pending' => $pending_count,
                            'resolved' => $resolved_count,
                            'fake' => $fake_count
                        ];
                        $max_status = max($status_data) > 0 ? max($status_data) : 1;
                        $status_labels = ['pending' => 'Pending', 'resolved' => 'Resolved', 'fake' => 'Fake'];
                        foreach ($status_data as $key => $value):
                            $percentage = ($value / $max_status) * 100;
                        ?>
                        <div class="bar-item">
                            <div class="bar-label">
                                <span><?php echo $status_labels[$key]; ?></span>
                                <span><?php echo $value; ?> cases</span>
                            </div>
                            <div class="bar-track">
                                <div class="bar-fill status-<?php echo $key; ?>" style="width: <?php echo $percentage; ?>%;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="chart-box">
                    <h4>⚠️ Priority Distribution</h4>
                    <div class="bar-chart">
                        <?php
                        $max_priority = max($priority_stats) > 0 ? max($priority_stats) : 1;
                        $priority_labels = ['high' => 'High', 'medium' => 'Medium', 'low' => 'Low', 'critical' => 'Critical'];
                        foreach ($priority_stats as $key => $value):
                            $percentage = ($value / $max_priority) * 100;
                        ?>
                        <div class="bar-item">
                            <div class="bar-label">
                                <span><?php echo $priority_labels[$key]; ?></span>
                                <span><?php echo $value; ?> cases</span>
                            </div>
                            <div class="bar-track">
                                <div class="bar-fill priority-<?php echo $key; ?>" style="width: <?php echo $percentage; ?>%;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Complaints Table -->
            <div class="table-section">
                <h4>📋 Complaint Details</h4>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Complaint #</th>
                            <th>Citizen</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Date</th>
                            <th>Loss</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($complaints) > 0): ?>
                            <?php foreach ($complaints as $c): ?>
                            <tr>
                                <td>#<?php echo $c['id']; ?></td>
                                <td><span class="complaint-num"><?php echo htmlspecialchars($c['complaint_num']); ?></span></td>
                                <td><?php echo htmlspecialchars($c['citizen_name']); ?></td>
                                <td><?php echo htmlspecialchars($c['category']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $c['status']; ?>">
                                        <?php echo ucfirst(str_replace('-', ' ', $c['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $c['priority']; ?>">
                                        <?php echo ucfirst($c['priority']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y', strtotime($c['created_at'])); ?></td>
                                <td><?php echo $c['financial_loss'] > 0 ? '₹' . number_format($c['financial_loss'], 2) : '—'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 30px; color: #999;">
                                    No complaints found for the selected period
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Footer -->
            <div class="footer">
                <p>This is a system-generated report from CyberShield - Cyber Crime Reporting System</p>
                <p>© <?php echo date('Y'); ?> CyberShield. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'dark';
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
    <title>Complaints Report - CyberShield</title>
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
        
        /* Filter Bar */
        .filter-bar {
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
        .filter-select, .filter-input {
            background: var(--bg4);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 14px;
            color: var(--text2);
            font-size: 13px;
            min-width: 140px;
        }
        .btn-filter {
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            cursor: pointer;
        }
        .btn-export-excel {
            background: #28a745;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            cursor: pointer;
        }
        .btn-export-pdf {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            cursor: pointer;
        }
        .btn-export-pdf:hover { background: #c82333; }
        .btn-export-excel:hover { background: #1e7e34; }
        
        /* Stats Cards */
        .stats-row { 
            display: flex; 
            gap: 20px; 
            margin-bottom: 24px; 
            flex-wrap: wrap; 
        }
        .stat-card { 
            flex: 1; 
            min-width: 180px; 
            background: var(--card); 
            border: 1px solid var(--border); 
            border-radius: 16px; 
            padding: 20px; 
            transition: transform 0.2s; 
            text-align: center;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card .stat-number { font-size: 32px; font-weight: 700; color: var(--text); }
        .stat-card .stat-label { font-size: 12px; color: var(--text3); margin-top: 5px; }
        
        .chart-container {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
            height: 100%;
        }
        .chart-container h6 {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
            color: var(--text);
        }
        .chart-container canvas {
            max-height: 280px;
            width: 100%;
        }
        
        .table-container {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
        }
        .table-container .card-header {
            background: var(--bg3);
            border-bottom: 1px solid var(--border);
            padding: 15px 20px;
        }
        .table-container .card-header h5 {
            margin: 0;
            color: var(--text);
            font-size: 16px;
            font-weight: 600;
        }
        .table { 
            color: var(--text2); 
            margin-bottom: 0; 
            width: 100%;
        }
        .table thead th { 
            background: var(--bg4); 
            color: var(--text3); 
            font-size: 11px; 
            font-weight: 600; 
            text-transform: uppercase; 
            padding: 12px; 
            border-bottom: 1px solid var(--border);
        }
        .table tbody td { 
            padding: 12px; 
            vertical-align: middle; 
            border-bottom: 1px solid var(--border);
        }
        .table tbody tr:hover { background: var(--bg3); cursor: pointer; }
        
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
        }
        .badge-success { background: #28a745; color: white; }
        .badge-warning { background: #ffc107; color: #333; }
        .badge-info { background: #17a2b8; color: white; }
        .badge-danger { background: #dc3545; color: white; }
        
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
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.active { transform: translateX(0); width: 260px; }
            .main-content { margin-left: 0; width: 100%; }
            .mobile-toggle { display: block; position: fixed; top: 20px; left: 20px; z-index: 101; background: var(--accent); border: none; color: white; padding: 10px 15px; border-radius: 10px; cursor: pointer; }
            .filter-form { flex-direction: column; align-items: stretch; }
            .stats-row { flex-direction: column; }
            .stats-row .stat-card { width: 100%; }
            .chart-container canvas { max-height: 220px; }
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
                left: 12px; 
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
        }
        @media (min-width: 769px) { 
            .mobile-toggle { display: none; }
        }
    </style>
</head>
<body>
    <button class="mobile-toggle" id="mobileToggle"><i class="fas fa-bars"></i></button>

    <div class="sidebar" id="sidebar">
        <h4 class="text-center"><i class="fas fa-shield-alt"></i> CyberShield</h4>
        <div class="nav flex-column">
            <a href="officer-dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> 📊 Dashboard</a>
            <a href="officer-complaints.php" class="nav-link"><i class="fas fa-list"></i> 📋 My Complaints</a>
            <a href="officer-reports.php" class="nav-link active"><i class="fas fa-chart-line"></i> 📈 Reports</a>
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
                <h2>Complaints Report</h2>
                <p>CyberShield / Reports</p>
            </div>
            <button class="theme-toggle-btn" onclick="toggleTheme()">
                <span id="themeIcon"><?php echo $theme == 'dark' ? '🌙' : '☀️'; ?></span>
                <span><?php echo $theme == 'dark' ? 'Dark' : 'Light'; ?></span>
            </button>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" class="filter-form">
                <select name="filter_type" class="filter-select">
                    <option value="weekly" <?php echo $filter_type == 'weekly' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="monthly" <?php echo $filter_type == 'monthly' ? 'selected' : ''; ?>>Last 30 Days</option>
                    <option value="yearly" <?php echo $filter_type == 'yearly' ? 'selected' : ''; ?>>Last Year</option>
                    <option value="custom" <?php echo $filter_type == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                </select>
                <input type="date" name="start_date" class="filter-input" value="<?php echo $start_date; ?>" placeholder="Start Date">
                <input type="date" name="end_date" class="filter-input" value="<?php echo $end_date; ?>" placeholder="End Date">
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filter</button>
                <button type="submit" name="export" value="excel" class="btn-export-excel">
                    <i class="fas fa-file-excel"></i> Excel
                </button>
                <button type="submit" name="export" value="pdf" class="btn-export-pdf">
                    <i class="fas fa-file-pdf"></i> PDF
                </button>
            </form>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_complaints; ?></div>
                <div class="stat-label">Total Complaints</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #10b981;"><?php echo $resolved_count; ?></div>
                <div class="stat-label">Resolved</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #f59e0b;"><?php echo $pending_count; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #dc3545;"><?php echo $fake_count; ?></div>
                <div class="stat-label">Fake</div>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="chart-container">
                    <h6><i class="fas fa-chart-pie"></i> Complaints by Category</h6>
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="chart-container">
                    <h6><i class="fas fa-chart-bar"></i> Complaints by Priority</h6>
                    <canvas id="priorityChart"></canvas>
                </div>
            </div>
            <div class="col-md-12 mb-3">
                <div class="chart-container">
                    <h6><i class="fas fa-chart-line"></i> Complaints Trend (Monthly)</h6>
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Complaints Table -->
        <div class="table-container">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> Complaints List (<?php echo count($complaints); ?> records)</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Complaint #</th>
                            <th>Citizen</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($complaints) > 0): ?>
                            <?php foreach ($complaints as $c): ?>
                            <tr onclick="window.location.href='complaint-detail.php?id=<?php echo $c['id']; ?>'">
                                <td data-label="ID">#<?php echo $c['id']; ?></td>
                                <td data-label="Complaint #"><span class="complaint-num"><?php echo htmlspecialchars($c['complaint_num']); ?></span></td>
                                <td data-label="Citizen"><?php echo htmlspecialchars($c['citizen_name']); ?></td>
                                <td data-label="Category"><?php echo htmlspecialchars($c['category']); ?></td>
                                <td data-label="Status">
                                    <?php if ($c['status'] == 'resolved'): ?>
                                        <span class="badge badge-success">✅ Resolved</span>
                                    <?php elseif ($c['status'] == 'pending'): ?>
                                        <span class="badge badge-warning">⏳ Pending</span>
                                    <?php elseif ($c['status'] == 'fake'): ?>
                                        <span class="badge badge-danger">⚠️ Fake</span>
                                    <?php else: ?>
                                        <span class="badge badge-info">🔍 In Progress</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Priority">
                                    <?php if ($c['priority'] == 'high'): ?>
                                        <span class="badge badge-danger">🔴 High</span>
                                    <?php elseif ($c['priority'] == 'medium'): ?>
                                        <span class="badge badge-warning">🟠 Medium</span>
                                    <?php elseif ($c['priority'] == 'critical'): ?>
                                        <span class="badge badge-danger">🔥 Critical</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">🟢 Low</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Date"><?php echo date('d M Y', strtotime($c['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                                    <p class="text-muted">No complaints found for the selected period</p>
                                    <small class="text-muted">Try changing your filter criteria</small>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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

        // Initialize Charts
        window.addEventListener('DOMContentLoaded', function() {
            const theme = getCookie('theme') || 'dark';
            applyTheme(theme);
            
            const textColor = theme === 'dark' ? '#e8e8f0' : '#4a3728';
            const gridColor = theme === 'dark' ? 'rgba(120,120,255,0.1)' : 'rgba(139,69,19,0.1)';
            
            // Category Chart (Pie)
            const categoryLabels = <?php echo json_encode(array_keys($category_stats)); ?>;
            const categoryData = <?php echo json_encode(array_values($category_stats)); ?>;
            if (categoryLabels.length > 0) {
                const categoryCtx = document.getElementById('categoryChart').getContext('2d');
                new Chart(categoryCtx, {
                    type: 'pie',
                    data: {
                        labels: categoryLabels,
                        datasets: [{
                            data: categoryData,
                            backgroundColor: ['#6c63ff', '#f59e0b', '#10b981', '#f43f5e', '#38bdf8', '#a78bfa', '#ec4899', '#14b8a6'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { position: 'bottom', labels: { color: textColor, font: { size: 11 } } },
                            tooltip: { backgroundColor: theme === 'dark' ? '#1a1a2e' : '#fff' }
                        }
                    }
                });
            }
            
            // Priority Chart (Bar)
            const priorityCtx = document.getElementById('priorityChart').getContext('2d');
            new Chart(priorityCtx, {
                type: 'bar',
                data: {
                    labels: ['High', 'Medium', 'Low', 'Critical'],
                    datasets: [{
                        label: 'Number of Complaints',
                        data: [<?php echo $priority_stats['high']; ?>, <?php echo $priority_stats['medium']; ?>, <?php echo $priority_stats['low']; ?>, <?php echo $priority_stats['critical']; ?>],
                        backgroundColor: ['#dc3545', '#fd7e14', '#28a745', '#6f42c1'],
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor, stepSize: 1 } },
                        x: { ticks: { color: textColor } }
                    },
                    plugins: { legend: { labels: { color: textColor } } }
                }
            });
            
            // Trend Chart (Line)
            const trendLabels = <?php echo json_encode(array_keys($monthly_data)); ?>;
            const trendData = <?php echo json_encode(array_values($monthly_data)); ?>;
            if (trendLabels.length > 0) {
                const trendCtx = document.getElementById('trendChart').getContext('2d');
                new Chart(trendCtx, {
                    type: 'line',
                    data: {
                        labels: trendLabels,
                        datasets: [{
                            label: 'Number of Complaints',
                            data: trendData,
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
                        scales: {
                            y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor, stepSize: 1 } },
                            x: { ticks: { color: textColor } }
                        },
                        plugins: { legend: { labels: { color: textColor } } }
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
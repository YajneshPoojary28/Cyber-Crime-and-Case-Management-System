<?php
require_once 'config/database.php';
redirectIfNotAdmin();

$pageTitle = 'Reports';
$conn = getConnection();

// Get filter parameters
$filter_type = $_GET['filter_type'] ?? 'all'; // all, monthly, yearly
$selected_month = $_GET['month'] ?? date('m');
$selected_year = $_GET['year'] ?? date('Y');

// Build WHERE clause based on filter
$where_clause = "";
if ($filter_type == 'monthly') {
    $where_clause = "WHERE MONTH(created_at) = $selected_month AND YEAR(created_at) = $selected_year";
} elseif ($filter_type == 'yearly') {
    $where_clause = "WHERE YEAR(created_at) = $selected_year";
}

// Get all data for reports with filters
$totalLoss = $conn->query("SELECT SUM(financial_loss) as total FROM complaints $where_clause")->fetch_assoc()['total'] ?? 0;
$totalCases = $conn->query("SELECT COUNT(*) as total FROM complaints $where_clause")->fetch_assoc()['total'];

$statusData = $conn->query("SELECT status, COUNT(*) as count FROM complaints $where_clause GROUP BY status")->fetch_all(MYSQLI_ASSOC);
$priorityData = $conn->query("SELECT priority, COUNT(*) as count FROM complaints $where_clause GROUP BY priority")->fetch_all(MYSQLI_ASSOC);

// Get all complaints for the table with filters
$allComplaints = $conn->query("SELECT id, complaint_num, user_name, category, incident_date, financial_loss, priority, status, suspicious, created_at FROM complaints $where_clause ORDER BY created_at DESC");

// Get available years for filter
$years = $conn->query("SELECT DISTINCT YEAR(created_at) as year FROM complaints ORDER BY year DESC");
$yearsArray = [];
while ($row = $years->fetch_assoc()) {
    $yearsArray[] = $row['year'];
}

$conn->close();

// Calculate percentages for status
$totalStatusCount = array_sum(array_column($statusData, 'count'));
foreach ($statusData as &$status) {
    $status['percentage'] = $totalStatusCount > 0 ? round(($status['count'] / $totalStatusCount) * 100, 1) : 0;
}

// Calculate percentages for priority
$totalPriorityCount = array_sum(array_column($priorityData, 'count'));
foreach ($priorityData as &$priority) {
    $priority['percentage'] = $totalPriorityCount > 0 ? round(($priority['count'] / $totalPriorityCount) * 100, 1) : 0;
}

// Calculate high priority count
$highPriorityCount = 0;
foreach ($priorityData as $p) {
    if ($p['priority'] == 'high' || $p['priority'] == 'critical') {
        $highPriorityCount += $p['count'];
    }
}

// Handle PDF Export
if (isset($_GET['export_pdf']) && $_GET['export_pdf'] == '1') {
    // Set PDF headers
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="cybershield_report_' . date('Y-m-d') . '.html"');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>CyberShield Compliance Report</title>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Syne:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap');
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                background: white;
                font-family: 'Inter', sans-serif;
                color: #1a1a2e;
                padding: 40px;
            }
            
            .report-container {
                max-width: 1200px;
                margin: 0 auto;
            }
            
            /* Header Section */
            .report-header {
                text-align: center;
                margin-bottom: 40px;
                padding-bottom: 20px;
                border-bottom: 2px solid #e0e0e0;
            }
            
            .report-logo {
                font-size: 48px;
                margin-bottom: 10px;
            }
            
            .report-title {
                font-family: 'Syne', sans-serif;
                font-size: 28px;
                font-weight: 800;
                color: #1e3c72;
                margin-bottom: 5px;
            }
            
            .report-subtitle {
                font-size: 12px;
                color: #666;
                font-family: 'DM Mono', monospace;
                margin-bottom: 15px;
            }
            
            .report-meta {
                font-size: 11px;
                color: #888;
                font-family: 'DM Mono', monospace;
            }
            
            /* Metrics Cards */
            .metrics-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
                margin-bottom: 40px;
            }
            
            .metric-card {
                background: #f5f5f5;
                border-radius: 12px;
                padding: 20px;
                text-align: center;
                border: 1px solid #e0e0e0;
            }
            
            .metric-icon {
                font-size: 36px;
                margin-bottom: 10px;
            }
            
            .metric-value {
                font-family: 'Syne', sans-serif;
                font-size: 28px;
                font-weight: 700;
                color: #1e3c72;
                margin-bottom: 5px;
            }
            
            .metric-label {
                font-size: 11px;
                font-family: 'DM Mono', monospace;
                color: #666;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            /* Charts Section */
            .charts-section {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 30px;
                margin-bottom: 40px;
            }
            
            .chart-box {
                background: #fafafa;
                border-radius: 12px;
                padding: 20px;
                border: 1px solid #e0e0e0;
            }
            
            .chart-header {
                text-align: center;
                margin-bottom: 20px;
            }
            
            .chart-title {
                font-family: 'Syne', sans-serif;
                font-size: 16px;
                font-weight: 700;
                color: #333;
                margin-bottom: 5px;
            }
            
            .chart-subtitle {
                font-size: 11px;
                color: #888;
                font-family: 'DM Mono', monospace;
            }
            
            /* Simple Chart Representation */
            .simple-chart {
                margin-top: 15px;
            }
            
            .chart-item {
                margin-bottom: 12px;
            }
            
            .chart-label {
                font-size: 12px;
                font-weight: 500;
                margin-bottom: 5px;
                display: flex;
                justify-content: space-between;
            }
            
            .chart-bar {
                height: 8px;
                background: #e0e0e0;
                border-radius: 4px;
                overflow: hidden;
            }
            
            .chart-fill {
                height: 100%;
                border-radius: 4px;
                transition: width 0.3s;
            }
            
            .chart-fill.status-pending { background: #f59e0b; }
            .chart-fill.status-progress { background: #38bdf8; }
            .chart-fill.status-resolved { background: #10b981; }
            .chart-fill.priority-low { background: #10b981; }
            .chart-fill.priority-medium { background: #f59e0b; }
            .chart-fill.priority-high { background: #f43f5e; }
            
            /* Table Section */
            .table-section {
                margin-top: 30px;
            }
            
            .table-title {
                font-family: 'Syne', sans-serif;
                font-size: 18px;
                font-weight: 700;
                color: #1e3c72;
                margin-bottom: 15px;
                padding-bottom: 10px;
                border-bottom: 2px solid #e0e0e0;
            }
            
            .complaints-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 12px;
            }
            
            .complaints-table th {
                background: #f0f0f0;
                padding: 12px;
                text-align: left;
                font-family: 'DM Mono', monospace;
                font-size: 10px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                border-bottom: 1px solid #ddd;
            }
            
            .complaints-table td {
                padding: 10px 12px;
                border-bottom: 1px solid #eee;
                color: #555;
            }
            
            .complaints-table tr:hover td {
                background: #f9f9f9;
            }
            
            .badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 12px;
                font-size: 10px;
                font-family: 'DM Mono', monospace;
                font-weight: 500;
            }
            
            .badge-pending { background: #fef3c7; color: #d97706; }
            .badge-progress { background: #dbeafe; color: #2563eb; }
            .badge-resolved { background: #d1fae5; color: #059669; }
            .badge-low { background: #d1fae5; color: #059669; }
            .badge-medium { background: #fef3c7; color: #d97706; }
            .badge-high { background: #fee2e2; color: #dc2626; }
            
            .complaint-id {
                font-family: 'DM Mono', monospace;
                font-size: 11px;
                color: #667eea;
                background: #eef2ff;
                padding: 3px 8px;
                border-radius: 5px;
            }
            
            .footer {
                margin-top: 40px;
                padding-top: 20px;
                text-align: center;
                border-top: 2px solid #e0e0e0;
                font-size: 10px;
                color: #999;
                font-family: 'DM Mono', monospace;
            }
            
            @media print {
                body {
                    padding: 20px;
                }
                .metric-card {
                    break-inside: avoid;
                }
                .chart-box {
                    break-inside: avoid;
                }
                .complaints-table tr {
                    break-inside: avoid;
                }
            }
        </style>
    </head>
    <body>
        <div class="report-container">
            <!-- Header -->
            <div class="report-header">
                <div class="report-logo">🛡️</div>
                <div class="report-title">CyberShield Compliance Report</div>
                <div class="report-subtitle">Cyber Crime Reporting & Case Management System</div>
                <div class="report-meta">
                    Generated on: <?php echo date('F d, Y \a\t h:i A'); ?><br>
                    <?php 
                    if ($filter_type == 'monthly') {
                        echo "Period: " . date('F Y', mktime(0,0,0,$selected_month,1,$selected_year));
                    } elseif ($filter_type == 'yearly') {
                        echo "Period: Year {$selected_year}";
                    } else {
                        echo "Period: All Time";
                    }
                    ?>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-icon">💰</div>
                    <div class="metric-value"><?php echo $totalLoss > 0 ? '₹' . number_format($totalLoss, 0) : '₹0'; ?></div>
                    <div class="metric-label">Total Financial Loss</div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon">📋</div>
                    <div class="metric-value"><?php echo $totalCases; ?></div>
                    <div class="metric-label">Total Cases Filed</div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon">🔴</div>
                    <div class="metric-value"><?php echo $highPriorityCount; ?></div>
                    <div class="metric-label">High Priority Cases</div>
                </div>
            </div>
            
            <!-- Charts -->
            <div class="charts-section">
                <div class="chart-box">
                    <div class="chart-header">
                        <div class="chart-title">📊 Status Distribution</div>
                        <div class="chart-subtitle">Complaint status breakdown</div>
                    </div>
                    <div class="simple-chart">
                        <?php foreach ($statusData as $status): ?>
                        <div class="chart-item">
                            <div class="chart-label">
                                <span><?php echo ucfirst($status['status']); ?></span>
                                <span><?php echo $status['count']; ?> cases (<?php echo $status['percentage']; ?>%)</span>
                            </div>
                            <div class="chart-bar">
                                <div class="chart-fill status-<?php echo $status['status'] == 'pending' ? 'pending' : ($status['status'] == 'in-progress' ? 'progress' : 'resolved'); ?>" style="width: <?php echo $status['percentage']; ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="chart-box">
                    <div class="chart-header">
                        <div class="chart-title">⚠️ Priority Distribution</div>
                        <div class="chart-subtitle">Priority level breakdown</div>
                    </div>
                    <div class="simple-chart">
                        <?php foreach ($priorityData as $priority): ?>
                        <div class="chart-item">
                            <div class="chart-label">
                                <span><?php echo ucfirst($priority['priority']); ?></span>
                                <span><?php echo $priority['count']; ?> cases (<?php echo $priority['percentage']; ?>%)</span>
                            </div>
                            <div class="chart-bar">
                                <div class="chart-fill priority-<?php echo $priority['priority']; ?>" style="width: <?php echo $priority['percentage']; ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Complaints List -->
            <div class="table-section">
                <div class="table-title">📋 All Complaints</div>
                <table class="complaints-table">
                    <thead>
                        <tr>
                            <th>Complaint #</th>
                            <th>User</th>
                            <th>Category</th>
                            <th>Date</th>
                            <th>Loss (₹)</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Flag</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $allComplaints->data_seek(0);
                        while($row = $allComplaints->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><span class="complaint-id"><?php echo htmlspecialchars($row['complaint_num']); ?></span></td>
                            <td><?php echo htmlspecialchars($row['user_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                            <td><?php echo date('d M Y', strtotime($row['incident_date'])); ?></td>
                            <td><?php echo $row['financial_loss'] > 0 ? '₹' . number_format($row['financial_loss'], 2) : '—'; ?></td>
                            <td><span class="badge badge-<?php echo $row['priority']; ?>"><?php echo ucfirst($row['priority']); ?></span></td>
                            <td><span class="badge badge-<?php echo str_replace('-', '', $row['status']); ?>"><?php echo ucfirst(str_replace('-', ' ', $row['status'])); ?></span></td>
                            <td><?php echo $row['suspicious'] ? '⚠️ Flagged' : '—'; ?></td>
                        </tr>
                        <?php endwhile; ?>
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
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="reports-container">
    <!-- Filter Section -->
    <div class="filter-section">
        <div class="filter-header">
            <div class="filter-title">📅 Filter Reports</div>
            <div class="filter-buttons">
                <button class="filter-btn <?php echo $filter_type == 'all' ? 'active' : ''; ?>" onclick="setFilter('all')">All Time</button>
                <button class="filter-btn <?php echo $filter_type == 'yearly' ? 'active' : ''; ?>" onclick="setFilter('yearly')">Yearly</button>
                <button class="filter-btn <?php echo $filter_type == 'monthly' ? 'active' : ''; ?>" onclick="setFilter('monthly')">Monthly</button>
            </div>
        </div>
        
        <form method="GET" id="filterForm" class="filter-controls">
            <input type="hidden" name="filter_type" id="filter_type" value="<?php echo $filter_type; ?>">
            
            <div id="yearlyControls" class="filter-group" style="display: <?php echo $filter_type == 'yearly' ? 'flex' : 'none'; ?>">
                <label>Select Year:</label>
                <select name="year" class="filter-select" onchange="this.form.submit()">
                    <?php foreach ($yearsArray as $year): ?>
                        <option value="<?php echo $year; ?>" <?php echo $selected_year == $year ? 'selected' : ''; ?>>
                            <?php echo $year; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div id="monthlyControls" class="filter-group" style="display: <?php echo $filter_type == 'monthly' ? 'flex' : 'none'; ?>">
                <label>Select Year:</label>
                <select name="year" class="filter-select" onchange="this.form.submit()">
                    <?php foreach ($yearsArray as $year): ?>
                        <option value="<?php echo $year; ?>" <?php echo $selected_year == $year ? 'selected' : ''; ?>>
                            <?php echo $year; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <label>Select Month:</label>
                <select name="month" class="filter-select" onchange="this.form.submit()">
                    <option value="01" <?php echo $selected_month == '01' ? 'selected' : ''; ?>>January</option>
                    <option value="02" <?php echo $selected_month == '02' ? 'selected' : ''; ?>>February</option>
                    <option value="03" <?php echo $selected_month == '03' ? 'selected' : ''; ?>>March</option>
                    <option value="04" <?php echo $selected_month == '04' ? 'selected' : ''; ?>>April</option>
                    <option value="05" <?php echo $selected_month == '05' ? 'selected' : ''; ?>>May</option>
                    <option value="06" <?php echo $selected_month == '06' ? 'selected' : ''; ?>>June</option>
                    <option value="07" <?php echo $selected_month == '07' ? 'selected' : ''; ?>>July</option>
                    <option value="08" <?php echo $selected_month == '08' ? 'selected' : ''; ?>>August</option>
                    <option value="09" <?php echo $selected_month == '09' ? 'selected' : ''; ?>>September</option>
                    <option value="10" <?php echo $selected_month == '10' ? 'selected' : ''; ?>>October</option>
                    <option value="11" <?php echo $selected_month == '11' ? 'selected' : ''; ?>>November</option>
                    <option value="12" <?php echo $selected_month == '12' ? 'selected' : ''; ?>>December</option>
                </select>
            </div>
            
            <?php if ($filter_type != 'all'): ?>
                <button type="submit" class="btn-apply-filter">Apply Filter</button>
                <a href="admin-reports.php?filter_type=all" class="btn-reset-filter">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Metric Cards -->
    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-icon">💰</div>
            <div class="metric-info">
                <div class="metric-value"><?php echo $totalLoss > 0 ? '₹' . number_format($totalLoss / 1000, 1) . 'K' : '₹0'; ?></div>
                <div class="metric-label">Total Financial Loss</div>
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-icon">📋</div>
            <div class="metric-info">
                <div class="metric-value"><?php echo $totalCases; ?></div>
                <div class="metric-label">Total Cases Filed</div>
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-icon">🔴</div>
            <div class="metric-info">
                <div class="metric-value" style="color: var(--danger);"><?php echo $highPriorityCount; ?></div>
                <div class="metric-label">High Priority Cases</div>
            </div>
        </div>
    </div>

    <!-- Charts Section - Same Size -->
    <div class="charts-same-size">
        <div class="chart-box">
            <div class="chart-header">
                <h3 class="chart-title">📊 Status Distribution</h3>
                <p class="chart-subtitle">Complaint status breakdown</p>
            </div>
            <div class="chart-wrapper">
                <canvas id="statusChart" width="400" height="400"></canvas>
            </div>
        </div>
        
        <div class="chart-box">
            <div class="chart-header">
                <h3 class="chart-title">⚠️ Priority Distribution</h3>
                <p class="chart-subtitle">Priority level breakdown</p>
            </div>
            <div class="chart-wrapper">
                <canvas id="priorityChart" width="400" height="400"></canvas>
            </div>
        </div>
    </div>

    <!-- All Complaints Table -->
    <div class="all-complaints-section">
        <div class="section-header">
            <div class="section-title">📋 All Complaints</div>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['export_pdf' => '1'])); ?>" class="btn-export-pdf">
                📄 Export PDF Report
            </a>
        </div>
        
        <div class="table-container">
            <table class="complaints-full-table">
                <thead>
                    <tr>
                        <th>Complaint #</th>
                        <th>User</th>
                        <th>Category</th>
                        <th>Date</th>
                        <th>Loss (₹)</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Flag</th>
                        <th>Filed On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($allComplaints->num_rows == 0): ?>
                        <tr>
                            <td colspan="9" class="empty-table">
                                <div class="empty-state">
                                    <div class="empty-icon">📭</div>
                                    <div class="empty-text">No complaints found for this period</div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $allComplaints->data_seek(0);
                        while($row = $allComplaints->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><span class="complaint-id"><?php echo htmlspecialchars($row['complaint_num']); ?></span></td>
                            <td><?php echo htmlspecialchars($row['user_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                            <td><?php echo date('d M Y', strtotime($row['incident_date'])); ?></td>
                            <td class="<?php echo $row['financial_loss'] > 0 ? 'loss-positive' : 'loss-zero'; ?>">
                                <?php echo $row['financial_loss'] > 0 ? '₹' . number_format($row['financial_loss'], 2) : '—'; ?>
                            </td>
                            <td><span class="priority-badge priority-<?php echo $row['priority']; ?>"><?php echo ucfirst($row['priority']); ?></span></td>
                            <td><span class="status-badge status-<?php echo str_replace('-', '', $row['status']); ?>"><?php echo ucfirst(str_replace('-', ' ', $row['status'])); ?></span></td>
                            <td><?php echo $row['suspicious'] ? '<span class="flag-badge">⚠️ Flagged</span>' : '—'; ?></td>
                            <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
/* Filter Section */
.filter-section {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 24px;
}

.filter-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    flex-wrap: wrap;
    gap: 12px;
}

.filter-title {
    font-family: var(--display);
    font-size: 16px;
    font-weight: 700;
    color: var(--text);
}

.filter-buttons {
    display: flex;
    gap: 8px;
}

.filter-btn {
    background: var(--bg4);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 6px 16px;
    color: var(--text2);
    font-size: 12px;
    font-family: var(--mono);
    cursor: pointer;
    transition: all 0.2s;
}

.filter-btn:hover {
    background: var(--bg3);
    color: var(--text);
}

.filter-btn.active {
    background: var(--accent);
    border-color: var(--accent);
    color: white;
}

.filter-controls {
    display: flex;
    gap: 16px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-group label {
    font-size: 12px;
    font-family: var(--mono);
    color: var(--text3);
}

.filter-select {
    background: var(--bg4);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 8px 12px;
    color: var(--text2);
    font-size: 13px;
    cursor: pointer;
}

.btn-apply-filter {
    background: var(--accent);
    border: none;
    border-radius: 8px;
    padding: 8px 20px;
    color: white;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-apply-filter:hover {
    background: #5a52e8;
}

.btn-reset-filter {
    background: rgba(244,63,94,0.15);
    border: 1px solid rgba(244,63,94,0.3);
    border-radius: 8px;
    padding: 7px 18px;
    color: var(--danger);
    font-size: 12px;
    text-decoration: none;
    transition: all 0.2s;
}

.btn-reset-filter:hover {
    background: rgba(244,63,94,0.25);
}

/* Metrics Grid */
.metrics-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 32px;
}

.metric-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    transition: all 0.2s;
}

.metric-card:hover {
    border-color: var(--border2);
    transform: translateY(-2px);
}

.metric-icon {
    font-size: 48px;
}

.metric-info {
    flex: 1;
}

.metric-value {
    font-family: var(--display);
    font-size: 32px;
    font-weight: 800;
    color: var(--text);
    margin-bottom: 4px;
}

.metric-label {
    font-size: 12px;
    font-family: var(--mono);
    color: var(--text3);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Charts Same Size */
.charts-same-size {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 40px;
}

.chart-box {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 24px;
    display: flex;
    flex-direction: column;
}

.chart-header {
    text-align: center;
    margin-bottom: 20px;
}

.chart-title {
    font-family: var(--display);
    font-size: 18px;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 4px;
}

.chart-subtitle {
    font-size: 12px;
    color: var(--text3);
    font-family: var(--mono);
}

.chart-wrapper {
    position: relative;
    width: 100%;
    max-width: 350px;
    margin: 0 auto;
}

/* All Complaints Section */
.all-complaints-section {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 24px;
    margin-top: 8px;
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
    font-size: 18px;
    font-weight: 700;
    color: var(--text);
}

.btn-export-pdf {
    background: rgba(16,185,129,0.15);
    border: 1px solid rgba(16,185,129,0.3);
    border-radius: 10px;
    padding: 10px 20px;
    color: #10b981;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-export-pdf:hover {
    background: rgba(16,185,129,0.25);
    transform: translateY(-1px);
}

/* Table Styles */
.table-container {
    overflow-x: auto;
}

.complaints-full-table {
    width: 100%;
    border-collapse: collapse;
}

.complaints-full-table th {
    text-align: left;
    padding: 14px 16px;
    background: var(--bg4);
    color: var(--text3);
    font-size: 11px;
    font-family: var(--mono);
    font-weight: 600;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    border-bottom: 1px solid var(--border);
}

.complaints-full-table td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    color: var(--text2);
    font-size: 13px;
}

.complaints-full-table tr:hover td {
    background: var(--bg4);
}

.complaints-full-table tr:last-child td {
    border-bottom: none;
}

/* Badges */
.complaint-id {
    font-family: var(--mono);
    font-size: 11px;
    color: var(--accent2);
    background: rgba(108,99,255,0.1);
    padding: 4px 10px;
    border-radius: 6px;
    display: inline-block;
}

.priority-badge, .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-family: var(--mono);
    font-weight: 500;
}

.priority-badge::before, .status-badge::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.priority-low { background: rgba(16,185,129,0.15); color: var(--success); }
.priority-medium { background: rgba(245,158,11,0.15); color: var(--warning); }
.priority-high { background: rgba(244,63,94,0.15); color: var(--danger); }
.priority-critical { background: rgba(139,0,0,0.2); color: #ff4060; }

.status-pending { background: rgba(245,158,11,0.15); color: var(--warning); }
.status-inprogress { background: rgba(56,189,248,0.15); color: var(--accent3); }
.status-resolved { background: rgba(16,185,129,0.15); color: var(--success); }
.status-closed { background: rgba(100,100,120,0.15); color: var(--text3); }

.flag-badge {
    background: rgba(244,63,94,0.15);
    color: var(--danger);
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 10px;
    font-family: var(--mono);
}

.loss-positive {
    color: var(--danger);
    font-weight: 500;
}

.loss-zero {
    color: var(--text3);
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

/* Responsive */
@media (max-width: 1024px) {
    .charts-same-size {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .metrics-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .complaints-full-table th {
        display: none;
    }
    
    .complaints-full-table td {
        display: block;
        padding: 12px 16px;
        text-align: right;
        position: relative;
        border-bottom: none;
    }
    
    .complaints-full-table td::before {
        content: attr(data-label);
        position: absolute;
        left: 16px;
        top: 12px;
        font-size: 11px;
        font-family: var(--mono);
        color: var(--text3);
        font-weight: 500;
    }
    
    .complaints-full-table tr {
        display: block;
        margin-bottom: 16px;
        border: 1px solid var(--border);
        border-radius: 12px;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .filter-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .filter-controls {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Status Chart Data
const statusLabels = <?php echo json_encode(array_column($statusData, 'status')); ?>;
const statusCounts = <?php echo json_encode(array_column($statusData, 'count')); ?>;
const statusPercentages = <?php echo json_encode(array_column($statusData, 'percentage')); ?>;

// Priority Chart Data
const priorityLabels = <?php echo json_encode(array_column($priorityData, 'priority')); ?>;
const priorityCounts = <?php echo json_encode(array_column($priorityData, 'count')); ?>;
const priorityPercentages = <?php echo json_encode(array_column($priorityData, 'percentage')); ?>;

// Status Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'pie',
    data: {
        labels: statusLabels.map((label, index) => `${label} (${statusPercentages[index]}%)`),
        datasets: [{
            data: statusCounts,
            backgroundColor: ['rgba(245,158,11,0.8)', 'rgba(56,189,248,0.8)', 'rgba(16,185,129,0.8)', 'rgba(100,100,120,0.8)'],
            borderWidth: 0,
            hoverOffset: 10
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { color: '#9090b0', font: { size: 11, family: 'DM Mono, monospace' }, boxWidth: 12, padding: 10 }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.raw || 0;
                        const percentage = statusPercentages[context.dataIndex];
                        return `${label}: ${value} cases (${percentage}%)`;
                    }
                }
            }
        }
    }
});

// Priority Chart
const priorityCtx = document.getElementById('priorityChart').getContext('2d');
new Chart(priorityCtx, {
    type: 'doughnut',
    data: {
        labels: priorityLabels.map((label, index) => `${label} (${priorityPercentages[index]}%)`),
        datasets: [{
            data: priorityCounts,
            backgroundColor: ['rgba(16,185,129,0.8)', 'rgba(245,158,11,0.8)', 'rgba(244,63,94,0.8)', 'rgba(139,0,0,0.8)'],
            borderWidth: 0,
            hoverOffset: 10
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { color: '#9090b0', font: { size: 11, family: 'DM Mono, monospace' }, boxWidth: 12, padding: 10 }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.raw || 0;
                        const percentage = priorityPercentages[context.dataIndex];
                        return `${label}: ${value} cases (${percentage}%)`;
                    }
                }
            }
        }
    }
});

// Set filter function
function setFilter(type) {
    document.getElementById('filter_type').value = type;
    document.getElementById('filterForm').submit();
}

// Add data labels to table cells
document.querySelectorAll('.complaints-full-table td').forEach(td => {
    const th = td.parentElement.parentElement.querySelectorAll('th')[td.cellIndex];
    if (th) {
        td.setAttribute('data-label', th.textContent);
    }
});
</script>

<?php include 'includes/footer.php'; ?>
<?php
require_once 'config/database.php';
redirectIfNotAdmin();

$pageTitle = 'Admin Dashboard';
$conn = getConnection();

// Get statistics - Fixed column aliases (removed reserved keyword 'high_priority')
$total = $conn->query("SELECT COUNT(*) as total FROM complaints")->fetch_assoc()['total'];
$pending = $conn->query("SELECT COUNT(*) as pending FROM complaints WHERE status = 'pending'")->fetch_assoc()['pending'];
$resolved = $conn->query("SELECT COUNT(*) as resolved FROM complaints WHERE status = 'resolved'")->fetch_assoc()['resolved'];

// Fixed: Changed 'high_priority' to 'high_priority_count'
$highPriorityCount = $conn->query("SELECT COUNT(*) as high_priority_count FROM complaints WHERE priority IN ('high', 'critical')")->fetch_assoc()['high_priority_count'];

$suspicious = $conn->query("SELECT COUNT(*) as suspicious FROM complaints WHERE suspicious = 1")->fetch_assoc()['suspicious'];

$recentComplaints = $conn->query("SELECT id, complaint_num, user_name, category, incident_date, priority, status, suspicious FROM complaints ORDER BY created_at DESC LIMIT 5");
$recentComplaintsArray = [];
if ($recentComplaints) {
    $recentComplaintsArray = $recentComplaints->fetch_all(MYSQLI_ASSOC);
}

$categories = $conn->query("SELECT category, COUNT(*) as count FROM complaints GROUP BY category");
$categoriesArray = [];
if ($categories) {
    $categoriesArray = $categories->fetch_all(MYSQLI_ASSOC);
}

// Get priority counts for chart
$lowCount = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE priority = 'low'")->fetch_assoc()['count'];
$mediumCount = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE priority = 'medium'")->fetch_assoc()['count'];
$highCount = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE priority = 'high'")->fetch_assoc()['count'];
$criticalCount = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE priority = 'critical'")->fetch_assoc()['count'];

$conn->close();
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="dashboard-container">
    <div class="welcome-hero">
        <div class="welcome-badge">🔐 Admin Console</div>
        <div class="welcome-title">Welcome, <span><?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>!</div>
        <div class="welcome-text">Manage cyber crime complaints, track investigations, and oversee user reports from this central dashboard.</div>
    </div>

    <!-- Statistics Cards - Removed In Progress -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-header">
                <div class="stat-label">Total Complaints</div>
                <div class="stat-icon">📁</div>
            </div>
            <div class="stat-value"><?php echo $total; ?></div>
            <div class="stat-delta">↗ All time</div>
        </div>
        <div class="stat-card yellow">
            <div class="stat-header">
                <div class="stat-label">Pending</div>
                <div class="stat-icon">⏳</div>
            </div>
            <div class="stat-value"><?php echo $pending; ?></div>
            <div class="stat-delta neg">Needs attention</div>
        </div>
        <div class="stat-card green">
            <div class="stat-header">
                <div class="stat-label">Resolved</div>
                <div class="stat-icon">✅</div>
            </div>
            <div class="stat-value"><?php echo $resolved; ?></div>
            <div class="stat-delta">Cases closed</div>
        </div>
    </div>

    <div class="stats-grid-second">
        <div class="stat-card orange">
            <div class="stat-header">
                <div class="stat-label">High Priority</div>
                <div class="stat-icon">🔴</div>
            </div>
            <div class="stat-value"><?php echo $highPriorityCount; ?></div>
            <div class="stat-delta neg">Critical cases</div>
        </div>
        <div class="stat-card purple">
            <div class="stat-header">
                <div class="stat-label">AI Flagged</div>
                <div class="stat-icon">🤖</div>
            </div>
            <div class="stat-value"><?php echo $suspicious; ?></div>
            <div class="stat-delta neg">Suspicious activity</div>
        </div>
    </div>

    <!-- Two Column Grid for Category Distribution (Left) and Priority Distribution (Right) - Same Size -->
    <div class="two-column-grid equal-size">
        <div>
            <div class="section-header">
                <div class="section-title">Category Distribution</div>
            </div>
            <div class="chart-card equal-chart">
                <canvas id="catChart" height="300"></canvas>
            </div>
        </div>
        
        <div>
            <div class="section-header">
                <div class="section-title">Priority Distribution</div>
            </div>
            <div class="chart-card equal-chart">
                <canvas id="priChart" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Recent Complaints at Bottom - Full Width with Proper Padding and Spacing -->
    <div class="recent-complaints-section">
        <div class="section-header">
            <div class="section-title">Recent Complaints</div>
            <a href="admin-complaints.php" class="view-all-btn">View All →</a>
        </div>
        <div class="complaints-card">
            <div class="table-responsive">
                <table class="complaints-table">
                    <thead>
                        <tr>
                            <th>COMPLAINT #</th>
                            <th>USER</th>
                            <th>CATEGORY</th>
                            <th>DATE</th>
                            <th>PRIORITY</th>
                            <th>STATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentComplaintsArray)): ?>
                            <tr>
                                <td colspan="6" class="empty-row">No complaints found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentComplaintsArray as $c): ?>
                            <tr onclick="location.href='complaint-detail.php?id=<?php echo $c['id']; ?>'">
                                <td><span class="complaint-id"><?php echo htmlspecialchars($c['complaint_num']); ?></span></td>
                                <td><?php echo htmlspecialchars($c['user_name']); ?></td>
                                <td><?php echo htmlspecialchars($c['category']); ?></td>
                                <td><?php echo $c['incident_date']; ?></td>
                                <td><span class="priority-badge priority-<?php echo $c['priority']; ?>"><?php echo ucfirst($c['priority']); ?></span></td>
                                <td><span class="status-badge status-<?php echo str_replace('-', '', $c['status']); ?>"><?php echo ucfirst(str_replace('-', ' ', $c['status'])); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.dashboard-container { 
    max-width: 1400px; 
    margin: 0 auto; 
    padding: 0;
}

/* Welcome Hero */
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
}

/* Statistics Cards */
.stats-grid { 
    display: grid; 
    grid-template-columns: repeat(3, 1fr); 
    gap: 20px; 
    margin-bottom: 20px; 
}
.stats-grid-second { 
    display: grid; 
    grid-template-columns: repeat(2, 1fr); 
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
.stat-card.orange::before { background: linear-gradient(90deg, #f97316, #fb923c); }
.stat-card.purple::before { background: linear-gradient(90deg, #a855f7, #c084fc); }
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

/* Two Column Grid - Equal Size */
.two-column-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 48px;
}
.equal-size {
    align-items: stretch;
}
.equal-chart {
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

/* Chart Cards */
.chart-card { 
    background: var(--card); 
    border: 1px solid var(--border); 
    border-radius: 16px; 
    padding: 24px; 
    min-height: 380px; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
}

/* Recent Complaints Section */
.recent-complaints-section {
    width: 100%;
    margin-top: 32px;
}

.section-header { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    margin-bottom: 20px; 
    padding: 0 4px;
}

.section-title { 
    font-family: var(--display); 
    font-size: 18px; 
    font-weight: 700; 
    color: var(--text); 
}

.view-all-btn { 
    background: none; 
    border: none; 
    color: var(--accent2); 
    cursor: pointer; 
    font-size: 12px; 
    font-family: var(--mono); 
    padding: 8px 16px; 
    border-radius: 8px; 
    transition: all 0.2s; 
    text-decoration: none;
    display: inline-block;
}
.view-all-btn:hover { 
    background: var(--bg4); 
    color: var(--accent3); 
}

/* Complaints Card */
.complaints-card { 
    background: var(--card); 
    border: 1px solid var(--border); 
    border-radius: 16px; 
    overflow: hidden;
}

.table-responsive {
    overflow-x: auto;
    padding: 0;
}

/* Complaints Table */
.complaints-table { 
    width: 100%; 
    border-collapse: collapse; 
}

.complaints-table th { 
    text-align: left; 
    padding: 16px 20px; 
    background: var(--bg4); 
    color: var(--text3); 
    font-size: 11px; 
    font-family: var(--mono); 
    font-weight: 600; 
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--border); 
}

.complaints-table td { 
    padding: 16px 20px; 
    border-bottom: 1px solid var(--border); 
    color: var(--text2); 
    font-size: 13px; 
}

.complaints-table tr { 
    cursor: pointer; 
    transition: background 0.2s; 
}

.complaints-table tr:hover td { 
    background: var(--bg4); 
}

.complaints-table tr:last-child td { 
    border-bottom: none; 
}

.empty-row {
    text-align: center;
    padding: 60px 20px !important;
    color: var(--text3);
}

/* Complaint ID */
.complaint-id { 
    font-family: var(--mono); 
    font-size: 12px; 
    color: var(--accent2); 
    background: rgba(108,99,255,0.1); 
    padding: 4px 10px; 
    border-radius: 6px; 
    display: inline-block;
}

/* Priority Badges */
.priority-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-family: var(--mono);
    font-weight: 500;
}

.priority-badge::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.priority-low {
    background: rgba(16,185,129,0.15);
    color: var(--success);
}

.priority-medium {
    background: rgba(245,158,11,0.15);
    color: var(--warning);
}

.priority-high {
    background: rgba(244,63,94,0.15);
    color: var(--danger);
}

.priority-critical {
    background: rgba(139,0,0,0.2);
    color: #ff4060;
}

/* Status Badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-family: var(--mono);
    font-weight: 500;
}

.status-badge::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.status-pending {
    background: rgba(245,158,11,0.15);
    color: var(--warning);
}

.status-inprogress {
    background: rgba(56,189,248,0.15);
    color: var(--accent3);
}

.status-resolved {
    background: rgba(16,185,129,0.15);
    color: var(--success);
}

.status-closed {
    background: rgba(100,100,120,0.15);
    color: var(--text3);
}

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
    .stats-grid-second { 
        grid-template-columns: 1fr; 
    }
    .complaints-table th,
    .complaints-table td {
        padding: 12px 16px;
    }
    .welcome-title {
        font-size: 24px;
    }
    .welcome-hero {
        padding: 28px 20px;
    }
    .recent-complaints-section {
        margin-top: 24px;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Priority Chart - Bar Chart (Right Side)
const priCtx = document.getElementById('priChart').getContext('2d');
new Chart(priCtx, {
    type: 'bar',
    data: {
        labels: ['Low', 'Medium', 'High', 'Critical'],
        datasets: [{
            label: 'Number of Complaints',
            data: [<?php echo $lowCount; ?>, <?php echo $mediumCount; ?>, <?php echo $highCount; ?>, <?php echo $criticalCount; ?>],
            backgroundColor: ['rgba(16,185,129,0.8)', 'rgba(245,158,11,0.8)', 'rgba(244,63,94,0.8)', 'rgba(139,0,0,0.8)'],
            borderRadius: 8,
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false },
            tooltip: { backgroundColor: 'var(--bg4)', titleColor: 'var(--text)', bodyColor: 'var(--text2)' }
        },
        scales: {
            x: { 
                ticks: { color: '#9090b0', font: { size: 12 } }, 
                grid: { display: false } 
            },
            y: { 
                ticks: { color: '#9090b0', stepSize: 1, font: { size: 11 } }, 
                grid: { color: 'rgba(255,255,255,0.05)' } 
            }
        }
    }
});

// Category Chart - Doughnut Chart (Left Side)
const catLabels = <?php echo json_encode(array_column($categoriesArray, 'category')); ?>;
const catData = <?php echo json_encode(array_column($categoriesArray, 'count')); ?>;
const catCtx = document.getElementById('catChart').getContext('2d');
new Chart(catCtx, {
    type: 'doughnut',
    data: {
        labels: catLabels,
        datasets: [{
            data: catData,
            backgroundColor: ['rgba(108,99,255,0.8)', 'rgba(244,63,94,0.8)', 'rgba(56,189,248,0.8)', 'rgba(16,185,129,0.8)', 'rgba(245,158,11,0.8)', 'rgba(139,0,0,0.8)'],
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
            tooltip: { backgroundColor: 'var(--bg4)', titleColor: 'var(--text)', bodyColor: 'var(--text2)' }
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>
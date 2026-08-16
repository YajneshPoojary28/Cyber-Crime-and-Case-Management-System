<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['officer_id']) && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$complaint_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$conn = getConnection();

if (isset($_SESSION['officer_id'])) {
    $stmt = $conn->prepare("SELECT c.*, u.name as citizen_name, u.email as citizen_email FROM complaints c JOIN users u ON c.user_id = u.id WHERE c.id = ? AND c.officer_id = ?");
    $stmt->bind_param("ii", $complaint_id, $_SESSION['officer_id']);
} else {
    $stmt = $conn->prepare("SELECT c.*, u.name as citizen_name FROM complaints c JOIN users u ON c.user_id = u.id WHERE c.id = ? AND c.user_id = ?");
    $stmt->bind_param("ii", $complaint_id, $_SESSION['user_id']);
}
$stmt->execute();
$complaint = $stmt->get_result()->fetch_assoc();

if (!$complaint) {
    header("Location: dashboard.php");
    exit();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaint Details - CyberShield</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { background: var(--bg); font-family: var(--sans); color: var(--text); }
        .container { max-width: 900px; margin: 50px auto; padding: 20px; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; }
        .card-header { background: linear-gradient(135deg, var(--accent) 0%, var(--accent2) 100%); color: white; padding: 20px; }
        .card-body { padding: 30px; }
        .info-row { display: flex; padding: 10px 0; border-bottom: 1px solid var(--border); }
        .info-label { width: 150px; font-weight: 600; color: var(--text2); }
        .info-value { flex: 1; color: var(--text); }
        .btn-back { background: var(--bg4); color: var(--text); border: 1px solid var(--border); padding: 10px 20px; border-radius: 8px; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h4>Complaint Details #<?php echo $complaint_id; ?></h4>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <div class="info-label">Complaint Number:</div>
                    <div class="info-value"><?php echo htmlspecialchars($complaint['complaint_num'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Citizen Name:</div>
                    <div class="info-value"><?php echo htmlspecialchars($complaint['citizen_name']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Crime Type:</div>
                    <div class="info-value"><?php echo htmlspecialchars($complaint['type'] ?? $complaint['category'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Status:</div>
                    <div class="info-value">
                        <span class="badge bg-<?php echo $complaint['status'] == 'Resolved' ? 'success' : ($complaint['status'] == 'In Progress' ? 'warning' : 'secondary'); ?>">
                            <?php echo $complaint['status']; ?>
                        </span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Priority:</div>
                    <div class="info-value">
                        <span class="badge bg-<?php echo $complaint['priority'] == 'High' ? 'danger' : ($complaint['priority'] == 'Medium' ? 'warning' : 'info'); ?>">
                            <?php echo $complaint['priority']; ?>
                        </span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Date Filed:</div>
                    <div class="info-value"><?php echo date('d M Y h:i A', strtotime($complaint['created_at'])); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Description:</div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></div>
                </div>
                <?php if (!empty($complaint['remarks'])): ?>
                <div class="info-row">
                    <div class="info-label">Remarks:</div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($complaint['remarks'])); ?></div>
                </div>
                <?php endif; ?>
                <div class="mt-4">
                    <a href="javascript:history.back()" class="btn-back">← Back</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
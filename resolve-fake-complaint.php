<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if officer is logged in
if (!isset($_SESSION['officer_id'])) {
    header("Location: officer-login.php");
    exit();
}

$complaint_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success = '';
$error = '';

$conn = getConnection();

// Get complaint details
$stmt = $conn->prepare("SELECT c.*, u.name as citizen_name, u.email as citizen_email FROM complaints c JOIN users u ON c.user_id = u.id WHERE c.id = ? AND c.assigned_to = ?");
$stmt->bind_param("ii", $complaint_id, $_SESSION['officer_id']);
$stmt->execute();
$complaint = $stmt->get_result()->fetch_assoc();

if (!$complaint) {
    header("Location: officer-dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_as_fake'])) {
    $resolution_notes = sanitize($_POST['resolution_notes']);
    
    if (empty($resolution_notes)) {
        $error = "Please provide resolution notes";
    } else {
        // IMPORTANT: Update complaint status to 'fake' (NOT 'resolved')
        $update = $conn->prepare("UPDATE complaints SET status = 'fake', resolved_date = NOW(), resolution_notes = ? WHERE id = ?");
        $update->bind_param("si", $resolution_notes, $complaint_id);
        
        if ($update->execute()) {
            // 1. Add to timeline
            $timeline_note = "⚠️ FAKE USER DETECTED - Complaint #{$complaint['complaint_num']} marked as FAKE by Officer {$_SESSION['officer_name']}";
            $timeline_stmt = $conn->prepare("INSERT INTO complaint_timeline (complaint_id, action, action_by, note) VALUES (?, ?, ?, ?)");
            $action_by = $_SESSION['officer_name'] ?? 'Officer';
            $timeline_stmt->bind_param("isss", $complaint_id, $timeline_note, $action_by, $resolution_notes);
            $timeline_stmt->execute();
            $timeline_stmt->close();
            
            // 2. Send notification to ADMIN (user_id = 1 for admin)
            $admin_message = "⚠️ FAKE USER DETECTED - Complaint #{$complaint['complaint_num']} has been marked as FAKE by Officer {$_SESSION['officer_name']}. Action taken: {$resolution_notes}";
            $admin_notification = $conn->prepare("INSERT INTO notifications (user_id, complaint_id, title, message, is_read, created_at) VALUES (1, ?, '⚠️ FAKE USER COMPLAINT', ?, 0, NOW())");
            $admin_notification->bind_param("is", $complaint_id, $admin_message);
            $admin_notification->execute();
            $admin_notification->close();
            
            // 3. Send notification to CITIZEN
            $citizen_message = "⚠️ Your complaint #{$complaint['complaint_num']} has been marked as a FAKE complaint after investigation by Officer {$_SESSION['officer_name']}. Reason: {$resolution_notes}";
            $citizen_notification = $conn->prepare("INSERT INTO notifications (user_id, complaint_id, title, message, is_read, created_at) VALUES (?, ?, '❌ Complaint Marked as Fake', ?, 0, NOW())");
            $citizen_notification->bind_param("iis", $complaint['user_id'], $complaint_id, $citizen_message);
            $citizen_notification->execute();
            $citizen_notification->close();
            
            $success = "Complaint marked as FAKE successfully! Admin and citizen have been notified.";
        } else {
            $error = "Failed to mark complaint as fake";
        }
        $update->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark as Fake Complaint - CyberShield</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { background: var(--bg); font-family: var(--sans); color: var(--text); }
        .container { max-width: 800px; margin: 50px auto; padding: 20px; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; }
        .card-header { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 20px; }
        .card-body { padding: 30px; }
        .form-control { background: var(--bg4); border: 1px solid var(--border); border-radius: 8px; padding: 12px; color: var(--text); width: 100%; }
        .btn-submit { background: #dc3545; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; transition: all 0.3s; }
        .btn-submit:hover { background: #c82333; transform: translateY(-1px); }
        .btn-back { background: var(--bg4); color: var(--text); border: 1px solid var(--border); padding: 12px 24px; border-radius: 8px; text-decoration: none; display: inline-block; transition: all 0.3s; }
        .btn-back:hover { background: var(--bg3); color: var(--text); text-decoration: none; }
        .complaint-details { background: var(--bg3); padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: rgba(40, 167, 69, 0.15); border: 1px solid rgba(40, 167, 69, 0.3); border-radius: 8px; padding: 15px; color: #28a745; margin-bottom: 20px; }
        .alert-danger { background: rgba(220, 53, 69, 0.15); border: 1px solid rgba(220, 53, 69, 0.3); border-radius: 8px; padding: 15px; color: #dc3545; margin-bottom: 20px; }
        .warning-box {
            background: rgba(220, 53, 69, 0.1);
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .warning-box i {
            color: #dc3545;
            font-size: 24px;
        }
        .warning-box strong {
            color: #dc3545;
        }
        .detail-row {
            margin-bottom: 12px;
        }
        .detail-row strong {
            display: inline-block;
            width: 140px;
            color: var(--text2);
        }
        .description-box {
            background: var(--bg4);
            padding: 12px;
            border-radius: 8px;
            margin-top: 5px;
            color: var(--text2);
            font-size: 14px;
            line-height: 1.5;
        }
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        @media (max-width: 768px) {
            .container { margin: 20px auto; }
            .detail-row strong { width: 100%; display: block; margin-bottom: 5px; }
            .action-buttons { flex-direction: column; }
            .action-buttons a, .action-buttons button { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-exclamation-triangle"></i> Mark as Fake Complaint</h4>
                <p class="mb-0" style="opacity: 0.9;">Complaint #<?php echo $complaint_id; ?></p>
            </div>
            <div class="card-body">
                <!-- Warning Box -->
                <div class="warning-box">
                    <i class="fas fa-shield-alt"></i>
                    <div>
                        <strong>⚠️ FAKE USER DETECTION ACTIVE</strong><br>
                        <small>This complaint has been marked as suspicious. Please review carefully before marking as fake.</small>
                    </div>
                </div>
                
                <?php if ($success): ?>
                    <div class="alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                    <div class="action-buttons">
                        <a href="officer-dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                        <a href="officer-complaints.php" class="btn-submit" style="background: #28a745; text-decoration: none;"><i class="fas fa-list"></i> View All Complaints</a>
                    </div>
                <?php else: ?>
                    <?php if ($error): ?>
                        <div class="alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="complaint-details">
                        <div class="detail-row">
                            <strong><i class="fas fa-tag"></i> Complaint Number:</strong>
                            <span><?php echo htmlspecialchars($complaint['complaint_num']); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong><i class="fas fa-user"></i> Citizen Name:</strong>
                            <span><?php echo htmlspecialchars($complaint['citizen_name']); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong><i class="fas fa-envelope"></i> Citizen Email:</strong>
                            <span><?php echo htmlspecialchars($complaint['citizen_email']); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong><i class="fas fa-bug"></i> Crime Type:</strong>
                            <span><?php echo htmlspecialchars($complaint['category']); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong><i class="fas fa-flag"></i> Status:</strong>
                            <span class="badge bg-danger">🔴 Suspicious / Fake User</span>
                        </div>
                        <div class="detail-row">
                            <strong><i class="fas fa-align-left"></i> Description:</strong>
                            <div class="description-box">
                                <?php echo nl2br(htmlspecialchars($complaint['description'] ?? 'No description provided')); ?>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-pen"></i> Resolution Notes *</label>
                            <textarea name="resolution_notes" class="form-control" rows="6" required placeholder="Describe how the fake user case was resolved, actions taken against the fraudulent account, and any recommendations..."></textarea>
                            <small class="text-muted mt-2 d-block">
                                <i class="fas fa-info-circle"></i> 
                                These notes will be shared with the admin and citizen as part of the fake user case registration.
                            </small>
                        </div>
                        <div class="action-buttons">
                            <button type="submit" name="mark_as_fake" class="btn-submit">
                                <i class="fas fa-exclamation-triangle"></i> Mark as Fake Complaint
                            </button>
                            <a href="officer-dashboard.php" class="btn-back">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
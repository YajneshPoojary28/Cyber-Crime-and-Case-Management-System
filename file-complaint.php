<?php
require_once 'config/database.php';
redirectIfNotLoggedIn();

$pageTitle = 'File a Complaint';
$error = '';
$success = '';

// Handle file upload function
function uploadProofImage($file, $complaint_id = null) {
    $target_dir = "uploads/proofs/";
    
    // Create directory if not exists
    $absolute_dir = __DIR__ . "/" . $target_dir;
    if (!file_exists($absolute_dir)) {
        mkdir($absolute_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    
    if (!in_array($file_extension, $allowed_extensions)) {
        return ['success' => false, 'error' => 'Only JPG, JPEG, PNG, GIF, and PDF files are allowed.'];
    }
    
    if ($file["size"] > 5 * 1024 * 1024) {
        return ['success' => false, 'error' => 'File size must be less than 5MB.'];
    }
    
    $new_filename = ($complaint_id ? "complaint_{$complaint_id}_" : "temp_") . time() . "_" . uniqid() . "." . $file_extension;
    $target_file = $target_dir . $new_filename;
    $absolute_file = $absolute_dir . $new_filename;
    
    if (move_uploaded_file($file["tmp_name"], $absolute_file)) {
        return [
            'success' => true, 
            'filename' => $new_filename, 
            'path' => $target_file
        ];
    } else {
        return ['success' => false, 'error' => 'Failed to upload file.'];
    }
}

// Function to generate unique complaint number with retry
function generateUniqueComplaintNumber($conn) {
    $year = date('Y');
    $max_attempts = 5;
    
    for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
        // Get the next sequence number
        $result = $conn->query("SELECT MAX(CAST(SUBSTRING_INDEX(complaint_num, '-', -1) AS UNSIGNED)) as max_num FROM complaints WHERE complaint_num LIKE 'CYB-{$year}-%'");
        $row = $result->fetch_assoc();
        $next_num = ($row['max_num'] ?? 0) + 1;
        
        $complaint_num = "CYB-{$year}-" . str_pad($next_num, 5, '0', STR_PAD_LEFT);
        
        // Check if this number already exists
        $check = $conn->prepare("SELECT id FROM complaints WHERE complaint_num = ?");
        $check->bind_param("s", $complaint_num);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();
        
        if (!$exists) {
            return $complaint_num;
        }
    }
    
    // Fallback: use timestamp based unique number
    return "CYB-{$year}-" . time() . rand(1000, 9999);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_complaint'])) {
    $category = $_POST['category'] ?? '';
    $incident_date = $_POST['incident_date'] ?? '';
    $incident_time = $_POST['incident_time'] ?? '00:00:00';
    $location = $_POST['location'] ?? '';
    $financial_loss = floatval($_POST['financial_loss'] ?? 0);
    $suspect_info = $_POST['suspect_info'] ?? 'Unknown';
    $description = $_POST['description'] ?? '';
    
    $errors = [];
    
    if (empty($category)) $errors[] = "Please select a crime category.";
    if (empty($incident_date)) $errors[] = "Please select the date of incident.";
    elseif (strtotime($incident_date) > strtotime(date('Y-m-d'))) $errors[] = "Incident date cannot be in the future.";
    if (empty($location)) $errors[] = "Please provide the location or URL where the incident occurred.";
    if ($financial_loss < 0) $errors[] = "Financial loss cannot be negative.";
    if (strlen($description) < 50) $errors[] = "Description must be at least 50 characters.";
    elseif (strlen($description) > 5000) $errors[] = "Description cannot exceed 5000 characters.";
    if (!empty($incident_time) && !preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $incident_time)) $errors[] = "Please enter a valid time format (HH:MM).";
    
    if (empty($errors)) {
        $conn = getConnection();
        
        // Generate unique complaint number
        $complaint_num = generateUniqueComplaintNumber($conn);
        $user_id = $_SESSION['user_id'];
        $user_name = $_SESSION['user_name'];
        $user_email = $_SESSION['user_email'];
        
        $keywords = detectKeywords($description);
        $priority = autoPriority($description, $financial_loss);
        $suspicious = !empty($keywords) ? 1 : 0;
        $status = 'pending';
        
        $financial_loss_str = number_format($financial_loss, 2, '.', '');
        
        // Insert complaint using prepared statement
        $sql = "INSERT INTO complaints (complaint_num, user_id, user_name, user_email, category, description, incident_date, incident_time, location, suspect_info, financial_loss, priority, suspicious, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sissssssssdsis", 
            $complaint_num, 
            $user_id, 
            $user_name, 
            $user_email, 
            $category, 
            $description, 
            $incident_date, 
            $incident_time, 
            $location, 
            $suspect_info, 
            $financial_loss_str, 
            $priority, 
            $suspicious, 
            $status
        );
        
        if ($stmt->execute()) {
            $complaint_id = $stmt->insert_id;
            $stmt->close();
            
            $uploaded_files = [];
            if (isset($_FILES['proof_images']) && !empty($_FILES['proof_images']['name'][0])) {
                $files = $_FILES['proof_images'];
                for ($i = 0; $i < count($files['name']); $i++) {
                    if ($files['error'][$i] == 0) {
                        $file = [
                            'name' => $files['name'][$i],
                            'type' => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error' => $files['error'][$i],
                            'size' => $files['size'][$i]
                        ];
                        $upload_result = uploadProofImage($file, $complaint_id);
                        if ($upload_result['success']) {
                            $uploaded_files[] = $upload_result['filename'];
                            $proof_sql = "INSERT INTO complaint_proofs (complaint_id, filename, file_path, file_type, uploaded_at) VALUES (?, ?, ?, ?, NOW())";
                            $proof_stmt = $conn->prepare($proof_sql);
                            $proof_stmt->bind_param("isss", $complaint_id, $upload_result['filename'], $upload_result['path'], $file['type']);
                            $proof_stmt->execute();
                            $proof_stmt->close();
                        }
                    }
                }
            }
            
            // Add to timeline
            $timeline_sql = "INSERT INTO complaint_timeline (complaint_id, action, action_by, note) VALUES (?, ?, ?, ?)";
            $timeline_stmt = $conn->prepare($timeline_sql);
            
            $timeline_note = "Complaint submitted via online portal";
            $timeline_stmt->bind_param("isss", $complaint_id, $timeline_note, $user_name, $timeline_note);
            $timeline_stmt->execute();
            $timeline_stmt->close();
            
            if (count($uploaded_files) > 0) {
                $timeline_stmt2 = $conn->prepare($timeline_sql);
                $upload_note = count($uploaded_files) . " proof document(s) uploaded";
                $timeline_stmt2->bind_param("isss", $complaint_id, $upload_note, $user_name, $upload_note);
                $timeline_stmt2->execute();
                $timeline_stmt2->close();
            }
            
            if ($suspicious == 1) {
                $timeline_stmt3 = $conn->prepare($timeline_sql);
                $ai_note = "Detected keywords: " . implode(', ', $keywords);
                $ai_action_by = 'AI System';
                $timeline_stmt3->bind_param("isss", $complaint_id, $ai_note, $ai_action_by, $ai_note);
                $timeline_stmt3->execute();
                $timeline_stmt3->close();
            }
            
            addNotification($user_id, $complaint_num, 'Complaint Submitted', "Your complaint $complaint_num has been filed successfully.");
            $success = "Complaint $complaint_num filed successfully!";
        } else {
            $error = "Failed to submit complaint. Please try again. Error: " . $stmt->error;
        }
        $conn->close();
    } else {
        $error = implode("<br>• ", $errors);
        $error = "• " . $error;
    }
}
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="complaint-form-container">
    <div class="form-header">
        <div class="form-title">📝 File a Cyber Crime Complaint</div>
        <div class="form-subtitle">Provide detailed information about the incident. All fields marked with <span class="req">*</span> are required.</div>
    </div>
    
    <?php if ($error): ?>
        <div class="error-message">
            <div class="error-icon">❌</div>
            <div class="error-content">
                <strong>Please correct the following errors:</strong><br>
                <?php echo $error; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="success-message">
            <div class="success-icon">✅</div>
            <div class="success-content">
                <strong><?php echo $success; ?></strong><br>
                Redirecting to your complaints...
            </div>
        </div>
        <script>
            setTimeout(function() { 
                window.location.href = 'my-complaints.php'; 
            }, 2000);
        </script>
    <?php endif; ?>
    
    <form method="POST" id="complaintForm" enctype="multipart/form-data" onsubmit="return validateForm()">
        <div class="form-grid">
            <!-- Crime Category -->
            <div class="form-group full">
                <label class="form-label">Crime Category <span class="req">*</span></label>
                <select name="category" id="category" class="form-select" required>
                    <option value="">Select crime type...</option>
                    <option value="Phishing">Phishing</option>
                    <option value="Hacking/Unauthorized Access">Hacking/Unauthorized Access</option>
                    <option value="Online Financial Fraud">Online Financial Fraud</option>
                    <option value="Identity Theft">Identity Theft</option>
                    <option value="Cyber Stalking/Harassment">Cyber Stalking/Harassment</option>
                    <option value="Ransomware Attack">Ransomware Attack</option>
                    <option value="Data Breach">Data Breach</option>
                    <option value="Other Cyber Crime">Other Cyber Crime</option>
                </select>
            </div>
            
            <!-- Date and Time -->
            <div class="form-group">
                <label class="form-label">Date of Incident <span class="req">*</span></label>
                <input type="date" name="incident_date" id="incident_date" class="form-input" max="<?php echo date('Y-m-d'); ?>" required>
                <div class="field-hint">Date when the incident occurred</div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Time of Incident</label>
                <input type="time" name="incident_time" id="incident_time" class="form-input" step="60">
                <div class="field-hint">Approximate time (optional)</div>
            </div>
            
            <!-- Location -->
            <div class="form-group full">
                <label class="form-label">Location / URL <span class="req">*</span></label>
                <input type="text" name="location" id="location" class="form-input" placeholder="e.g., https://example.com, Instagram, Facebook, or physical location" required>
                <div class="field-hint">Where did the incident occur? (Website URL, app name, or physical address)</div>
            </div>
            
            <!-- Financial Loss -->
            <div class="form-group">
                <label class="form-label">Financial Loss (₹)</label>
                <input type="number" name="financial_loss" id="financial_loss" class="form-input" step="0.01" min="0" value="0" placeholder="0 if none">
                <div class="field-hint">Enter 0 if no financial loss occurred</div>
            </div>
            
            <!-- Suspect Information -->
            <div class="form-group">
                <label class="form-label">Suspect Information</label>
                <input type="text" name="suspect_info" id="suspect_info" class="form-input" placeholder="Name, email, phone number (if known)">
                <div class="field-hint">Any information about the suspected perpetrator</div>
            </div>
            
            <!-- Proof Upload -->
            <div class="form-group full">
                <label class="form-label">📎 Upload Proof / Evidence</label>
                <div class="upload-area" id="uploadArea">
                    <div class="upload-icon">📁</div>
                    <div class="upload-text">Drag & drop files here or click to browse</div>
                    <div class="upload-hint">Supported formats: JPG, PNG, GIF, PDF (Max 5MB per file)</div>
                    <input type="file" name="proof_images[]" id="proofImages" multiple accept="image/jpeg,image/png,image/gif,application/pdf" style="display: none;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('proofImages').click()">Choose Files</button>
                </div>
                <div id="fileList" class="file-list"></div>
                <div class="field-hint">Upload screenshots, documents, or any evidence related to the incident (max 5 files)</div>
            </div>
            
            <!-- Description -->
            <div class="form-group full">
                <label class="form-label">Detailed Description <span class="req">*</span></label>
                <textarea name="description" id="complaintDesc" class="form-textarea" style="min-height: 180px;" required oninput="updateCharCount(this)"></textarea>
                <div class="description-stats">
                    <div class="help-tip">Minimum 50 characters required. Provide as much detail as possible.</div>
                    <div class="char-count" id="charCount">0 / 5000 characters</div>
                </div>
                <div class="description-tips">
                    <small>💡 Tips: Include how the incident occurred, what information was compromised, any communication with the suspect, and the sequence of events.</small>
                </div>
            </div>
        </div>
        
        <!-- AI Analysis -->
        <div id="aiAnalysis" style="display: none;" class="ai-flag">
            <div class="ai-flag-icon">🤖</div>
            <div>
                <div class="ai-flag-title">⚠️ AI ANALYSIS — SUSPICIOUS CONTENT DETECTED</div>
                <div class="ai-flag-text" id="aiFlagMsg"></div>
            </div>
        </div>
        
        <!-- Form Actions -->
        <div class="form-actions">
            <button type="button" class="btn btn-ghost" onclick="location.href='dashboard.php'">Cancel</button>
            <button type="submit" name="submit_complaint" class="btn btn-primary">🚀 Submit Complaint</button>
        </div>
    </form>
    
    <!-- Disclaimer -->
    <div class="disclaimer-note">
        <div class="disclaimer-icon">⚠️</div>
        <div class="disclaimer-text">
            <strong>Important:</strong> Once your complaint is submitted, you cannot edit or modify it. Please review all information carefully before submitting.
        </div>
    </div>
</div>

<style>
.complaint-form-container { max-width: 1000px; margin: 0 auto; }
.form-header { margin-bottom: 28px; text-align: center; }
.form-title { font-family: var(--display); font-size: 24px; font-weight: 800; color: var(--text); margin-bottom: 8px; }
.form-subtitle { font-size: 14px; color: var(--text2); }
.req { color: var(--danger); }
.error-message { background: rgba(244,63,94,0.1); border: 1px solid rgba(244,63,94,0.3); border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; display: flex; gap: 14px; align-items: flex-start; }
.error-icon { font-size: 22px; }
.error-content { flex: 1; color: var(--danger); font-size: 13px; line-height: 1.6; }
.success-message { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; display: flex; gap: 14px; align-items: center; }
.success-icon { font-size: 22px; }
.success-content { flex: 1; color: var(--success); font-size: 13px; }
.field-hint { font-size: 11px; color: var(--text3); margin-top: 4px; font-family: var(--mono); }
.upload-area { border: 2px dashed var(--border); border-radius: 12px; padding: 32px; text-align: center; background: var(--bg3); transition: all 0.2s; cursor: pointer; }
.upload-area:hover { border-color: var(--accent); background: var(--bg2); }
.upload-area.drag-over { border-color: var(--accent); background: rgba(56,189,248,0.05); }
.upload-icon { font-size: 48px; margin-bottom: 12px; }
.upload-text { font-size: 14px; color: var(--text2); margin-bottom: 8px; }
.upload-hint { font-size: 11px; color: var(--text3); margin-bottom: 16px; }
.btn-secondary { background: var(--bg4); color: var(--text2); border: 1px solid var(--border); }
.btn-secondary:hover { background: var(--bg3); transform: translateY(-1px); }
.file-list { margin-top: 16px; }
.file-item { display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; background: var(--bg4); border-radius: 8px; margin-bottom: 8px; border: 1px solid var(--border); }
.file-info { display: flex; align-items: center; gap: 10px; flex: 1; }
.file-icon { font-size: 20px; }
.file-name { font-size: 13px; color: var(--text); font-family: var(--mono); }
.file-size { font-size: 11px; color: var(--text3); margin-left: 8px; }
.remove-file { background: none; border: none; color: var(--danger); cursor: pointer; font-size: 18px; padding: 4px 8px; border-radius: 6px; transition: all 0.2s; }
.remove-file:hover { background: rgba(244,63,94,0.1); }
.description-stats { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; flex-wrap: wrap; gap: 8px; }
.description-tips { background: rgba(56,189,248,0.05); padding: 10px 14px; border-radius: 8px; margin-top: 12px; border-left: 3px solid var(--accent3); }
.description-tips small { color: var(--text2); font-size: 11px; }
.form-actions { display: flex; justify-content: flex-end; gap: 16px; margin-top: 28px; padding-top: 20px; border-top: 1px solid var(--border); }
.ai-flag { background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.3); border-radius: 12px; padding: 16px; margin: 20px 0; display: flex; gap: 14px; align-items: flex-start; }
.ai-flag-icon { font-size: 24px; }
.ai-flag-title { font-weight: 700; color: var(--warning); margin-bottom: 8px; font-size: 13px; }
.ai-flag-text { font-size: 12px; color: var(--text2); }
.kw-chip { display: inline-block; background: rgba(245,158,11,0.2); padding: 2px 8px; border-radius: 12px; margin: 2px; font-family: var(--mono); font-size: 11px; }
.disclaimer-note { display: flex; align-items: center; gap: 12px; background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.3); border-radius: 12px; padding: 14px 18px; margin-top: 24px; }
.disclaimer-icon { font-size: 22px; flex-shrink: 0; }
.disclaimer-text { font-size: 12px; color: var(--text2); line-height: 1.5; }
.disclaimer-text strong { color: var(--warning); }
@media (max-width: 768px) { 
    .form-title { font-size: 20px; } 
    .form-actions { flex-direction: column; } 
    .form-actions .btn { width: 100%; justify-content: center; } 
    .file-item { flex-wrap: wrap; }
    .disclaimer-note { flex-direction: column; text-align: center; }
}
</style>

<script>
let selectedFiles = [];

document.getElementById('proofImages').addEventListener('change', function(e) {
    const files = Array.from(e.target.files);
    selectedFiles = [...selectedFiles, ...files];
    updateFileList();
});

function updateFileList() {
    const fileListDiv = document.getElementById('fileList');
    fileListDiv.innerHTML = '';
    selectedFiles.forEach((file, index) => {
        const fileSize = (file.size / 1024).toFixed(1) + ' KB';
        const fileIcon = file.type.startsWith('image/') ? '🖼️' : '📄';
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';
        fileItem.innerHTML = `
            <div class="file-info">
                <span class="file-icon">${fileIcon}</span>
                <span class="file-name">${file.name}</span>
                <span class="file-size">(${fileSize})</span>
            </div>
            <button type="button" class="remove-file" onclick="removeFile(${index})">✕</button>
        `;
        fileListDiv.appendChild(fileItem);
    });
    const dataTransfer = new DataTransfer();
    selectedFiles.forEach(file => dataTransfer.items.add(file));
    document.getElementById('proofImages').files = dataTransfer.files;
}

function removeFile(index) {
    selectedFiles.splice(index, 1);
    updateFileList();
}

const uploadArea = document.getElementById('uploadArea');
if (uploadArea) {
    uploadArea.addEventListener('click', () => document.getElementById('proofImages').click());
    uploadArea.addEventListener('dragover', (e) => { e.preventDefault(); uploadArea.classList.add('drag-over'); });
    uploadArea.addEventListener('dragleave', () => { uploadArea.classList.remove('drag-over'); });
    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('drag-over');
        const files = Array.from(e.dataTransfer.files);
        const validFiles = files.filter(file => {
            const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            return validTypes.includes(file.type) && file.size <= 5 * 1024 * 1024;
        });
        selectedFiles = [...selectedFiles, ...validFiles];
        updateFileList();
    });
}

function updateCharCount(el) {
    const count = el.value.length;
    const charCountSpan = document.getElementById('charCount');
    charCountSpan.textContent = count + ' / 5000 characters';
    if (count < 50) charCountSpan.style.color = 'var(--warning)';
    else if (count >= 50) charCountSpan.style.color = 'var(--success)';
    
    const keywords = ['otp', 'bank', 'credit card', 'upi', 'password', 'scam', 'fraud', 'phishing', 'ransomware', 'bitcoin', 'aadhaar', 'pan'];
    const found = keywords.filter(k => el.value.toLowerCase().includes(k));
    const aiDiv = document.getElementById('aiAnalysis');
    if (found.length > 0) {
        aiDiv.style.display = 'flex';
        document.getElementById('aiFlagMsg').innerHTML = `⚠️ Detected high-risk keywords: ${found.map(k => `<span class="kw-chip">${k}</span>`).join('')}. This complaint will be auto-flagged for priority review.`;
    } else {
        aiDiv.style.display = 'none';
    }
}

function validateForm() {
    let isValid = true;
    const errors = [];
    const category = document.getElementById('category').value;
    if (!category) { errors.push('Please select a crime category'); isValid = false; }
    const incidentDate = document.getElementById('incident_date').value;
    if (!incidentDate) { errors.push('Please select the date of incident'); isValid = false; }
    else if (new Date(incidentDate) > new Date()) { errors.push('Incident date cannot be in the future'); isValid = false; }
    const location = document.getElementById('location').value.trim();
    if (!location) { errors.push('Please provide the location or URL'); isValid = false; }
    const financialLoss = parseFloat(document.getElementById('financial_loss').value);
    if (isNaN(financialLoss)) { errors.push('Please enter a valid financial loss amount'); isValid = false; }
    else if (financialLoss < 0) { errors.push('Financial loss cannot be negative'); isValid = false; }
    const description = document.getElementById('complaintDesc').value;
    if (description.length < 50) { errors.push('Description must be at least 50 characters (current: ' + description.length + ' characters)'); isValid = false; }
    else if (description.length > 5000) { errors.push('Description cannot exceed 5000 characters'); isValid = false; }
    
    if (!isValid) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.innerHTML = '<div class="error-icon">❌</div><div class="error-content"><strong>Please correct the following errors:</strong><br>• ' + errors.join('<br>• ') + '</div>';
        const existingError = document.querySelector('.error-message');
        if (existingError) existingError.remove();
        const formHeader = document.querySelector('.form-header');
        formHeader.insertAdjacentElement('afterend', errorDiv);
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }
    return true;
}

const dateInput = document.getElementById('incident_date');
if (dateInput && !dateInput.value) {
    const today = new Date().toISOString().split('T')[0];
    dateInput.max = today;
}
</script>

<?php include 'includes/footer.php'; ?>
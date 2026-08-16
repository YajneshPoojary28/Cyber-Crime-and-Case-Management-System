<?php
require_once 'config/database.php';
redirectIfNotLoggedIn();

$pageTitle = 'My Complaints';
$conn = getConnection();
$userId = $_SESSION['user_id'];

$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$query = "SELECT id, complaint_num, category, incident_date, financial_loss, priority, status, suspicious FROM complaints WHERE user_id = ?";
$params = [$userId];
$types = "i";

if ($search) {
    $query .= " AND (complaint_num LIKE ? OR category LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}
if ($status_filter) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}
$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$complaints = $stmt->get_result();

// Fetch files for each complaint
$complaints_with_files = [];
while ($c = $complaints->fetch_assoc()) {
    $file_stmt = $conn->prepare("SELECT id, filename, file_path, file_type FROM complaint_proofs WHERE complaint_id = ?");
    $file_stmt->bind_param("i", $c['id']);
    $file_stmt->execute();
    $files_result = $file_stmt->get_result();
    $files = [];
    while ($file = $files_result->fetch_assoc()) {
        // Clean the path - remove backslashes and ensure consistent format
        $clean_path = str_replace('\\', '/', $file['file_path']);
        // Remove any leading slash to prevent double slashes when rendering
        $clean_path = ltrim($clean_path, '/');
        
        $files[] = [
            'id' => $file['id'],
            'name' => $file['filename'],
            'path' => $clean_path,
            'type' => $file['file_type']
        ];
    }
    $file_stmt->close();
    $c['files'] = $files;
    $complaints_with_files[] = $c;
}
$conn->close();
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="search-bar">
    <div class="search-wrap" style="flex: 2;">
        <form method="GET" style="display: flex; gap: 10px; width: 100%;">
            <input type="text" name="search" class="search-input" placeholder="Search complaints..." value="<?php echo htmlspecialchars($search); ?>">
            <select name="status" class="filter-select">
                <option value="">All Status</option>
                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="in-progress" <?php echo $status_filter == 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
                <option value="resolved" <?php echo $status_filter == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                <option value="closed" <?php echo $status_filter == 'closed' ? 'selected' : ''; ?>>Closed</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
            <?php if ($search || $status_filter): ?>
                <a href="my-complaints.php" class="btn btn-ghost btn-sm">Clear</a>
            <?php endif; ?>
        </form>
    </div>
    <button class="btn btn-primary btn-sm" onclick="location.href='file-complaint.php'">+ New Complaint</button>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr><th>Complaint #</th><th>Category</th><th>Date Filed</th><th>Loss</th><th>Priority</th><th>Status</th><th>Suspicious</th><th>Files</th></tr>
        </thead>
        <tbody>
            <?php if (count($complaints_with_files) == 0): ?>
                <tr><td colspan="8" style="text-align: center; padding: 40px;">No complaints found</td></tr>
            <?php else: ?>
                <?php foreach ($complaints_with_files as $c): ?>
                <tr>
                    <td onclick="location.href='complaint-detail.php?id=<?php echo $c['id']; ?>'" style="cursor: pointer;"><?php echo htmlspecialchars($c['complaint_num']); ?></td>
                    <td onclick="location.href='complaint-detail.php?id=<?php echo $c['id']; ?>'" style="cursor: pointer;"><?php echo htmlspecialchars($c['category']); ?></td>
                    <td onclick="location.href='complaint-detail.php?id=<?php echo $c['id']; ?>'" style="cursor: pointer;"><?php echo $c['incident_date']; ?></td>
                    <td onclick="location.href='complaint-detail.php?id=<?php echo $c['id']; ?>'" style="cursor: pointer; color: <?php echo $c['financial_loss'] > 0 ? '#f43f5e' : '#9090b0'; ?>"><?php echo $c['financial_loss'] > 0 ? '₹' . number_format($c['financial_loss'], 2) : '-'; ?></td>
                    <td onclick="location.href='complaint-detail.php?id=<?php echo $c['id']; ?>'" style="cursor: pointer;"><span class="pill pill-<?php echo $c['priority']; ?>"><?php echo $c['priority']; ?></span></td>
                    <td onclick="location.href='complaint-detail.php?id=<?php echo $c['id']; ?>'" style="cursor: pointer;"><span class="pill pill-<?php echo str_replace('-', '', $c['status']); ?>"><?php echo $c['status']; ?></span></td>
                    <td onclick="location.href='complaint-detail.php?id=<?php echo $c['id']; ?>'" style="cursor: pointer;"><?php echo $c['suspicious'] ? '<span class="pill pill-suspicious">⚠ Flagged</span>' : '-'; ?></td>
                    <td>
                        <?php if (count($c['files']) > 0): ?>
                            <button class="view-files-btn" onclick="event.stopPropagation(); showFiles(<?php echo htmlspecialchars(json_encode($c['files'])); ?>)">📎 View Files (<?php echo count($c['files']); ?>)</button>
                        <?php else: ?>
                            <span class="no-files">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Files Modal -->
<div id="filesModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-icon">📎</div>
        <div class="modal-title">Evidence Files</div>
        <div id="filesList" class="files-list"></div>
        <div class="modal-buttons"><button onclick="closeFilesModal()" class="btn btn-primary">Close</button></div>
    </div>
</div>

<!-- Preview Modal -->
<div id="previewModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 90%; width: auto;">
        <div class="modal-icon">👁️</div>
        <div class="modal-title">File Preview</div>
        <div id="previewContent" class="preview-content"></div>
        <div class="modal-buttons"><button onclick="closePreviewModal()" class="btn btn-primary">Close</button></div>
    </div>
</div>

<style>
.view-files-btn { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 500; cursor: pointer; border: none; background: #10b981; color: white; }
.view-files-btn:hover { background: #059669; }
.no-files { color: var(--text3); font-size: 12px; }
.modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 10000; align-items: center; justify-content: center; }
.modal-content { background: var(--card); border: 1px solid var(--border); border-radius: 20px; padding: 32px; margin: 20px; text-align: center; animation: modalSlideIn 0.3s ease; max-height: 90vh; overflow-y: auto; }
@keyframes modalSlideIn { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.modal-icon { font-size: 56px; margin-bottom: 16px; }
.modal-title { font-size: 22px; font-weight: 700; margin-bottom: 12px; color: var(--text); }
.files-list { max-height: 400px; overflow-y: auto; margin: 20px 0; text-align: left; }
.file-item { display: flex; align-items: center; justify-content: space-between; padding: 12px; background: var(--bg3); border-radius: 8px; margin-bottom: 8px; border: 1px solid var(--border); }
.file-info { display: flex; align-items: center; gap: 10px; flex: 1; }
.file-icon { font-size: 24px; }
.file-name { font-size: 13px; word-break: break-all; color: var(--text); }
.view-file-btn { padding: 4px 12px; border-radius: 6px; font-size: 12px; cursor: pointer; background: var(--accent); color: white; border: none; }
.view-file-btn:hover { opacity: 0.9; }
.preview-content { margin: 20px 0; text-align: center; }
.preview-content img { max-width: 100%; max-height: 70vh; border-radius: 8px; }
.preview-content iframe { width: 100%; height: 70vh; border: none; }
.modal-buttons { display: flex; gap: 12px; justify-content: center; }
.modal-buttons .btn { padding: 10px 24px; }
.error-loading { padding: 40px; text-align: center; color: var(--text2); }
.error-loading a { color: var(--accent); text-decoration: underline; }
</style>

<script>
// Set the base URL for your project (update this if your folder name is different)
const BASE_URL = '/a1/';

function showFiles(files) {
    const filesListDiv = document.getElementById('filesList');
    filesListDiv.innerHTML = '';
    
    files.forEach((file) => {
        const fileIcon = (file.type && file.type.startsWith('image/')) ? '🖼️' : (file.type === 'application/pdf' ? '📕' : '📄');
        
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';
        fileItem.innerHTML = `
            <div class="file-info">
                <span class="file-icon">${fileIcon}</span>
                <span class="file-name">${escapeHtml(file.name)}</span>
            </div>
            <button class="view-file-btn" onclick="previewFile('${escapeHtml(file.path)}', '${escapeHtml(file.type)}')">👁️ View</button>
        `;
        filesListDiv.appendChild(fileItem);
    });
    
    document.getElementById('filesModal').style.display = 'flex';
}

function previewFile(filePath, fileType) {
    const previewContent = document.getElementById('previewContent');
    
    // Clean the path - remove any leading slashes
    let cleanPath = filePath.replace(/^\/+/, '');
    
    // Build URL with base path for the a1 folder
    let fullUrl = BASE_URL + cleanPath;
    
    // Remove any double slashes
    fullUrl = fullUrl.replace(/\/\//g, '/');
    
    // Log for debugging
    console.log('Loading file from path:', filePath);
    console.log('Full URL:', fullUrl);
    
    if (fileType && fileType.startsWith('image/')) {
        previewContent.innerHTML = `
            <img src="${fullUrl}" alt="Evidence" style="max-width: 100%; max-height: 70vh; border-radius: 8px;" 
                 onerror="console.error('Image load failed:', '${fullUrl}');this.style.display='none';this.parentElement.innerHTML='<div class=\\'error-loading\\'>❌ Image could not be loaded.<br><small style=\\'color:var(--text3);\\'>Path: ${fullUrl}</small><br><a href=\\'${fullUrl}\\' download style=\\'color: var(--accent);\\'>📥 Download file instead</a></div>'">
        `;
    } else if (fileType === 'application/pdf') {
        previewContent.innerHTML = `<iframe src="${fullUrl}#toolbar=0" style="width: 100%; height: 70vh; border: none;"></iframe>`;
    } else {
        previewContent.innerHTML = `
            <div style="padding: 40px; text-align: center;">
                <div style="font-size: 48px; margin-bottom: 16px;">📄</div>
                <p style="color: var(--text2); margin-bottom: 16px;">Preview not available for this file type</p>
                <a href="${fullUrl}" download class="btn btn-primary">📥 Download File</a>
            </div>
        `;
    }
    
    closeFilesModal();
    document.getElementById('previewModal').style.display = 'flex';
}

function closeFilesModal() { 
    document.getElementById('filesModal').style.display = 'none'; 
}

function closePreviewModal() { 
    document.getElementById('previewModal').style.display = 'none'; 
    document.getElementById('previewContent').innerHTML = ''; 
}

function escapeHtml(text) { 
    const div = document.createElement('div'); 
    div.textContent = text; 
    return div.innerHTML; 
}

// Close modals when clicking outside
document.addEventListener('click', function(e) {
    if (e.target === document.getElementById('filesModal')) closeFilesModal();
    if (e.target === document.getElementById('previewModal')) closePreviewModal();
});

// Close modals with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeFilesModal();
        closePreviewModal();
    }
});
</script>

<?php include 'includes/footer.php'; ?>
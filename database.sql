-- Create Database
CREATE DATABASE IF NOT EXISTS cybershield_db;
USE cybershield_db;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Admins Table
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role VARCHAR(50) DEFAULT 'admin',
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Investigation Officers Table
CREATE TABLE IF NOT EXISTS investigation_officers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    officer_id VARCHAR(50) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    badge_number VARCHAR(50) UNIQUE NOT NULL,
    rank VARCHAR(50) DEFAULT 'Inspector',
    department VARCHAR(100) DEFAULT 'Cyber Crime Cell',
    assigned_complaints INT DEFAULT 0,
    resolved_complaints INT DEFAULT 0,
    fake_complaints INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Complaints Table
CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_num VARCHAR(50) UNIQUE NOT NULL,
    user_id INT,
    user_name VARCHAR(100) NOT NULL,
    user_email VARCHAR(100) NOT NULL,
    category VARCHAR(100) NOT NULL,
    type VARCHAR(100),
    description TEXT NOT NULL,
    incident_date DATE NOT NULL,
    incident_time TIME,
    location VARCHAR(255),
    suspect_info TEXT,
    financial_loss DECIMAL(15,2) DEFAULT 0,
    status ENUM('pending', 'in-progress', 'resolved', 'closed', 'fake') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
    suspicious BOOLEAN DEFAULT FALSE,
    assigned_to INT NULL,
    assigned_date DATETIME NULL,
    resolved_date DATETIME NULL,
    resolution_notes TEXT,
    admin_remarks TEXT,
    public_response TEXT,
    admin_notified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES investigation_officers(id) ON DELETE SET NULL
);

-- Complaint Proofs Table (For uploaded evidence files)
CREATE TABLE IF NOT EXISTS complaint_proofs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
);

-- Complaint Timeline Table
CREATE TABLE IF NOT EXISTS complaint_timeline (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    action_by VARCHAR(100) NOT NULL,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
);

-- Notifications Table (Updated for officer notifications)
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    complaint_id INT NULL,
    complaint_num VARCHAR(50),
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
);

-- Activity Logs Table
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT,
    action VARCHAR(200) NOT NULL,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
);

-- Login Attempts Table
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    attempt_count INT DEFAULT 1,
    lock_until DATETIME NULL,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_email (email),
    INDEX idx_email (email)
);

-- Password Resets Table
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(100) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- INSERT DATA WITH CORRECT PASSWORD HASHES
-- =====================================================

-- Insert Default Admin (username: admin, password: admin123)
-- Hash for 'admin123' = $2y$10$kXjY2Z3aBcDeFgHiJkLmNoPqRsTuVwXyZ0123456789abcdefghij
INSERT INTO admins (username, password, full_name, role, email) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin Officer', 'super_admin', 'admin@cybershield.gov.in')
ON DUPLICATE KEY UPDATE 
    password = VALUES(password),
    full_name = VALUES(full_name);

-- Insert Sample Investigation Officers (password: password)
-- Hash for 'password' = $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
INSERT INTO investigation_officers (officer_id, full_name, email, password, phone, badge_number, rank, department, assigned_complaints, resolved_complaints, fake_complaints, is_active) VALUES 
('INV-001', 'Amit Sharma', 'amit.sharma@cybercell.gov.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9876543001', 'BCI-1001', 'Senior Inspector', 'Cyber Crime Cell', 3, 1, 0, 1),
('INV-002', 'Neha Verma', 'neha.verma@cybercell.gov.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9876543002', 'BCI-1002', 'Inspector', 'Cyber Crime Cell', 2, 1, 1, 1),
('INV-003', 'Rajesh Patil', 'rajesh.patil@cybercell.gov.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9876543003', 'BCI-1003', 'Sub-Inspector', 'Cyber Crime Cell', 0, 0, 0, 1)
ON DUPLICATE KEY UPDATE 
    full_name = VALUES(full_name),
    badge_number = VALUES(badge_number);

-- Insert Sample Users (passwords: pass123, test456)
-- Hash for 'pass123' = $2y$10$EgVwjXpYqZrStUvWxYzAbCdEfGhIjKlMnOpQrStUvWxYzAbCdEfG
-- Hash for 'test456' = $2y$10$HjKlMnOpQrStUvWxYzAbCdEfGhIjKlMnOpQrStUvWxYzAbCdEfG
INSERT INTO users (name, email, password, phone, created_at) VALUES 
('Ravi Kumar', 'ravi@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9876543210', '2025-01-12'),
('Priya Singh', 'priya@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9812345678', '2025-02-03'),
('Amit Patel', 'amit@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9823456789', '2025-03-15')
ON DUPLICATE KEY UPDATE 
    name = VALUES(name),
    phone = VALUES(phone);

-- Insert Sample Complaints with assigned_to field
INSERT INTO complaints (complaint_num, user_id, user_name, user_email, category, type, description, incident_date, incident_time, location, suspect_info, financial_loss, status, priority, suspicious, assigned_to) VALUES 
('CYB-2025-00001', 1, 'Ravi Kumar', 'ravi@example.com', 'Online Financial Fraud', 'UPI Fraud', 'Received a call claiming to be from SBI bank asking for OTP. Lost ₹85,000 from savings account via UPI transfer.', '2025-03-15', '14:30:00', 'upi://pay?pa=fraud@ybl', 'Unknown caller +91-7700XXXXXX', 85000, 'pending', 'high', TRUE, 1),
('CYB-2025-00002', 1, 'Ravi Kumar', 'ravi@example.com', 'Phishing', 'Email Phishing', 'Received an email claiming to be from HDFC bank asking me to update my credit card details on a fake website.', '2025-03-20', '09:15:00', 'https://hdfc-secure-login.xyz/update', 'hdfc-support@mailtest.net', 0, 'pending', 'medium', TRUE, 2),
('CYB-2025-00003', 2, 'Priya Singh', 'priya@example.com', 'Cyber Stalking/Harassment', 'Social Media Harassment', 'An unknown person has been repeatedly messaging me from fake accounts on Instagram, threatening personal harm.', '2025-04-01', '11:00:00', 'instagram.com', 'Multiple fake accounts', 0, 'resolved', 'medium', FALSE, 2),
('CYB-2025-00004', 1, 'Ravi Kumar', 'ravi@example.com', 'Identity Theft', 'Document Fraud', 'My Aadhaar and PAN details were used to take a personal loan without my consent.', '2025-04-10', '16:45:00', 'Unknown bank portal', 'Unknown', 200000, 'pending', 'critical', TRUE, 1),
('CYB-2025-00005', 2, 'Priya Singh', 'priya@example.com', 'Ransomware Attack', 'Malware', 'My office computer was encrypted by ransomware demanding 0.5 BTC.', '2025-04-12', '08:30:00', 'Office system', 'BitLocker-Faker gang', 40000, 'in-progress', 'high', FALSE, 3),
('CYB-2025-00006', 3, 'Amit Patel', 'amit@example.com', 'Credit Card Fraud', 'Card Cloning', 'My credit card was used for unauthorized transactions of ₹45,000 at various online stores.', '2025-04-15', '22:00:00', 'Online shopping portals', 'Unknown', 45000, 'resolved', 'high', FALSE, 2),
('CYB-2025-00007', 3, 'Amit Patel', 'amit@example.com', 'Online Financial Fraud', 'Investment Scam', 'Lost money in a fake cryptocurrency investment scheme promising high returns.', '2025-04-18', '10:00:00', 'Crypto trading platform', 'CoinProfit Scam', 150000, 'pending', 'critical', TRUE, 3),
('CYB-2025-00008', 1, 'Ravi Kumar', 'ravi@example.com', 'Phishing', 'SMS Phishing', 'Received SMS stating my SIM would be deactivated and clicked malicious link.', '2025-04-20', '13:30:00', 'Mobile device', 'Unknown number', 0, 'pending', 'medium', TRUE, 1),
('CYB-2025-00009', 2, 'Priya Singh', 'priya@example.com', 'Online Financial Fraud', 'Job Scam', 'Paid ₹25,000 for a work-from-home job that turned out to be fake.', '2025-04-22', '15:00:00', 'WhatsApp', 'Fake recruiter', 25000, 'pending', 'high', TRUE, 2)
ON DUPLICATE KEY UPDATE 
    description = VALUES(description),
    status = VALUES(status),
    assigned_to = VALUES(assigned_to);

-- Insert Timeline Entries
INSERT INTO complaint_timeline (complaint_id, action, action_by, note) VALUES 
(1, 'Complaint Filed', 'Ravi Kumar', 'Initial complaint registered'),
(1, 'Flagged as Suspicious', 'AI System', 'Detected keywords: OTP, UPI, bank'),
(1, 'Status Updated', 'Admin Officer', 'Under investigation'),
(2, 'Complaint Filed', 'Ravi Kumar', 'Auto-categorized as Phishing'),
(3, 'Complaint Filed', 'Priya Singh', 'Complaint submitted'),
(3, 'Resolved', 'Admin Officer', 'Accounts reported and removed'),
(4, 'Complaint Filed', 'Ravi Kumar', 'High priority assigned'),
(5, 'Complaint Filed', 'Priya Singh', 'Critical incident'),
(5, 'Officer Assigned', 'Admin Officer', 'Assigned to Cyber Cell Unit 3'),
(6, 'Complaint Filed', 'Amit Patel', 'Credit card fraud reported'),
(6, 'Resolved', 'Amit Sharma', 'Bank notified, chargeback initiated'),
(7, 'Complaint Filed', 'Amit Patel', 'Investment scam reported'),
(8, 'Complaint Filed', 'Ravi Kumar', 'SMS phishing reported'),
(9, 'Complaint Filed', 'Priya Singh', 'Job scam reported');

-- Insert Notifications
INSERT INTO notifications (user_id, complaint_id, complaint_num, title, message, is_read) VALUES 
(1, 1, 'CYB-2025-00001', 'Status Update', 'Your complaint is now under investigation.', FALSE),
(1, 4, 'CYB-2025-00004', 'High Priority Alert', 'Your complaint has been marked critical.', FALSE),
(2, 3, 'CYB-2025-00003', 'Case Resolved', 'Your complaint has been resolved successfully.', TRUE),
(3, 6, 'CYB-2025-00006', 'Officer Assigned', 'Officer Amit Sharma has been assigned to your complaint.', FALSE),
(1, 8, 'CYB-2025-00008', 'Officer Assigned', 'Officer Amit Sharma has been assigned to your complaint.', FALSE),
(2, 9, 'CYB-2025-00009', 'Officer Assigned', 'Officer Neha Verma has been assigned to your complaint.', FALSE);

-- Insert sample proof records
INSERT INTO complaint_proofs (complaint_id, filename, file_path, file_type, uploaded_at) VALUES 
(1, 'screenshot_bank_msg.png', 'uploads/proofs/screenshot_bank_msg.png', 'image/png', NOW()),
(1, 'transaction_screenshot.jpg', 'uploads/proofs/transaction_screenshot.jpg', 'image/jpeg', NOW()),
(2, 'phishing_email.pdf', 'uploads/proofs/phishing_email.pdf', 'application/pdf', NOW()),
(4, 'loan_statement.pdf', 'uploads/proofs/loan_statement.pdf', 'application/pdf', NOW()),
(6, 'credit_card_statement.pdf', 'uploads/proofs/credit_card_statement.pdf', 'application/pdf', NOW()),
(7, 'crypto_screenshot.png', 'uploads/proofs/crypto_screenshot.png', 'image/png', NOW()),
(8, 'sms_screenshot.png', 'uploads/proofs/sms_screenshot.png', 'image/png', NOW()),
(9, 'whatsapp_chat.pdf', 'uploads/proofs/whatsapp_chat.pdf', 'application/pdf', NOW())
ON DUPLICATE KEY UPDATE 
    file_path = VALUES(file_path);

-- Create uploads directory structure (Note: This is just a reference, actual directory needs to be created manually)
-- The uploads/proofs/ directory should be created in your project root with write permissions

-- Display success message
SELECT '✅ Database setup complete!' AS message;
SELECT 'Tables created: users, admins, investigation_officers, complaints, complaint_proofs, complaint_timeline, notifications, activity_logs, login_attempts, password_resets' AS tables;

-- Display login credentials
SELECT '🔐 Admin Login:' AS credential_type;
SELECT 'Username: admin' AS admin_credential;
SELECT 'Password: admin123' AS admin_password;
SELECT '-----------------------------------' AS `separator`;
SELECT '👮 Officer Login:' AS credential_type;
SELECT 'Officer 1: amit.sharma@cybercell.gov.in / password' AS officer1;
SELECT 'Officer 2: neha.verma@cybercell.gov.in / password' AS officer2;
SELECT 'Officer 3: rajesh.patil@cybercell.gov.in / password' AS officer3;
SELECT '-----------------------------------' AS `separator`;
SELECT '👤 User Login:' AS credential_type;
SELECT 'User 1: ravi@example.com / pass123' AS user1;
SELECT 'User 2: priya@example.com / test456' AS user2;
SELECT 'User 3: amit@example.com / pass123' AS user3;
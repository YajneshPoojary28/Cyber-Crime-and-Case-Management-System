# 🛡️ CyberShield — Cyber Crime Reporting Portal

A web-based platform for citizens to report cyber crimes, track complaint status, and connect with investigation officers. Built with PHP and MySQL.

---

## Features

**Citizens**
- Register and log in to a personal account
- File cyber crime complaints with incident details, suspect info, financial loss, and proof uploads (JPG, PNG, PDF — up to 5MB)
- Track complaint status in real time
- Receive notifications on complaint updates

**Investigation Officers**
- Dedicated officer login (law enforcement only)
- View and manage assigned complaints
- Update complaint status, add resolution notes
- Dashboard with personal stats (assigned, in-progress, resolved)

**Admin**
- Full complaint management — assign, update, close, or flag fake complaints
- Manage users and officers
- Activity logs and reports
- Notification system

---

## Tech Stack

- **Backend:** PHP (procedural)
- **Database:** MySQL (`cybershield_db`)
- **Frontend:** HTML, CSS, vanilla JS (dark theme UI)

---

## Getting Started

### Requirements

- PHP 7.4+
- MySQL 5.7+
- Apache or Nginx with `mod_rewrite`
- Composer (optional, PHPMailer is bundled)

### Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/your-username/cybershield.git
   cd cybershield
   ```

2. Import the database:
   ```bash
   mysql -u root -p < database.sql
   ```

3. Configure the database connection in `config/database.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_db_user');
   define('DB_PASS', 'your_db_password');
   define('DB_NAME', 'cybershield_db');
   ```

4. Ensure the uploads directory is writable:
   ```bash
   chmod 777 uploads/proofs/
   ```

5. Serve the project from your web root (e.g. `htdocs/` or `/var/www/html/`) and open it in your browser.

---

## Project Structure

```
cybershield/
├── config/
│   └── database.php          # DB connection & shared functions
├── css/
│   └── style.css             # Global stylesheet
├── js/
│   └── main.js               # Frontend scripts
├── includes/
│   ├── header.php
│   ├── footer.php
│   ├── sidebar.php
│   └── functions.php
├── PHPMailer/                 # Bundled PHPMailer library
├── uploads/
│   └── proofs/               # Uploaded complaint evidence
├── index.php                 # Landing / login selector
├── login.php                 # Citizen login
├── register.php              # Citizen registration
├── dashboard.php             # Citizen dashboard
├── file-complaint.php        # Submit a new complaint
├── my-complaints.php         # View own complaints
├── complaint-detail.php      # Single complaint view
├── notifications.php         # User notifications
├── profile.php               # User profile
├── reset-password.php        # Password reset
├── officer-login.php         # Officer login
├── officer-dashboard.php     # Officer dashboard
├── officer-complaints.php    # Officer complaint management
├── officer-reports.php       # Officer reports
├── admin-login.php           # Admin login
├── admin-dashboard.php       # Admin dashboard
├── admin-complaints.php      # Admin complaint management
├── admin-users.php           # User management
├── admin-reports.php         # Admin reports
├── admin-logs.php            # Activity logs
├── admin-notifications.php   # Admin notifications
├── resolve-fake-complaint.php
├── guidelines.php
└── database.sql              # Full DB schema
```

---

## Complaint Number Format

Complaints are assigned a unique ID on submission:

```
CYB-YYYY-NNNNN
Example: CYB-2026-00042
```

---

## Auto-Priority Logic

Complaints are automatically assigned a priority on filing:

| Condition | Priority |
|---|---|
| Financial loss ≥ ₹50,000 | Critical |
| Keywords: bank, OTP, credit card, UPI | High |
| All others | Medium |

---

## Roles

| Role | Login Page | Access |
|---|---|---|
| Citizen | `/login.php` | File & track complaints |
| Investigation Officer | `/officer-login.php` | Manage assigned cases |
| Admin | `/admin-login.php` | Full system access |

---

## Notes

- The default DB credentials in `config/database.php` use `root` with no password — update these before deploying.
- The `uploads/proofs/` directory is protected by `.htaccess` to prevent direct access.
---

## License

This project was developed as an academic submission. All rights reserved.

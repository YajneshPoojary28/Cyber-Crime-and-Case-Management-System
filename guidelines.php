<?php
require_once 'config/database.php';
redirectIfNotLoggedIn();

$pageTitle = 'Cyber Safety Guidelines';
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="guidelines-container">
    <div class="guidelines-header">
        <h1 class="guidelines-title">🛡️ Cyber Safety Guidelines</h1>
        <p class="guidelines-subtitle">Learn how to protect yourself from cyber crimes and stay safe online</p>
    </div>

    <!-- Quick Tips Section -->
    <div class="quick-tips">
        <div class="tip-card">
            <div class="tip-icon">🔐</div>
            <h3>Strong Passwords</h3>
            <p>Use unique, complex passwords for different accounts</p>
        </div>
        <div class="tip-card">
            <div class="tip-icon">📧</div>
            <h3>Email Safety</h3>
            <p>Don't click suspicious links or download unknown attachments</p>
        </div>
        <div class="tip-card">
            <div class="tip-icon">📱</div>
            <h3>2FA Enabled</h3>
            <p>Enable two-factor authentication wherever possible</p>
        </div>
        <div class="tip-card">
            <div class="tip-icon">🔄</div>
            <h3>Regular Updates</h3>
            <p>Keep your software and devices updated</p>
        </div>
    </div>

    <!-- Detailed Guidelines Sections -->
    <div class="guidelines-grid">
        <!-- Banking & UPI Frauds -->
        <div class="guideline-card">
            <div class="card-header">
                <div class="card-icon">🏦</div>
                <h2>Banking & UPI Frauds</h2>
            </div>
            <div class="card-content">
                <ul>
                    <li><strong>Never share OTP</strong> - Bank will never ask for OTP over call or message</li>
                    <li><strong>Verify UPI IDs</strong> - Double-check before sending money</li>
                    <li><strong>Avoid unknown links</strong> - Don't click on SMS/email links claiming bank update</li>
                    <li><strong>Use official apps</strong> - Always use official banking apps from Play Store/App Store</li>
                    <li><strong>Check transaction alerts</strong> - Monitor bank messages regularly</li>
                    <li><strong>Report immediately</strong> - Call 1930 if you suspect fraud</li>
                </ul>
            </div>
        </div>

        <!-- Phishing Attacks -->
        <div class="guideline-card">
            <div class="card-header">
                <div class="card-icon">🎣</div>
                <h2>Phishing Attacks</h2>
            </div>
            <div class="card-content">
                <ul>
                    <li><strong>Check email addresses</strong> - Look for slight misspellings in sender email</li>
                    <li><strong>Hover before clicking</strong> - See where links actually lead</li>
                    <li><strong>Don't download attachments</strong> - From unknown senders</li>
                    <li><strong>Verify website URLs</strong> - Look for HTTPS and correct domain names</li>
                    <li><strong>Be wary of urgency</strong> - Scammers create fake urgency</li>
                    <li><strong>Report phishing</strong> - Forward suspicious emails to report@phishing.gov.in</li>
                </ul>
            </div>
        </div>

        <!-- Social Media Safety -->
        <div class="guideline-card">
            <div class="card-header">
                <div class="card-icon">📱</div>
                <h2>Social Media Safety</h2>
            </div>
            <div class="card-content">
                <ul>
                    <li><strong>Privacy settings</strong> - Set profiles to private</li>
                    <li><strong>Don't overshare</strong> - Avoid sharing location, travel plans, personal details</li>
                    <li><strong>Verify friend requests</strong> - Don't accept unknown requests</li>
                    <li><strong>Think before posting</strong> - Content can be screenshotted and shared</li>
                    <li><strong>Report harassment</strong> - Use platform reporting tools</li>
                    <li><strong>Block suspicious accounts</strong> - Don't engage with trolls</li>
                </ul>
            </div>
        </div>

        <!-- Online Shopping Safety -->
        <div class="guideline-card">
            <div class="card-header">
                <div class="card-icon">🛒</div>
                <h2>Online Shopping Safety</h2>
            </div>
            <div class="card-content">
                <ul>
                    <li><strong>Shop on trusted sites</strong> - Use well-known e-commerce platforms</li>
                    <li><strong>Check reviews</strong> - Look for seller ratings and customer feedback</li>
                    <li><strong>Use secure payment</strong> - Prefer COD or trusted payment gateways</li>
                    <li><strong>Beware of too-good deals</strong> - Extremely low prices are often scams</li>
                    <li><strong>Save order details</strong> - Keep screenshots of transactions</li>
                    <li><strong>Check return policies</strong> - Before making payment</li>
                </ul>
            </div>
        </div>

        <!-- Password Security -->
        <div class="guideline-card">
            <div class="card-header">
                <div class="card-icon">🔑</div>
                <h2>Password Security</h2>
            </div>
            <div class="card-content">
                <ul>
                    <li><strong>Use strong passwords</strong> - Minimum 12 characters with mix of letters, numbers, symbols</li>
                    <li><strong>Don't reuse passwords</strong> - Different passwords for different accounts</li>
                    <li><strong>Use password manager</strong> - Tools like Bitwarden, LastPass</li>
                    <li><strong>Change regularly</strong> - Update passwords every 3-6 months</li>
                    <li><strong>Don't share passwords</strong> - Never share via email or message</li>
                    <li><strong>Enable 2FA</strong> - Two-factor authentication adds extra layer</li>
                </ul>
            </div>
        </div>

        <!-- Child Safety Online -->
        <div class="guideline-card">
            <div class="card-header">
                <div class="card-icon">👶</div>
                <h2>Child Safety Online</h2>
            </div>
            <div class="card-content">
                <ul>
                    <li><strong>Monitor online activity</strong> - Keep computers in common areas</li>
                    <li><strong>Use parental controls</strong> - Set age-appropriate restrictions</li>
                    <li><strong>Teach about privacy</strong> - Don't share personal information</li>
                    <li><strong>Discuss online strangers</strong> - Never meet online friends alone</li>
                    <li><strong>Report cyberbullying</strong> - Save evidence and report to school/platform</li>
                    <li><strong>Set screen time limits</strong> - Balance online and offline activities</li>
                </ul>
            </div>
        </div>

        <!-- What to Do If You're a Victim -->
        <div class="guideline-card emergency-card">
            <div class="card-header">
                <div class="card-icon">🚨</div>
                <h2>What To Do If You're a Victim</h2>
            </div>
            <div class="card-content">
                <ul>
                    <li><strong>Stay calm</strong> - Don't panic or delete evidence</li>
                    <li><strong>Document everything</strong> - Take screenshots of messages, emails, transactions</li>
                    <li><strong>Change passwords</strong> - Immediately secure your accounts</li>
                    <li><strong>Contact your bank</strong> - If financial fraud, block cards immediately</li>
                    <li><strong>File a complaint</strong> - Report on cybercrime.gov.in or call 1930</li>
                    <li><strong>Preserve evidence</strong> - Don't delete anything until investigation is complete</li>
                    <li><strong>Follow up</strong> - Keep track of your complaint status</li>
                </ul>
            </div>
        </div>

        <!-- Emergency Contact Numbers -->
        <div class="guideline-card contact-card">
            <div class="card-header">
                <div class="card-icon">📞</div>
                <h2>Emergency Contact Numbers</h2>
            </div>
            <div class="card-content">
                <div class="contact-list">
                    <div class="contact-item">
                        <span class="contact-name">Cyber Crime Helpline:</span>
                        <span class="contact-number">1930</span>
                    </div>
                    <div class="contact-item">
                        <span class="contact-name">National Police Helpline:</span>
                        <span class="contact-number">100</span>
                    </div>
                    <div class="contact-item">
                        <span class="contact-name">Women Helpline:</span>
                        <span class="contact-number">1091</span>
                    </div>
                    <div class="contact-item">
                        <span class="contact-name">Child Helpline:</span>
                        <span class="contact-number">1098</span>
                    </div>
                    <div class="contact-item">
                        <span class="contact-name">National Cyber Crime Portal:</span>
                        <span class="contact-number">www.cybercrime.gov.in</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Note -->
    <div class="guidelines-footer">
        <p>⚠️ Remember: Prevention is better than cure. Stay vigilant, stay safe!</p>
    </div>
</div>

<style>
.guidelines-container {
    max-width: 1400px;
    margin: 0 auto;
}

.guidelines-header {
    text-align: center;
    margin-bottom: 32px;
}

.guidelines-title {
    font-family: var(--display);
    font-size: 32px;
    font-weight: 800;
    color: var(--text);
    margin-bottom: 12px;
}

.guidelines-subtitle {
    font-size: 16px;
    color: var(--text2);
}

/* Quick Tips Section */
.quick-tips {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 40px;
}

.tip-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
    text-align: center;
    transition: all 0.2s;
}

.tip-card:hover {
    transform: translateY(-5px);
    border-color: var(--accent);
}

.tip-icon {
    font-size: 48px;
    margin-bottom: 16px;
}

.tip-card h3 {
    font-family: var(--display);
    font-size: 18px;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 8px;
}

.tip-card p {
    font-size: 13px;
    color: var(--text2);
    line-height: 1.5;
}

/* Guidelines Grid */
.guidelines-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
    margin-bottom: 32px;
}

.guideline-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.2s;
}

.guideline-card:hover {
    border-color: var(--border2);
}

.card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 20px 24px;
    background: var(--bg4);
    border-bottom: 1px solid var(--border);
}

.card-icon {
    font-size: 32px;
}

.card-header h2 {
    font-family: var(--display);
    font-size: 18px;
    font-weight: 700;
    color: var(--text);
}

.card-content {
    padding: 20px 24px;
}

.card-content ul {
    margin: 0;
    padding-left: 20px;
}

.card-content li {
    color: var(--text2);
    font-size: 13px;
    line-height: 1.8;
    margin-bottom: 8px;
}

.card-content li strong {
    color: var(--accent2);
}

/* Emergency Card */
.emergency-card {
    border-left: 4px solid var(--danger);
}

.emergency-card .card-header {
    background: rgba(244,63,94,0.1);
}

/* Contact Card */
.contact-card {
    border-left: 4px solid var(--success);
}

.contact-card .card-header {
    background: rgba(16,185,129,0.1);
}

.contact-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.contact-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
}

.contact-item:last-child {
    border-bottom: none;
}

.contact-name {
    font-size: 14px;
    font-weight: 500;
    color: var(--text);
}

.contact-number {
    font-size: 14px;
    font-family: var(--mono);
    color: var(--accent2);
    font-weight: 600;
}

/* Footer */
.guidelines-footer {
    text-align: center;
    padding: 24px;
    background: rgba(245,158,11,0.1);
    border: 1px solid rgba(245,158,11,0.3);
    border-radius: 16px;
}

.guidelines-footer p {
    color: var(--warning);
    font-size: 14px;
    font-weight: 500;
}

/* Responsive */
@media (max-width: 1024px) {
    .quick-tips {
        grid-template-columns: repeat(2, 1fr);
    }
    .guidelines-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .quick-tips {
        grid-template-columns: 1fr;
    }
    .guidelines-title {
        font-size: 24px;
    }
    .contact-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
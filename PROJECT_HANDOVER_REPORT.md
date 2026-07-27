# Project Handover Report

## Refer • Earn • Reward & Donate

**Project Title:** Refer Earn Reward and Donate – A Referral Management Platform  
**Submitted By:** Deeksha DS, Darshan Poojary  
**Date of Handover:** 27 July 2026  
**Repository:** https://github.com/deeksha2808/Refer-Earn-Reward-Donate  
**Status:** Completed  

---

## 1. Executive Summary

Refer • Earn • Reward & Donate is a full-stack web application that facilitates referral-based business growth. It connects Businesses with Referrers through a secure customer referral ecosystem. Businesses publish referral opportunities, receive qualified customer leads from referrers, verify customer consent before accessing contact details, process commission payments, and track performance analytics. Referrers earn rewards, manage their wallet balance, withdraw earnings via Razorpay payouts, and optionally donate a portion of their income to registered NGOs.

The platform enforces a multi-stage referral workflow with customer consent verification to prevent referral fraud and protect customer privacy.

---

## 2. Technology Stack

| Layer | Technology | Version / Details |
|-------|-----------|-------------------|
| Backend Language | PHP | 8.1+ (Developed on PHP 8.3.6) |
| Frontend | HTML5, CSS3, JavaScript | Vanilla JS with Bootstrap 5 |
| Database | MySQL | InnoDB engine, utf8mb4 charset |
| Payment Gateway | Razorpay | Orders API, Payouts API (RazorpayX), Webhooks |
| Email Service | PHPMailer | v6.9+ via Composer |
| SMTP Providers | Gmail / Mailtrap / Brevo | Configurable via .env |
| Version Control | Git + GitHub | Remote repository hosted on GitHub |
| Development Server | PHP Built-in Server | `php -S localhost:8000` |
| Operating System | Ubuntu 24.04 LTS | Linux development environment |
| IDE | Visual Studio Code / Kiro | Primary development environment |

---

## 3. Architecture Overview

### 3.1 Application Architecture

The application follows a traditional server-rendered MVC-like PHP architecture:

```
┌─────────────────────────────────────────────────┐
│                   Browser (Client)               │
└────────────────────────┬────────────────────────┘
                         │ HTTP
┌────────────────────────▼────────────────────────┐
│              PHP Built-in Server (:8000)          │
├──────────────────────────────────────────────────┤
│  Routing Layer (Direct PHP file access)          │
│  ├── index.php (Landing page)                    │
│  ├── auth/ (Login, Register, Password Reset)     │
│  ├── business/ (Business dashboard & features)   │
│  ├── referrer/ (Referrer dashboard & features)   │
│  ├── api/ (JSON API endpoints)                   │
│  └── dashboard/ (Generic dashboard redirect)     │
├──────────────────────────────────────────────────┤
│  Business Logic Layer (includes/)                │
│  ├── functions.php (Core helpers, auth, CSRF)    │
│  ├── customer_referrals.php (Referral logic)     │
│  ├── wallet.php (Wallet & commission engine)     │
│  ├── razorpay_service.php (Payment integration)  │
│  ├── notification_service.php (Notifications)    │
│  ├── email_service.php (SMTP email delivery)     │
│  ├── reports.php (Reporting & exports)           │
│  └── activity_log_service.php (Audit trail)      │
├──────────────────────────────────────────────────┤
│  Data Layer (PDO with prepared statements)       │
│  └── MySQL Database (referral_platform)          │
└──────────────────────────────────────────────────┘
```

### 3.2 Directory Structure

```
refer-earn-reward-donate/
├── api/                    # REST API endpoints (JSON responses)
│   ├── customer_consent.php
│   ├── ngos.php
│   ├── razorpay_order.php
│   ├── razorpay_verify.php
│   ├── razorpay_webhook.php
│   └── withdrawal.php
├── assets/
│   ├── css/               # Stylesheets (style.css, module-specific CSS)
│   ├── js/                # JavaScript (app.js, pincode-lookup.js)
│   └── images/
├── auth/                  # Authentication pages
│   ├── login.php
│   ├── register.php
│   ├── logout.php
│   ├── forgot_password.php
│   └── reset_password.php
├── business/              # Business user pages
│   ├── dashboard.php
│   ├── profile.php
│   ├── opportunities.php
│   ├── opportunity_form.php
│   ├── opportunity_view.php
│   ├── referrals.php
│   ├── referral_view.php
│   ├── analytics.php
│   ├── reports.php
│   └── report_export.php
├── config/                # Application configuration
│   ├── app.php            # Session setup, base includes
│   └── database.php       # PDO connection, .env loader
├── database/              # SQL schema and migrations
│   ├── referral_platform.sql
│   ├── module2_business_profiles.sql
│   ├── module3_referrer_profiles.sql
│   ├── module4_referral_opportunities.sql
│   ├── module5_customer_referrals.sql
│   ├── module6_wallet_system.sql
│   ├── module7_business_analytics.sql
│   ├── module8_category_custom_values.sql
│   └── migrations/        # Incremental schema changes
├── includes/              # Shared business logic
│   ├── functions.php
│   ├── wallet.php
│   ├── customer_referrals.php
│   ├── razorpay_service.php
│   ├── notification_service.php
│   ├── email_service.php
│   ├── reports.php
│   ├── activity_log_service.php
│   └── ... (other service files)
├── referrer/              # Referrer user pages
│   ├── dashboard.php
│   ├── profile.php
│   ├── opportunities.php
│   ├── opportunity.php
│   ├── referral_form.php
│   ├── referral_view.php
│   ├── referrals.php
│   ├── wallet.php
│   ├── donate.php
│   ├── donations.php
│   ├── analytics.php
│   ├── reward_history.php
│   └── transactions.php
├── uploads/               # User-uploaded files
│   ├── business_logos/
│   ├── business_documents/
│   ├── profile_photos/
│   └── referrer_documents/
├── vendor/                # Composer dependencies (PHPMailer)
├── .env.example           # Environment variable template
├── composer.json          # PHP dependency manifest
├── index.php              # Landing page / entry point
└── README.md              # Project documentation
```

---

## 4. Database Schema

### 4.1 Entity Relationship Overview

The database `referral_platform` contains the following core tables:

| Table | Purpose |
|-------|---------|
| `users` | Stores all user accounts (Business & Referrer roles) |
| `business_profiles` | Extended business information, verification docs |
| `referrer_profiles` | Referrer personal info, bank details, KYC documents |
| `referral_opportunities` | Business-created referral campaigns |
| `opportunity_products` | Products/services within an opportunity with commission rates |
| `customer_referrals` | Submitted referrals from referrers to businesses |
| `referral_status_history` | Audit trail of referral status changes |
| `wallets` | Referrer wallet balances |
| `wallet_transactions` | All wallet credits/debits (rewards, donations, withdrawals) |
| `donations` | Donation records to NGOs/causes |
| `ngos` | NGO directory |
| `notifications` | In-app notification records |
| `commission_payments` | Razorpay payment records for commissions |
| `withdrawals` | Wallet withdrawal requests and payout tracking |
| `platform_revenue` | Platform fee revenue tracking |
| `activity_logs` | System-wide activity audit log |
| `password_reset_tokens` | Secure password reset token storage |
| `razorpay_webhook_events` | Idempotent webhook event tracking |

### 4.2 Key Relationships

- `users` (1) → (1) `business_profiles` or `referrer_profiles`
- `users` (1) → (N) `referral_opportunities` (business creates many)
- `referral_opportunities` (1) → (N) `opportunity_products`
- `referral_opportunities` (1) → (N) `customer_referrals`
- `users` (1) → (N) `customer_referrals` (referrer submits many)
- `users` (1) → (1) `wallets`
- `wallets` (1) → (N) `wallet_transactions`
- `wallets` (1) → (N) `donations`
- `customer_referrals` (1) → (N) `referral_status_history`
- `customer_referrals` (1) → (0..1) `commission_payments`

---

## 5. Module Descriptions

### Module 1: Authentication & User Management
- User registration with role selection (Business/Referrer)
- Secure login with password hashing (bcrypt via `password_hash`)
- Session-based authentication with `session_regenerate_id` on login
- CSRF token protection on all forms
- Password reset via email with secure time-limited tokens
- Role-based access control on all pages

### Module 2: Business Profile Management
- Multi-step profile completion (business details, contact, address, verification docs)
- Logo upload and business document upload
- GST number field
- Profile completion percentage tracking
- Verification status workflow (Pending → Verified/Rejected)
- Pincode-based auto-fill for city/state (via JavaScript)

### Module 3: Referrer Profile Management
- Comprehensive profile with personal info, address, occupation, bio
- Government ID verification (Aadhaar, PAN, Driving Licence, Passport, Voter ID)
- Bank account details (account name, number, IFSC) for payouts
- UPI ID support
- Profile photo upload
- Experience level and service category selection
- Profile completion tracking

### Module 4: Referral Opportunities
- Businesses create referral campaigns with title, category, description, location, validity
- Each opportunity has multiple products/services with individual commission percentages
- Active/Inactive status management
- Referrers can browse, search, and filter active opportunities
- Category-based and location-based filtering

### Module 5: Customer Referral Submission & Workflow
- Referrers submit customer referrals with: name, phone, email, address, product selection
- Unique referral code generation (format: REF-YYYYMMDD-000001)
- Multi-stage status workflow:
  ```
  Submitted → Under Review → Processing → Waiting for Customer Approval
  → Customer Approved → Completed → Commission Payment → Wallet Credit
  ```
- Customer contact details are masked until customer consent is obtained
- Status transition rules enforced server-side
- Complete referral status history maintained

### Module 6: Secure Customer Consent Workflow
- When business requests contact access, a secure approval token is generated
- Email sent to customer with approve/decline links
- Token is 64 characters (cryptographically random), time-limited (7 days)
- Customer can approve or decline sharing their contact details
- On approval: business sees full contact info, referral advances to "Customer Approved"
- On decline: referral moves to "Declined by Customer" (terminal state)
- Prevents referral fraud and protects customer privacy

### Module 7: Wallet & Rewards System
- Automatic wallet creation for referrers
- Commission calculation: `sale_amount × commission_percentage`
- Platform service fee: 2% deducted from gross commission
- Net commission credited to referrer wallet
- Transaction types: Reward Credit, Donation, Withdrawal
- Balance tracking: current balance, total earned, total donated
- Today's earnings calculation
- Pending rewards estimation

### Module 8: Razorpay Payment Integration
- **Commission Payments (Business → Platform):**
  - Create Razorpay order for commission amount
  - Verify payment signature (HMAC SHA256)
  - Record payment status (created → paid/failed)
- **Wallet Withdrawals (Platform → Referrer):**
  - Minimum withdrawal: ₹100
  - RazorpayX Payouts API for bank transfer / UPI
  - Contact and Fund Account creation via API
  - Payout status tracking
- **Webhook Integration:**
  - Signature verification for webhook security
  - Idempotent event processing (duplicate detection)
  - Handles payout status updates
- **Demo Mode:**
  - Simulated payments without real Razorpay credentials
  - Configurable via `PAYMENT_MODE=demo` in .env

### Module 9: NGO Donation System
- NGO directory with name, email, phone, address, website, category
- Referrers can donate wallet balance to NGOs or predefined causes
- Supported causes: Education, Child Welfare, Medical Help, Animal Rescue, Environmental Protection, Old Age Home
- Donation recorded as wallet debit transaction
- Complete donation history tracking

### Module 10: Analytics & Reports
- **Business Analytics:**
  - Referral statistics (received, pending, accepted, rejected, completed)
  - Commission reports with platform fee breakdown
  - Campaign performance with conversion rates
  - Monthly referral trends
  - Status distribution charts
- **Referrer Analytics:**
  - Referral performance metrics
  - Earnings summary
  - Wallet statistics
  - Donation reports
- **Report Exports:**
  - CSV export
  - XLSX export (native PHP, no external library needed beyond ZipArchive)
  - PDF export (native PDF generation)
  - Filter by date range, campaign, product, status, referrer

### Module 11: Notification System
- In-app notifications stored in database
- Email notifications via PHPMailer (SMTP)
- Notification types: Welcome, Opportunity, Referral Status, Wallet Credit, System
- Supports Gmail, Mailtrap, and Brevo SMTP providers
- HTML email templates with branded design
- Activity logging for all notification events

### Module 12: Activity Logging
- System-wide audit trail
- Logs: authentication events, profile updates, referral status changes, payments, notifications
- Structured logging with: user, module, action, entity type, entity ID, description

---

## 6. Security Implementation

| Security Feature | Implementation |
|-----------------|----------------|
| Password Storage | bcrypt hash via `password_hash(PASSWORD_DEFAULT)` |
| CSRF Protection | Random 32-byte token per session, verified on every POST |
| SQL Injection Prevention | PDO prepared statements with parameterized queries exclusively |
| XSS Prevention | `htmlspecialchars()` output escaping via `e()` helper |
| Session Security | `httponly`, `samesite=Lax`, `secure` (on HTTPS), strict mode |
| Session Fixation | `session_regenerate_id(true)` on login |
| Role-Based Access | `require_login($role)` gate on every protected page |
| Customer Privacy | Contact details masked until customer approves via email token |
| Approval Tokens | Cryptographically random (64 hex chars), time-limited (7 days) |
| Payment Verification | Razorpay HMAC-SHA256 signature validation |
| Webhook Security | Webhook signature verification, idempotent event processing |
| Input Validation | Server-side validation on all user inputs with length and format checks |
| File Upload Security | Stored in non-web-accessible paths with generated names |
| CSV Formula Injection | Cell values starting with `=+@-` are prefixed with `'` |
| Error Handling | Exceptions logged server-side, generic messages shown to users |
| Sensitive Data | .env file excluded from git, secrets never exposed in responses |

---

## 7. Payment Flow

### 7.1 Commission Payment Flow (Business pays commission)

```
1. Business marks referral as "Completed" with sale amount
2. System calculates: Gross Commission = Sale Amount × Rate%
3. Platform Fee = Gross Commission × 2%
4. Net Commission = Gross Commission - Platform Fee
5. Razorpay Order created for Gross Commission amount
6. Business completes payment via Razorpay checkout
7. Payment signature verified server-side
8. Net Commission credited to referrer wallet
9. Platform fee recorded in platform_revenue table
```

### 7.2 Withdrawal Flow (Referrer withdraws earnings)

```
1. Referrer requests withdrawal (minimum ₹100)
2. Balance check performed
3. Wallet debited immediately
4. Razorpay Contact created (or reused)
5. Fund Account created (bank or UPI)
6. RazorpayX Payout initiated
7. Withdrawal status updated via webhook callbacks
8. Reference number and UTR assigned on success
```

---

## 8. Configuration & Environment Variables

The application is configured via a `.env` file (copy from `.env.example`):

| Variable | Purpose | Example |
|----------|---------|---------|
| `DB_HOST` | MySQL host | `127.0.0.1` |
| `DB_NAME` | Database name | `referral_platform` |
| `DB_USER` | Database username | `root` |
| `DB_PASSWORD` | Database password | (secret) |
| `DB_CHARSET` | Character set | `utf8mb4` |
| `APP_URL` | Public application URL | `http://localhost:8000` |
| `APP_BASE_URL` | Base path for routing | `/` |
| `PAYMENT_MODE` | Payment mode | `demo` or `live` |
| `RAZORPAY_KEY_ID` | Razorpay API key | (from Razorpay dashboard) |
| `RAZORPAY_KEY_SECRET` | Razorpay secret | (from Razorpay dashboard) |
| `RAZORPAY_WEBHOOK_SECRET` | Webhook signing secret | (from Razorpay dashboard) |
| `RAZORPAY_ACCOUNT_NUMBER` | RazorpayX account | (for payouts) |
| `EMAIL_PROVIDER` | SMTP provider | `mailtrap` / `gmail` / `brevo` |
| `SMTP_HOST` | SMTP server host | `sandbox.smtp.mailtrap.io` |
| `SMTP_PORT` | SMTP port | `587` |
| `SMTP_ENCRYPTION` | Encryption type | `tls` |
| `SMTP_USERNAME` | SMTP username | (provider credentials) |
| `SMTP_PASSWORD` | SMTP password | (provider credentials) |
| `SMTP_FROM_EMAIL` | Sender email | `no-reply@example.com` |
| `SMTP_FROM_NAME` | Sender name | `Refer Earn Bill Reward and Donate` |

---

## 9. Installation & Setup Guide

### Prerequisites
- PHP 8.1 or higher with extensions: PDO, pdo_mysql, mbstring, json, zip
- MySQL 8.0+
- Composer (for PHPMailer dependency)
- Git

### Step-by-Step Setup

```bash
# 1. Clone the repository
git clone https://github.com/deeksha2808/Refer-Earn-Reward-Donate.git
cd Refer-Earn-Reward-Donate

# 2. Install PHP dependencies
composer install

# 3. Configure environment
cp .env.example .env
# Edit .env with your database credentials and SMTP settings

# 4. Create MySQL database
mysql -u root -p -e "CREATE DATABASE referral_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 5. Import database schema (in order)
mysql -u root -p referral_platform < database/referral_platform.sql
mysql -u root -p referral_platform < database/module2_business_profiles.sql
mysql -u root -p referral_platform < database/module3_referrer_profiles.sql
mysql -u root -p referral_platform < database/module4_referral_opportunities.sql
mysql -u root -p referral_platform < database/module5_customer_referrals.sql
mysql -u root -p referral_platform < database/module6_wallet_system.sql
mysql -u root -p referral_platform < database/module7_business_analytics.sql
mysql -u root -p referral_platform < database/module8_category_custom_values.sql

# 6. Apply migrations
mysql -u root -p referral_platform < database/migrations/20260719_business_profile_category_varchar.sql
mysql -u root -p referral_platform < database/migrations/20260719_commission_system.sql
mysql -u root -p referral_platform < database/migrations/20260720_search_filter_indexes.sql
mysql -u root -p referral_platform < database/migrations/20260721_activity_logs.sql
mysql -u root -p referral_platform < database/migrations/20260721_notification_center.sql
mysql -u root -p referral_platform < database/migrations/20260721_notification_indexes.sql
mysql -u root -p referral_platform < database/migrations/20260721_notification_legacy_body_nullable.sql
mysql -u root -p referral_platform < database/migrations/20260722_analytics_view_collations.sql
mysql -u root -p referral_platform < database/migrations/20260722_referral_status_workflow.sql
mysql -u root -p referral_platform < database/migrations/20260723_password_reset_tokens.sql
mysql -u root -p referral_platform < database/migrations/20260723_referral_processing_status.sql
mysql -u root -p referral_platform < database/migrations/20260724_ngo_directory.sql
mysql -u root -p referral_platform < database/migrations/20260724_ngo_directory_expand.sql
mysql -u root -p referral_platform < database/migrations/20260724_platform_service_fee.sql
mysql -u root -p referral_platform < database/migrations/20260724_razorpay_payments.sql
mysql -u root -p referral_platform < database/migrations/20260724_razorpay_payouts.sql
mysql -u root -p referral_platform < database/migrations/20260725_payment_mode_platform_revenue.sql
mysql -u root -p referral_platform < database/migrations/20260726_secure_referral_approval.sql

# 7. Start the application
php -S localhost:8000

# 8. Open in browser
# http://localhost:8000
```

---

## 10. User Roles & Permissions

### Business User
| Permission | Description |
|-----------|-------------|
| Create opportunities | Publish referral campaigns with products and commission rates |
| View received referrals | See all referrals submitted by referrers |
| Change referral status | Move referrals through the workflow stages |
| Request customer consent | Trigger email to customer for contact approval |
| View approved contacts | Access customer details after consent |
| Complete referrals | Record sale amount and trigger commission |
| View analytics | Access business performance dashboards |
| Export reports | Download CSV/XLSX/PDF reports |

### Referrer User
| Permission | Description |
|-----------|-------------|
| Browse opportunities | View all active referral campaigns |
| Submit referrals | Submit customer leads to businesses |
| Track referral status | Monitor progress of submitted referrals |
| View wallet | Check balance, earnings, and transactions |
| Request withdrawal | Withdraw wallet balance via bank/UPI |
| Donate to NGOs | Donate wallet balance to registered NGOs |
| View analytics | Access personal performance metrics |

---

## 11. API Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/customer_consent.php` | GET | Customer approval/decline page (token-based) |
| `/api/ngos.php` | GET | Fetch NGO directory listing |
| `/api/razorpay_order.php` | POST | Create Razorpay payment order |
| `/api/razorpay_verify.php` | POST | Verify Razorpay payment signature |
| `/api/razorpay_webhook.php` | POST | Razorpay webhook event handler |
| `/api/withdrawal.php` | POST | Process wallet withdrawal request |

---

## 12. Testing Guide

### Functional Testing Checklist

**Authentication:**
- [ ] Business registration with all fields
- [ ] Referrer registration with all fields
- [ ] Login with valid credentials
- [ ] Login with invalid credentials (error displayed)
- [ ] Logout clears session
- [ ] Password reset email sent
- [ ] Password reset with valid token
- [ ] CSRF token validation on all forms

**Business Flow:**
- [ ] Profile creation and completion
- [ ] Create referral opportunity with multiple products
- [ ] Edit/deactivate opportunity
- [ ] Receive and review referral
- [ ] Change referral status through workflow
- [ ] Request customer consent (email sent)
- [ ] View contact after customer approval
- [ ] Complete referral with sale amount
- [ ] View commission breakdown (gross, fee, net)
- [ ] Analytics dashboard displays correct data
- [ ] Export reports (CSV, XLSX, PDF)

**Referrer Flow:**
- [ ] Profile creation with bank details
- [ ] Browse and filter opportunities
- [ ] Submit referral with customer details
- [ ] Track referral status changes
- [ ] Receive commission in wallet
- [ ] View wallet balance and transaction history
- [ ] Request withdrawal (minimum ₹100)
- [ ] Donate wallet balance to NGO
- [ ] View donation history
- [ ] Analytics show correct metrics

**Payment (Demo Mode):**
- [ ] Commission payment creates demo order
- [ ] Demo payment verification succeeds
- [ ] Wallet credited with net commission
- [ ] Withdrawal creates demo payout
- [ ] Reference number and UTR generated

---

## 13. Known Limitations & Future Scope

### Current Limitations
- No admin panel (admin functions require direct DB access)
- No REST API for mobile app integration
- No real-time notifications (polling-based)
- Single-language (English only)
- No Docker containerization
- Report charts are data-ready but need frontend charting library
- No automated test suite

### Future Enhancements
- Admin dashboard for platform management
- Mobile-responsive progressive web app (PWA)
- REST API with JWT authentication
- Docker + Docker Compose deployment
- Cloud deployment (AWS/GCP)
- SMS notifications (Twilio/MSG91)
- Real-time notifications via WebSocket
- Advanced analytics with Chart.js
- Multi-language (i18n) support
- Automated unit and integration tests
- Rate limiting and brute force protection
- Two-factor authentication (2FA)

---

## 14. Dependencies

### PHP Dependencies (via Composer)
```json
{
  "require": {
    "php": ">=8.1",
    "phpmailer/phpmailer": "^6.9"
  }
}
```

### Frontend Dependencies (CDN)
- Bootstrap 5 (CSS framework)
- Bootstrap Icons (Icon library)

### System Requirements
- PHP 8.1+ with extensions: PDO, pdo_mysql, mbstring, json, zip, curl
- MySQL 8.0+
- Composer 2.x
- Web server (PHP built-in server for development, Apache/Nginx for production)

---

## 15. File Count & Codebase Metrics

| Category | Count |
|----------|-------|
| PHP Files | ~50+ |
| SQL Schema Files | 8 base + 15 migrations |
| CSS Files | 5 |
| JavaScript Files | 2 |
| Total Source Files | ~80+ |
| Composer Dependencies | 1 (PHPMailer) |

---

## 16. Conclusion

The Refer • Earn • Reward & Donate platform is a complete, working web application covering user authentication, role-based profile management, referral opportunity creation, secure customer consent workflow, commission calculation with platform fee deduction, Razorpay payment integration (both inbound payments and outbound payouts), wallet management, NGO donations, analytics, and report exports.

The codebase follows secure coding practices including prepared statements, CSRF protection, password hashing, input validation, and output escaping. The application is ready for demonstration and further development toward production deployment.

---

*End of Handover Report*

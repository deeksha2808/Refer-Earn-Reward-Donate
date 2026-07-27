# Refer Earn Reward & Donate Platform
## Complete Project Report

---

## 1. Project Overview

**Project Name:** Refer Earn Reward & Donate Platform  
**Type:** Multi-tenant SaaS Web Application  
**Purpose:** A referral-based hiring and commission platform that connects Businesses with Referrers. Businesses create referral opportunities, Referrers submit candidate introductions, and successful referrals earn commissions credited to wallets. The platform also supports charitable donations to NGOs.

---

## 2. Technology Stack

| Layer | Technology |
|-------|-----------|
| Frontend | HTML5, CSS3, Bootstrap 5.3, JavaScript (ES6+), AJAX |
| Backend | PHP 8.3 (pure PHP, no framework) |
| Database | MySQL 8.0 |
| Mail | PHPMailer + Gmail SMTP (App Password) |
| Payments | Razorpay Checkout + Razorpay Payouts (RazorpayX) |
| Server | PHP Built-in Development Server / Apache |
| IDE | VS Code / Kiro |

---

## 3. Application Architecture

```
├── api/                    # REST API endpoints
├── assets/                 # CSS, JS, images
├── auth/                   # Authentication pages
├── business/               # Business role pages
├── config/                 # App & DB configuration
├── dashboard/              # Shared dashboard
├── database/               # Schema & migrations
├── includes/               # Shared PHP services
├── referrer/               # Referrer role pages
├── uploads/                # User uploads
├── vendor/                 # Composer dependencies (PHPMailer)
├── .env                    # Environment configuration
├── index.php               # Landing page
└── notifications.php       # Notification center
```

---

## 4. User Roles

| Role | Description |
|------|-------------|
| **Business** | Creates referral opportunities, reviews referrals, pays commissions |
| **Referrer** | Browses opportunities, submits referrals, earns commissions, withdraws/donates |

There is NO Admin module or admin role.

---

## 5. Complete Module List

### 5.1 Authentication
- Business Registration
- Referrer Registration
- Login / Logout
- Password Reset (token-based, 30-min expiry)
- Session Management (secure cookies, regeneration)
- Role-based Access Control
- CSRF Protection on all forms

### 5.2 Profile Management
- **Business Profile Wizard** (5 steps): Business info → Category → Address (PIN code auto-lookup) → Description → Uploads
- **Referrer Profile Wizard** (6 steps): Personal → Address (PIN code auto-lookup) → Professional → ID Verification → Payment Info → Photo
- Profile Completion Tracking (percentage)
- File Uploads (logo, verification documents, profile photos)

### 5.3 PIN Code Auto-Lookup
- Integrated with `api.postalpincode.in` (free, no API key)
- Auto-fills City, State, Country from 6-digit Indian PIN code
- Fallback to manual entry on API failure
- Applied to both Business and Referrer profile forms

### 5.4 Opportunity Management
- Create / Edit / Delete Opportunities
- Multiple Products per Opportunity (each with own commission %)
- Category, Location, Expiry Date
- Active / Inactive status
- Search & Filter (by title, category, status, product)
- Pagination

### 5.5 Referral Submission
- Referrer browses active opportunities
- Selects product → enters customer details
- Customer email is mandatory (required for approval workflow)
- Unique Referral Code generated (format: REF-YYYYMMDD-NNNNNN)
- Initial status: Submitted

### 5.6 Secure Referral Approval Workflow

**Problem Solved:** Prevents businesses from extracting customer contact details and bypassing the referrer's commission.

**Workflow:**
```
Submitted
    ↓
Under Review
    ↓
Processing
    ↓
Business clicks "Request Contact Access"
    ↓
Customer receives approval email (with Approve/Decline buttons)
    ↓
Waiting for Customer Approval
    ↓
Customer Approves → Contact details unlocked → Business can proceed
Customer Declines → Details stay hidden → Referral cannot proceed
    ↓
Customer Approved
    ↓
Business completes sale → Pays commission
    ↓
Completed
```

**Security Features:**
- Contact info masked until customer consent (phone: 98******21, email: ra****@gmail.com)
- Approval token: 64-char random hex, 7-day expiry
- One-time use (nullified after action)
- No login required for customer (link-based consent)
- Approval/decline timestamp recorded for audit

### 5.7 Commission & Platform Fee

**Calculation:**
```
Sale Amount (entered by business)
    ↓
Gross Commission = Sale Amount × Commission %
    ↓
Platform Fee = Gross Commission × 2%
    ↓
Net Commission = Gross Commission − Platform Fee
    ↓
Business pays Gross Commission via Razorpay
Platform keeps Platform Fee
Referrer wallet receives Net Commission
```

**Example:** Sale ₹500,000, Commission 10%
- Gross: ₹50,000
- Platform Fee: ₹1,000
- Net to Referrer: ₹49,000

### 5.8 Payment System (Razorpay)

**Dual Mode:** Configured via `PAYMENT_MODE` in `.env`
- `demo` — Simulates payments instantly (no Razorpay credentials needed)
- `live` — Real Razorpay Checkout + Payouts

**Business Payment Flow:**
1. Business enters sale amount → sees commission preview (Gross/Fee/Net)
2. Clicks "Pay Commission"
3. Demo: instantly verified | Live: Razorpay Checkout opens
4. Payment verified → Referral marked Completed → Wallet credited → Platform revenue recorded

**Referrer Withdrawal Flow:**
1. Selects Bank Account or UPI
2. Enters withdrawal amount (min ₹100)
3. Demo: instantly processed with UTR | Live: Razorpay Payout created
4. Webhook confirms transfer → Wallet updated

**Webhook Handling:**
- `payout.processed` → marks withdrawal successful, records UTR
- `payout.failed` → refunds wallet
- `payout.reversed` → refunds wallet
- `payout.cancelled` → refunds wallet
- Duplicate webhook prevention via event log table

### 5.9 Wallet System
- Auto-created on first commission
- Tracks: Current Balance, Lifetime Earnings, Total Donated
- Transaction types: Reward Credit, Donation, Withdrawal, Adjustment
- Platform fee deduction stored per referral
- Wallet page shows: stats, withdrawal form, withdrawal history with UTR

### 5.10 Donation Module (NGO Directory)
- 80 seeded NGOs from Dakshina Kannada & Udupi districts
- Browse with search, district filter, category filter
- NGO Cards: name, category, city, description, website, donate button
- Donation flow: Select NGO → Enter amount → Confirm → Wallet deducted
- Donation History with tabs: History, Reports, Analytics
- Reports: NGO-wise, District-wise, Category-wise
- Analytics: 4 Chart.js charts (by NGO, district, category, monthly)

### 5.11 Notification System
- In-app notification bell with unread count
- Notification dropdown (latest 5)
- Full notification center page
- Types: Welcome, Opportunity, Referral Submitted/Accepted/Rejected/Completed, Wallet Credit, System
- Email notifications via PHPMailer + Gmail SMTP
- HTML email templates with action buttons

### 5.12 Email System
- PHPMailer library
- Supports: Gmail, Mailtrap, Brevo
- TLS/SSL encryption
- Welcome emails, referral status emails, completion emails, withdrawal emails
- Customer consent emails with Approve/Decline buttons
- Configurable `APP_URL` for email links (works across devices on local network)

### 5.13 Activity Logs
- Every significant action logged with: user, module, action, entity, description, IP, user agent, timestamp
- Viewable by both Business and Referrer
- Covers: auth, profile, opportunities, referrals, payments, wallet, notifications

### 5.14 Reports (Business)
- 4 report types: Referrals, Commissions, Campaign Performance, Earnings Summary
- Filters: date range, campaign, product, status, referrer, search
- KPI cards: Revenue, Gross Commission, Platform Fees, Net Commission, Completed, Pending
- Export: CSV, Excel (XLSX), PDF
- Charts: Monthly Referral Trend, Monthly Commission, Campaign Performance, Status Distribution

### 5.15 Analytics
- **Business Analytics:** Active/Inactive opportunities, referral counts, Gross Commission, Platform Revenue, Net Rewards Paid, opportunity breakdown, recent referrals
- **Referrer Analytics:** Total/Accepted/Completed referrals, Gross Earnings, Platform Fees, Net Earnings, Wallet Balance, Donations, recent referrals
- Both use database views (`business_referral_summary`, `referrer_performance_summary`)

---

## 6. Database Schema

### Tables (18 total)
| Table | Purpose |
|-------|---------|
| users | Business & Referrer accounts |
| business_profiles | Business profile details |
| referrer_profiles | Referrer profile + bank + Razorpay contact/fund IDs |
| referral_opportunities | Campaign definitions |
| opportunity_products | Products per opportunity with commission % |
| customer_referrals | Referrals with approval workflow fields |
| referral_status_history | Audit trail for status changes |
| wallets | Referrer wallet balances |
| wallet_transactions | Credit/debit ledger |
| commission_payments | Razorpay payment records |
| platform_revenue | Platform fee revenue log |
| withdrawals | Payout records with Razorpay payout IDs |
| donations | Donation records |
| ngos | NGO directory (80 entries) |
| notifications | In-app + email notification log |
| activity_logs | System-wide audit trail |
| password_reset_tokens | Secure password reset |
| razorpay_webhook_events | Webhook idempotency |

### Views
| View | Purpose |
|------|---------|
| business_referral_summary | Pre-computed business KPIs |
| referrer_performance_summary | Pre-computed referrer KPIs |

---

## 7. API Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/razorpay_order.php` | POST | Creates Razorpay order for commission payment |
| `/api/razorpay_verify.php` | POST | Verifies payment signature, completes referral |
| `/api/withdrawal.php` | POST | Initiates Razorpay payout for referrer |
| `/api/razorpay_webhook.php` | POST | Handles Razorpay payout webhooks |
| `/api/customer_consent.php` | GET | Customer approval/decline (token-based) |
| `/api/ngos.php` | GET | NGO data endpoint |

---

## 8. Security Implementation

| Feature | Implementation |
|---------|---------------|
| Password Hashing | `password_hash()` with `PASSWORD_DEFAULT` (bcrypt) |
| Session Security | `httponly`, `samesite=Lax`, `secure` (HTTPS), `use_strict_mode`, `session_regenerate_id` on login |
| CSRF | Token per session, validated on every POST |
| SQL Injection | PDO prepared statements everywhere |
| XSS | `htmlspecialchars()` via `e()` helper on all output |
| Input Validation | Server-side validation for every form |
| File Uploads | MIME verification, size limits, random filenames |
| Razorpay Signature | HMAC-SHA256 verification |
| Webhook Security | Signature verification + event deduplication |
| Customer Consent | 64-char random token, 7-day expiry, one-time use |
| Contact Masking | Phone/email masked until explicit customer approval |

---

## 9. Configuration (.env)

| Variable | Purpose |
|----------|---------|
| DB_HOST, DB_NAME, DB_USER, DB_PASSWORD | MySQL connection |
| APP_BASE_URL | URL path prefix |
| APP_URL | Absolute URL for email links |
| EMAIL_PROVIDER | gmail / mailtrap / brevo |
| SMTP_HOST, SMTP_PORT, SMTP_ENCRYPTION | Mail server |
| SMTP_USERNAME, SMTP_PASSWORD | Mail auth |
| SMTP_FROM_EMAIL, SMTP_FROM_NAME | Sender identity |
| PAYMENT_MODE | demo / live |
| RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET | Razorpay API credentials |
| RAZORPAY_ACCOUNT_NUMBER | RazorpayX account for payouts |
| RAZORPAY_WEBHOOK_SECRET | Webhook signature verification |

---

## 10. Database Migrations

| Migration File | Changes |
|---------------|---------|
| `20260724_platform_service_fee.sql` | Platform fee columns + updated views |
| `20260724_ngo_directory.sql` | NGO table expansion + 27 seed records |
| `20260724_ngo_directory_expand.sql` | 53 additional NGOs (total 80) |
| `20260724_razorpay_payments.sql` | commission_payments, withdrawals tables |
| `20260724_razorpay_payouts.sql` | Payout columns, webhook events table |
| `20260725_payment_mode_platform_revenue.sql` | platform_revenue table |
| `20260726_secure_referral_approval.sql` | Referral approval workflow columns |

---

## 11. File Structure (Key Files)

### Backend Services (includes/)
- `functions.php` — Core helpers (url, redirect, csrf, session, roles)
- `customer_referrals.php` — Referral CRUD, status workflow, masking, consent
- `wallet.php` — Commission calculation, wallet operations, donations
- `referral_opportunities.php` — Opportunity CRUD, validation
- `razorpay_service.php` — Razorpay orders, payouts, webhooks, demo mode
- `notification_service.php` — Multi-channel notifications
- `email_service.php` — PHPMailer wrapper
- `reports.php` — Report queries, exports (CSV/XLSX/PDF)
- `activity_log_service.php` — Audit logging
- `ngos.php` — NGO directory queries
- `business_profile.php` — Business profile helpers
- `referrer_profile.php` — Referrer profile helpers

### Business Pages (business/)
- `dashboard.php` — KPIs, opportunity stats, referral stats
- `opportunities.php` — Campaign listing with search/filter
- `opportunity_form.php` — Create/edit campaigns
- `referrals.php` — Referral listing with search/filter/sort/pagination
- `referral_view.php` — Referral details + Razorpay payment + consent workflow
- `reports.php` — 4-tab reports with charts and exports
- `analytics.php` — Business analytics dashboard
- `profile.php` — Business profile wizard

### Referrer Pages (referrer/)
- `dashboard.php` — Wallet stats, quick actions, profile status
- `opportunities.php` — Browse active opportunities
- `referral_form.php` — Submit customer referral
- `referrals.php` — My referrals listing
- `referral_view.php` — Referral details with history
- `wallet.php` — Wallet stats + withdrawal (bank/UPI) + history
- `transactions.php` — Full transaction ledger with filters
- `reward_history.php` — Commission credit history
- `donate.php` — NGO directory with donation flow
- `donations.php` — Donation history, reports, analytics
- `analytics.php` — Referrer performance analytics
- `profile.php` — Referrer profile wizard

---

## 12. Frontend Features

- Responsive Bootstrap 5.3 UI
- Multi-step profile wizards with client-side validation
- AJAX form submissions with real-time feedback
- Chart.js visualizations (line, bar, doughnut, pie)
- Instant search (debounced)
- Toast notifications
- Modal dialogs (Razorpay payment, completion)
- PIN code auto-lookup (Indian postal API)
- Dynamic product rows (add/remove)
- File upload preview
- Notification bell with unread count

---

## 13. How to Run

```bash
# 1. Ensure MySQL is running with database 'referral_platform'
# 2. Run all migrations in database/migrations/ folder
# 3. Install dependencies
composer install

# 4. Configure .env (copy from .env.example)
cp .env.example .env
# Edit .env with your credentials

# 5. Start PHP development server
php -S 0.0.0.0:8000 -t .

# 6. Open in browser
# http://localhost:8000 (this machine)
# http://YOUR_IP:8000 (other devices on same network)
```

---

## 14. Complete User Workflow

### Business Workflow
1. Register → Complete profile wizard
2. Create referral opportunity with products
3. Referrers see the opportunity and submit referrals
4. Business reviews referral (masked contact details)
5. Business moves referral: Under Review → Processing
6. Business clicks "Request Contact Access"
7. Customer receives email → Approves/Declines
8. If approved: Business sees full contact details
9. Business contacts customer, makes sale
10. Business clicks "Pay Commission" → Razorpay Checkout → Payment verified
11. Platform fee deducted, net commission credited to referrer wallet
12. Referral marked Completed

### Referrer Workflow
1. Register → Complete profile wizard (including bank details)
2. Browse opportunities → Submit customer referral
3. Track referral status in dashboard
4. When referral is completed: commission appears in wallet
5. Withdraw via bank account or UPI (min ₹100)
6. Or donate to NGOs from wallet balance
7. View transaction history, rewards, analytics

---

## 15. Deployment Checklist

- [ ] Set `PAYMENT_MODE=live` in `.env`
- [ ] Add real Razorpay Key ID and Secret
- [ ] Add RazorpayX Account Number
- [ ] Configure webhook URL in Razorpay Dashboard: `https://yourdomain.com/api/razorpay_webhook.php`
- [ ] Set `APP_URL=https://yourdomain.com`
- [ ] Configure production SMTP credentials
- [ ] Set up SSL/TLS certificate
- [ ] Disable `display_errors` in PHP
- [ ] Set proper file permissions on `uploads/`
- [ ] Run all database migrations
- [ ] Test complete end-to-end workflow

---

## 16. Project Status: 100% Complete

All modules implemented and tested:
- ✅ Authentication & Authorization
- ✅ Business & Referrer Profile Wizards (with PIN code lookup)
- ✅ Opportunity Management
- ✅ Referral Submission & Lifecycle
- ✅ Secure Customer Consent Workflow
- ✅ Contact Masking & Unlocking
- ✅ Commission Calculation with 2% Platform Fee
- ✅ Razorpay Payment Integration (Demo + Live modes)
- ✅ Razorpay Payout Integration (Bank + UPI)
- ✅ Webhook Processing with Idempotency
- ✅ Wallet System (credit, withdrawal, donation)
- ✅ NGO Donation Directory (80 NGOs, search, filter, analytics)
- ✅ In-app + Email Notifications
- ✅ Reports with Export (CSV, XLSX, PDF)
- ✅ Analytics with Charts
- ✅ Activity Logs
- ✅ Password Reset
- ✅ Security (CSRF, XSS, SQLi, signature verification)

---

*Report generated: July 2026*

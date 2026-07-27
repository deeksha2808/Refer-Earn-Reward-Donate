# Refer • Earn • Reward & Donate

## ✅ Project Status: Completed

A full-stack referral management platform that securely connects **Businesses** and **Referrers** through a customer referral ecosystem. Businesses can publish referral opportunities, receive qualified referrals, request customer consent before accessing contact details, process commission payments, while referrers earn rewards, manage their wallet, withdraw earnings, and donate a portion of their income.

---

# Project Overview

Refer • Earn • Reward & Donate is a secure role-based web application designed to simplify referral management while preventing referral fraud through customer consent verification.

The platform provides two user roles:

- 🏢 Business
- 👤 Referrer

Businesses create referral opportunities, review referrals, securely obtain customer consent before viewing contact details, pay referral commissions, and analyze referral performance.

Referrers browse opportunities, submit referrals, track referral status, receive commissions, manage their wallet, request withdrawals, and donate part of their earnings to NGOs.

---

# Features

## Authentication & Security

- Secure Registration
- Secure Login
- Password Hashing
- Password Reset via Email
- Session Management
- Role-Based Authentication
- CSRF Protection
- Secure Input Validation

---

## Business Module

- Business Registration
- Business Profile Management
- Business Dashboard
- Create Referral Opportunities
- Edit/Delete Opportunities
- Review Customer Referrals
- Multi-stage Referral Workflow
- Customer Consent Request
- Secure Contact Access
- Commission Payment
- Reports & Analytics

---

## Referrer Module

- Referrer Registration
- Profile Management
- Browse Opportunities
- Submit Customer Referrals
- Referral Dashboard
- Referral Tracking
- Wallet Dashboard
- Donation Dashboard
- Withdrawal Requests
- Analytics

---

## Secure Referral Workflow

The platform prevents referral misuse using a customer approval mechanism.

### Referral Lifecycle

```
Submitted
      ↓
Under Review
      ↓
Processing
      ↓
Request Contact Access
      ↓
Waiting for Customer Approval
      ↓
Customer Approved
      ↓
Completed
      ↓
Commission Payment
      ↓
Wallet Credit
      ↓
Withdrawal / Donation
```

### Security Features

- Unique Referral ID Generation
- Masked Customer Contact Details
- Email-based Customer Consent
- Secure Approval Tokens
- Time-Limited Approval Links
- Fraud Prevention Workflow

---

## Wallet & Rewards

- Automatic Commission Credits
- Wallet Balance
- Transaction History
- Reward History
- Donation Tracking
- Withdrawal Requests
- Platform Service Fee Support

---

## Payment System

Integrated payment architecture supporting:

- Razorpay Payment Gateway
- Commission Payments
- Platform Fee Calculation
- Wallet Credits
- Withdrawal Processing
- Demo & Live Payment Modes

---

## NGO Donation System

- NGO Directory
- Donate Wallet Balance
- Donation History
- Donation Tracking

---

## Analytics & Reports

### Business Analytics

- Referral Statistics
- Conversion Reports
- Commission Reports
- Revenue Insights

### Referrer Analytics

- Referral Performance
- Earnings Summary
- Wallet Statistics
- Donation Reports

---

## Notifications

- Password Reset Emails
- Customer Consent Emails
- System Notifications
- Activity Logs

---

# Tech Stack

## Frontend

- HTML5
- CSS3
- JavaScript

## Backend

- PHP 8

## Database

- MySQL

## Payment Gateway

- Razorpay

## Email Service

- PHPMailer

## Version Control

- Git
- GitHub

## Development Environment

- Ubuntu 24.04
- Visual Studio Code
- PHP Built-in Server

---

# Project Modules

| Module | Status |
|---------|--------|
| Authentication & Role Management | ✅ |
| Business Profile Management | ✅ |
| Referrer Profile Management | ✅ |
| Referral Opportunity Management | ✅ |
| Customer Referral Submission | ✅ |
| Secure Customer Consent Workflow | ✅ |
| Wallet & Rewards System | ✅ |
| Razorpay Payment Integration | ✅ |
| Withdrawal System | ✅ |
| NGO Donation System | ✅ |
| Analytics & Reports | ✅ |
| Notifications | ✅ |
| Activity Logs | ✅ |
| Security Enhancements | ✅ |

---

# Project Structure

```
refer-earn-reward-donate/

├── api/
├── assets/
│   ├── css/
│   └── js/
├── auth/
├── business/
├── config/
├── dashboard/
├── database/
│   ├── migrations/
│   └── *.sql
├── includes/
├── referrer/
├── uploads/
├── index.php
├── README.md
└── .env.example
```

---

# Installation

## 1. Install Dependencies

```bash
sudo apt update

sudo apt install -y \
apache2 \
mysql-server \
php \
php-mysql \
php-cli \
php-mbstring \
curl
```

Verify installation

```bash
php -v

php -m | grep -E 'PDO|pdo_mysql|mbstring'
```

---

## 2. Clone Repository

```bash
git clone https://github.com/deeksha2808/Refer-Earn-Reward-Donate.git

cd Refer-Earn-Reward-Donate
```

Open in VS Code

```bash
code .
```

---

## 3. Configure MySQL

```bash
sudo systemctl enable --now mysql

sudo mysql_secure_installation
```

---

## 4. Create Database

```sql
CREATE DATABASE referral_platform;
```

Import the SQL files in the required order.

---

## 5. Configure Environment

```bash
cp .env.example .env
```

Update

```env
DB_HOST=127.0.0.1
DB_NAME=referral_platform
DB_USER=root
DB_PASSWORD=YOUR_PASSWORD
DB_CHARSET=utf8mb4

APP_URL=http://localhost:8000

PAYMENT_MODE=demo
```

---

## 6. Start Application

```bash
php -S localhost:8000
```

Open

```
http://localhost:8000
```

---

# Testing Checklist

## Authentication

- Business Registration
- Referrer Registration
- Login
- Logout
- Password Reset

## Business

- Profile Management
- Opportunity Management
- Referral Review
- Customer Consent
- Commission Payment

## Referrer

- Submit Referral
- Track Referral
- Wallet
- Withdraw Funds
- Donate Earnings

## Payments

- Razorpay Demo Payment
- Wallet Credit
- Withdrawal Flow

## Reports

- Business Reports
- Referrer Reports
- Analytics

---

# Security Features

- Password Hashing
- CSRF Protection
- Session Security
- Secure Referral Approval
- Email Verification Workflow
- Token-Based Customer Consent
- Masked Customer Information
- Fraud Prevention
- Activity Logging

---

# Future Improvements

- Mobile Responsive UI
- REST API
- Docker Support
- Cloud Deployment
- SMS Notifications
- Advanced Dashboard Charts
- Multi-language Support

---

# License

This project is developed for learning, portfolio, and educational purposes.

---

# Author

**Deeksha DS**  
**Darshan Poojary**

## GitHub

https://github.com/deeksha2808
---

⭐ If you found this project useful, consider giving it a star on GitHub!

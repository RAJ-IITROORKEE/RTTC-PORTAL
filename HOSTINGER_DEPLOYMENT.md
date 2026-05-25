# 🚀 Hostinger Deployment Guide - RTTC 2026 Portal

## Overview
This guide sets up **auto-deployment via SSH git pull** on Hostinger. Changes pushed to GitHub automatically update your live site.

---

## 📋 **Phase 1: Hostinger Initial Setup (One-Time)**

### Step 1: Connect via SSH

```bash
# Connect to Hostinger via SSH
ssh username@your-domain.com

# Go to your public_html directory
cd public_html
```

### Step 2: Clone the Repository

```bash
# Clone the RTTC portal repository
git clone https://github.com/RAJ-IITROORKEE/RTTC-PORTAL.git rttc-portal

# Go to project directory
cd rttc-portal
```

### Step 3: Set Up Directory Structure

```bash
# Create persistent directories (outside git, won't be affected by deployments)
mkdir -p ../rttc-data/storage/uploads/documents
mkdir -p ../rttc-data/storage/uploads/notices
mkdir -p ../rttc-data/storage/logs
mkdir -p ../rttc-data/vendor

# Create symlinks inside project to persistent directories
ln -s ../../rttc-data/storage storage
ln -s ../../rttc-data/vendor vendor

# Verify symlinks
ls -la | grep storage
ls -la | grep vendor
```

**Why symlinks?**
- `storage/` = Student documents, uploads, logs (persistent)
- `vendor/` = Composer dependencies (won't reinstall on each deploy)
- They survive every `git pull` deployment

---

## 🔐 **Phase 2: SSH Key Setup for Auto-Deployment**

### Step 1: Generate SSH Key on Hostinger

```bash
# Generate a new SSH key (one-time)
ssh-keygen -t ed25519 -f ~/.ssh/rttc-deploy -N ""

# View the public key
cat ~/.ssh/rttc-deploy.pub
```

### Step 2: Add to GitHub Deploy Keys

1. Go to: https://github.com/RAJ-IITROORKEE/RTTC-PORTAL/settings/keys
2. Click "Add deploy key"
3. Paste the content from `~/.ssh/rttc-deploy.pub`
4. **Enable "Allow write access"** (if you want to auto-commit)
5. Save

### Step 3: Test SSH Connection

```bash
# Test if SSH key works
ssh -i ~/.ssh/rttc-deploy -T git@github.com

# Expected output:
# Hi RAJ-IITROORKEE/RTTC-PORTAL! You've successfully authenticated, but GitHub does not provide shell access.
```

### Step 4: Configure Git to Use SSH Key

```bash
# In your project directory
cd ~/public_html/rttc-portal

# Configure git to use the SSH key
git config core.sshCommand "ssh -i ~/.ssh/rttc-deploy"

# Change remote to SSH (if still using HTTPS)
git remote set-url origin git@github.com:RAJ-IITROORKEE/RTTC-PORTAL.git

# Verify
git remote -v
```

---

## ⚙️ **Phase 3: Environment Configuration**

### Step 1: Create `.env` File

```bash
# Go to project directory
cd ~/public_html/rttc-portal

# Copy template
cp .env.example .env

# Edit with your Hostinger credentials
nano .env
```

### Step 2: Update `.env` for Live Environment

```env
# Application
APP_NAME="RTTC 2026 Registration Portal"
APP_ENV=production
APP_URL=https://your-live-domain.com/rttc-portal/

# Database (Update with your Hostinger MySQL credentials)
DB_HOST=localhost
DB_USERNAME=your_cpanel_username_db
DB_PASSWORD=your_db_password_here
DB_NAME=your_cpanel_username_rttc2026

# Email (Keep Gmail or update to your email service)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your_app_password
SMTP_FROM_EMAIL=your-email@gmail.com
SMTP_FROM_NAME="RTTC Admissions"

# Payment (Update with live Razorpay keys)
RAZORPAY_KEY_ID=your_live_razorpay_key
RAZORPAY_KEY_SECRET=your_live_razorpay_secret
RAZORPAY_AMOUNT=50000

# Session
SESSION_LIFETIME=3600

# Security
CSRF_TOKEN_NAME=csrf_token
PASSWORD_MIN_LENGTH=8

# OTP
OTP_EXPIRY=600

# File Upload
MAX_FILE_SIZE=5242880
ALLOWED_FILE_TYPES=jpg,jpeg,png,pdf
```

**Save:** `Ctrl+O` → `Enter` → `Ctrl+X`

### Step 3: Verify `.env` is Not in Git

```bash
# Check .env is in .gitignore
grep ".env" .gitignore

# Expected output: .env
```

✅ **Good!** `.env` will never be accidentally committed.

---

## 📦 **Phase 4: Install Dependencies**

```bash
# Go to project
cd ~/public_html/rttc-portal

# Install Composer dependencies
composer install --no-dev

# Verify vendor directory was created
ls -la vendor/
```

---

## 🗄️ **Phase 5: Database Setup**

```bash
# Import database schema
# Option 1: Via SSH MySQL client
mysql -u your_db_username -p your_db_name < database/final_updated.sql

# Enter password when prompted
```

Or use **phpMyAdmin** in Hostinger cPanel:
1. Go to cPanel → phpMyAdmin
2. Select your database
3. Click "Import" tab
4. Upload `database/final_updated.sql`
5. Click "Go"

---

## 🔄 **Phase 6: Auto-Deployment Setup**

### Option A: Manual Deploy (Simplest)

When you push changes to GitHub, SSH into Hostinger and run:

```bash
cd ~/public_html/rttc-portal

# Pull latest changes
git pull

# If you added/updated dependencies, reinstall
# composer install --no-dev

# Clear any caches (if applicable)
# Optional: php artisan cache:clear
```

### Option B: Webhook Auto-Deploy (Advanced)

Create a deployment script that GitHub triggers automatically:

**File:** `~/public_html/rttc-portal/deploy.php`

```php
<?php
/**
 * GitHub Webhook Auto-Deployment Script
 * 
 * Setup:
 * 1. Go to GitHub repo Settings → Webhooks
 * 2. Add webhook: https://your-domain.com/rttc-portal/deploy.php
 * 3. Select "Just the push event"
 * 4. Add secret: your-webhook-secret
 */

// Verify webhook signature
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$payload = file_get_contents('php://input');
$secret = 'your-webhook-secret'; // Change this

$hash = 'sha256=' . hash_hmac('sha256', $payload, $secret);

if (!hash_equals($hash, $signature)) {
    http_response_code(403);
    die('Signature mismatch');
}

// Log deployment
$log = fopen(__DIR__ . '/storage/logs/deploy.log', 'a');
fwrite($log, date('Y-m-d H:i:s') . " - Deployment triggered\n");

// Execute git pull
$output = shell_exec('cd ' . __DIR__ . ' && git pull 2>&1');
fwrite($log, "Git output: $output\n");

// Optional: reinstall dependencies
// shell_exec('cd ' . __DIR__ . ' && composer install --no-dev 2>&1');

fwrite($log, "Deployment completed\n\n");
fclose($log);

http_response_code(200);
echo 'Deployed successfully';
?>
```

**Setup in GitHub:**
1. Go to repo → Settings → Webhooks → Add webhook
2. Payload URL: `https://your-live-domain.com/rttc-portal/deploy.php`
3. Event: "Just the push event"
4. Add Secret: (generate a secure random string)
5. Click "Add webhook"

---

## 📂 **Directory Structure (Final)**

```
~/public_html/
├── rttc-portal/                    (Git repository)
│   ├── .git/                       (Version control)
│   ├── config/                     (Config files)
│   ├── helpers/                    (PHP helpers)
│   ├── storage → ../../rttc-data/storage  (Symlink - PERSISTENT)
│   ├── vendor → ../../rttc-data/vendor    (Symlink - PERSISTENT)
│   ├── .env                        (Not in git - PERSISTENT)
│   ├── .env.example                (Template)
│   ├── .gitignore
│   └── ... other files ...
│
└── rttc-data/                      (Persistent data, outside git)
    ├── storage/
    │   ├── uploads/documents/      (Student documents)
    │   ├── uploads/notices/        (Notice uploads)
    │   └── logs/                   (Application logs)
    └── vendor/                     (Composer packages)
```

**Key:** Symlinks allow git to be deployed fresh without losing data!

---

## ✅ **Deployment Workflow**

### Make Changes Locally
```bash
# On your local machine
git commit -m "feat: Add new feature"
git push origin main
```

### Deploy to Hostinger (Manual)
```bash
# SSH into Hostinger
ssh username@your-domain.com

# Go to project
cd ~/public_html/rttc-portal

# Pull latest changes
git pull

# Verify changes
git log --oneline -3
```

### Deploy with Webhook (Automatic)
Just push to GitHub → webhook auto-triggers → site updates automatically ✨

---

## 🔒 **Security Checklist**

- ✅ `.env` is NOT in git (add to .gitignore)
- ✅ SSH key is generated and added to GitHub
- ✅ Webhook secret is strong and unique
- ✅ Database password is strong
- ✅ `APP_ENV=production` in live `.env`
- ✅ Symlinks protect `storage/` and `vendor/`
- ✅ `.git/` directory is not web-accessible

---

## 🚨 **Troubleshooting**

### Git pull fails: "Permission denied"
```bash
# Recreate SSH key and add to GitHub deploy keys
ssh-keygen -t ed25519 -f ~/.ssh/rttc-deploy -N ""
cat ~/.ssh/rttc-deploy.pub  # Copy to GitHub
```

### `storage/` or `vendor/` not found
```bash
# Recreate symlinks
ln -s ../../rttc-data/storage storage
ln -s ../../rttc-data/vendor vendor
```

### Database connection error
```bash
# Check credentials in .env match cPanel MySQL
# Verify database exists in phpMyAdmin
# Check if MySQL is running: mysql -u root -p
```

### Email not sending
```bash
# Check SMTP credentials in .env
# Verify Gmail/email service allows app passwords
# Check logs: tail -f storage/logs/error.log
```

---

## 📞 **Support**

If you need help:
1. Check error logs: `storage/logs/error.log`
2. Verify `.env` file is correct
3. Test git pull manually: `git pull -v`
4. Check GitHub Deploy Key has been added

---

**You're ready for production! 🎉**

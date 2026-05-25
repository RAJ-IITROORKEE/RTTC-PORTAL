# 🚀 Hostinger GitHub Auto-Deploy (EASIEST METHOD)

## Overview
Hostinger has built-in GitHub integration that auto-deploys on every push. **No SSH keys needed!**

---

## ✅ **Step 1: Click "Connect with GitHub"**

1. In Hostinger control panel, go to **Deploy from GitHub**
2. Click **"Connect with GitHub"**
3. Authorize Hostinger to access your GitHub account
4. Select repository: **RAJ-IITROORKEE/RTTC-PORTAL**

---

## 📁 **Step 2: Configure Deployment Settings**

When you click "Connect with GitHub", you'll see:

```
Repository: RAJ-IITROORKEE/RTTC-PORTAL
Branch: main (✓ Select this)
Deploy to: /public_html/rttc-portal  (or your preferred path)
```

---

## 🔧 **Step 3: Post-Deployment Configuration**

After connecting, Hostinger will ask if you want to run commands after deployment.

Add this in the **Post-deployment hook** (if available):

```bash
# Create persistent directories
mkdir -p ../rttc-data/storage/uploads/documents
mkdir -p ../rttc-data/storage/uploads/notices
mkdir -p ../rttc-data/storage/logs
mkdir -p ../rttc-data/vendor

# Create symlinks
ln -sf ../../rttc-data/storage storage
ln -sf ../../rttc-data/vendor vendor

# Install Composer dependencies
composer install --no-dev --optimize-autoloader

# Set permissions
chmod -R 755 .
chmod -R 755 storage/
chmod 644 .env
```

---

## 📝 **Step 4: Set Up `.env` File**

### Option A: Manual Edit via FTP/File Manager (ONE-TIME)

1. In Hostinger cPanel, go to **File Manager**
2. Navigate to `/public_html/rttc-portal/`
3. Upload your `.env` file with Hostinger credentials

**Content of `.env`:**

```env
# Application
APP_NAME="RTTC 2026 Registration Portal"
APP_ENV=production
APP_URL=https://your-live-domain.com/rttc-portal/

# Database (From Hostinger cPanel)
DB_HOST=localhost
DB_USERNAME=your_cpanel_db_username
DB_PASSWORD=your_db_password
DB_NAME=your_cpanel_db_name

# Email (Gmail or your email service)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your_app_password
SMTP_FROM_EMAIL=your-email@gmail.com
SMTP_FROM_NAME="RTTC Admissions"

# Payment (Razorpay)
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

### Option B: Use SSH to Create `.env`

```bash
# SSH into Hostinger
ssh username@your-domain.com

# Go to project
cd public_html/rttc-portal

# Copy template
cp .env.example .env

# Edit with credentials
nano .env
# (Paste your content and save)
```

---

## 🗄️ **Step 5: Setup Database**

### Option A: Via phpMyAdmin (Easiest)

1. In Hostinger cPanel, open **phpMyAdmin**
2. Create a new database (if not exists)
3. Select the database
4. Click **Import** tab
5. Upload `database/final_updated.sql`
6. Click **Go**

### Option B: Via SSH

```bash
ssh username@your-domain.com
cd public_html/rttc-portal

mysql -u your_db_username -p your_db_name < database/final_updated.sql
# Enter password when prompted
```

---

## 🚀 **Step 6: Test Deployment**

### Make a Change Locally

```bash
# On your local machine
cd RTTC_2026
echo "# Test update" >> README.md
git add README.md
git commit -m "test: Test GitHub auto-deploy"
git push origin main
```

### Watch Hostinger Deploy Automatically

1. Go to Hostinger **Deploy from GitHub** section
2. You should see the deployment triggered automatically
3. Wait for it to complete (usually 1-2 minutes)
4. Visit your live site to verify changes

---

## 📂 **Directory Structure on Hostinger**

```
~/public_html/
├── rttc-portal/                 (Git repository - auto-updated)
│   ├── .git/
│   ├── config/
│   ├── helpers/
│   ├── storage → ../../rttc-data/storage  (Symlink - PERSISTENT)
│   ├── vendor → ../../rttc-data/vendor    (Symlink - PERSISTENT)
│   ├── .env                     (Manual upload - PERSISTENT)
│   ├── .gitignore
│   └── ... other files ...
│
└── rttc-data/                   (Persistent data outside git)
    ├── storage/
    │   ├── uploads/documents/
    │   ├── uploads/notices/
    │   └── logs/
    └── vendor/
```

---

## ✨ **How It Works**

### Automatic Deployment Flow

```
1. Push to GitHub
   git push origin main
           ↓
2. Hostinger receives webhook notification
           ↓
3. Auto-triggers git pull in /public_html/rttc-portal/
           ↓
4. Post-deployment scripts run:
   - Create symlinks (no effect if exist)
   - Install dependencies
   - Set permissions
           ↓
5. Site updates automatically ✓
```

---

## 🔄 **Making Updates (Simple!)**

### For Every New Feature/Fix:

```bash
# Local machine
git commit -m "feature: Add new feature"
git push origin main

# That's it! Hostinger auto-deploys
```

No manual SSH, no typing commands on server. Just push → auto-deploy! 🎉

---

## 🔒 **Important: Persistent Files**

These files **survive every deployment** (not affected by git pulls):

✅ `.env` - Environment variables
✅ `storage/` - Student documents, uploads, logs
✅ `vendor/` - Composer packages
✅ Database - Hosted separately

**Why?** Symlinks point to `../rttc-data/` which is outside the git repository.

---

## 🚨 **If Deployment Fails**

### Check Hostinger Deployment Log

1. Go to **Deploy from GitHub**
2. Look for deployment status/logs
3. Common errors:
   - Missing `.env` → Create it manually
   - Permission denied → Contact Hostinger support
   - Database error → Check DB credentials in `.env`

### Manual Fix via SSH

```bash
ssh username@your-domain.com
cd public_html/rttc-portal

# Check git status
git status

# Check if symlinks exist
ls -la | grep storage
ls -la | grep vendor

# Manually pull if needed
git pull
```

---

## 📞 **Support Contacts**

- **Hostinger Issues:** Hostinger Live Chat
- **GitHub Issues:** GitHub Help
- **Database:** phpMyAdmin in cPanel

---

## ✅ **Checklist Before Going Live**

- [ ] GitHub repository is public or Hostinger has access
- [ ] `.env` file uploaded with correct credentials
- [ ] Database imported successfully
- [ ] Symlinks created for `storage/` and `vendor/`
- [ ] Test deployment from GitHub works
- [ ] Email sending works (test signup/password reset)
- [ ] File uploads work (test document upload)
- [ ] Domain SSL certificate is valid
- [ ] `APP_URL` in `.env` matches your live domain
- [ ] `APP_ENV=production` in `.env`

---

**You're ready for automatic deployments! 🚀**

Every push to GitHub will now automatically update your live site!

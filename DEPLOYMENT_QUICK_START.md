# 🎯 HOSTINGER DEPLOYMENT - QUICK SUMMARY

## The EASIEST Method: Use Hostinger's Built-in GitHub Deploy

You saw the "Deploy from GitHub" button in Hostinger? **That's exactly what you need!**

---

## 🚀 **5-Minute Setup**

### Step 1: Click "Connect with GitHub" (In Hostinger)
✅ Authorize your GitHub account
✅ Select repository: `RAJ-IITROORKEE/RTTC-PORTAL`
✅ Select branch: `main`

### Step 2: Upload `.env` File (One-time)
Use Hostinger **File Manager** or **FTP**:
- Path: `/public_html/rttc-portal/.env`
- Content: Your database + email credentials

### Step 3: Import Database (One-time)
Use Hostinger **phpMyAdmin**:
- Upload `database/final_updated.sql`
- Click Import

### Step 4: Done! ✓

---

## 🔄 **How to Deploy Changes**

### Before (What you do locally):
```bash
git commit -m "Your changes"
git push origin main
```

### After (Automatic on Hostinger):
✅ Hostinger detects push
✅ Auto-runs `git pull` on server
✅ Site updates automatically
✅ No manual commands needed!

---

## 💾 **What Stays Safe (Persistent)**

These files **DON'T** get overwritten on every deployment:

```
✅ .env                    (Your credentials)
✅ storage/                (Student documents, uploads)
✅ vendor/                 (Composer packages)
✅ Database                (Separate from git)
```

**How?** Using **symlinks** (shortcuts to outside git folder)

---

## 📋 **Your `.env` File Template**

Get these values from **Hostinger cPanel**:

```env
APP_NAME="RTTC 2026 Registration Portal"
APP_ENV=production
APP_URL=https://your-domain.com/rttc-portal/

# Database - From cPanel "MySQL Databases"
DB_HOST=localhost
DB_USERNAME=<your_cpanel_username>_db_username
DB_PASSWORD=<your_db_password>
DB_NAME=<your_cpanel_username>_rttc2026

# Email - Use Gmail or your email
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your_app_password
SMTP_FROM_EMAIL=your-email@gmail.com
SMTP_FROM_NAME="RTTC Admissions"

# Razorpay - Update with live keys
RAZORPAY_KEY_ID=your_live_key
RAZORPAY_KEY_SECRET=your_live_secret
RAZORPAY_AMOUNT=50000

# Keep as-is
SESSION_LIFETIME=3600
CSRF_TOKEN_NAME=csrf_token
PASSWORD_MIN_LENGTH=8
OTP_EXPIRY=600
MAX_FILE_SIZE=5242880
ALLOWED_FILE_TYPES=jpg,jpeg,png,pdf
```

---

## 🎯 **Step-by-Step Process**

### ONE-TIME SETUP:

```
1. In Hostinger: Click "Deploy from GitHub"
   ↓
2. Click "Connect with GitHub"
   ↓
3. Select: RAJ-IITROORKEE/RTTC-PORTAL, branch: main
   ↓
4. Upload .env file (File Manager)
   ↓
5. Import database (phpMyAdmin)
   ↓
6. Test the site
   ↓
DONE! ✓
```

### FOR EVERY NEW UPDATE:

```
Local Machine:
  git commit -m "Feature X"
  git push origin main
        ↓
Hostinger automatically:
  - Pulls latest code
  - Keeps .env safe
  - Keeps storage/ safe
  - Keeps database safe
  - Site updates ✓
```

---

## ✅ **Verification Checklist**

- [ ] GitHub account connected to Hostinger
- [ ] Repository selected correctly
- [ ] `.env` file uploaded with correct DB credentials
- [ ] Database imported successfully
- [ ] Test signup form (verify email works)
- [ ] Test file upload (verify documents save)
- [ ] Visit live domain - site is online

---

## 🔧 **If Something Goes Wrong**

### Deployment didn't trigger?
- Check Hostinger deployment logs
- Try re-clicking "Deploy" button manually

### Site shows error?
- Check `.env` file exists and credentials are correct
- Check database was imported
- Contact Hostinger support

### Need to debug?
Use **Hostinger File Manager** to:
- Check `.env` exists
- Check `storage/` folder exists
- Check `vendor/` folder exists

---

## 🎉 **Result**

After setup:
- ✅ Every push to GitHub = automatic update on live site
- ✅ No manual SSH commands
- ✅ No credentials exposed in git
- ✅ Documents/uploads never lost
- ✅ Database stays safe
- ✅ One-click deployment ready!

---

## 📞 **Need Help?**

Detailed guides available:
- `HOSTINGER_GITHUB_DEPLOY.md` (Full guide - this method)
- `HOSTINGER_DEPLOYMENT.md` (Advanced SSH method - not recommended)

---

**You're ready for production! 🚀**

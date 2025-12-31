# GitHub Repository Setup Instructions

## Quick Setup Guide

Since GitHub CLI is not installed, follow these manual steps to create the repository and push your code:

### Step 1: Create GitHub Repository

1. Go to https://github.com/new
2. **Repository name:** `oswp-secure-login`
3. **Description:** Advanced WordPress security plugin with custom login URL, OTP authentication, and SMTP settings
4. **Visibility:** Public (required for auto-updates)
5. **DO NOT** initialize with README, .gitignore, or license (we already have these)
6. Click **"Create repository"**

### Step 2: Link Local Repository to GitHub

After creating the repository on GitHub, run these commands in your terminal:

```bash
cd /c/xampp/htdocs/wordpress/wp-content/plugins/oswp-secure-login

# Add the remote repository (replace 'onliveserver' with your GitHub username)
git remote add origin https://github.com/onliveserver/oswp-secure-login.git

# Rename branch to 'main' if needed
git branch -M main

# Push to GitHub
git push -u origin main
```

### Step 3: Create First Release (v2.0.0)

1. Go to your repository: https://github.com/onliveserver/oswp-secure-login
2. Click on **"Releases"** (right sidebar)
3. Click **"Create a new release"**
4. **Tag version:** `v2.0.0`
5. **Release title:** `OSWP Secure Login v2.0.0 - GitHub Auto-Update Support`
6. **Description:** Copy from CHANGELOG.md or use this:

```markdown
## OSWP Secure Login v2.0.0

### 🚀 New Features
- **GitHub Auto-Update System**: Plugin now supports self-hosted updates from GitHub
- Automatic update notifications in WordPress admin
- One-click update installation from GitHub releases
- Version comparison and changelog display

### 🔒 Security Features
- Custom Login URL protection
- Two-Factor Authentication (OTP via Email)
- SMTP Configuration for reliable email delivery
- IP Blocking System
- Session-based OTP security

### 📦 Installation
1. Download `oswp-secure-login.zip` below
2. Upload to WordPress via Plugins > Add New > Upload Plugin
3. Activate and configure in Secure Login menu

### ✨ What's Changed
- Added GitHub updater class for automatic version checking
- Plugin structure optimized for GitHub hosting
- Enhanced security with version verification
- Compatible with WordPress 5.0 to 6.7+
- Compatible with PHP 7.2 to 8.2+

**Full Changelog**: https://github.com/onliveserver/oswp-secure-login/blob/main/CHANGELOG.md
```

7. **IMPORTANT:** Before publishing, you need to create a ZIP file of the plugin

### Step 4: Create Release ZIP File

Run this command to create a release package:

```bash
cd /c/xampp/htdocs/wordpress/wp-content/plugins

# Create ZIP file (excluding git files)
zip -r oswp-secure-login.zip oswp-secure-login -x "*.git*" "oswp-secure-login/.git/*"
```

Or use this PowerShell command on Windows:

```powershell
cd C:\xampp\htdocs\wordpress\wp-content\plugins
Compress-Archive -Path oswp-secure-login\* -DestinationPath oswp-secure-login.zip -Force
```

8. Upload `oswp-secure-login.zip` as a release asset when creating the release
9. Click **"Publish release"**

### Step 5: Test Auto-Update

1. Install the plugin on a WordPress site
2. Go to Plugins page in WordPress admin
3. The plugin should check GitHub for updates
4. When you create a new release, update notification will appear

---

## Alternative: Use GitHub CLI (Recommended)

If you want to install GitHub CLI for easier management:

### Windows Installation:
```bash
winget install --id GitHub.cli
```

Or download from: https://cli.github.com/

### After Installing gh CLI:

```bash
cd /c/xampp/htdocs/wordpress/wp-content/plugins/oswp-secure-login

# Authenticate with GitHub
gh auth login

# Create repository
gh repo create onliveserver/oswp-secure-login --public --description "Advanced WordPress security plugin with custom login URL, OTP authentication, and SMTP settings" --source=. --remote=origin --push

# Create release with ZIP
gh release create v2.0.0 --title "OSWP Secure Login v2.0.0" --notes-file CHANGELOG.md
```

---

## Verification Checklist

- [ ] Repository created on GitHub
- [ ] Code pushed to main branch
- [ ] Release v2.0.0 created
- [ ] ZIP file attached to release
- [ ] Plugin installed and tested
- [ ] Auto-update working

---

## Repository URL

Your repository will be at: **https://github.com/onliveserver/oswp-secure-login**

---

## Support

For issues or questions:
- GitHub Issues: https://github.com/onliveserver/oswp-secure-login/issues
- Email: onliveserver@gmail.com
- Website: https://onliveserver.com

---

**Next Steps:**
1. Follow Step 1-3 above to create the GitHub repository
2. Push your code
3. Create the first release
4. Test the auto-update feature!

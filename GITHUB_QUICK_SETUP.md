# Quick GitHub Setup - OSWP Secure Login

## Prerequisites
You need to create a Personal Access Token to authenticate with GitHub.

### Get Your GitHub Personal Access Token

1. Go to: https://github.com/settings/tokens/new
2. **Token name**: `oswp-secure-login-setup`
3. **Expiration**: Select appropriate expiration (e.g., 90 days)
4. **Scopes**: Select only `repo` (Full control of private repositories)
5. Click **"Generate token"**
6. **Copy the token** (you won't see it again)

### Create Repository on GitHub

1. Go to: https://github.com/new
2. **Repository name**: `oswp-secure-login`
3. **Description**: `Advanced WordPress security plugin with custom login URL, OTP authentication, and SMTP settings. Self-hosted from GitHub with auto-update support.`
4. **Visibility**: Select **Public**
5. **Initialize repository**: Leave unchecked (Don't add README, gitignore, or license)
6. Click **"Create repository"**

### Push Code to GitHub

After creating the repository and getting your Personal Access Token:

```bash
cd C:\xampp\htdocs\wordpress\wp-content\plugins\oswp-secure-login

# Configure git with email (if not already done)
git config user.email "onliveserver@gmail.com"
git config user.name "Onlive Server"

# Add remote origin (if not already done)
git remote add origin https://github.com/onliveserver/oswp-secure-login.git

# Rename branch to main (if not already done)
git branch -M main

# Push to GitHub
git push -u origin main
```

When prompted for username/password:
- **Username**: Your GitHub username
- **Password**: Paste your Personal Access Token (not your password!)

### After Successful Push

Check your repository at: https://github.com/onliveserver/oswp-secure-login

You should see all files including:
- README.md
- CHANGELOG.md
- oswp-secure-login.php
- admin/
- assets/
- includes/

### Create Release v2.0.0

1. Go to: https://github.com/onliveserver/oswp-secure-login/releases/new
2. **Tag version**: `v2.0.0`
3. **Release title**: `OSWP Secure Login v2.0.0 - GitHub Self-Hosted with Auto-Updates`
4. **Description**: Use the CHANGELOG.md content
5. Click **"Publish release"**

### Optional: Create ZIP Release Asset

For easier downloads, create a ZIP file:

**PowerShell:**
```powershell
$source = "C:\xampp\htdocs\wordpress\wp-content\plugins\oswp-secure-login"
$dest = "C:\xampp\htdocs\wordpress\wp-content\plugins\oswp-secure-login.zip"
Get-ChildItem -Path $source -Exclude '.git*' | 
    Compress-Archive -DestinationPath $dest -Force
```

Then attach to the release.

---

## Troubleshooting

### Error: "fatal: remote origin already exists"
```bash
git remote remove origin
git remote add origin https://github.com/onliveserver/oswp-secure-login.git
```

### Error: "Authentication failed"
- Verify your Personal Access Token has `repo` scope
- Token may have expired (get a new one)
- Check your GitHub username is correct

### Error: "Repository not found"
- Make sure you created the repository on GitHub first
- Check the repository is public
- Verify the URL is correct

---

## Testing Auto-Updates

1. Install the plugin on a WordPress site
2. Go to **Plugins** in WordPress admin
3. Find OSWP Secure Login in the list
4. Plugin should check GitHub for updates automatically
5. When a new release is created, update notification will appear

---

**Repository**: https://github.com/onliveserver/oswp-secure-login
**Email**: onliveserver@gmail.com
**Website**: https://onliveserver.com

# OSWP Secure Login - WordPress Security Plugin

A powerful WordPress security plugin with custom login URL, OTP authentication, SMTP configuration, and IP blocking capabilities.

## Features

### 1. Custom Login URL
- Hide the default WordPress login pages (`/wp-admin` and `/wp-login.php`)
- Auto-generates a unique login URL on installation
- Shows 404 page for unauthorized access attempts
- Prevents brute force attacks

### 2. Two-Factor Authentication (OTP)
- Email-based 6-digit OTP verification
- Configurable maximum attempts (default: 3)
- OTP resend functionality with limits (default: 2 resends)
- Auto-expires after 5 minutes
- Session-based storage for security
- IP blocking after exceeded attempts

### 3. SMTP Configuration
- Full SMTP setup for reliable email delivery
- One-click test email with debug logging
- Support for TLS/SSL encryption
- Essential for OTP functionality

### 4. IP Blocking System
- Automatic IP blocking after failed OTP attempts
- Configurable block duration (default: 1 hour)
- Admin interface to view and manage blocked IPs
- Manual unblock capability

## Installation

1. Upload the `oswp-secure-login` folder to `/wp-content/plugins/`
2. Activate the plugin
3. Go to **Secure Login** in the admin menu
4. Configure your settings in each tab

## Configuration

### Custom Login URL

1. Go to **Secure Login** > **Custom Login URL** tab
2. Enable the feature
3. Set your preferred login slug
4. Your new login URL: `https://yoursite.com/your-slug/`
5. **Important**: Accessing `/wp-admin` or `/wp-login.php` will show 404 page

### OTP Authentication

Settings available:
- **Enable OTP**: Toggle OTP requirement
- **Maximum Attempts**: Wrong OTP attempts before blocking (default: 3)
- **Maximum Resends**: How many times user can resend OTP (default: 2)
- **Block Duration**: Time to block IP in seconds (default: 3600 = 1 hour)
- **Enable IP Blocking**: Block IPs that exceed maximum attempts

### SMTP Settings

Configure your SMTP provider:
- Host, Port, Encryption (TLS/SSL)
- Username and Password
- From Email and From Name
- Test email with debug logging option

### Blocked IPs Management

Access **Secure Login** > **Blocked IPs** to:
- View all blocked IP addresses
- See block expiration times
- Manually unblock IPs
- Monitor security threats

## How It Works

### Login Flow with OTP

1. User enters username and password
2. If credentials are correct, 6-digit OTP is sent to email
3. User enters OTP (with resend option)
4. After 3 wrong attempts, IP is blocked for 1 hour
5. User can resend OTP up to 2 times (3 total sends)

### Security Features

- Session-based OTP storage (not cookies)
- IP address tracking and blocking
- Email address masking for privacy
- Automatic cleanup of expired blocks
- 404 responses for unauthorized login attempts

## Plugin Structure

```
oswp-secure-login/
├── oswp-secure-login.php          # Main plugin file
├── admin/
│   └── class-admin-settings.php   # WordPress Settings API
├── includes/
│   ├── class-custom-login-url.php # Custom login URL handler
│   ├── class-otp-auth.php         # OTP with IP blocking
│   └── class-smtp-settings.php    # SMTP configuration
├── assets/
│   ├── css/admin.css
│   └── js/admin.js
└── README.md
```

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- SSL certificate (recommended)

## Changelog

### Version 2.0.1
- Fixed redirect issue - now shows 404 instead of auto-redirect
- Fixed undefined variable warnings in wp-login.php
- Added OTP resend functionality with limits
- Added configurable OTP attempt limits
- Added IP blocking system
- Added blocked IPs management page
- Removed emojis, using dashicons only
- Implemented WordPress Settings API
- Added tabbed interface for settings
- Enhanced SMTP testing with debug logs

### Version 2.0.0
- Complete OOP rewrite
- Added custom login URL
- Added OTP authentication
- Added SMTP configuration

## Support

For support, contact Onlive Server Dev Team.

## License

GPL2


## Configuration

### Custom Login URL Setup

1. Go to **Secure Login** > **Custom Login URL**
2. Toggle the switch to enable
3. Set your preferred login slug (default is auto-generated)
4. **Important:** Save the URL! You'll need it to access your site
5. Your new login URL will be: `https://yoursite.com/your-slug/`

### OTP Authentication Setup

1. Go to **Secure Login** > **Two-Factor Authentication**
2. Toggle the switch to enable OTP
3. (Recommended) Configure SMTP settings first for reliable email delivery
4. Save settings

**How it works:**
- User enters username and password
- If credentials are correct, a 6-digit OTP is sent to their email
- User enters OTP to complete login
- OTP expires after 5 minutes

### SMTP Configuration

1. Go to **Secure Login** > **SMTP Email Settings**
2. Toggle the switch to enable SMTP
3. Enter your SMTP details:
   - **SMTP Host:** e.g., `smtp.gmail.com`
   - **SMTP Port:** 587 (TLS) or 465 (SSL)
   - **Encryption:** TLS or SSL
   - **Username:** Your email address
   - **Password:** Your email password or app password
   - **From Email:** Email address for sending emails
   - **From Name:** Name to display in emails
4. Click **Send Test Email** to verify configuration
5. Save settings

### Common SMTP Settings

**Gmail:**
- Host: `smtp.gmail.com`
- Port: 587
- Encryption: TLS
- Note: Use [App Password](https://support.google.com/accounts/answer/185833)

**Outlook/Office365:**
- Host: `smtp.office365.com`
- Port: 587
- Encryption: TLS

**SendGrid:**
- Host: `smtp.sendgrid.net`
- Port: 587
- Encryption: TLS
- Username: `apikey`
- Password: Your SendGrid API key

## Plugin Structure

```
oswp-secure-login/
├── oswp-secure-login.php          # Main plugin file
├── admin/
│   └── class-admin-settings.php   # Admin interface
├── includes/
│   ├── class-custom-login-url.php # Custom login URL handler
│   ├── class-otp-auth.php         # OTP authentication
│   └── class-smtp-settings.php    # SMTP configuration
├── assets/
│   ├── css/
│   │   └── admin.css              # Admin styles
│   └── js/
│       └── admin.js               # Admin JavaScript
└── README.md                      # This file
```

## How It Works

### Object-Oriented Architecture

The plugin uses a clean OOP structure with separate classes for each feature:

- **OSWP_Secure_Login**: Main plugin class (singleton pattern)
- **OSWP_Custom_Login_URL**: Handles custom login URL functionality
- **OSWP_OTP_Auth**: Manages OTP generation, sending, and verification
- **OSWP_SMTP_Settings**: Configures SMTP for email delivery
- **OSWP_Admin_Settings**: Renders admin interface and handles settings

### Security Features

- Nonce verification on all forms
- Sanitization of all user inputs
- Session-based OTP storage (more secure than cookies)
- Email masking for privacy
- AJAX for SMTP testing
- Automatic session cleanup

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- SSL certificate (recommended for security)

## Frequently Asked Questions

**Q: What happens if I forget my custom login URL?**
A: You can disable the plugin via FTP or reset the URL in the database. The option name is `oswp_custom_login_slug`.

**Q: Can I use this with other security plugins?**
A: Yes, but avoid plugins with similar features (like custom login URLs) to prevent conflicts.

**Q: Is SMTP required?**
A: Not required, but highly recommended for OTP functionality. Without SMTP, WordPress will use the default PHP mail function, which may not be reliable.

**Q: Will OTP work without SMTP?**
A: Yes, but email delivery may be unreliable. SMTP is recommended for best results.

**Q: Can I customize the OTP email template?**
A: Yes! Edit the `send_otp_email()` method in `class-otp-auth.php`.

## Support

For issues, questions, or feature requests, please contact the Onlive Server Dev Team.

## Changelog

### Version 2.0.0
- Complete rewrite with OOP architecture
- Added custom login URL feature
- Added OTP authentication
- Added SMTP configuration
- Modern admin interface with toggle switches
- AJAX SMTP testing
- Session-based OTP storage
- Improved security and code organization

### Version 1.0.0
- Initial release with basic OTP functionality

## License

This plugin is licensed under GPL2. You are free to use, modify, and distribute it.

## Credits

Developed by **Onlive Server Dev Team**

---

**⚠️ Security Tips:**
- Always use HTTPS on your WordPress site
- Use strong, unique passwords
- Keep WordPress and all plugins updated
- Regularly backup your site
- Save your custom login URL in a secure location

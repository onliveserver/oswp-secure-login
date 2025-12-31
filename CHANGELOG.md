# Changelog

All notable changes to OSWP Secure Login will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2025-12-31

### Added
- **GitHub Auto-Update System**: Plugin now supports self-hosted updates from GitHub
- Custom GitHub updater class for automatic version checking
- GitHub repository integration with release management
- Automatic update notifications in WordPress admin
- One-click update installation from GitHub releases
- Version comparison and changelog display
- GitHub links in plugin meta information

### Enhanced
- Plugin structure optimized for GitHub hosting
- Added proper .gitignore for WordPress projects
- Improved plugin documentation
- Added GitHub-specific plugin headers

### Changed
- Plugin now hosted on GitHub for easier maintenance
- Update system decoupled from WordPress.org repository
- Enhanced security with version verification

### Technical
- Added `class-github-updater.php` for update management
- Integrated GitHub API for release checking
- Implemented transient caching for API calls (12 hours)
- Added support for GitHub release assets and zipball downloads
- Plugin tested with WordPress 5.0 to 6.7+
- Compatible with PHP 7.2 to 8.2+

## [1.0.0] - Initial Release

### Features
- Custom Login URL protection
- Two-Factor Authentication (OTP via Email)
- SMTP Configuration for reliable email delivery
- IP Blocking System
- Admin settings interface
- Session-based OTP security
- Automatic IP blocking after failed attempts
- Manual IP unblock capability

### Security
- Hide default WordPress login pages
- 404 responses for unauthorized access
- Brute force attack prevention
- Email-based OTP verification
- Configurable attempt limits
- Time-based IP blocking

---

## Version History

- **2.0.0** - GitHub self-hosting and auto-update system
- **1.0.0** - Initial release with core security features

---

## Upgrade Notes

### From 1.0.0 to 2.0.0
- Automatic updates now available from GitHub
- No configuration changes required
- All existing settings and data preserved
- Update available through WordPress admin interface
- Manual update also supported via GitHub releases

---

## Links
- [GitHub Repository](https://github.com/onliveserver/oswp-secure-login)
- [Latest Release](https://github.com/onliveserver/oswp-secure-login/releases/latest)
- [Report Issues](https://github.com/onliveserver/oswp-secure-login/issues)
- [Website](https://onliveserver.com)

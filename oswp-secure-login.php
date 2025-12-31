<?php
/**
 * Plugin Name: OSWP Secure Login
 * Plugin URI: https://onliveserver.com
 * Description: Advanced WordPress security plugin with custom login URL, OTP authentication, and SMTP settings. Simple, powerful, and easy to use.
 * Version: 2.0.0
 * Author: Onlive Server Dev Team
 * Author URI: https://onliveserver.com
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: oswp-secure-login
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * GitHub Plugin URI: onliveserver/oswp-secure-login
 * GitHub Branch: main
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('OSWP_SECURE_LOGIN_VERSION', '2.0.0');
define('OSWP_SECURE_LOGIN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OSWP_SECURE_LOGIN_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main Plugin Class
 */
class OSWP_Secure_Login {
    
    private static $instance = null;
    
    private $custom_login;
    private $otp_auth;
    private $smtp_settings;
    private $admin_settings;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_components();
        $this->setup_hooks();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once OSWP_SECURE_LOGIN_PLUGIN_DIR . 'includes/class-custom-login-url.php';
        require_once OSWP_SECURE_LOGIN_PLUGIN_DIR . 'includes/class-otp-auth.php';
        require_once OSWP_SECURE_LOGIN_PLUGIN_DIR . 'includes/class-smtp-settings.php';
        require_once OSWP_SECURE_LOGIN_PLUGIN_DIR . 'includes/class-github-updater.php';
        require_once OSWP_SECURE_LOGIN_PLUGIN_DIR . 'admin/class-admin-settings.php';
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        $this->custom_login = new OSWP_Custom_Login_URL();
        $this->otp_auth = new OSWP_OTP_Auth();
        $this->smtp_settings = new OSWP_SMTP_Settings();
        $this->admin_settings = new OSWP_Admin_Settings(
            $this->custom_login,
            $this->otp_auth,
            $this->smtp_settings
        );
    }
    
    /**
     * Setup WordPress hooks
     */
    private function setup_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        global $wpdb;
        
        // Set default settings on first activation
        if (!get_option('oswp_settings_initialized')) {
            // Custom Login URL Defaults
            $default_slug = OSWP_Custom_Login_URL::generate_default_slug();
            update_option('oswp_custom_login_slug', $default_slug);
            update_option('oswp_custom_login_enabled', '1');
            
            // OTP Defaults
            update_option('oswp_otp_enabled', '1');
            update_option('oswp_otp_max_attempts', '3');
            update_option('oswp_otp_max_resends', '2');
            update_option('oswp_otp_block_duration', '3600');
            update_option('oswp_otp_ip_blocking_enabled', '1');
            
            // SMTP Defaults
            update_option('oswp_smtp_enabled', '0');
            update_option('oswp_smtp_port', '587');
            update_option('oswp_smtp_encryption', 'tls');
            update_option('oswp_smtp_from_email', get_option('admin_email'));
            update_option('oswp_smtp_from_name', get_bloginfo('name'));
            
            // Initialize blocked IPs array
            update_option('oswp_blocked_ips', array());
            
            // Mark as initialized
            update_option('oswp_settings_initialized', '1');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up session data
        if (session_id()) {
            session_destroy();
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'oswp-secure-login',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
    
    /**
     * Get custom login instance
     */
    public function get_custom_login() {
        return $this->custom_login;
    }
    
    /**
     * Get OTP auth instance
     */
    public function get_otp_auth() {
        return $this->otp_auth;
    }
    
    /**
     * Get SMTP settings instance
     */
    public function get_smtp_settings() {
        return $this->smtp_settings;
    }
}

/**
 * Initialize the plugin
 */
function oswp_secure_login_init() {
    return OSWP_Secure_Login::get_instance();
}

// Start the plugin
oswp_secure_login_init();

// Initialize GitHub updater
if (is_admin()) {
    new OSWP_GitHub_Updater(__FILE__);
}

<?php
/**
 * SMTP Settings Handler
 * 
 * Handles SMTP configuration for email sending
 * Compatible with WP Mail SMTP, Mailgun, SendGrid and other SMTP plugins
 */

if (!defined('ABSPATH')) {
    exit;
}

class OSWP_SMTP_Settings {
    
    public function __construct() {
        $this->init();
    }
    
    /**
     * Initialize hooks
     */
    private function init() {
        // Only apply our SMTP if enabled AND no other SMTP plugin is active
        add_action('phpmailer_init', array($this, 'configure_smtp'), 20); // Priority 20 so other plugins run first
        add_action('wp_ajax_oswp_test_smtp', array($this, 'test_smtp_connection'));
        add_action('wp_ajax_oswp_flush_rewrite', array($this, 'flush_rewrite_rules_ajax'));
    }
    
    /**
     * Check if other SMTP plugins are active
     */
    private function is_other_smtp_plugin_active() {
        $active_plugins = get_option('active_plugins', array());
        
        // List of known SMTP plugins
        $smtp_plugins = array(
            'wp-mail-smtp/wp_mail_smtp.php',
            'mailgun/mailgun.php',
            'sendgrid-email-delivery-simplified/sendgrid.php',
            'brevo/brevo.php', // Sendinblue
            'postman-smtp/postman-smtp.php',
            'easy-wp-smtp/easy-wp-smtp.php',
            'amazon-ses-wp-mail/amazon-ses-wp-mail.php',
            'mailerlite/mailerlite.php',
        );
        
        foreach ($smtp_plugins as $plugin) {
            if (in_array($plugin, $active_plugins)) {
                return true;
            }
        }
        
        // Check if WordPress SMTP constants are set
        if (defined('SMTP') && SMTP) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Flush rewrite rules via AJAX
     */
    public function flush_rewrite_rules_ajax() {
        check_ajax_referer('oswp_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized access'));
        }
        
        flush_rewrite_rules();
        wp_send_json_success(array('message' => 'Rewrite rules flushed successfully'));
    }
    
    /**
     * Check if SMTP is enabled
     */
    public function is_enabled() {
        $value = get_option('oswp_smtp_enabled', '0');
        return $value === '1' || $value === 1 || $value === true;
    }
    
    /**
     * Configure PHPMailer with SMTP settings
     * Only applies if enabled and no other SMTP plugin is active
     */
    public function configure_smtp($phpmailer) {
        // Only apply if our SMTP is enabled
        if (!$this->is_enabled()) {
            return;
        }
        
        // Don't override if another SMTP plugin is active
        if ($this->is_other_smtp_plugin_active()) {
            return;
        }
        
        // Check if PHPMailer is already configured by another plugin
        if ($phpmailer->Host && $phpmailer->Host !== 'localhost') {
            return;
        }
        
        $smtp_host = get_option('oswp_smtp_host', '');
        
        // Only configure if host is provided
        if (empty($smtp_host)) {
            return;
        }
        
        $phpmailer->isSMTP();
        $phpmailer->Host = $smtp_host;
        $phpmailer->SMTPAuth = true;
        $phpmailer->Port = absint(get_option('oswp_smtp_port', 587));
        $phpmailer->Username = get_option('oswp_smtp_username', '');
        $phpmailer->Password = get_option('oswp_smtp_password', '');
        $phpmailer->SMTPSecure = get_option('oswp_smtp_encryption', 'tls');
        
        $from_email = get_option('oswp_smtp_from_email', '');
        if (!empty($from_email)) {
            $phpmailer->From = $from_email;
        }
        
        $from_name = get_option('oswp_smtp_from_name', '');
        if (!empty($from_name)) {
            $phpmailer->FromName = $from_name;
        }
    }
    
    /**
     * Test SMTP connection
     */
    public function test_smtp_connection() {
        check_ajax_referer('oswp_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized access'));
        }
        
        $test_email = isset($_POST['email']) ? sanitize_email($_POST['email']) : get_option('admin_email');
        $enable_debug = isset($_POST['debug']) && $_POST['debug'] === 'true';
        
        // Validate email
        if (!is_email($test_email)) {
            wp_send_json_error(array(
                'message' => 'Invalid email address provided.',
                'debug' => 'Email: ' . $test_email
            ));
        }
        
        $subject = 'SMTP Test - ' . get_bloginfo('name');
        $message = 'This is a test email to verify your SMTP configuration is working correctly. Sent at ' . current_time('mysql');
        
        // Get SMTP settings
        $smtp_enabled = get_option('oswp_smtp_enabled', '0');
        if (!$smtp_enabled || $smtp_enabled !== '1') {
            wp_send_json_error(array(
                'message' => 'SMTP is not enabled. Please enable SMTP first.',
                'debug' => ''
            ));
        }
        
        $smtp_host = get_option('oswp_smtp_host', '');
        $smtp_port = get_option('oswp_smtp_port', '587');
        $smtp_username = get_option('oswp_smtp_username', '');
        $smtp_password = get_option('oswp_smtp_password', '');
        $smtp_encryption = get_option('oswp_smtp_encryption', 'tls');
        
        // Validate required settings
        $errors = array();
        if (empty($smtp_host)) $errors[] = 'SMTP Host is not configured';
        if (empty($smtp_username)) $errors[] = 'SMTP Username is not configured';
        if (empty($smtp_password)) $errors[] = 'SMTP Password is not configured';
        
        if (!empty($errors)) {
            wp_send_json_error(array(
                'message' => 'Missing SMTP Configuration: ' . implode(', ', $errors),
                'debug' => ''
            ));
        }
        
        // Check if another SMTP plugin is active
        if ($this->is_other_smtp_plugin_active()) {
            wp_send_json_error(array(
                'message' => 'Another SMTP plugin is active. Email will be sent using that plugin\'s configuration instead of these settings.',
                'debug' => 'Active SMTP Plugin Detected'
            ));
        }
        
        // Capture debug output if requested
        $debug_output = '';
        $error_messages = '';
        
        if ($enable_debug) {
            add_action('phpmailer_init', function($phpmailer) use (&$debug_output, &$error_messages) {
                $phpmailer->SMTPDebug = 2;
                $phpmailer->Debugoutput = function($str, $level) use (&$debug_output) {
                    $debug_output .= $str . "\n";
                };
            }, 999);
        }
        
        // Attempt to send test email
        add_action('wp_mail_failed', function($wp_error) use (&$error_messages) {
            $error_messages = $wp_error->get_error_message();
        });
        
        $result = wp_mail($test_email, $subject, $message);
        
        if ($result) {
            $response = array(
                'message' => 'Test email sent successfully to ' . $test_email . '!',
                'debug' => $enable_debug ? $debug_output : ''
            );
            wp_send_json_success($response);
        } else {
            // Prepare detailed error message
            $error_msg = 'Failed to send test email.';
            
            if (!empty($error_messages)) {
                $error_msg .= ' Error: ' . $error_messages;
            } else {
                $error_msg .= ' Possible issues: Check SMTP credentials, host, port, and encryption settings.';
            }
            
            $response = array(
                'message' => $error_msg,
                'debug' => $enable_debug ? $debug_output : ''
            );
            wp_send_json_error($response);
        }
    }
    
    /**
     * Get SMTP settings
     */
    public function get_settings() {
        return array(
            'enabled' => $this->is_enabled(),
            'host' => get_option('oswp_smtp_host', ''),
            'port' => get_option('oswp_smtp_port', 587),
            'username' => get_option('oswp_smtp_username', ''),
            'password' => get_option('oswp_smtp_password', ''),
            'encryption' => get_option('oswp_smtp_encryption', 'tls'),
            'from_email' => get_option('oswp_smtp_from_email', get_option('admin_email')),
            'from_name' => get_option('oswp_smtp_from_name', get_bloginfo('name')),
            'other_smtp_active' => $this->is_other_smtp_plugin_active(),
        );
    }
    
    /**
     * Save SMTP settings
     */
    public function save_settings($settings) {
        update_option('oswp_smtp_enabled', isset($settings['enabled']) ? 1 : 0);
        update_option('oswp_smtp_host', sanitize_text_field($settings['host']));
        update_option('oswp_smtp_port', absint($settings['port']));
        update_option('oswp_smtp_username', sanitize_text_field($settings['username']));
        update_option('oswp_smtp_password', $settings['password']); // Store securely
        update_option('oswp_smtp_encryption', sanitize_text_field($settings['encryption']));
        update_option('oswp_smtp_from_email', sanitize_email($settings['from_email']));
        update_option('oswp_smtp_from_name', sanitize_text_field($settings['from_name']));
    }
}

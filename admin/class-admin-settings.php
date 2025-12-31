<?php
/**
 * Admin Settings Page
 * 
 * WordPress Settings API with Card UI and Toggle Switches
 */

if (!defined('ABSPATH')) {
    exit;
}

class OSWP_Admin_Settings {
    
    private $custom_login;
    private $otp_auth;
    private $smtp_settings;
    
    public function __construct($custom_login, $otp_auth, $smtp_settings) {
        $this->custom_login = $custom_login;
        $this->otp_auth = $otp_auth;
        $this->smtp_settings = $smtp_settings;
        
        add_action('admin_menu', array($this, 'create_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_filter('plugin_action_links_' . plugin_basename(OSWP_SECURE_LOGIN_PLUGIN_DIR . 'oswp-secure-login.php'), array($this, 'add_settings_link'));
    }
    
    /**
     * Add settings link to plugin list
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=oswp-secure-login') . '">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Create admin menu
     */
    public function create_menu() {
        add_menu_page(
            'OSWP Secure Login',
            'Secure Login',
            'manage_options',
            'oswp-secure-login',
            array($this, 'render_page'),
            'dashicons-shield',
            80
        );
        
        add_submenu_page(
            'oswp-secure-login',
            'Blocked IPs',
            'Blocked IPs',
            'manage_options',
            'oswp-blocked-ips',
            array($this, 'render_blocked_ips')
        );
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, 'oswp-') === false) {
            return;
        }
        
        wp_enqueue_style('dashicons');
        wp_enqueue_style('oswp-admin', plugin_dir_url(dirname(__FILE__)) . 'assets/css/admin.css', array('dashicons'), '2.0.0');
        wp_enqueue_script('oswp-admin', plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin.js', array('jquery'), '2.0.0', true);
        
        wp_localize_script('oswp-admin', 'oswpAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('oswp_ajax'),
        ));
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        $fields = $this->get_fields();
        
        foreach ($fields as $field) {
            register_setting('oswp_settings', $field['id']);
        }
    }
    
    /**
     * Get all fields configuration
     */
    private function get_fields() {
        return array(
            // Custom Login URL
            array(
                'id' => 'oswp_custom_login_enabled', 
                'type' => 'toggle', 
                'label' => 'Enable Custom Login URL', 
                'section' => 'custom_login',
                'default' => '1',
                'description' => 'Hide the default wp-login.php and wp-admin URLs. When enabled, only the custom URL will work for login.'
            ),
            array(
                'id' => 'oswp_custom_login_slug', 
                'type' => 'text', 
                'label' => 'Login URL Slug', 
                'section' => 'custom_login', 
                'placeholder' => 'os224',
                'default' => 'os224',
                'description' => 'Your custom login URL will be: <strong>' . home_url('/') . '<span id="login-slug-preview">os224</span></strong><br>Use only letters and numbers, no spaces or special characters.'
            ),
            
            // OTP Settings
            array(
                'id' => 'oswp_otp_enabled', 
                'type' => 'toggle', 
                'label' => 'Enable OTP Authentication', 
                'section' => 'otp',
                'default' => '1',
                'description' => 'Add an extra layer of security by requiring a one-time password sent to user\'s email after login.'
            ),
            array(
                'id' => 'oswp_otp_max_attempts', 
                'type' => 'number', 
                'label' => 'Maximum OTP Attempts', 
                'section' => 'otp', 
                'placeholder' => '3',
                'default' => '3',
                'description' => 'Number of times a user can enter wrong OTP before being blocked. Default: 3'
            ),
            array(
                'id' => 'oswp_otp_max_resends', 
                'type' => 'number', 
                'label' => 'Maximum Resends', 
                'section' => 'otp', 
                'placeholder' => '2',
                'default' => '2',
                'description' => 'How many times users can request a new OTP code. Total attempts = 1 initial + resends. Default: 2'
            ),
            array(
                'id' => 'oswp_otp_block_duration', 
                'type' => 'number', 
                'label' => 'Block Duration (seconds)', 
                'section' => 'otp', 
                'placeholder' => '3600',
                'default' => '3600',
                'description' => 'How long to block an IP after failed attempts. 3600 seconds = 1 hour. Set to 0 to disable.'
            ),
            array(
                'id' => 'oswp_otp_ip_blocking_enabled', 
                'type' => 'toggle', 
                'label' => 'Enable IP Blocking', 
                'section' => 'otp',
                'default' => '1',
                'description' => 'Automatically block IP addresses that exceed the maximum OTP attempts. View and manage blocked IPs in the "Blocked IPs" menu.'
            ),
            
            // SMTP Settings
            array(
                'id' => 'oswp_smtp_enabled', 
                'type' => 'toggle', 
                'label' => 'Enable SMTP', 
                'section' => 'smtp',
                'default' => '',
                'description' => 'Use custom SMTP server for OTP emails. If another SMTP plugin is active (WP Mail SMTP, Mailgun, etc.), it will be used instead of these settings.'
            ),
            array(
                'id' => 'oswp_smtp_host', 
                'type' => 'text', 
                'label' => 'SMTP Host', 
                'section' => 'smtp', 
                'placeholder' => 'smtp.gmail.com',
                'description' => 'Your SMTP server address. Example: smtp.gmail.com, smtp.office365.com, mail.yourdomain.com'
            ),
            array(
                'id' => 'oswp_smtp_port', 
                'type' => 'number', 
                'label' => 'SMTP Port', 
                'section' => 'smtp', 
                'placeholder' => '587',
                'default' => '587',
                'description' => 'SMTP port number. Common ports: 587 (TLS), 465 (SSL), 25 (no encryption)'
            ),
            array(
                'id' => 'oswp_smtp_encryption', 
                'type' => 'select', 
                'label' => 'Encryption', 
                'section' => 'smtp', 
                'options' => array('tls' => 'TLS', 'ssl' => 'SSL', '' => 'None'),
                'default' => 'tls',
                'description' => 'Encryption method. TLS (port 587) is recommended for most modern SMTP servers.'
            ),
            array(
                'id' => 'oswp_smtp_username', 
                'type' => 'text', 
                'label' => 'SMTP Username', 
                'section' => 'smtp',
                'description' => 'Your SMTP authentication username, usually your email address.'
            ),
            array(
                'id' => 'oswp_smtp_password', 
                'type' => 'password', 
                'label' => 'SMTP Password', 
                'section' => 'smtp',
                'description' => 'Your SMTP password or app-specific password. For Gmail, use an App Password, not your regular password.'
            ),
            array(
                'id' => 'oswp_smtp_from_email', 
                'type' => 'email', 
                'label' => 'From Email', 
                'section' => 'smtp',
                'default' => get_option('admin_email'),
                'description' => 'Email address that appears in the "From" field. Should match your SMTP username.'
            ),
            array(
                'id' => 'oswp_smtp_from_name', 
                'type' => 'text', 
                'label' => 'From Name', 
                'section' => 'smtp',
                'default' => get_bloginfo('name'),
                'description' => 'Name that appears in the "From" field. Example: "' . get_bloginfo('name') . ' Security"'
            ),
        );
    }
    
    /**
     * Render field
     */
    private function render_field($field) {
        $value = get_option($field['id']);
        $placeholder = isset($field['placeholder']) ? $field['placeholder'] : '';
        $description = isset($field['description']) ? $field['description'] : '';
        $default = isset($field['default']) ? $field['default'] : '';
        
        // Use default value if not saved in database (false means option doesn't exist)
        if ($value === false || $value === '') {
            $display_value = $default;
            $is_default = true;
        } else {
            $display_value = $value;
            $is_default = false;
        }
        
        switch ($field['type']) {
            case 'toggle':
                $is_checked = ($value !== false && $value !== '') ? ($value === '1' || $value === 1) : ($default === '1' || $default === 1);
                echo '<label class="oswp-toggle">';
                printf('<input type="checkbox" name="%s" value="1" %s>', $field['id'], checked($is_checked, true, false));
                echo '<span class="oswp-toggle-slider"></span>';
                echo '</label>';
                if ($default !== '' && $is_default) {
                    echo '<span class="oswp-default-indicator" style="margin-left: 10px;">(Default: ' . ($default ? 'Enabled' : 'Disabled') . ')</span>';
                }
                break;
                
            case 'select':
                printf('<select name="%s" class="regular-text">', $field['id']);
                foreach ($field['options'] as $opt_val => $opt_label) {
                    printf('<option value="%s" %s>%s</option>', $opt_val, selected($display_value, $opt_val, false), $opt_label);
                }
                echo '</select>';
                if ($default !== '' && $is_default) {
                    echo ' <span class="oswp-default-indicator">(Default: ' . esc_html($default) . ')</span>';
                }
                break;
                
            case 'number':
                printf('<input type="number" name="%s" value="%s" placeholder="%s" class="small-text">', 
                    $field['id'], esc_attr($display_value), $placeholder);
                if ($default !== '' && $is_default) {
                    echo ' <span class="oswp-default-indicator">(Default: ' . esc_html($default) . ')</span>';
                }
                break;
                
            default:
                printf('<input type="%s" name="%s" value="%s" placeholder="%s" class="regular-text" id="%s">', 
                    $field['type'], $field['id'], esc_attr($display_value), $placeholder, $field['id']);
                if ($default !== '' && $is_default) {
                    echo ' <span class="oswp-default-indicator">(Default: ' . esc_html($default) . ')</span>';
                }
        }
        
        // Display description (without default info)
        if ($description) {
            // Remove "Default: X" from description if present
            $description = preg_replace('/\s*Default:\s*[^<.]*\.?/', '', $description);
            echo '<p class="description">' . $description . '</p>';
        }
    }
    
    /**
     * Render settings page
     */
    public function render_page() {
        if (isset($_POST['submit']) && check_admin_referer('oswp_settings_save', 'oswp_nonce')) {
            $this->save_settings();
        }
        
        $fields = $this->get_fields();
        $sections = array(
            'custom_login' => array('title' => 'Custom Login URL'),
            'otp' => array('title' => 'OTP Authentication'),
            'smtp' => array('title' => 'SMTP Email Settings'),
        );
        ?>
        <div class="wrap oswp-settings-wrap">
            <h1>OSWP Secure Login Settings</h1>
            <?php settings_errors(); ?>
            
            <?php 
            // Check if another SMTP plugin is active
            $active_plugins = get_option('active_plugins', array());
            $smtp_plugins = array(
                'wp-mail-smtp/wp_mail_smtp.php' => 'WP Mail SMTP',
                'mailgun/mailgun.php' => 'Mailgun',
                'sendgrid-email-delivery-simplified/sendgrid.php' => 'SendGrid',
                'brevo/brevo.php' => 'Brevo (Sendinblue)',
                'postman-smtp/postman-smtp.php' => 'Postman SMTP',
                'easy-wp-smtp/easy-wp-smtp.php' => 'Easy WP SMTP',
                'amazon-ses-wp-mail/amazon-ses-wp-mail.php' => 'Amazon SES',
                'mailerlite/mailerlite.php' => 'MailerLite',
            );
            
            $active_smtp = '';
            foreach ($smtp_plugins as $plugin => $name) {
                if (in_array($plugin, $active_plugins)) {
                    $active_smtp = $name;
                    break;
                }
            }
            ?>
            
            <?php if ($active_smtp): ?>
            <div class="notice notice-info" style="margin: 20px 0;">
                <p><strong>Info:</strong> We detected that <strong><?php echo $active_smtp; ?></strong> is active. OSWP Secure Login will respect that plugin's email configuration and won't override it. Your OTP emails will be sent using <?php echo $active_smtp; ?>'s settings.</p>
            </div>
            <?php else: ?>
            <div class="notice notice-warning" style="margin: 20px 0;">
                <p><strong>Note:</strong> Configure SMTP below for OTP emails, or install a dedicated SMTP plugin for better email reliability and more options.</p>
            </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('oswp_settings_save', 'oswp_nonce'); ?>
                
                <div class="oswp-settings-grid">
                    <?php foreach ($sections as $section_id => $section): ?>
                        <div class="oswp-card">
                            <div class="oswp-card-header">
                                <h2><?php echo $section['title']; ?></h2>
                            </div>
                            <div class="oswp-card-body">
                                <table class="form-table">
                                    <?php foreach ($fields as $field): ?>
                                        <?php if ($field['section'] === $section_id): ?>
                                            <tr>
                                                <th scope="row"><?php echo $field['label']; ?></th>
                                                <td><?php $this->render_field($field); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </table>
                                
                                <?php if ($section_id === 'smtp'): ?>
                                    <div class="oswp-smtp-test">
                                        <hr>
                                        <h3>Test SMTP Connection</h3>
                                        <p class="description" style="margin-bottom: 15px;">Send a test email to verify your SMTP configuration is working correctly.</p>
                                        <p>
                                            <input type="email" id="test_email" class="regular-text" 
                                                   value="<?php echo esc_attr(get_option('admin_email')); ?>" 
                                                   placeholder="Test email address">
                                            <label style="margin-left: 15px;">
                                                <input type="checkbox" id="smtp_debug"> Show debug information
                                            </label>
                                        </p>
                                        <p>
                                            <button type="button" id="test_smtp" class="button">
                                                Send Test Email
                                            </button>
                                            <span id="smtp_result"></span>
                                        </p>
                                        <pre id="smtp_debug_output" style="display:none;"></pre>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <p class="submit">
                    <button type="submit" name="submit" class="button button-primary button-large">
                        Save All Settings
                    </button>
                    <button type="button" id="flush_rewrite" class="button button-large" style="margin-left: 10px;">
                        Flush Rewrite Rules
                    </button>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        foreach ($this->get_fields() as $field) {
            $value = isset($_POST[$field['id']]) ? $_POST[$field['id']] : '';
            
            if ($field['type'] === 'toggle') {
                $value = $value === '1' ? '1' : '0';
            } elseif ($field['type'] === 'number') {
                $value = absint($value);
            } elseif ($field['type'] === 'email') {
                $value = sanitize_email($value);
            } else {
                $value = sanitize_text_field($value);
            }
            
            update_option($field['id'], $value);
        }
        
        add_settings_error('oswp_messages', 'oswp_saved', 'Settings saved successfully!', 'updated');
    }
    
    /**
     * Render blocked IPs page
     */
    public function render_blocked_ips() {
        if (isset($_POST['unblock_ip']) && check_admin_referer('oswp_unblock', 'oswp_nonce')) {
            $ip = sanitize_text_field($_POST['ip']);
            $blocked = get_option('oswp_blocked_ips', array());
            unset($blocked[$ip]);
            update_option('oswp_blocked_ips', $blocked);
            echo '<div class="notice notice-success is-dismissible"><p><strong>Success!</strong> IP address unblocked successfully.</p></div>';
        }
        
        if (isset($_POST['clear_all_blocks']) && check_admin_referer('oswp_clear_all', 'oswp_nonce')) {
            update_option('oswp_blocked_ips', array());
            echo '<div class="notice notice-success is-dismissible"><p><strong>Success!</strong> All IP blocks have been cleared.</p></div>';
        }
        
        $blocked_ips = get_option('oswp_blocked_ips', array());
        ?>
        <div class="wrap oswp-settings-wrap">
            <h1>Blocked IP Addresses</h1>
            <p class="description">Manage IP addresses that have been blocked due to failed OTP attempts. Blocks are automatically removed after the configured duration.</p>
            
            <?php if (empty($blocked_ips)): ?>
                <div class="oswp-card" style="margin-top: 20px;">
                    <div class="oswp-card-body" style="text-align: center; padding: 60px 24px;">
                        <h2 style="margin-top: 20px; color: #1d2327;">No Blocked IPs</h2>
                        <p style="color: #646970; margin-top: 10px;">No IP addresses are currently blocked. Your site is secure!</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="oswp-card" style="margin-top: 20px;">
                    <div class="oswp-card-header">
                        <h2>Currently Blocked IPs (<?php echo count($blocked_ips); ?>)</h2>
                    </div>
                    <div class="oswp-card-body" style="padding: 0;">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th style="padding: 12px;">IP Address</th>
                                    <th style="padding: 12px;">Blocked Until</th>
                                    <th style="padding: 12px;">Time Remaining</th>
                                    <th style="padding: 12px;">Status</th>
                                    <th style="padding: 12px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($blocked_ips as $ip => $until): ?>
                                    <?php
                                    $remaining = $until - time();
                                    $expired = $remaining <= 0;
                                    $status_class = $expired ? 'error' : 'warning';
                                    $status_text = $expired ? 'Expired' : 'Active';
                                    ?>
                                    <tr>
                                        <td style="padding: 12px;"><code style="background: #f6f7f7; padding: 4px 8px; border-radius: 3px;"><?php echo esc_html($ip); ?></code></td>
                                        <td style="padding: 12px;"><?php echo date('Y-m-d H:i:s', $until); ?></td>
                                        <td style="padding: 12px;">
                                            <?php
                                            if ($expired) {
                                                echo '<span style="color: #646970;">Auto-removing soon...</span>';
                                            } else {
                                                $hours = floor($remaining / 3600);
                                                $minutes = floor(($remaining % 3600) / 60);
                                                echo sprintf('<strong>%d</strong> hours, <strong>%d</strong> minutes', $hours, $minutes);
                                            }
                                            ?>
                                        </td>
                                        <td style="padding: 12px;">
                                            <span class="oswp-status-badge oswp-status-<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                        </td>
                                        <td style="padding: 12px;">
                                            <form method="post" style="display:inline;">
                                                <?php wp_nonce_field('oswp_unblock', 'oswp_nonce'); ?>
                                                <input type="hidden" name="ip" value="<?php echo esc_attr($ip); ?>">
                                                <button type="submit" name="unblock_ip" class="button button-small">Unblock Now</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="oswp-card-body" style="border-top: 1px solid #e5e7eb; background: #f9fafb;">
                        <form method="post" onsubmit="return confirm('Are you sure you want to unblock all IP addresses?');">
                            <?php wp_nonce_field('oswp_clear_all', 'oswp_nonce'); ?>
                            <button type="submit" name="clear_all_blocks" class="button">Clear All Blocks</button>
                            <span class="description" style="margin-left: 10px;">This will remove all IP blocks immediately.</span>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

<?php
/**
 * OTP Authentication Handler
 * 
 * Handles Two-Factor Authentication with OTP
 */

if (!defined('ABSPATH')) {
    exit;
}

class OSWP_OTP_Auth {
    
    public function __construct() {
        $this->init();
    }
    
    /**
     * Initialize hooks
     */
    private function init() {
        add_action('wp_authenticate', array($this, 'intercept_login'), 30, 2);
        add_action('init', array($this, 'start_session'), 1); // Run very early for session
        add_action('init', array($this, 'verify_otp'), 5); // High priority - before handle_custom_login
        add_action('init', array($this, 'handle_resend_otp'), 5); // High priority - before handle_custom_login
    }
    
    /**
     * Start PHP session for OTP storage
     */
    public function start_session() {
        if (!session_id() && !headers_sent()) {
            session_start();
        }
    }
    
    /**
     * Check if OTP is enabled
     */
    public function is_enabled() {
        $value = get_option('oswp_otp_enabled', '0');
        return $value === '1' || $value === 1 || $value === true;
    }
    
    /**
     * Get max OTP attempts
     */
    private function get_max_attempts() {
        $value = get_option('oswp_otp_max_attempts', '3');
        $attempts = absint($value);
        return $attempts > 0 ? $attempts : 3; // Default to 3 if 0 or empty
    }
    
    /**
     * Get max resend attempts
     */
    private function get_max_resends() {
        $value = get_option('oswp_otp_max_resends', '2');
        $resends = absint($value);
        return $resends > 0 ? $resends : 2; // Default to 2 if 0 or empty
    }
    
    /**
     * Get block duration in seconds
     */
    private function get_block_duration() {
        $value = get_option('oswp_otp_block_duration', '3600');
        $duration = absint($value);
        return $duration > 0 ? $duration : 3600; // Default to 1 hour if 0 or empty
    }
    
    /**
     * Check if IP blocking is enabled
     */
    private function is_ip_blocking_enabled() {
        $value = get_option('oswp_otp_ip_blocking_enabled', '1');
        return $value === '1' || $value === 1 || $value === true;
    }
    
    /**
     * Get user IP address
     */
    private function get_user_ip() {
        $ip = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return sanitize_text_field($ip);
    }
    
    /**
     * Check if IP is blocked
     */
    private function is_ip_blocked($ip) {
        $blocked_ips = get_option('oswp_blocked_ips', array());
        
        if (isset($blocked_ips[$ip])) {
            $block_until = $blocked_ips[$ip];
            if (time() < $block_until) {
                return true;
            } else {
                // Unblock if time has passed
                unset($blocked_ips[$ip]);
                update_option('oswp_blocked_ips', $blocked_ips);
            }
        }
        
        return false;
    }
    
    /**
     * Block IP address
     */
    private function block_ip($ip) {
        $blocked_ips = get_option('oswp_blocked_ips', array());
        if (!is_array($blocked_ips)) {
            $blocked_ips = array();
        }
        $blocked_ips[$ip] = time() + $this->get_block_duration();
        update_option('oswp_blocked_ips', $blocked_ips);
    }
    
    /**
     * Intercept login process
     */
    public function intercept_login($username, $password) {
        // Only intercept if OTP is enabled
        if (!$this->is_enabled()) {
            return;
        }
        
        // Check if this is a login attempt
        if (!isset($_POST['log']) || !isset($_POST['pwd'])) {
            return;
        }
        
        // Don't intercept OTP verification
        if (isset($_POST['verify_otp']) || isset($_POST['resend_otp'])) {
            return;
        }
        
        // Check if IP is blocked
        $user_ip = $this->get_user_ip();
        if ($this->is_ip_blocking_enabled() && $this->is_ip_blocked($user_ip)) {
            $this->show_otp_form('Your IP has been temporarily blocked due to multiple failed attempts. Please try again later.', true);
            exit;
        }
        
        // Authenticate user credentials
        $user = wp_authenticate_username_password(null, $username, $password);
        
        if (is_wp_error($user)) {
            // Return error without intercepting, let WordPress handle it
            return;
        }
        
        // Generate OTP
        $otp = $this->generate_otp();
        
        // Store in session
        $_SESSION['oswp_otp_code'] = $otp;
        $_SESSION['oswp_otp_user'] = $user->user_login;
        $_SESSION['oswp_otp_remember'] = !empty($_POST['rememberme']);
        $_SESSION['oswp_otp_time'] = time();
        $_SESSION['oswp_otp_attempts'] = 0;
        $_SESSION['oswp_otp_resends'] = 0;
        $_SESSION['oswp_otp_ip'] = $user_ip;
        
        // Send OTP email
        if ($this->send_otp_email($user, $otp)) {
            $this->show_otp_form('OTP has been sent to: ' . $this->mask_email($user->user_email), false);
        } else {
            // Clear session if email fails
            $this->clear_otp_session();
            $this->show_error_page('Failed to send OTP email. Please contact the site administrator or support for assistance.');
        }
        
        exit;
    }
    
    /**
     * Handle resend OTP request
     */
    public function handle_resend_otp() {
        if (!$this->is_enabled()) {
            return;
        }
        
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_POST['resend_otp'])) {
            return;
        }
        
        $username = isset($_SESSION['oswp_otp_user']) ? $_SESSION['oswp_otp_user'] : '';
        $resends = isset($_SESSION['oswp_otp_resends']) ? $_SESSION['oswp_otp_resends'] : 0;
        $max_resends = $this->get_max_resends();
        
        if ($resends >= $max_resends) {
            $this->show_otp_form('Maximum resend attempts reached. Please return to login page and start over.', true, $resends);
            exit;
        }
        
        if ($username) {
            $user = get_user_by('login', $username);
            if ($user) {
                // Generate new OTP
                $otp = $this->generate_otp();
                $_SESSION['oswp_otp_code'] = $otp;
                $_SESSION['oswp_otp_time'] = time();
                $_SESSION['oswp_otp_attempts'] = 0; // Reset attempts on resend
                $_SESSION['oswp_otp_resends'] = $resends + 1;
                
                // Send OTP email
                if ($this->send_otp_email($user, $otp)) {
                    $remaining = $max_resends - ($_SESSION['oswp_otp_resends']);
                    $this->show_otp_form('New OTP has been sent to: ' . $this->mask_email($user->user_email) . ' (Resends remaining: ' . $remaining . ')', false);
                } else {
                    $this->show_otp_form('Failed to resend OTP email. Please contact the site administrator or support for assistance.', true, $resends);
                }
            }
        }
        
        exit;
    }
    
    /**
     * Verify OTP submitted by user
     */
    public function verify_otp() {
        if (!$this->is_enabled()) {
            return;
        }
        
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_POST['verify_otp']) || !isset($_POST['otp_input'])) {
            return;
        }
        
        $entered_otp = sanitize_text_field($_POST['otp_input']);
        $stored_otp = isset($_SESSION['oswp_otp_code']) ? $_SESSION['oswp_otp_code'] : '';
        $username = isset($_SESSION['oswp_otp_user']) ? $_SESSION['oswp_otp_user'] : '';
        $remember = isset($_SESSION['oswp_otp_remember']) ? $_SESSION['oswp_otp_remember'] : false;
        $otp_time = isset($_SESSION['oswp_otp_time']) ? $_SESSION['oswp_otp_time'] : 0;
        $attempts = isset($_SESSION['oswp_otp_attempts']) ? $_SESSION['oswp_otp_attempts'] : 0;
        $resends = isset($_SESSION['oswp_otp_resends']) ? $_SESSION['oswp_otp_resends'] : 0;
        $user_ip = $this->get_user_ip();
        
        // Check if session data exists
        if (!$stored_otp || !$username || !$otp_time) {
            $this->show_otp_form('Session expired. Please return to login page and try again.', true, 0);
            exit;
        }
        
        // Check OTP expiration (10 minutes instead of 5)
        if (time() - $otp_time > 600) {
            $this->clear_otp_session();
            $this->show_otp_form('Your verification code has expired. Please return to login page and try again.', true, $resends);
            exit;
        }
        
        // Verify OTP
        if ($entered_otp === $stored_otp && $username) {
            $user = get_user_by('login', $username);
            
            if ($user) {
                // Clear OTP session
                $this->clear_otp_session();
                
                // Log user in
                wp_set_auth_cookie($user->ID, $remember);
                wp_set_current_user($user->ID);
                do_action('wp_login', $user->user_login, $user);
                
                // Redirect to admin
                wp_redirect(admin_url());
                exit;
            }
        }
        
        // Invalid OTP - increment attempts
        $attempts++;
        $_SESSION['oswp_otp_attempts'] = $attempts;
        $max_attempts = $this->get_max_attempts();
        
        if ($attempts >= $max_attempts) {
            // Block IP if enabled
            if ($this->is_ip_blocking_enabled()) {
                $this->block_ip($user_ip);
                $block_duration_hours = ceil($this->get_block_duration() / 3600);
                $this->clear_otp_session();
                $this->show_otp_form('Your IP has been blocked for ' . $block_duration_hours . ' hour(s) due to multiple failed attempts. Please contact the site administrator if you need assistance.', true, $resends);
            } else {
                $this->clear_otp_session();
                $this->show_otp_form('Maximum attempts exceeded. Please contact the site administrator for assistance.', true, $resends);
            }
        } else {
            // Show error with remaining attempts
            $remaining = $max_attempts - $attempts;
            $this->show_otp_form('Invalid OTP. You have ' . $remaining . ' attempt(s) remaining.', true, $resends);
        }
        exit;
    }
    
    /**
     * Generate random 6-digit OTP
     */
    private function generate_otp() {
        return sprintf('%06d', rand(0, 999999));
    }
    
    /**
     * Send OTP via email
     */
    private function send_otp_email($user, $otp) {
        $to = $user->user_email;
        $subject = 'Your Login OTP - ' . get_bloginfo('name');
        
        $message = '
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .otp-box { background: #f4f4f4; border-left: 4px solid #0073aa; padding: 20px; margin: 20px 0; }
                .otp-code { font-size: 32px; font-weight: bold; color: #0073aa; letter-spacing: 5px; }
                .footer { margin-top: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <h2>Login Verification Code</h2>
                <p>Hello ' . esc_html($user->display_name) . ',</p>
                <p>Your One-Time Password (OTP) for logging into ' . get_bloginfo('name') . ' is:</p>
                <div class="otp-box">
                    <div class="otp-code">' . $otp . '</div>
                </div>
                <p><strong>This code will expire in 5 minutes.</strong></p>
                <p>If you did not request this code, please ignore this email.</p>
                <div class="footer">
                    <p>This is an automated email from ' . get_bloginfo('name') . '</p>
                </div>
            </div>
        </body>
        </html>';
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        return wp_mail($to, $subject, $message, $headers);
    }
    
    /**
     * Display OTP verification form
     */
    private function show_otp_form($message = '', $is_error = false, $resends = 0) {
        $max_resends = $this->get_max_resends();
        $can_resend = $resends < $max_resends;
        
        // Get current attempts if available
        $attempts = isset($_SESSION['oswp_otp_attempts']) ? $_SESSION['oswp_otp_attempts'] : 0;
        $max_attempts = $this->get_max_attempts();
        $remaining = $max_attempts - $attempts;
        
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>OTP Verification - <?php echo get_bloginfo('name'); ?></title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                    background: #f0f0f1;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                }
                .otp-container {
                    background: #fff;
                    padding: 40px;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    max-width: 400px;
                    width: 100%;
                }
                .otp-container h1 {
                    text-align: center;
                    color: #1d2327;
                    margin-bottom: 10px;
                    font-size: 24px;
                }
                .otp-container .subtitle {
                    text-align: center;
                    color: #646970;
                    margin-bottom: 30px;
                    font-size: 14px;
                }
                .attempts-info {
                    text-align: center;
                    padding: 8px;
                    background: #f0f6ff;
                    border-radius: 4px;
                    margin-bottom: 20px;
                    font-size: 13px;
                    color: #2271b1;
                }
                .message {
                    padding: 12px;
                    margin-bottom: 20px;
                    border-radius: 4px;
                    font-size: 14px;
                }
                .message.success {
                    background: #e7f3fe;
                    border-left: 4px solid #0073aa;
                    color: #0073aa;
                }
                .message.error {
                    background: #fcf0f1;
                    border-left: 4px solid #d63638;
                    color: #d63638;
                }
                .form-group {
                    margin-bottom: 20px;
                }
                .form-group label {
                    display: block;
                    margin-bottom: 8px;
                    color: #1d2327;
                    font-weight: 500;
                }
                .form-group input[type="text"] {
                    width: 100%;
                    padding: 12px;
                    border: 1px solid #8c8f94;
                    border-radius: 4px;
                    font-size: 18px;
                    letter-spacing: 3px;
                    text-align: center;
                }
                .form-group input[type="text"]:focus {
                    outline: none;
                    border-color: #2271b1;
                    box-shadow: 0 0 0 1px #2271b1;
                }
                .submit-btn {
                    width: 100%;
                    padding: 12px;
                    background: #2271b1;
                    color: #fff;
                    border: none;
                    border-radius: 4px;
                    font-size: 16px;
                    cursor: pointer;
                    transition: background 0.2s;
                }
                .submit-btn:hover {
                    background: #135e96;
                }
                .submit-btn:disabled {
                    background: #999;
                    cursor: not-allowed;
                }
                .resend-btn {
                    width: 100%;
                    padding: 10px;
                    background: #fff;
                    color: #2271b1;
                    border: 1px solid #2271b1;
                    border-radius: 4px;
                    font-size: 14px;
                    cursor: pointer;
                    margin-top: 10px;
                    transition: all 0.2s;
                }
                .resend-btn:hover:not(:disabled) {
                    background: #f0f0f1;
                }
                .resend-btn:disabled {
                    color: #999;
                    border-color: #999;
                    cursor: not-allowed;
                }
                .back-link {
                    text-align: center;
                    margin-top: 20px;
                    padding-top: 15px;
                    border-top: 1px solid #e5e7eb;
                }
                .back-link a {
                    color: #2271b1;
                    text-decoration: none;
                    font-size: 13px;
                    margin: 0 5px;
                }
                .back-link a:hover {
                    text-decoration: underline;
                }
                .back-link span {
                    margin: 0 8px;
                    color: #ddd;
                }
            </style>
        </head>
        <body>
            <div class="otp-container">
                <h1>Verification</h1>
                <p class="subtitle">Enter the code sent to your email</p>
                
                <?php if ($remaining > 0 && $remaining < $max_attempts): ?>
                    <div class="attempts-info">
                        Attempts remaining: <?php echo $remaining; ?> of <?php echo $max_attempts; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($message): ?>
                    <div class="message <?php echo $is_error ? 'error' : 'success'; ?>">
                        <?php echo esc_html($message); ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <div class="form-group">
                        <label for="otp_input">6-Digit Code</label>
                        <input type="text" 
                               id="otp_input" 
                               name="otp_input" 
                               maxlength="6" 
                               pattern="[0-9]{6}" 
                               required 
                               autofocus
                               autocomplete="off"
                               <?php echo ($remaining <= 0 && $attempts >= $max_attempts) ? 'disabled' : ''; ?>>
                    </div>
                    <button type="submit" name="verify_otp" class="submit-btn" <?php echo ($remaining <= 0 && $attempts >= $max_attempts) ? 'disabled' : ''; ?>>Verify</button>
                    
                    <?php if ($can_resend && $remaining > 0): ?>
                    <button type="submit" name="resend_otp" class="resend-btn">Resend Code</button>
                    <?php else: ?>
                    <button type="button" class="resend-btn" disabled>
                        <?php echo ($remaining <= 0) ? 'Maximum attempts reached' : 'Maximum resends reached'; ?>
                    </button>
                    <?php endif; ?>
                </form>
                
                <div class="back-link">
                    <a href="<?php echo wp_login_url(); ?>">← Back to Login</a>
                    <span style="margin: 0 8px; color: #646970;">|</span>
                    <a href="<?php echo home_url('/'); ?>">← Back to Site</a>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
    
    /**
     * Clear OTP session data
     */
    private function clear_otp_session() {
        unset($_SESSION['oswp_otp_code']);
        unset($_SESSION['oswp_otp_user']);
        unset($_SESSION['oswp_otp_remember']);
        unset($_SESSION['oswp_otp_time']);
        unset($_SESSION['oswp_otp_attempts']);
        unset($_SESSION['oswp_otp_resends']);
        unset($_SESSION['oswp_otp_ip']);
    }
    
    /**
     * Mask email for security
     */
    private function mask_email($email) {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }
        
        $name = $parts[0];
        $domain = $parts[1];
        
        $name_length = strlen($name);
        // Show last 2 characters, mask the rest
        if ($name_length <= 2) {
            $masked = str_repeat('*', $name_length);
        } else {
            $visible_chars = substr($name, -2);
            $masked = str_repeat('*', $name_length - 2) . $visible_chars;
        }
        
        return $masked . '@' . $domain;
    }
    
    /**
     * Show error page without OTP form
     */
    private function show_error_page($message) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Error - <?php echo get_bloginfo('name'); ?></title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                    background: #f0f0f1;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                }
                .error-container {
                    background: #fff;
                    padding: 40px;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    max-width: 500px;
                    width: 100%;
                    text-align: center;
                }
                .error-container h1 {
                    color: #d63638;
                    margin-bottom: 20px;
                    font-size: 24px;
                }
                .error-message {
                    padding: 20px;
                    background: #fcf0f1;
                    border-left: 4px solid #d63638;
                    color: #d63638;
                    margin-bottom: 30px;
                    text-align: left;
                    border-radius: 4px;
                }
                .back-link {
                    padding-top: 20px;
                    border-top: 1px solid #e5e7eb;
                }
                .back-link a {
                    color: #2271b1;
                    text-decoration: none;
                    font-size: 14px;
                    margin: 0 10px;
                }
                .back-link a:hover {
                    text-decoration: underline;
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <h1>Email Delivery Error</h1>
                <div class="error-message">
                    <?php echo esc_html($message); ?>
                </div>
                <div class="back-link">
                    <a href="<?php echo wp_login_url(); ?>">Return to Login</a>
                    <span style="color: #646970;">|</span>
                    <a href="<?php echo home_url('/'); ?>">Go to Homepage</a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

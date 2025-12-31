<?php
/**
 * Custom Login URL Handler
 * 
 * Handles custom login URL functionality to hide wp-admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class OSWP_Custom_Login_URL {
    
    private $custom_slug;
    
    public function __construct() {
        $this->init();
    }
    
    /**
     * Initialize hooks
     */
    private function init() {
        add_action('init', array($this, 'handle_custom_login'));
        add_filter('site_url', array($this, 'filter_login_url'), 10, 2);
        add_filter('wp_redirect', array($this, 'filter_redirect_url'), 10, 1);
        add_action('template_redirect', array($this, 'block_default_access'), 1); // Priority 1 to run early
        add_action('login_init', array($this, 'block_wp_login_access'), 1); // Block wp-login.php directly
    }
    
    /**
     * Check if custom login is enabled
     */
    public function is_enabled() {
        $value = get_option('oswp_custom_login_enabled', '0');
        return $value === '1' || $value === 1 || $value === true;
    }
    
    /**
     * Get custom login slug
     */
    public function get_slug() {
        if (!$this->custom_slug) {
            $this->custom_slug = get_option('oswp_custom_login_slug', 'os224');
        }
        return $this->custom_slug;
    }
    
    /**
     * Handle custom login page requests
     */
    public function handle_custom_login() {
        if (!$this->is_enabled()) {
            return;
        }
        
        $slug = $this->get_slug();
        $request_uri = $_SERVER['REQUEST_URI'];
        
        // Check if accessing custom login URL
        if (strpos($request_uri, '/' . $slug) !== false && !is_user_logged_in()) {
            // Add custom login form styling
            add_action('login_head', array($this, 'add_custom_login_styles'));
            add_action('login_footer', array($this, 'add_back_to_site_link'));
            
            // Set all required variables to prevent undefined warnings
            global $error, $interim_login, $action, $user_login;
            
            $error = '';
            $interim_login = false;
            $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'login';
            $user_login = isset($_POST['log']) ? $_POST['log'] : '';
            
            // Set GET variables if not set
            if (!isset($_GET['action'])) {
                $_GET['action'] = $action;
            }
            if (!isset($_REQUEST['redirect_to'])) {
                $_REQUEST['redirect_to'] = admin_url();
            }
            
            require_once ABSPATH . 'wp-login.php';
            exit;
        }
    }
    
    /**
     * Add custom login styles
     */
    public function add_custom_login_styles() {
        ?>
        <style>
            .oswp-back-link {
                text-align: center;
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px solid #e5e7eb;
            }
            .oswp-back-link a {
                color: #2271b1;
                text-decoration: none;
                font-size: 13px;
            }
            .oswp-back-link a:hover {
                text-decoration: underline;
            }
        </style>
        <?php
    }
    
    /**
     * Add back to site link
     */
    public function add_back_to_site_link() {
        ?>
        <div class="oswp-back-link">
            <a href="<?php echo home_url('/'); ?>">← Back to <?php echo get_bloginfo('name'); ?></a>
        </div>
        <?php
    }
    
    /**
     * Block default wp-admin and wp-login.php access when enabled
     */
    public function block_default_access() {
        if (!$this->is_enabled() || is_user_logged_in()) {
            return;
        }
        
        $request_uri = strtolower($_SERVER['REQUEST_URI']);
        $slug = $this->get_slug();
        
        // Don't block custom login URL
        if (strpos($request_uri, '/' . $slug) !== false) {
            return;
        }
        
        // Don't block admin-ajax.php (required for AJAX requests)
        if (strpos($request_uri, 'admin-ajax.php') !== false) {
            return;
        }
        
        // List of protected paths to block
        $protected_paths = array(
            'wp-login',
            'wp-admin',
            '/admin',
            '/login',
            'wp-login.php',
        );
        
        // Check if accessing any protected path
        $should_block = false;
        foreach ($protected_paths as $path) {
            if (strpos($request_uri, $path) !== false) {
                $should_block = true;
                break;
            }
        }
        
        if ($should_block) {
            // Show 404 page - prevent any redirects
            status_header(404);
            nocache_headers();
            
            global $wp_query;
            $wp_query->set_404();
            
            // Get 404 template
            $template_404 = get_404_template();
            if ($template_404) {
                include($template_404);
            } else {
                // Fallback 404 page
                wp_die('404 - Page Not Found', '404 - Not Found', array('response' => 404));
            }
            exit;
        }
    }
    
    /**
     * Filter login URL to use custom slug
     */
    public function filter_login_url($url, $path) {
        if (!$this->is_enabled()) {
            return $url;
        }
        
        if ($path === 'wp-login.php' || strpos($url, 'wp-login.php') !== false) {
            $url = home_url('/' . $this->get_slug() . '/');
        }
        
        return $url;
    }
    
    /**
     * Filter redirect URLs
     */
    public function filter_redirect_url($location) {
        if (!$this->is_enabled()) {
            return $location;
        }
        
        // Block any redirects to wp-login.php or custom login URL when not accessing custom URL
        $request_uri = isset($_SERVER['REQUEST_URI']) ? strtolower($_SERVER['REQUEST_URI']) : '';
        $slug = $this->get_slug();
        
        // If trying to redirect to wp-login or custom slug from wp-admin, prevent it
        if (strpos($request_uri, 'wp-admin') !== false && strpos($request_uri, $slug) === false) {
            if (strpos($location, 'wp-login.php') !== false || strpos($location, $slug) !== false) {
                // Don't redirect, let the 404 handler take over
                return home_url('/404');
            }
        }
        
        return $location;
    }
    
    /**
     * Block wp-login.php access directly
     */
    public function block_wp_login_access() {
        if (!$this->is_enabled() || is_user_logged_in()) {
            return;
        }
        
        $request_uri = strtolower($_SERVER['REQUEST_URI']);
        $slug = $this->get_slug();
        
        // Allow access if using custom slug
        if (strpos($request_uri, '/' . $slug) !== false) {
            return;
        }
        
        // Block wp-login.php when not using custom slug
        wp_die('404 - Page Not Found', '404 - Not Found', array('response' => 404));
        exit;
    }
    
    /**
     * Generate default slug on activation
     */
    public static function generate_default_slug() {
        return 'os224';
    }
}

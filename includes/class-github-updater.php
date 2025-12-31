<?php
/**
 * GitHub Updater Class
 * 
 * Handles automatic updates from GitHub releases
 * 
 * @package OSWP_Secure_Login
 * @since 2.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class OSWP_GitHub_Updater {
    
    private $plugin_slug;
    private $plugin_basename;
    private $github_repo;
    private $plugin_file;
    private $github_response;
    
    /**
     * Constructor
     */
    public function __construct($plugin_file) {
        $this->plugin_file = $plugin_file;
        $this->plugin_basename = plugin_basename($plugin_file);
        $this->plugin_slug = dirname($this->plugin_basename);
        
        // Set GitHub repository (format: username/repo-name)
        $this->github_repo = 'onliveserver/oswp-secure-login';
        
        // Hook into WordPress
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);
        add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
    }
    
    /**
     * Get GitHub API URL
     */
    private function get_api_url() {
        return "https://api.github.com/repos/{$this->github_repo}/releases/latest";
    }
    
    /**
     * Get GitHub repository data
     */
    private function get_github_data() {
        // Check cache first
        $cache_key = 'oswp_github_' . md5($this->github_repo);
        $cache_allowed = true;
        
        if ($cache_allowed) {
            $cached_data = get_transient($cache_key);
            if ($cached_data !== false) {
                return $cached_data;
            }
        }
        
        // Fetch from GitHub
        $response = wp_remote_get($this->get_api_url(), array(
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
            ),
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);
        
        if (!$data || !isset($data->tag_name)) {
            return false;
        }
        
        // Cache for 12 hours
        set_transient($cache_key, $data, 12 * HOUR_IN_SECONDS);
        
        return $data;
    }
    
    /**
     * Check for plugin update
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $github_data = $this->get_github_data();
        
        if (!$github_data) {
            return $transient;
        }
        
        // Get current version
        $plugin_data = get_plugin_data($this->plugin_file);
        $current_version = $plugin_data['Version'];
        
        // Get latest version from GitHub (remove 'v' prefix if present)
        $latest_version = ltrim($github_data->tag_name, 'v');
        
        // Compare versions
        if (version_compare($current_version, $latest_version, '<')) {
            $plugin_info = array(
                'slug' => $this->plugin_slug,
                'plugin' => $this->plugin_basename,
                'new_version' => $latest_version,
                'url' => $github_data->html_url,
                'package' => $this->get_download_url($github_data),
                'tested' => '6.7',
                'requires_php' => '7.2',
            );
            
            $transient->response[$this->plugin_basename] = (object) $plugin_info;
        }
        
        return $transient;
    }
    
    /**
     * Get download URL from GitHub release
     */
    private function get_download_url($github_data) {
        // Try to get ZIP asset first
        if (isset($github_data->assets) && is_array($github_data->assets)) {
            foreach ($github_data->assets as $asset) {
                if (strpos($asset->name, '.zip') !== false) {
                    return $asset->browser_download_url;
                }
            }
        }
        
        // Fallback to zipball URL
        return isset($github_data->zipball_url) ? $github_data->zipball_url : '';
    }
    
    /**
     * Provide plugin information for update screen
     */
    public function plugin_info($false, $action, $response) {
        if ($action !== 'plugin_information') {
            return $false;
        }
        
        if (!isset($response->slug) || $response->slug !== $this->plugin_slug) {
            return $false;
        }
        
        $github_data = $this->get_github_data();
        
        if (!$github_data) {
            return $false;
        }
        
        $plugin_data = get_plugin_data($this->plugin_file);
        $latest_version = ltrim($github_data->tag_name, 'v');
        
        $plugin_info = new stdClass();
        $plugin_info->name = $plugin_data['Name'];
        $plugin_info->slug = $this->plugin_slug;
        $plugin_info->version = $latest_version;
        $plugin_info->author = $plugin_data['Author'];
        $plugin_info->homepage = $plugin_data['PluginURI'];
        $plugin_info->download_link = $this->get_download_url($github_data);
        $plugin_info->requires = '5.0';
        $plugin_info->tested = '6.7';
        $plugin_info->requires_php = '7.2';
        $plugin_info->last_updated = $github_data->published_at;
        $plugin_info->sections = array(
            'description' => $plugin_data['Description'],
            'changelog' => $this->parse_changelog($github_data),
        );
        
        if (isset($github_data->body)) {
            $plugin_info->sections['changelog'] = wp_kses_post($github_data->body);
        }
        
        return $plugin_info;
    }
    
    /**
     * Parse changelog from GitHub release notes
     */
    private function parse_changelog($github_data) {
        if (!isset($github_data->body)) {
            return 'No changelog available.';
        }
        
        return wp_kses_post($github_data->body);
    }
    
    /**
     * After update installation
     */
    public function after_install($response, $hook_extra, $result) {
        global $wp_filesystem;
        
        // Move files to the correct location
        $install_directory = plugin_dir_path($this->plugin_file);
        $wp_filesystem->move($result['destination'], $install_directory);
        $result['destination'] = $install_directory;
        
        // Reactivate plugin if it was active
        if ($this->is_plugin_active()) {
            activate_plugin($this->plugin_basename);
        }
        
        return $result;
    }
    
    /**
     * Check if plugin is active
     */
    private function is_plugin_active() {
        return is_plugin_active($this->plugin_basename);
    }
    
    /**
     * Add GitHub link to plugin row meta
     */
    public function plugin_row_meta($links, $file) {
        if ($file === $this->plugin_basename) {
            $links[] = '<a href="https://github.com/' . $this->github_repo . '" target="_blank">View on GitHub</a>';
            $links[] = '<a href="https://github.com/' . $this->github_repo . '/releases" target="_blank">Releases</a>';
        }
        return $links;
    }
    
    /**
     * Clear update cache (useful for debugging)
     */
    public function clear_cache() {
        $cache_key = 'oswp_github_' . md5($this->github_repo);
        delete_transient($cache_key);
    }
}

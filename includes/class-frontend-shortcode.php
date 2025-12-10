<?php

class Boat_Chatbot_Frontend_Shortcode {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_shortcode('boat_chatbot', array($this, 'render_chatbot'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('wp_footer', array($this, 'render_chatbot_html'));
    }
    
    public function render_chatbot($atts) {
        // This shortcode just enqueues scripts, HTML is in footer
        return '<div id="boat-chatbot-container"></div>';
    }
    
    public function enqueue_frontend_scripts() {
        wp_enqueue_script('boat-chatbot-frontend', BOAT_CHATBOT_PLUGIN_URL . 'assets/frontend.js', array('jquery'), BOAT_CHATBOT_VERSION, true);
        wp_enqueue_style('boat-chatbot-frontend', BOAT_CHATBOT_PLUGIN_URL . 'assets/frontend.css', array(), BOAT_CHATBOT_VERSION);
        
        // Localize script with REST API URL
        wp_localize_script('boat-chatbot-frontend', 'boat_chatbot_ajax', array(
            'rest_url' => rest_url('boat-chatbot/v1'),
            'rest_nonce' => wp_create_nonce('wp_rest'), // WordPress REST API nonce
            'ajax_url' => admin_url('admin-ajax.php'), // Keep for backward compatibility
            'nonce' => wp_create_nonce('boat_chatbot_nonce') // Legacy nonce for AJAX
        ));
    }
    
    public function render_chatbot_html() {
        // Don't render chatbot widget on landing page or full chatbot page
        
        // Method 1: Check query vars (works when rewrite rules are set)
        $is_landing_page = get_query_var('boat_chatbot_landing');
        $is_full_chatbot = get_query_var('boat_chatbot_full');
        
        if ($is_landing_page || $is_full_chatbot) {
            return;
        }
        
        // Method 2: Check URL path directly (works even if rewrite rules aren't flushed)
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $parsed_uri = parse_url($request_uri);
        $path = isset($parsed_uri['path']) ? trim($parsed_uri['path'], '/') : '';
        
        // Remove WordPress base path if present (for subdirectory installs)
        $home_path = parse_url(home_url(), PHP_URL_PATH);
        if ($home_path && $home_path !== '/') {
            $home_path = trim($home_path, '/');
            if (strpos($path, $home_path) === 0) {
                $path = substr($path, strlen($home_path));
                $path = trim($path, '/');
            }
        }
        
        // Check for landing page or full chatbot page in path
        if ($path === 'virtual-yachtbroker' || $path === 'boat-chatbot' || 
            strpos($path, 'virtual-yachtbroker') !== false || 
            strpos($path, 'boat-chatbot') !== false ||
            strpos($path, '/virtual-yachtbroker') !== false ||
            strpos($path, '/boat-chatbot') !== false) {
            return;
        }
        
        // Method 3: Check GET parameters
        if (isset($_GET['boat_chatbot_landing']) || isset($_GET['boat_chatbot_full'])) {
            return;
        }
        
        // Method 4: Check if the page has already been handled by landing page handler
        // (This prevents rendering if the landing page handler has already executed)
        global $wp;
        if (isset($wp->query_vars['boat_chatbot_landing']) || 
            isset($wp->query_vars['boat_chatbot_full'])) {
            return;
        }
        
        ?>
        <div id="boat-chatbot-widget">
            <div id="boat-chatbot-header">
                <h3><a href="https://boat.com" target="_blank">BOAT.COM</a>  BROKER</h3>
                <button id="boat-chatbot-close">×</button>
            </div>
            <div id="boat-chatbot-messages"></div>
            <div id="boat-chatbot-input">
                <input type="text" id="boat-chatbot-message" placeholder="<?php echo esc_attr(get_option('boat_chatbot_input_placeholder', 'Type your query here...')); ?>" />
                <button id="boat-chatbot-send">Send</button>
            </div>
        </div>
        <button id="boat-chatbot-toggle" class="boat-chatbot-closed">
            <span>💬</span>
        </button>
        <?php
    }
}
?>

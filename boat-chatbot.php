<?php
/**
 * Plugin Name: Boat Chatbot AI
 * Plugin URI: https://yourclientwebsite.com
 * Description: AI-powered chatbot for boat listings and general boating knowledge
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BOAT_CHATBOT_VERSION', '1.0.0');
define('BOAT_CHATBOT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BOAT_CHATBOT_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Main plugin class
class Boat_Chatbot_Plugin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
        
        // Schedule periodic sync (daily)
        add_action('boat_chatbot_daily_sync', array($this, 'daily_sync'));
        
        // Register cron schedule if not exists
        if (!wp_next_scheduled('boat_chatbot_daily_sync')) {
            wp_schedule_event(time(), 'daily', 'boat_chatbot_daily_sync');
        }
    }
    
    /**
     * Daily sync job - syncs pending records to Pinecone
     */
    public function daily_sync() {
        $sync_manager = Boat_Chatbot_Vector_Sync_Manager::get_instance();
        $pending_ids = $sync_manager->get_records_needing_sync(500); // Sync up to 500 records per day
        
        if (!empty($pending_ids)) {
            $sync_manager->sync_records_batch($pending_ids);
        }
    }
    
    public function init() {
        // Load dependencies
        $this->load_dependencies();
        
        // Initialize components
        $this->init_components();
    }
    
    private function load_dependencies() {
        // Admin settings
        require_once BOAT_CHATBOT_PLUGIN_PATH . 'includes/class-admin-settings.php';
        
        // Chatbot handler
        require_once BOAT_CHATBOT_PLUGIN_PATH . 'includes/class-chatbot-handler.php';
        
        // Frontend shortcode
        // require_once BOAT_CHATBOT_PLUGIN_PATH . 'includes/class-frontend-shortcode.php';
        
        // Landing page
        require_once BOAT_CHATBOT_PLUGIN_PATH . 'includes/class-landing-page.php';
        
        // Note: Core dependencies (groq-embeddings, pinecone, database, vector-sync) 
        // are loaded early in boat_chatbot_load_dependencies() to ensure they're 
        // available for AJAX requests
    }
    
    private function init_components() {
        Boat_Chatbot_Admin_Settings::get_instance();
        Boat_Chatbot_Handler::get_instance();
        // Boat_Chatbot_Frontend_Shortcode::get_instance();
        Boat_Chatbot_Landing_Page::get_instance();
    }
}

// Load dependencies early for AJAX and admin requests
function boat_chatbot_load_dependencies() {
    // Ensure constants are defined
    if (!defined('BOAT_CHATBOT_PLUGIN_PATH')) {
        define('BOAT_CHATBOT_PLUGIN_PATH', plugin_dir_path(__FILE__));
    }
    
    // Load core dependencies that might be needed for AJAX
    $includes_path = BOAT_CHATBOT_PLUGIN_PATH . 'includes/';
    
    if (file_exists($includes_path . 'class-groq-embeddings-manager.php')) {
        require_once $includes_path . 'class-groq-embeddings-manager.php';
    }
    if (file_exists($includes_path . 'class-pinecone-manager.php')) {
        require_once $includes_path . 'class-pinecone-manager.php';
    }
    if (file_exists($includes_path . 'class-database-manager.php')) {
        require_once $includes_path . 'class-database-manager.php';
    }
    if (file_exists($includes_path . 'class-vector-sync-manager.php')) {
        require_once $includes_path . 'class-vector-sync-manager.php';
    }
    if (file_exists($includes_path . 'class-sparse-vector-generator.php')) {
        require_once $includes_path . 'class-sparse-vector-generator.php';
    }
    if (file_exists($includes_path . 'class-reranking-manager.php')) {
        require_once $includes_path . 'class-reranking-manager.php';
    }
    if (file_exists($includes_path . 'class-redis-cache-manager.php')) {
        require_once $includes_path . 'class-redis-cache-manager.php';
    }
    if (file_exists($includes_path . 'class-speech-handler.php')) {
        require_once $includes_path . 'class-speech-handler.php';
    }
}
// Load early with priority 5, before the main plugin init
add_action('plugins_loaded', 'boat_chatbot_load_dependencies', 5);

// Load WP-CLI command if WP-CLI is available
if (defined('WP_CLI') && WP_CLI) {
    add_action('plugins_loaded', 'boat_chatbot_load_wp_cli_command', 10);
}
function boat_chatbot_load_wp_cli_command() {
    if (!defined('BOAT_CHATBOT_PLUGIN_PATH')) {
        define('BOAT_CHATBOT_PLUGIN_PATH', plugin_dir_path(__FILE__));
    }
    $wp_cli_file = BOAT_CHATBOT_PLUGIN_PATH . 'includes/class-wp-cli-sync-command.php';
    if (file_exists($wp_cli_file)) {
        require_once $wp_cli_file;
    }
}

// Initialize the plugin
function boat_chatbot_init() {
    return Boat_Chatbot_Plugin::get_instance();
}
add_action('plugins_loaded', 'boat_chatbot_init', 10);

// Activation hook
register_activation_hook(__FILE__, 'boat_chatbot_activate');
function boat_chatbot_activate() {
    // Create necessary database tables for logs
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'boat_chatbot_logs';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        timestamp datetime DEFAULT CURRENT_TIMESTAMP,
        user_message text NOT NULL,
        classified_intent varchar(100),
        sql_query_used text,
        ai_prompt_sent text,
        ai_response_received text,
        full_response_sent_to_user text,
        response_time float,
        performance_metrics text,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    // Check if performance_metrics column exists, add if not (for upgrades)
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'performance_metrics'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN performance_metrics text AFTER response_time");
    }
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Create vector sync tracking table
    $sync_table_name = $wpdb->prefix . 'boat_chatbot_vector_sync';
    $sync_sql = "CREATE TABLE $sync_table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        record_id bigint(20) NOT NULL,
        status varchar(20) DEFAULT 'pending',
        last_synced datetime,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY record_id (record_id),
        KEY status (status),
        KEY last_synced (last_synced)
    ) $charset_collate;";
    
    dbDelta($sync_sql);
    
    // Flush rewrite rules for landing page
    add_rewrite_rule('^virtual-yachtbroker/?$', 'index.php?boat_chatbot_landing=1', 'top');
    flush_rewrite_rules();
}
?>
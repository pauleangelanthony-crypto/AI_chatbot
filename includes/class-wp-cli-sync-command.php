<?php

/**
 * WP-CLI Command for Boat Chatbot Vector Sync
 * 
 * Allows syncing records via command line to avoid timeout issues
 * 
 * Usage:
 *   wp boat-chatbot sync all
 *   wp boat-chatbot sync pending [--limit=<number>]
 *   wp boat-chatbot sync records <id1> [<id2>...] [--batch-size=<number>]
 *   wp boat-chatbot sync all [--limit=<number>] [--offset=<number>]
 */
class Boat_Chatbot_WP_CLI_Sync_Command {
    
    /**
     * Sync all records from database
     * 
     * ## OPTIONS
     * 
     * [--limit=<number>]
     * : Limit the number of records to sync
     * 
     * [--offset=<number>]
     * : Offset for batch processing (default: 0)
     * 
     * ## EXAMPLES
     * 
     *     # Sync all records
     *     wp boat-chatbot sync all
     * 
     *     # Sync first 1000 records
     *     wp boat-chatbot sync all --limit=1000
     * 
     *     # Sync records starting from offset 1000
     *     wp boat-chatbot sync all --limit=500 --offset=1000
     * 
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function all($args, $assoc_args) {
        $this->ensure_dependencies();
        
        $sync_manager = Boat_Chatbot_Vector_Sync_Manager::get_instance();
        
        // Validate required keys
        $validation = $sync_manager->validate_required_keys();
        if (!$validation['valid']) {
            WP_CLI::error('Configuration error: ' . $validation['message']);
            return;
        }
        
        $limit = isset($assoc_args['limit']) ? intval($assoc_args['limit']) : null;
        $offset = isset($assoc_args['offset']) ? intval($assoc_args['offset']) : 0;
        
        WP_CLI::log('Starting sync of all records...');
        if ($limit) {
            WP_CLI::log("Limit: {$limit}, Offset: {$offset}");
        }
        
        $start_time = microtime(true);
        $results = $sync_manager->sync_all_records($limit, $offset);
        $end_time = microtime(true);
        $duration = round($end_time - $start_time, 2);
        
        $this->display_results($results, $duration);
    }
    
    /**
     * Sync pending records that need syncing
     * 
     * ## OPTIONS
     * 
     * [--limit=<number>]
     * : Maximum number of pending records to sync (default: 100)
     * 
     * ## EXAMPLES
     * 
     *     # Sync up to 100 pending records
     *     wp boat-chatbot sync pending
     * 
     *     # Sync up to 500 pending records
     *     wp boat-chatbot sync pending --limit=500
     * 
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function pending($args, $assoc_args) {
        $this->ensure_dependencies();
        
        $sync_manager = Boat_Chatbot_Vector_Sync_Manager::get_instance();
        
        // Validate required keys
        $validation = $sync_manager->validate_required_keys();
        if (!$validation['valid']) {
            WP_CLI::error('Configuration error: ' . $validation['message']);
            return;
        }
        
        $limit = isset($assoc_args['limit']) ? intval($assoc_args['limit']) : 100;
        
        WP_CLI::log("Fetching up to {$limit} pending records...");
        
        $pending_ids = $sync_manager->get_records_needing_sync($limit);
        
        if (empty($pending_ids)) {
            WP_CLI::success('No records need syncing.');
            return;
        }
        
        WP_CLI::log('Found ' . count($pending_ids) . ' records needing sync.');
        
        $start_time = microtime(true);
        $results = $sync_manager->sync_records_batch($pending_ids);
        $end_time = microtime(true);
        $duration = round($end_time - $start_time, 2);
        
        $this->display_results($results, $duration);
    }
    
    /**
     * Sync specific records by ID
     * 
     * ## OPTIONS
     * 
     * [--batch-size=<number>]
     * : Batch size for processing (default: 50)
     * 
     * ## EXAMPLES
     * 
     *     # Sync specific records
     *     wp boat-chatbot sync records 123 456 789
     * 
     *     # Sync with custom batch size
     *     wp boat-chatbot sync records 123 456 789 --batch-size=25
     * 
     * @param array $args Positional arguments (record IDs)
     * @param array $assoc_args Associative arguments
     */
    public function records($args, $assoc_args) {
        if (empty($args)) {
            WP_CLI::error('Please provide at least one record ID.');
            return;
        }
        
        $this->ensure_dependencies();
        
        $sync_manager = Boat_Chatbot_Vector_Sync_Manager::get_instance();
        
        // Validate required keys
        $validation = $sync_manager->validate_required_keys();
        if (!$validation['valid']) {
            WP_CLI::error('Configuration error: ' . $validation['message']);
            return;
        }
        
        // Extract IDs from bracket-wrapped strings if needed
        $record_ids = $this->extract_record_ids($args);
        
        WP_CLI::log('Syncing ' . count($record_ids) . ' specific record(s)...');
        WP_CLI::log('Record IDs: ' . implode(', ', $record_ids));
        
        $start_time = microtime(true);
        $results = $sync_manager->sync_records_batch($record_ids);
        $end_time = microtime(true);
        $duration = round($end_time - $start_time, 2);
        
        $this->display_results($results, $duration);
    }
    
    /**
     * Extract record IDs from array, handling bracket-wrapped strings like "[2760467]"
     * 
     * @param array $record_ids Array of record IDs (may contain strings with brackets)
     * @return array Array of integer record IDs
     */
    private function extract_record_ids($record_ids) {
        if (!is_array($record_ids)) {
            return array();
        }
        
        $extracted_ids = array();
        
        foreach ($record_ids as $id) {
            // If it's already an integer, use it directly
            if (is_int($id)) {
                $extracted_ids[] = $id;
                continue;
            }
            
            // Convert to string for processing
            $id_str = (string)$id;
            
            // Remove brackets if present (e.g., "[2760467]" -> "2760467")
            $id_str = trim($id_str, '[]');
            
            // Extract numeric value
            if (is_numeric($id_str)) {
                $extracted_ids[] = intval($id_str);
            }
        }
        
        return $extracted_ids;
    }
    
    /**
     * Display sync results in a formatted way
     * 
     * @param array $results Sync results
     * @param float $duration Duration in seconds
     */
    private function display_results($results, $duration) {
        if (isset($results['error']) && $results['error']) {
            WP_CLI::error('Sync failed: ' . (isset($results['message']) ? $results['message'] : 'Unknown error'));
            return;
        }
        
        $total = isset($results['total']) ? $results['total'] : 0;
        $success = isset($results['success']) ? $results['success'] : 0;
        $failed = isset($results['failed']) ? $results['failed'] : 0;
        
        WP_CLI::log('');
        WP_CLI::log('=== Sync Results ===');
        WP_CLI::log("Total records: {$total}");
        WP_CLI::log("Successful: {$success}");
        WP_CLI::log("Failed: {$failed}");
        WP_CLI::log("Duration: {$duration} seconds");
        
        if ($duration > 0 && $total > 0) {
            $avg_time = round($duration / $total, 3);
            WP_CLI::log("Average time per record: {$avg_time} seconds");
        }
        
        // Display error summary if available
        if (isset($results['error_summary']) && !empty($results['error_summary'])) {
            WP_CLI::log('');
            WP_CLI::log('=== Error Summary ===');
            foreach ($results['error_summary'] as $error_type => $count) {
                WP_CLI::log("{$error_type}: {$count}");
            }
        }
        
        // Display error details if available (limit to first 10)
        if (isset($results['error_details']) && !empty($results['error_details'])) {
            WP_CLI::log('');
            WP_CLI::log('=== Error Details (first 10) ===');
            $error_count = min(10, count($results['error_details']));
            for ($i = 0; $i < $error_count; $i++) {
                WP_CLI::log(($i + 1) . '. ' . $results['error_details'][$i]);
            }
            if (count($results['error_details']) > 10) {
                WP_CLI::log('... and ' . (count($results['error_details']) - 10) . ' more errors');
            }
        }
        
        WP_CLI::log('');
        
        if ($failed === 0) {
            WP_CLI::success('All records synced successfully!');
        } elseif ($success > 0) {
            WP_CLI::warning("Sync completed with {$failed} failure(s).");
        } else {
            WP_CLI::error('All records failed to sync.');
        }
    }
    
    /**
     * Ensure all required dependencies are loaded
     */
    private function ensure_dependencies() {
        // Ensure core dependencies are loaded
        if (!function_exists('boat_chatbot_load_dependencies')) {
            WP_CLI::error('Plugin dependencies not loaded. Please ensure the plugin is activated.');
            return;
        }
        
        boat_chatbot_load_dependencies();
        
        if (!class_exists('Boat_Chatbot_Vector_Sync_Manager')) {
            WP_CLI::error('Vector Sync Manager class not found. Please check plugin installation.');
            return;
        }
    }
}

// Register WP-CLI command using the cli_init hook
// This ensures WordPress is fully loaded before registering the command
if (defined('WP_CLI') && WP_CLI) {
    add_action('cli_init', function() {
        if (class_exists('WP_CLI')) {
            WP_CLI::add_command('boat-chatbot sync', 'Boat_Chatbot_WP_CLI_Sync_Command');
        }
    });
}

?>

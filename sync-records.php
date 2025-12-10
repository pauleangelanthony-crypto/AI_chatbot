<?php
/**
 * Standalone PHP Script for Boat Chatbot Vector Sync
 * 
 * This script can be run directly with PHP CLI to sync records without using WP-CLI
 * Failed records are automatically logged to logs/sync-failures-YYYY-MM-DD.log
 * 
 * Usage:
 *   php sync-records.php all
 *   php sync-records.php all --limit=1000 --offset=0
 *   php sync-records.php pending [--limit=100]
 *   php sync-records.php records 123 456 789
 * 
 * Make sure to run this from the WordPress root directory or adjust the wp-load.php path below
 * 
 * Log Files:
 *   - Location: {plugin_dir}/logs/sync-failures-YYYY-MM-DD.log
 *   - Format: Timestamp, Record ID, Failure Reason
 *   - Automatically created if logs directory doesn't exist
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

// Find WordPress root directory
// Adjust this path if your WordPress installation is in a different location
$wp_load_paths = array(
    __DIR__ . '/../../../../wp-load.php',  // If script is in wp-content/plugins/boat-chatbot/
    __DIR__ . '/../../../wp-load.php',     // Alternative path
    dirname(__DIR__) . '/../../../../wp-load.php', // From includes/ directory
);

$wp_load = null;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        $wp_load = $path;
        break;
    }
}

if (!$wp_load) {
    // Try to find it relative to current directory
    $current_dir = getcwd();
    $wp_load = $current_dir . '/wp-load.php';
    if (!file_exists($wp_load)) {
        die("Error: Could not find wp-load.php. Please run this script from the WordPress root directory or edit the script to set the correct path.\n");
    }
}

// Load WordPress
require_once $wp_load;

// Ensure plugin dependencies are loaded
if (function_exists('boat_chatbot_load_dependencies')) {
    boat_chatbot_load_dependencies();
} else {
    // Manual loading if function doesn't exist
    $plugin_path = dirname(__FILE__);
    $includes_path = $plugin_path . '/includes/';
    
    if (file_exists($includes_path . 'class-database-manager.php')) {
        require_once $includes_path . 'class-database-manager.php';
    }
    if (file_exists($includes_path . 'class-groq-embeddings-manager.php')) {
        require_once $includes_path . 'class-groq-embeddings-manager.php';
    }
    if (file_exists($includes_path . 'class-pinecone-manager.php')) {
        require_once $includes_path . 'class-pinecone-manager.php';
    }
    if (file_exists($includes_path . 'class-vector-sync-manager.php')) {
        require_once $includes_path . 'class-vector-sync-manager.php';
    }
}

// Check if Vector Sync Manager class exists
if (!class_exists('Boat_Chatbot_Vector_Sync_Manager')) {
    die("Error: Vector Sync Manager class not found. Please ensure the plugin is installed and activated.\n");
}

/**
 * Extract record IDs from array, handling bracket-wrapped strings like "[2760467]"
 * 
 * @param array $record_ids Array of record IDs (may contain strings with brackets)
 * @return array Array of integer record IDs
 */
function extract_record_ids($record_ids) {
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

// Parse command line arguments
$args = array_slice($argv, 1);
$command = isset($args[0]) ? $args[0] : null;
$options = array();

// Parse options (--key=value format)
for ($i = 1; $i < count($args); $i++) {
    if (strpos($args[$i], '--') === 0) {
        $option = substr($args[$i], 2);
        if (strpos($option, '=') !== false) {
            list($key, $value) = explode('=', $option, 2);
            $options[$key] = $value;
        } else {
            $options[$option] = true;
        }
    } else {
        // Positional arguments (for record IDs)
        if (!isset($options['record_ids'])) {
            $options['record_ids'] = array();
        }
        $options['record_ids'][] = $args[$i];
    }
}

// Display usage if no command provided
if (!$command) {
    display_usage();
    exit(1);
}

// Get sync manager instance
$sync_manager = Boat_Chatbot_Vector_Sync_Manager::get_instance();

// Validate required keys
$validation = $sync_manager->validate_required_keys();
if (!$validation['valid']) {
    echo "ERROR: Configuration error: " . $validation['message'] . "\n";
    exit(1);
}

// Setup logging
$log_dir = __DIR__ . '/logs';
if (!file_exists($log_dir)) {
    if (!mkdir($log_dir, 0755, true)) {
        echo "WARNING: Could not create logs directory. Logging will be disabled.\n";
        $log_dir = null;
    }
}

$log_file = null;
if ($log_dir) {
    $log_filename = 'sync-failures-' . date('Y-m-d') . '.log';
    $log_file = $log_dir . '/' . $log_filename;
}

// Execute command
$start_time = microtime(true);
$results = null;

switch ($command) {
    case 'all':
        $limit = isset($options['limit']) ? intval($options['limit']) : null;
        $offset = isset($options['offset']) ? intval($options['offset']) : 0;
        
        echo "Starting sync of all records...\n";
        if ($limit) {
            echo "Limit: {$limit}, Offset: {$offset}\n";
        }
        
        $results = $sync_manager->sync_all_records($limit, $offset);
        break;
        
    case 'pending':
        $limit = isset($options['limit']) ? intval($options['limit']) : 100;
        
        echo "Fetching up to {$limit} pending records...\n";
        
        $pending_ids = $sync_manager->get_records_needing_sync($limit);
        
        if (empty($pending_ids)) {
            echo "SUCCESS: No records need syncing.\n";
            exit(0);
        }
        
        echo "Found " . count($pending_ids) . " records needing sync.\n";
        $results = $sync_manager->sync_records_batch($pending_ids);
        break;
        
    case 'records':
        if (empty($options['record_ids'])) {
            echo "ERROR: Please provide at least one record ID.\n";
            exit(1);
        }
        
        // Extract IDs from bracket-wrapped strings if needed
        $record_ids = extract_record_ids($options['record_ids']);
        
        echo "Syncing " . count($record_ids) . " specific record(s)...\n";
        echo "Record IDs: " . implode(', ', $record_ids) . "\n";
        
        $results = $sync_manager->sync_records_batch($record_ids);
        break;
        
    default:
        echo "ERROR: Unknown command '{$command}'\n\n";
        display_usage();
        exit(1);
}

$end_time = microtime(true);
$duration = round($end_time - $start_time, 2);

// Log failed records to file
if ($log_file && $results && isset($results['error_details']) && !empty($results['error_details'])) {
    log_failed_records($log_file, $results, $command, $options);
}

// Display results
display_results($results, $duration);

/**
 * Display usage information
 */
function display_usage() {
    echo "Boat Chatbot Vector Sync - Standalone Script\n";
    echo "=============================================\n\n";
    echo "Usage:\n";
    echo "  php sync-records.php <command> [options] [arguments]\n\n";
    echo "Commands:\n";
    echo "  all                    Sync all records from database\n";
    echo "  pending                Sync pending records that need syncing\n";
    echo "  records <id1> [id2...] Sync specific records by ID\n\n";
    echo "Options:\n";
    echo "  --limit=<number>       Limit the number of records to sync\n";
    echo "  --offset=<number>      Offset for batch processing (default: 0)\n\n";
    echo "Examples:\n";
    echo "  php sync-records.php all\n";
    echo "  php sync-records.php all --limit=1000 --offset=0\n";
    echo "  php sync-records.php pending\n";
    echo "  php sync-records.php pending --limit=500\n";
    echo "  php sync-records.php records 123 456 789\n\n";
    echo "Note: Run this script from the WordPress root directory.\n";
}

/**
 * Log failed records to file
 * 
 * @param string $log_file Path to log file
 * @param array $results Sync results
 * @param string $command Command that was executed
 * @param array $options Command options
 */
function log_failed_records($log_file, $results, $command, $options) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "\n" . str_repeat('=', 80) . "\n";
    $log_entry .= "Sync Run: {$timestamp}\n";
    $log_entry .= "Command: {$command}\n";
    
    if (isset($options['limit'])) {
        $log_entry .= "Limit: {$options['limit']}\n";
    }
    if (isset($options['offset'])) {
        $log_entry .= "Offset: {$options['offset']}\n";
    }
    if (isset($options['record_ids'])) {
        $log_entry .= "Record IDs: " . implode(', ', $options['record_ids']) . "\n";
    }
    
    $log_entry .= "Total: " . (isset($results['total']) ? $results['total'] : 0) . " | ";
    $log_entry .= "Success: " . (isset($results['success']) ? $results['success'] : 0) . " | ";
    $log_entry .= "Failed: " . (isset($results['failed']) ? $results['failed'] : 0) . "\n";
    $log_entry .= str_repeat('-', 80) . "\n";
    
    // Extract record IDs from error details
    $failed_records = array();
    
    if (isset($results['error_details']) && is_array($results['error_details'])) {
        foreach ($results['error_details'] as $error_detail) {
            // Try to extract record ID from error message
            // Format: "Record {ID}: {reason}" or "Record {ID} - {reason}"
            if (preg_match('/Record\s+(\d+)[:\-\s]+(.+)/i', $error_detail, $matches)) {
                $record_id = intval($matches[1]);
                $reason = trim($matches[2]);
                if (empty($reason)) {
                    $reason = $error_detail;
                }
                $failed_records[] = array(
                    'id' => $record_id,
                    'reason' => $reason
                );
            } elseif (preg_match('/Record\s+(\d+)/i', $error_detail, $matches)) {
                // Format: "Record {ID} {reason}" (no colon/dash)
                $record_id = intval($matches[1]);
                $reason = trim(str_replace($matches[0], '', $error_detail));
                if (empty($reason)) {
                    $reason = $error_detail;
                }
                $failed_records[] = array(
                    'id' => $record_id,
                    'reason' => $reason
                );
            } else {
                // If we can't extract ID, log the full error with UNKNOWN ID
                // This ensures we always log something, even if ID extraction fails
                $failed_records[] = array(
                    'id' => null,
                    'reason' => $error_detail
                );
            }
        }
    }
    
    // Remove duplicates (same record ID with same reason)
    $unique_failures = array();
    foreach ($failed_records as $failure) {
        $key = ($failure['id'] !== null ? $failure['id'] : 'null') . '|' . md5($failure['reason']);
        if (!isset($unique_failures[$key])) {
            $unique_failures[$key] = $failure;
        }
    }
    $failed_records = array_values($unique_failures);
    
    // Sort by record ID for easier reading
    usort($failed_records, function($a, $b) {
        if ($a['id'] === null && $b['id'] === null) return 0;
        if ($a['id'] === null) return 1;
        if ($b['id'] === null) return -1;
        return $a['id'] - $b['id'];
    });
    
    if (empty($failed_records)) {
        $log_entry .= "No failed records to log.\n";
    } else {
        $log_entry .= "Failed Records: " . count($failed_records) . "\n\n";
        
        foreach ($failed_records as $failure) {
            $record_id = $failure['id'] !== null ? $failure['id'] : 'UNKNOWN';
            $reason = $failure['reason'];
            // Ensure both ID and error are always present in log
            $log_entry .= "[{$timestamp}] ID: {$record_id} | Error: {$reason}\n";
        }
    }
    
    // Add error summary if available
    if (isset($results['error_summary']) && is_array($results['error_summary']) && !empty($results['error_summary'])) {
        $log_entry .= "\nError Summary:\n";
        foreach ($results['error_summary'] as $error_type => $count) {
            $log_entry .= "  - {$error_type}: {$count}\n";
        }
    }
    
    $log_entry .= str_repeat('=', 80) . "\n";
    
    // Append to log file
    if (file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX) === false) {
        echo "WARNING: Could not write to log file: {$log_file}\n";
    } else {
        echo "\nFailed records logged to: {$log_file}\n";
    }
}

/**
 * Display sync results
 */
function display_results($results, $duration) {
    if (isset($results['error']) && $results['error']) {
        echo "ERROR: Sync failed: " . (isset($results['message']) ? $results['message'] : 'Unknown error') . "\n";
        exit(1);
    }
    
    $total = isset($results['total']) ? $results['total'] : 0;
    $success = isset($results['success']) ? $results['success'] : 0;
    $failed = isset($results['failed']) ? $results['failed'] : 0;
    
    echo "\n";
    echo "=== Sync Results ===\n";
    echo "Total records: {$total}\n";
    echo "Successful: {$success}\n";
    echo "Failed: {$failed}\n";
    echo "Duration: {$duration} seconds\n";
    
    if ($duration > 0 && $total > 0) {
        $avg_time = round($duration / $total, 3);
        echo "Average time per record: {$avg_time} seconds\n";
    }
    
    // Display error summary if available
    if (isset($results['error_summary']) && !empty($results['error_summary'])) {
        echo "\n";
        echo "=== Error Summary ===\n";
        foreach ($results['error_summary'] as $error_type => $count) {
            echo "{$error_type}: {$count}\n";
        }
    }
    
    // Display error details if available (limit to first 10)
    if (isset($results['error_details']) && !empty($results['error_details'])) {
        echo "\n";
        echo "=== Error Details (first 10) ===\n";
        $error_count = min(10, count($results['error_details']));
        for ($i = 0; $i < $error_count; $i++) {
            echo ($i + 1) . ". " . $results['error_details'][$i] . "\n";
        }
        if (count($results['error_details']) > 10) {
            echo "... and " . (count($results['error_details']) - 10) . " more errors\n";
        }
    }
    
    echo "\n";
    
    if ($failed === 0) {
        echo "SUCCESS: All records synced successfully!\n";
        exit(0);
    } elseif ($success > 0) {
        echo "WARNING: Sync completed with {$failed} failure(s).\n";
        exit(0);
    } else {
        echo "ERROR: All records failed to sync.\n";
        exit(1);
    }
}

?>


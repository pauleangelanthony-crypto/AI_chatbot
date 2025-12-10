<?php

class Boat_Chatbot_Handler {
    
    private static $instance = null;
    private $cache_group = 'boat_chatbot';
    private $cache_expiration = 300; // 5 minutes
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Register REST API endpoint
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Keep old AJAX for backward compatibility (can be removed later)
        add_action('wp_ajax_boat_chatbot_send_message', array($this, 'handle_chat_message'));
        add_action('wp_ajax_nopriv_boat_chatbot_send_message', array($this, 'handle_chat_message'));
        
        // Register async logging hook
        add_action('boat_chatbot_async_log', array($this, 'async_log_interaction'), 10, 5);
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
    
    public function async_log_interaction($user_message, $intent, $response, $response_time, $performance_log) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'boat_chatbot_logs',
            array(
                'user_message' => $user_message,
                'classified_intent' => $intent,
                'ai_response_received' => $response,
                'full_response_sent_to_user' => $response,
                'response_time' => $response_time,
                'performance_metrics' => json_encode($performance_log)
            ),
            array('%s', '%s', '%s', '%s', '%f', '%s')
        );
    }
    
    public function register_rest_routes() {
        register_rest_route('boat-chatbot/v1', '/send-message', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_rest_message'),
            'permission_callback' => array($this, 'check_rest_nonce'),
            'args' => array(
                'message' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'conversation_history' => array(
                    'required' => false,
                    'type' => 'array',
                    'default' => array(),
                    'validate_callback' => function($param) {
                        return is_array($param);
                    },
                ),
                'nonce' => array(
                    'required' => false,
                    'type' => 'string',
                ),
            ),
        ));
        
        // Endpoint for streaming responses
        register_rest_route('boat-chatbot/v1', '/send-message-stream', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_rest_message_stream'),
            'permission_callback' => array($this, 'check_rest_nonce_stream'), // Custom permission check for streaming
            'args' => array(
                'message' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'conversation_history' => array(
                    'required' => false,
                    'type' => 'array',
                    'default' => array(),
                    'validate_callback' => function($param) {
                        return is_array($param);
                    },
                ),
                'nonce' => array(
                    'required' => false,
                    'type' => 'string',
                ),
            ),
        ));
        
        // Endpoint for lazy loading more listings
        register_rest_route('boat-chatbot/v1', '/load-listings', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_load_listings'),
            'permission_callback' => array($this, 'check_rest_nonce'),
            'args' => array(
                'query' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'offset' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0,
                ),
                'limit' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 20,
                ),
                'nonce' => array(
                    'required' => false,
                    'type' => 'string',
                ),
            ),
        ));
        
        // Endpoint for vector search
        register_rest_route('boat-chatbot/v1', '/vector-search', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_vector_search'),
            'permission_callback' => array($this, 'check_rest_nonce'),
            'args' => array(
                'query' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'top_k' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 10,
                ),
                'nonce' => array(
                    'required' => false,
                    'type' => 'string',
                ),
            ),
        ));
        
        // Endpoint for syncing records to vector database
        // Can be called by Python scraper after updating SQL database
        register_rest_route('boat-chatbot/v1', '/sync-records', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_sync_records'),
            'permission_callback' => array($this, 'check_sync_permission'),
            'args' => array(
                'record_ids' => array(
                    'required' => false,
                    'type' => 'array',
                    'default' => array(),
                ),
                'sync_all' => array(
                    'required' => false,
                    'type' => 'boolean',
                    'default' => false,
                ),
                'api_key' => array(
                    'required' => true,
                    'type' => 'string',
                ),
            ),
        ));
        
        // Endpoint for deleting records from vector database
        // Can be called by Python scraper after deleting records from SQL database
        register_rest_route('boat-chatbot/v1', '/delete-records', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_delete_records'),
            'permission_callback' => array($this, 'check_sync_permission'),
            'args' => array(
                'record_ids' => array(
                    'required' => true,
                    'type' => 'array',
                    'validate_callback' => function($param) {
                        return is_array($param) && !empty($param);
                    },
                ),
                'api_key' => array(
                    'required' => true,
                    'type' => 'string',
                ),
            ),
        ));
        
        // Endpoint for Speech-to-Text
        register_rest_route('boat-chatbot/v1', '/speech-to-text', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_speech_to_text'),
            'permission_callback' => array($this, 'check_rest_nonce'),
            'args' => array(
                'audio' => array(
                    'required' => true,
                    'type' => 'string',
                ),
                'format' => array(
                    'required' => false,
                    'type' => 'string',
                    'default' => 'webm',
                ),
                'nonce' => array(
                    'required' => false,
                    'type' => 'string',
                ),
            ),
        ));
        
        // Endpoint for Text-to-Speech
        register_rest_route('boat-chatbot/v1', '/text-to-speech', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_text_to_speech'),
            'permission_callback' => array($this, 'check_rest_nonce'),
            'args' => array(
                'text' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'voice_id' => array(
                    'required' => false,
                    'type' => 'string',
                ),
                'nonce' => array(
                    'required' => false,
                    'type' => 'string',
                ),
            ),
        ));
    }
    
    /**
     * Check permission for streaming endpoint
     * More lenient check - we'll verify nonce in handler for streaming
     * This allows the handler to run and send proper error via SSE if auth fails
     * 
     * @param WP_REST_Request $request REST request
     * @return bool|WP_Error True if authorized, WP_Error otherwise
     */
    public function check_rest_nonce_stream($request) {
        // For streaming endpoints, we need to allow the handler to run
        // so it can send proper SSE error messages instead of HTTP 401
        // We'll do full nonce verification in the handler itself
        
        // Always return true here - handler will verify and send SSE error if needed
        // This prevents WordPress from sending HTTP 401 before our handler runs
        return true;
    }
    
    /**
     * Check permission for sync endpoint
     * Uses API key for authentication (can be called by Python scraper)
     * 
     * @param WP_REST_Request $request REST request
     * @return bool|WP_Error True if authorized, WP_Error otherwise
     */
    public function check_sync_permission($request) {
        $api_key = $request->get_param('api_key');
        $stored_api_key = get_option('boat_chatbot_sync_api_key');
        
        // If no API key is set, allow if user is authenticated admin
        if (empty($stored_api_key)) {
            return current_user_can('manage_options');
        }
        
        // Check API key
        if (!empty($api_key) && hash_equals($stored_api_key, $api_key)) {
            return true;
        }
        
        // Fallback: check if user is admin
        if (current_user_can('manage_options')) {
            return true;
        }
        
        return new WP_Error(
            'rest_forbidden',
            __('Invalid API key or insufficient permissions.', 'boat-chatbot'),
            array('status' => 403)
        );
    }
    
    /**
     * Handle sync records request
     * Called when SQL database is updated to sync vectors to Pinecone
     * 
     * @param WP_REST_Request $request REST request
     * @return WP_REST_Response REST response
     */
    /**
     * Ensure core dependencies are loaded (Groq Embeddings, Pinecone, Database)
     */
    private function ensure_core_dependencies() {
        $plugin_path = defined('BOAT_CHATBOT_PLUGIN_PATH') 
            ? BOAT_CHATBOT_PLUGIN_PATH 
            : plugin_dir_path(dirname(__FILE__));
        $includes_path = $plugin_path . 'includes/';
        
        // Load dependencies in order
        if (!class_exists('Boat_Chatbot_Database_Manager')) {
            $file = $includes_path . 'class-database-manager.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
        
        if (!class_exists('Boat_Chatbot_Groq_Embeddings_Manager')) {
            $file = $includes_path . 'class-groq-embeddings-manager.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
        
        if (!class_exists('Boat_Chatbot_Pinecone_Manager')) {
            $file = $includes_path . 'class-pinecone-manager.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
        
        return true;
    }
    
    /**
     * Ensure all dependencies for Vector Sync Manager are loaded
     */
    private function ensure_vector_sync_dependencies() {
        // First ensure core dependencies
        $this->ensure_core_dependencies();
        
        $plugin_path = defined('BOAT_CHATBOT_PLUGIN_PATH') 
            ? BOAT_CHATBOT_PLUGIN_PATH 
            : plugin_dir_path(dirname(__FILE__));
        $includes_path = $plugin_path . 'includes/';
        
        if (!class_exists('Boat_Chatbot_Vector_Sync_Manager')) {
            $file = $includes_path . 'class-vector-sync-manager.php';
            if (file_exists($file)) {
                require_once $file;
            } else {
                return false;
            }
        }
        
        return true;
    }
    
    public function handle_sync_records($request) {
        $sync_all = $request->get_param('sync_all');
        $record_ids = $request->get_param('record_ids');
        
        // Ensure all dependencies are loaded
        if (!$this->ensure_vector_sync_dependencies()) {
            return new WP_Error('class_not_found', 'Vector Sync Manager dependencies could not be loaded', array('status' => 500));
        }
        
        $sync_manager = Boat_Chatbot_Vector_Sync_Manager::get_instance();
        
        try {
            if ($sync_all) {
                // Sync all records
                $results = $sync_manager->sync_all_records();
            } elseif (!empty($record_ids) && is_array($record_ids)) {
                // Sync specific records - extract IDs from bracket-wrapped strings if needed
                $record_ids = $this->extract_record_ids($record_ids);
                $results = $sync_manager->sync_records_batch($record_ids);
            } else {
                // Sync pending records
                $pending_ids = $sync_manager->get_records_needing_sync(100);
                if (empty($pending_ids)) {
                    return rest_ensure_response(array(
                        'success' => true,
                        'message' => 'No records need syncing',
                        'results' => array('success' => 0, 'failed' => 0, 'total' => 0)
                    ));
                }
                $results = $sync_manager->sync_records_batch($pending_ids);
            }
            
            if (isset($results['error']) && $results['error']) {
                // Error handling without logging
            }
            
            return rest_ensure_response(array(
                'success' => true,
                'message' => sprintf(
                    'Sync completed: %d successful, %d failed out of %d total',
                    $results['success'],
                    $results['failed'],
                    $results['total']
                ),
                'results' => $results
            ));
        } catch (Exception $e) {
            return new WP_Error('sync_exception', 'An error occurred during sync: ' . $e->getMessage(), array('status' => 500));
        } catch (Error $e) {
            return new WP_Error('sync_fatal_error', 'A fatal error occurred during sync: ' . $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Handle delete records request
     * Called when records are deleted from SQL database to remove them from Pinecone
     * 
     * @param WP_REST_Request $request REST request
     * @return WP_REST_Response REST response
     */
    public function handle_delete_records($request) {
        $record_ids = $request->get_param('record_ids');
        
        if (empty($record_ids) || !is_array($record_ids)) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => 'No record IDs provided for deletion'
            ));
        }
        
        // Ensure all dependencies are loaded
        if (!$this->ensure_vector_sync_dependencies()) {
            return new WP_Error('class_not_found', 'Vector Sync Manager dependencies could not be loaded', array('status' => 500));
        }
        
        $sync_manager = Boat_Chatbot_Vector_Sync_Manager::get_instance();
        
        // Extract IDs from bracket-wrapped strings if needed
        $record_ids = $this->extract_record_ids($record_ids);
        
        // Delete records in batch
        $success_count = 0;
        $failed_count = 0;
        $errors = array();
        
        try {
            foreach ($record_ids as $record_id) {
                $success = $sync_manager->delete_record($record_id);
                if ($success) {
                    $success_count++;
                } else {
                    $failed_count++;
                    $errors[] = "Failed to delete record {$record_id}";
                }
            }
            
            return rest_ensure_response(array(
                'success' => $failed_count === 0,
                'message' => sprintf(
                    'Deletion completed: %d successful, %d failed out of %d total',
                    $success_count,
                    $failed_count,
                    count($record_ids)
                ),
                'results' => array(
                    'success' => $success_count,
                    'failed' => $failed_count,
                    'total' => count($record_ids),
                'errors' => $errors
                )
            ));
        } catch (Exception $e) {
            return new WP_Error('delete_exception', 'An error occurred during deletion: ' . $e->getMessage(), array('status' => 500));
        } catch (Error $e) {
            return new WP_Error('delete_fatal_error', 'A fatal error occurred during deletion: ' . $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Check REST API nonce
     * Supports both custom nonce and WordPress REST API nonce
     */
    public function check_rest_nonce($request) {
        // First, try to get nonce from header (WordPress REST API standard)
        $nonce = $request->get_header('X-WP-Nonce');
        
        // If not in header, try from request body
        if (empty($nonce)) {
            $nonce = $request->get_param('nonce');
        }
        
        // If still empty, try from query parameter
        if (empty($nonce)) {
            $nonce = isset($_REQUEST['_wpnonce']) ? $_REQUEST['_wpnonce'] : '';
        }
        
        // Verify with wp_rest (WordPress REST API standard)
        if (!empty($nonce) && wp_verify_nonce($nonce, 'wp_rest')) {
            return true;
        }
        
        // Fallback: verify with custom nonce action (for backward compatibility)
        if (!empty($nonce) && wp_verify_nonce($nonce, 'boat_chatbot_nonce')) {
            return true;
        }
        
        // If no nonce provided, deny access
        return new WP_Error(
            'rest_forbidden',
            __('Security check failed. Please refresh the page and try again.', 'boat-chatbot'),
            array('status' => 403)
        );
    }
    
    public function handle_rest_message($request) {
        // Nonce is already verified in permission_callback
        $user_message = $request->get_param('message');
        $conversation_history = $request->get_param('conversation_history');
        $offset = $request->get_param('offset'); // For pagination via "Load More"
        
        // Sanitize conversation history
        if (!is_array($conversation_history)) {
            $conversation_history = array();
        }
        
        // Sanitize each message in conversation history
        $sanitized_history = array();
        foreach ($conversation_history as $msg) {
            if (isset($msg['role']) && isset($msg['content'])) {
                $sanitized_history[] = array(
                    'role' => sanitize_text_field($msg['role']),
                    'content' => sanitize_text_field($msg['content'])
                );
            }
        }
        $conversation_history = $sanitized_history;
        
        $performance_log = array();
        $start_time = microtime(true);
        
        // Check cache first (only for single messages without history to avoid stale context)
        $cache_key = 'message_' . md5($user_message);
        $cached_response = $this->get_cache($cache_key);
        // Only use cache if there's no conversation history (to maintain context)
        if ($cached_response !== false && empty($conversation_history)) {
            $performance_log['cache_hit'] = true;
            $performance_log['total_time'] = microtime(true) - $start_time;
            return rest_ensure_response(array(
                'success' => true,
                'data' => array(
                    'response' => $cached_response['response'],
                    'intent' => $cached_response['intent'],
                    'listings' => $cached_response['listings'] ?? array(),
                    'total_listings' => $cached_response['total_listings'] ?? 0,
                    'response_time' => $performance_log['total_time'],
                    'performance_log' => $performance_log,
                    'cached' => true
                )
            ));
        }
        
        $performance_log['cache_hit'] = false;
        $intent_start = microtime(true);
        
        // Classify intent with parallel processing (if Parallel extension is available)
        $intent = $this->classify_intent_parallel($user_message);
        $performance_log['intent_time'] = microtime(true) - $intent_start;
        
        // Sanitize offset
        $offset = max(0, intval($offset));
        
        // Process based on intent - run DB and AI queries in parallel where possible
        // Note: Both database_query and hybrid now support offset parameter for pagination
        if ($intent === 'hybrid') {
            // Check if parallel search is enabled (default: true)
            $use_parallel_search = get_option('boat_chatbot_use_parallel_search', true);
            
            if ($use_parallel_search) {
                // Use new parallel hybrid search with score combination
                $response_data = $this->handle_parallel_hybrid_search($user_message, $performance_log, $conversation_history, $offset);
            } else {
                // Fallback to original hybrid query method
                $response_data = $this->handle_hybrid_query_optimized($user_message, $performance_log, $conversation_history, $offset);
            }

        } elseif ($intent === 'database_query') {
            $response_data = $this->handle_database_query_optimized($user_message, $performance_log, $conversation_history, $offset);
        } else {
            $response_data = $this->handle_general_query_optimized($user_message, $performance_log, $conversation_history);
        }
        
        $performance_log['total_time'] = microtime(true) - $start_time;
        
        // Cache the response (only for single messages without history)
        if (empty($conversation_history)) {
            $this->set_cache($cache_key, array(
                'response' => $response_data['response'],
                'intent' => $intent,
                'listings' => $response_data['listings'] ?? array(),
                'total_listings' => $response_data['total_listings'] ?? 0,
            ));
        }
        
        // Log the interaction
        $this->log_interaction($user_message, $intent, $response_data['response'], $performance_log['total_time'], $performance_log);
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => array_merge($response_data, array(
                'intent' => $intent,
                'response_time' => $performance_log['total_time'],
                'performance_log' => $performance_log,
                'cached' => false
            ))
        ));
    }
    
    /**
     * Handle streaming REST message - sends response in chunks via Server-Sent Events
     * 
     * @param WP_REST_Request $request REST request
     * @return WP_REST_Response|WP_Error REST response
     */
    public function handle_rest_message_stream($request) {
        // Set headers first (before any output)
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable nginx buffering
        
        // Flush output buffer
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Verify nonce manually
        $nonce = $request->get_header('X-WP-Nonce');
        if (empty($nonce)) {
            $nonce = $request->get_param('nonce');
        }
        
        // Verify nonce
        $nonce_valid = false;
        $nonce_error = '';
        
        if (empty($nonce)) {
            $nonce_error = 'No nonce provided';
        } else {
            // Try WordPress REST API nonce first
            $verify_result = wp_verify_nonce($nonce, 'wp_rest');
            if ($verify_result === false) {
                // Try legacy nonce
                $verify_result = wp_verify_nonce($nonce, 'boat_chatbot_nonce');
                if ($verify_result !== false) {
                    $nonce_valid = true;
                } else {
                    $nonce_error = 'Nonce verification failed for both wp_rest and boat_chatbot_nonce';
                }
            } else {
                $nonce_valid = true;
            }
        }
        
        if (!$nonce_valid) {
            // Send error via SSE format
            $error_data = array(
                'type' => 'error',
                'data' => 'Authentication failed. Please refresh the page and try again.'
            );
            // Add debug info only if WP_DEBUG is enabled
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $error_data['debug'] = $nonce_error;
            }
            echo "data: " . json_encode($error_data) . "\n\n";
            flush();
            exit;
        }
        
        // Get parameters
        $user_message = $request->get_param('message');
        $conversation_history = $request->get_param('conversation_history');
        
        // Sanitize conversation history
        if (!is_array($conversation_history)) {
            $conversation_history = array();
        }
        
        $sanitized_history = array();
        foreach ($conversation_history as $msg) {
            if (isset($msg['role']) && isset($msg['content'])) {
                $sanitized_history[] = array(
                    'role' => sanitize_text_field($msg['role']),
                    'content' => sanitize_text_field($msg['content'])
                );
            }
        }
        $conversation_history = $sanitized_history;
        
        $performance_log = array();
        $start_time = microtime(true);
        
        // Classify intent
        $intent_start = microtime(true);
        $intent = $this->classify_intent_parallel($user_message);
        $performance_log['intent_time'] = microtime(true) - $intent_start;
        
        // Send intent info
        $this->send_sse_chunk(array(
            'type' => 'intent',
            'data' => $intent
        ));
        
        // Process based on intent - for streaming, we'll focus on general queries first
        // Database and hybrid queries can stream the AI response part
        if ($intent === 'hybrid' || $intent === 'database_query') {
            // For queries with listings, we'll stream the AI response
            // Listings will be sent at the end
            $response_data = $this->handle_query_with_streaming($user_message, $intent, $performance_log, $conversation_history);
        } else {
            // General query - stream AI response
            $response_data = $this->handle_general_query_streaming($user_message, $performance_log, $conversation_history);
        }
        
        $performance_log['total_time'] = microtime(true) - $start_time;
        // Send final data (listings, metadata, etc.)
        $this->send_sse_chunk(array(
            'type' => 'done',
            'data' => array_merge($response_data, array(
                'intent' => $intent,
                'response_time' => $performance_log['total_time'],
                'performance_log' => $performance_log
            ))
        ));
        
        // Close connection
        $this->send_sse_chunk(array('type' => 'close'));
        
        // Exit to prevent WordPress from adding anything else
        exit;
    }
    
    /**
     * Send Server-Sent Event chunk
     * 
     * @param array $data Data to send
     */
    private function send_sse_chunk($data) {
        echo "data: " . json_encode($data) . "\n\n";
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }
    
    /**
     * Handle general query with streaming
     * 
     * @param string $user_message User message
     * @param array $performance_log Performance log
     * @param array $conversation_history Conversation history
     * @return array Response data
     */
    private function handle_general_query_streaming($user_message, &$performance_log, $conversation_history = array()) {
        $grok_api_start = microtime(true);
        $response = $this->get_ai_response_streaming($user_message, '', false, $conversation_history);
        $performance_log['grok_api_time'] = microtime(true) - $grok_api_start;
        $performance_log['ai_time'] = $performance_log['grok_api_time'];
        
        return array(
            'response' => $response,
            'listings' => array(),
            'total_listings' => 0
        );
    }
    
    /**
     * Handle query with listings (hybrid/database) with streaming
     * 
     * @param string $user_message User message
     * @param string $intent Intent type
     * @param array $performance_log Performance log
     * @param array $conversation_history Conversation history
     * @return array Response data
     */
    private function handle_query_with_streaming($user_message, $intent, &$performance_log, $conversation_history = array()) {
        if ($intent === 'hybrid') {
            $use_parallel_search = get_option('boat_chatbot_use_parallel_search', true);
            if ($use_parallel_search) {
                $response_data = $this->handle_parallel_hybrid_search($user_message, $performance_log, $conversation_history, 0);
            } else {
                $response_data = $this->handle_hybrid_query_optimized($user_message, $performance_log, $conversation_history, 0);
            }
        } else {
            $response_data = $this->handle_database_query_optimized($user_message, $performance_log, $conversation_history, 0);
        }
        
        // Stream the AI response part
        if (isset($response_data['response'])) {
            $this->stream_text_chunks($response_data['response']);
        }
        
        return $response_data;
    }
    
    /**
     * Stream text in chunks (for non-streaming API responses)
     * 
     * @param string $text Text to stream
     */
    private function stream_text_chunks($text) {
        // Split text into words and send each word
        $words = preg_split('/(\s+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $current_chunk = '';
        
        foreach ($words as $word) {
            $current_chunk .= $word;
            
            // Send chunk every 3 words or on punctuation
            if (strlen($current_chunk) > 20 || preg_match('/[.!?]\s*$/', $current_chunk)) {
                $this->send_sse_chunk(array(
                    'type' => 'content',
                    'data' => $current_chunk
                ));
                $current_chunk = '';
                usleep(50000); // 50ms delay for smooth streaming effect
            }
        }
        
        // Send remaining chunk
        if (!empty($current_chunk)) {
            $this->send_sse_chunk(array(
                'type' => 'content',
                'data' => $current_chunk
            ));
        }
    }
    
    /**
     * Get AI response with streaming support
     * This method calls the Groq API and streams the response
     * 
     * @param string $user_message User message
     * @param mixed $data_context Data context (listings count or formatted listings)
     * @param bool $is_database_query Whether this is a database query
     * @param array $conversation_history Conversation history
     * @return string Full response (for fallback)
     */
    private function get_ai_response_streaming($user_message, $data_context = '', $is_database_query = false, $conversation_history = array()) {
        // Use the same option names as get_ai_response_optimized
        $api_key = get_option('boat_chatbot_grok_api_key');
        $api_url = get_option('boat_chatbot_grok_api_url');
        $tone = get_option('boat_chatbot_tone_of_voice');
        $blocked_websites = get_option('boat_chatbot_blocked_websites', '');
        
        if (empty($api_key) || empty($api_url)) {
            $this->send_sse_chunk(array(
                'type' => 'error',
                'data' => 'API key or URL not configured'
            ));
            return "I'm currently undergoing maintenance. Please try again later.";
        }
        
        // Ensure tone has a default value if empty
        if (empty($tone)) {
            $tone = "You are a helpful boat assistant. ";
        }
        
        // Build restrictions from blocked websites (same as get_ai_response_optimized)
        $restrictions = '';
        if (!empty($blocked_websites)) {
            // Handle both string (comma/newline separated) and array formats
            if (is_string($blocked_websites)) {
                $websites = preg_split('/[,\n\r]+/', trim($blocked_websites));
                $websites = array_map('trim', $websites);
                $websites = array_filter($websites);
                $websites = array_map('sanitize_text_field', $websites);
            } elseif (is_array($blocked_websites)) {
                $websites = array_filter($blocked_websites);
            } else {
                $websites = array();
            }
            
            if (!empty($websites)) {
                $display_websites = array_map(function($site) {
                    return preg_replace('#^https?://#', '', $site);
                }, $websites);
                $websites_list = implode(', ', array_slice($display_websites, 0, 10));
                $restrictions = "\n\nRESTRICTION: Do not reference these sites: {$websites_list}\n";
            }
        }
        
        $messages = array();
        $system_content = $tone;
        if (!empty($restrictions)) {
            $system_content .= $restrictions;
        }
        $system_content .= "\n\nUse markdown formatting for better readability:\n- Use **bold** for important terms\n- Use *italic* for emphasis\n- Use bullet points (-) or numbered lists (1. 2. 3.) for multiple items\n- Use line breaks to separate paragraphs\n- Keep responses clear and well-structured.\n\nIMPORTANT: When generating search URLs with query parameters, always use 'manufacturer' (not 'make') as the parameter name for boat manufacturers. For example: /yachts-for-sale?manufacturer=everglades (not ?make=everglades).";
        
        // Extract and summarize key information from conversation history
        $user_context = '';
        if (method_exists($this, 'extract_user_context')) {
            try {
                $user_context = $this->extract_user_context($conversation_history);
            } catch (Exception $e) {
                // Silently fail if function has issues
                $user_context = '';
            }
        }
        
        // Add user context to system message if available
        if (!empty($user_context)) {
            $system_content .= "\n\nUSER CONTEXT (Important information to remember throughout this conversation):\n" . $user_context . "\n\nAlways reference this context when answering questions, especially when the user asks follow-up questions without repeating their previous preferences.";
        }
        
        $messages[] = array('role' => 'system', 'content' => $system_content);
        
        // Add conversation history (increased limit to 20 messages for better context)
        if (!empty($conversation_history)) {
            $recent_history = array_slice($conversation_history, -20);
            foreach ($recent_history as $hist_msg) {
                $role = ($hist_msg['role'] === 'assistant') ? 'assistant' : 'user';
                $messages[] = array(
                    'role' => $role,
                    'content' => $hist_msg['content']
                );
            }
        }
        
        if ($is_database_query) {
            if (is_numeric($data_context)) {
                $listing_count = intval($data_context);
                if ($listing_count > 0) {
                    $current_message = "Search Results: Found {$listing_count} boat listing(s) matching the query.\n\nQuestion: {$user_message}\n\nProvide a helpful summary about the search results. The listings will be displayed separately below your response.";
                } else {
                    $current_message = "Search Results: No boat listings found matching the query.\n\nQuestion: {$user_message}\n\nProvide a helpful response explaining that no listings were found and suggest alternative search terms or criteria.";
                }
            } else {
                $current_message = "Listings:\n{$data_context}\n\nQuestion: {$user_message}\n\nProvide a helpful summary.";
            }
        } else {
            $current_message = "Question: {$user_message}\n\nProvide accurate boating information.";
        }
        
        $messages[] = array('role' => 'user', 'content' => $current_message);
        
        // Check if Groq API supports streaming
        // For now, we'll use a simulated streaming approach
        // In the future, we can implement true API streaming if Groq supports it
        
        // Make API request (non-streaming for now, but we'll simulate streaming)
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body' => json_encode(array(
                'messages' => $messages,
                'model' => 'grok-4-fast-reasoning',
                'temperature' => 0.5,
                'max_tokens' => 1000 // Increased to accommodate more context
            )),
            'timeout' => 30,
            'blocking' => true
        ));
        
        if (is_wp_error($response)) {
            $this->send_sse_chunk(array(
                'type' => 'error',
                'data' => $response->get_error_message()
            ));
            return "I'm having trouble connecting right now. Please try again in a moment.";
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body_raw = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $this->send_sse_chunk(array(
                'type' => 'error',
                'data' => "HTTP {$response_code} error"
            ));
            return "I'm having trouble connecting to the Grok API (HTTP {$response_code}). Please try again in a moment.";
        }
        
        $body = json_decode($response_body_raw, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->send_sse_chunk(array(
                'type' => 'error',
                'data' => 'Invalid JSON response'
            ));
            return "I'm having trouble processing the response from Grok API. Please try again.";
        }
        
        // Extract response text
        $ai_response = '';
        if (isset($body['choices'][0]['message']['content'])) {
            $ai_response = $body['choices'][0]['message']['content'];
        } elseif (isset($body['choices'][0]['text'])) {
            $ai_response = $body['choices'][0]['text'];
        } elseif (isset($body['content'])) {
            $ai_response = $body['content'];
        } elseif (isset($body['response'])) {
            $ai_response = $body['response'];
        } else {
            $this->send_sse_chunk(array(
                'type' => 'error',
                'data' => 'Unexpected response format'
            ));
            return "I apologize, but I couldn't process your request at the moment. Please try again.";
        }
        
        // Filter blocked websites (same as get_ai_response_optimized)
        // The filter function handles string format, so convert array to string if needed
        if (!empty($blocked_websites)) {
            if (is_array($blocked_websites)) {
                $blocked_websites = implode(', ', array_filter($blocked_websites));
            }
            if (!empty($blocked_websites)) {
                $ai_response = $this->filter_blocked_websites_from_response($ai_response, $blocked_websites);
            }
        }
        
        // Stream the response in chunks
        $this->stream_text_chunks($ai_response);
        
        return $ai_response;
    }
    
    public function handle_load_listings($request) {
        // Nonce is already verified in permission_callback
        // Ensure all required dependencies are loaded
        $this->ensure_core_dependencies();
        
        // Handle both JSON and form data requests
        // WordPress REST API: get_json_params() for JSON body, get_param() for query/form data
        $json_params = $request->get_json_params();
        if (!empty($json_params) && is_array($json_params)) {
            // JSON request (from frontend with contentType: 'application/json')
            $query = isset($json_params['query']) ? sanitize_text_field($json_params['query']) : '';
            $offset = isset($json_params['offset']) ? max(0, intval($json_params['offset'])) : 0;
        } else {
            // Form data request (fallback for URL-encoded or query params)
            $query = $request->get_param('query');
            $offset = $request->get_param('offset');
            if (empty($offset)) {
                $offset = 0;
            } else {
                $offset = max(0, intval($offset));
            }
        }
        
        // Validate query is not empty
        if (empty($query)) {
            error_log('[Boat Chatbot] load-listings: Missing query parameter. JSON params: ' . print_r($json_params, true) . ', Form params: ' . print_r($request->get_params(), true));
            return new WP_Error('missing_query', 'Query parameter is required', array('status' => 400));
        }
        
        // Determine intent to handle hybrid queries properly
        $intent = $this->classify_intent($query);
        error_log('[Boat Chatbot] load-listings: Query="' . $query . '", Intent=' . $intent . ', Offset=' . $offset);
        
        // Always use 5 items per page for "Load More" button, regardless of what's requested
        $limit = 5;
        
        // Check for hybrid cache first (even if intent isn't 'hybrid', cache might exist from initial request)
        // This handles cases where intent classification might differ between requests
        $use_parallel_search = get_option('boat_chatbot_use_parallel_search', true);
        $cache_key_hybrid = $use_parallel_search 
            ? 'hybrid_sorted_ids_' . md5(trim(strtolower($query)))
            : 'hybrid_optimized_sorted_ids_' . md5(trim(strtolower($query)));
        
        $cached_hybrid_ids = $this->get_cache($cache_key_hybrid);
        if ($cached_hybrid_ids !== false && is_array($cached_hybrid_ids) && !empty($cached_hybrid_ids) && $offset > 0) {
            error_log('[Boat Chatbot] load-listings: Found hybrid cache (even though intent=' . $intent . '), using hybrid handler. CacheKey="' . $cache_key_hybrid . '", CachedIDs=' . count($cached_hybrid_ids));
            $intent = 'hybrid'; // Force hybrid handling
        }
        
        // Handle hybrid queries differently - they need to use cached sorted IDs
        if ($intent === 'hybrid') {
            error_log('[Boat Chatbot] load-listings: Detected hybrid query, using hybrid handler');
            // Check if parallel search is enabled (default: true)
            $use_parallel_search = get_option('boat_chatbot_use_parallel_search', true);
            
            // Create a minimal performance log for hybrid handlers
            $performance_log = array();
            $conversation_history = array();
            
            if ($use_parallel_search) {
                // Use parallel hybrid search with offset
                $response_data = $this->handle_parallel_hybrid_search($query, $performance_log, $conversation_history, $offset);
            } else {
                // Use original hybrid query method with offset
                $response_data = $this->handle_hybrid_query_optimized($query, $performance_log, $conversation_history, $offset);
            }
            
            // Log for debugging
            error_log('[Boat Chatbot] load-listings (hybrid): Query="' . $query . '", Offset=' . $offset . ', Found=' . count($response_data['listings']) . ', Total=' . $response_data['total_listings']);
            
            return rest_ensure_response(array(
                'success' => true,
                'data' => array(
                    'listings' => $response_data['listings'],
                    'total' => $response_data['total_listings'],
                    'total_listings' => $response_data['total_listings'], // For consistency
                    'offset' => $response_data['offset'],
                    'limit' => $response_data['limit'],
                    'has_more' => $response_data['has_more'] ?? false,
                    'enable_pagination' => $response_data['enable_pagination'] ?? true
                )
            ));
        }
        
        // For database queries, use the original logic
        $db_manager = new Boat_Chatbot_Database_Manager();
        $listings = $db_manager->query_listings($query, $limit, $offset);
        $total = $db_manager->get_total_count($query);
        
        // Log for debugging (can be removed in production)
        error_log('[Boat Chatbot] load-listings (database): Query="' . $query . '", Offset=' . $offset . ', Limit=' . $limit . ', Found=' . count($listings) . ', Total=' . $total);
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => array(
                'listings' => $listings,    
                'total' => $total,
                'total_listings' => $total, // For consistency
                'offset' => $offset,
                'limit' => $limit
            )
        ));
    }
    
    /**
     * Handle vector search request
     * Unified backend API endpoint for vector search
     * 
     * @param WP_REST_Request $request REST request object
     * @return WP_REST_Response REST response
     */
    public function handle_vector_search($request) {
        // Nonce is already verified in permission_callback
        // Ensure all required dependencies are loaded
        $this->ensure_core_dependencies();
        
        $query = $request->get_param('query');
        $top_k = min(absint($request->get_param('top_k')), 100); // Max 100
        
        $groq_manager = Boat_Chatbot_Groq_Embeddings_Manager::get_instance();
        $pinecone_manager = Boat_Chatbot_Pinecone_Manager::get_instance();
        
        // Generate query embedding
        $query_embedding = $groq_manager->generate_embedding($query);
        
        if ($query_embedding === false) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => 'Failed to generate embedding'
            ));
        }
        
        // Query Pinecone
        $vector_results = $pinecone_manager->query($query_embedding, $top_k);
        
        if ($vector_results === false) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => 'Vector search failed'
            ));
        }
        
        // Extract record IDs and scores
        $results = array();
        foreach ($vector_results as $result) {
            if (isset($result['id']) && isset($result['score'])) {
                $results[] = array(
                    'id' => intval($result['id']),
                    'score' => floatval($result['score']),
                    'metadata' => isset($result['metadata']) ? $result['metadata'] : array()
                );
            }
        }
        
        // Optionally fetch full records from database
        $include_full_records = $request->get_param('include_records');
        if ($include_full_records) {
            $record_ids = array_column($results, 'id');
            $listings = $this->get_listings_by_ids($record_ids);
            
            // Merge listings with scores
            $listings_with_scores = array();
            foreach ($listings as $listing) {
                $id = isset($listing->ID) ? $listing->ID : (isset($listing->id) ? $listing->id : null);
                if ($id) {
                    $score = 0;
                    foreach ($results as $result) {
                        if ($result['id'] == $id) {
                            $score = $result['score'];
                            break;
                        }
                    }
                    $listing->vector_score = $score;
                    $listings_with_scores[] = $listing;
                }
            }
            
            return rest_ensure_response(array(
                'success' => true,
                'data' => array(
                    'results' => $results,
                    'listings' => $listings_with_scores,
                    'total' => count($results)
                )
            ));
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => array(
                'results' => $results,
                'total' => count($results)
            )
        ));
    }
    
    // Legacy AJAX handler (for backward compatibility)
    public function handle_chat_message() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'boat_chatbot_nonce')) {
            wp_die('Security check failed');
        }
        
        $user_message = sanitize_text_field($_POST['message']);
        $performance_log = array();
        $start_time = microtime(true);
        
        $intent_start = microtime(true);
        $intent = $this->classify_intent($user_message);
        $performance_log['intent_time'] = microtime(true) - $intent_start;
        
        if ($intent === 'hybrid') {
            // Hybrid queries: semantic search (vector DB) + SQL filters
            $response_data = $this->handle_hybrid_query_optimized($user_message, $performance_log);
        } elseif ($intent === 'database_query') {
            $response_data = $this->handle_database_query_optimized($user_message, $performance_log);
        } else {
            $response_data = $this->handle_general_query_optimized($user_message, $performance_log);
        }
        
        $performance_log['total_time'] = microtime(true) - $start_time;
        
        $this->log_interaction($user_message, $intent, $response_data['response'], $performance_log['total_time'], $performance_log);
        
        wp_send_json_success(array_merge($response_data, array(
            'intent' => $intent,
            'response_time' => $performance_log['total_time'],
            'performance_log' => $performance_log
        )));
    }
    
    /**
     * Classify intent with parallel processing support
     * If Parallel extension is available, runs Intent Cache Check in parallel with Keyword/Regex checks
     * Falls back to sequential processing if extension is not available
     * 
     * @param string $message User message
     * @return string Intent classification
     */
    private function classify_intent_parallel($message) {
        // Check if Parallel extension is available and functional
        if ($this->is_parallel_available()) {
            try {
                return $this->classify_intent_with_parallel($message);
            } catch (Exception $e) {
                // If parallel processing fails, fall back to sequential
                error_log('[Boat Chatbot] Parallel processing failed, using sequential: ' . $e->getMessage());
                return $this->classify_intent($message);
            } catch (Error $e) {
                // If parallel processing fails, fall back to sequential
                error_log('[Boat Chatbot] Parallel processing failed, using sequential: ' . $e->getMessage());
                return $this->classify_intent($message);
            }
        } else {
            // Fallback to sequential processing
            return $this->classify_intent($message);
        }
    }
    
    /**
     * Check if Parallel extension is available and functional
     * 
     * @return bool True if Parallel extension can be used
     */
    private function is_parallel_available() {
        // Check if extension is loaded
        if (!extension_loaded('parallel')) {
            return false;
        }
        
        // Check if class exists
        if (!class_exists('\parallel\Runtime')) {
            return false;
        }
        
        // Optional: Test if it actually works (cache the result)
        static $parallel_works = null;
        if ($parallel_works === null) {
            try {
                $runtime = new \parallel\Runtime();
                $future = $runtime->run(function() {
                    return true;
                });
                $parallel_works = ($future->value() === true);
            } catch (Exception $e) {
                $parallel_works = false;
            } catch (Error $e) {
                $parallel_works = false;
            }
        }
        
        return $parallel_works;
    }
    
    /**
     * Classify intent using Parallel extension for concurrent processing
     * Runs Intent Cache Check in main thread while Keyword/Regex checks run in parallel
     * 
     * @param string $message User message
     * @return string Intent classification
     */
    private function classify_intent_with_parallel($message) {
        $message_lower = strtolower(trim($message));
        $message_original = trim($message);
        $cache_key = 'intent_' . md5($message_original);
        
        // Start keyword/regex checks in parallel thread (this is the slow part)
        $runtime = new \parallel\Runtime();
        $indicators_task = $runtime->run(function($message_lower_param, $message_original_param) {
            // This closure runs in parallel thread
            // We need to recreate the keyword maps and patterns here
            // Since we can't access $this, we'll need to pass the data
            
            $result = array(
                'db' => false, 
                'semantic' => false,
                'matched_keywords' => array('db' => array(), 'semantic' => array()),
                'reasoning' => array()
            );
            
            // Special case: "show me all [boats/yachts/listings]" queries should be database_query only
            if (preg_match('/\b(?:show|list|display|find|get)\s+(?:me\s+)?all\s+(?:boats?|yachts?|listings?|vessels?|crafts?|ships?)\b/i', $message_original_param)) {
                $result['reasoning'][] = 'Matched "show all" pattern';
                $semantic_descriptors = array('luxury', 'luxurious', 'comfortable', 'comfort', 'fast', 'spacious', 'roomy',
                                             'well-maintained', 'well maintained', 'maintained', 'family-friendly', 'family friendly',
                                             'modern', 'classic', 'vintage', 'contemporary', 'traditional', 'elegant', 'stylish',
                                             'beautiful', 'gorgeous', 'stunning', 'impressive', 'amazing', 'excellent', 'premium',
                                             'high-quality', 'high quality', 'quality', 'reliable', 'durable', 'safe', 'secure',
                                             'best', 'good', 'nice', 'great', 'perfect', 'ideal', 'suitable', 'popular', 'recommended',
                                             'with', 'has', 'have', 'includes', 'including', 'features', 'feature', 'for fishing',
                                             'for cruising', 'for racing', 'for charter', 'for sailing', 'for living', 'for weekend',
                                             'for day trips', 'for long distance', 'for beginners', 'for families', 'for couples',
                                             'suitable for', 'good for', 'perfect for', 'ideal for');
                
                $has_semantic_descriptors = false;
                $matched_descriptors = array();
                foreach ($semantic_descriptors as $descriptor) {
                    // Use word boundaries to match whole words only (not substrings)
                    $escaped_descriptor = preg_quote($descriptor, '/');
                    if (preg_match('/\b' . $escaped_descriptor . '\b/i', $message_lower_param)) {
                        $has_semantic_descriptors = true;
                        $matched_descriptors[] = $descriptor;
                    }
                }
                
                if (!$has_semantic_descriptors) {
                    $result['db'] = true;
                    $result['semantic'] = false;
                    $result['reasoning'][] = 'No semantic descriptors found - simple listing query';
                    return $result;
                } else {
                    $result['matched_keywords']['semantic'] = array_merge($result['matched_keywords']['semantic'], $matched_descriptors);
                    $result['reasoning'][] = 'Found semantic descriptors: ' . implode(', ', $matched_descriptors);
                }
            }
            
            // Early exit: Check word count first
            $word_count = str_word_count($message_original_param);
            if ($word_count >= 10) {
                $result['semantic'] = true;
                $result['reasoning'][] = "Query length ($word_count words) indicates complexity";
            }
            
            // Get keyword maps (recreate in parallel thread)
            $db_keywords = array(
                'list', 'show', 'find', 'search', 'price', 'cost', 'buy', 'sale', 'listing',
                'display', 'view', 'browse', 'look', 'see', 'available', 'inventory', 'stock',
                'purchase', 'acquire', 'get', 'obtain', 'shop', 'marketplace',
                'afford', 'budget', 'expensive', 'cheap', 'value', 'worth', 'pricing',
                'where', 'location', 'area', 'region', 'city', 'place',
                'boat', 'yacht', 'vessel', 'craft', 'ship', 'sailboat', 'powerboat', 'catamaran'
            );
            $db_keywords_map = array_flip($db_keywords);
            
            $semantic_keywords = array_merge(
                array('luxury', 'luxurious', 'comfortable', 'comfort', 'fast', 'spacious', 'roomy',
                      'well-maintained', 'well maintained', 'maintained', 'family-friendly', 'family friendly',
                      'modern', 'classic', 'vintage', 'contemporary', 'traditional', 'elegant', 'stylish',
                      'beautiful', 'gorgeous', 'stunning', 'impressive', 'amazing', 'excellent', 'premium',
                      'high-quality', 'high quality', 'quality', 'reliable', 'durable', 'safe', 'secure'),
                array('best', 'good', 'nice', 'great', 'excellent', 'perfect', 'ideal', 'suitable',
                      'popular', 'famous', 'renowned', 'well-known', 'well known', 'recommended',
                      'top', 'premium', 'superior', 'outstanding', 'exceptional'),
                array('with', 'has', 'have', 'includes', 'including', 'features', 'feature',
                      'galley', 'kitchen', 'sleeps', 'sleep', 'berth', 'berths', 'cabin', 'cabins',
                      'generator', 'air conditioning', 'ac', 'heating', 'gps', 'radar', 'autopilot',
                      'anchor', 'winch', 'sail', 'engine', 'engines', 'motor', 'motors',
                      'bathroom', 'head', 'shower', 'refrigerator', 'fridge', 'freezer'),
                array('for fishing', 'for cruising', 'for racing', 'for charter', 'for sailing',
                      'for living', 'for weekend', 'for day trips', 'for long distance',
                      'for beginners', 'for families', 'for couples', 'for solo',
                      'suitable for', 'good for', 'perfect for', 'ideal for'),
                array('better', 'compared to', 'similar to', 'like', 'similar', 'comparable',
                      'versus', 'vs', 'different from', 'unlike', 'same as', 'equivalent'),
                array('what', 'which', 'who', 'how', 'where', 'when', 'why',
                      'are there', 'can you', 'could you', 'would you', 'tell me')
            );
            $semantic_keywords_map = array_flip($semantic_keywords);
            
            // Split message into words for hash-based lookup
            $words = preg_split('/\s+/', $message_lower_param);
            $message_words = array_flip($words);
            
            // Check database indicators using whole-word matching
            foreach ($db_keywords_map as $keyword => $type) {
                // Use word boundaries to match whole words only (not substrings)
                $escaped_keyword = preg_quote($keyword, '/');
                if (preg_match('/\b' . $escaped_keyword . '\b/i', $message_lower_param)) {
                    $result['db'] = true;
                    if (!in_array($keyword, $result['matched_keywords']['db'])) {
                        $result['matched_keywords']['db'][] = $keyword;
                    }
                }
            }
            
            // Check semantic indicators using whole-word matching
            if (!$result['semantic']) {
                foreach ($semantic_keywords_map as $keyword => $type) {
                    // Use word boundaries to match whole words only (not substrings)
                    $escaped_keyword = preg_quote($keyword, '/');
                    if (preg_match('/\b' . $escaped_keyword . '\b/i', $message_lower_param)) {
                        $result['semantic'] = true;
                        if (!in_array($keyword, $result['matched_keywords']['semantic'])) {
                            $result['matched_keywords']['semantic'][] = $keyword;
                        }
                    }
                }
            }
            
            // Check price patterns
            if (!$result['db']) {
                $price_patterns = array(
                    '/\b(how\s+much|what.*\s+(?:is|are|does|do).*\s+(?:the\s+)?(?:price|cost|value|worth))\b/i',
                    '/\b(what.*\s+(?:price|cost|pricing|value))\b/i',
                    '/\b(price|cost|pricing|priced|costs)\b/i',
                    '/\b(\$|dollar|dollars|usd|€|euro|euros|£|pound|pounds)\b/i',
                    '/\b(under|below|less\s+than|cheaper\s+than|under\s+\$|below\s+\$)\b/i',
                    '/\b(over|above|more\s+than|greater\s+than|exceeding|over\s+\$|above\s+\$)\b/i',
                    '/\b(between|from\s+\$|to\s+\$|up\s+to|around\s+\$|about\s+\$)\b/i',
                    '/\b(affordable|afford|budget|cheap|inexpensive|expensive|costly|reasonably\s+priced)\b/i',
                    '/\b(cheapest|most\s+expensive|lowest\s+price|highest\s+price|best\s+price|best\s+deal)\b/i',
                    '/\b(value|worth|worth\s+it|good\s+value|worth\s+the\s+price)\b/i',
                    '/\b(for\s+sale|selling\s+for|asking\s+price|list\s+price|market\s+price)\b/i',
                    '/\b(how\s+.*\s+(?:price|cost)|what.*\s+(?:price|cost)|tell\s+me.*\s+(?:price|cost))\b/i'
                );
                foreach ($price_patterns as $pattern) {
                    if (preg_match($pattern, $message_original_param)) {
                        $result['db'] = true;
                        $result['reasoning'][] = 'Matched price pattern (regex)';
                        break;
                    }
                }
            }
            
            // Check semantic patterns
            if (!$result['semantic']) {
                $question_indicators = array('what', 'which', 'who', 'how', 'where', 'when', 'why',
                                           'are there', 'can you', 'could you', 'would you', 'tell me');
                $question_patterns = array();
                foreach ($question_indicators as $indicator) {
                    $question_patterns[] = '/^' . preg_quote($indicator, '/') . '\s+/i';
                }
                foreach ($question_patterns as $pattern) {
                    if (preg_match($pattern, $message_lower_param)) {
                        $result['semantic'] = true;
                        $result['reasoning'][] = 'Matched semantic pattern (regex/question format)';
                        break;
                    }
                }
                
                // Check for complex multi-criteria patterns
                if (!$result['semantic']) {
                    $common_adjectives = array('luxury', 'comfortable', 'spacious', 'modern', 'classic', 'beautiful', 
                                              'well-maintained', 'family-friendly', 'fast', 'reliable', 'safe');
                    $adjective_count = 0;
                    $matched_adjectives = array();
                    foreach ($common_adjectives as $adj) {
                        // Use word boundaries to match whole words only (not substrings)
                        $escaped_adj = preg_quote($adj, '/');
                        if (preg_match('/\b' . $escaped_adj . '\b/i', $message_lower_param)) {
                            $adjective_count++;
                            $matched_adjectives[] = $adj;
                            if ($adjective_count >= 2) {
                                $result['semantic'] = true;
                                $result['reasoning'][] = 'Multiple adjectives found: ' . implode(', ', $matched_adjectives);
                                break;
                            }
                        }
                    }
                }
            }
            
            return $result;
        }, array($message_lower, $message_original));
        
        // Check intent cache in main thread while parallel task runs (fast operation)
        // This runs concurrently with the keyword/regex checks in the parallel thread
        $cached_intent = $this->get_cache($cache_key);
        
        // If cache hit, we can return immediately (parallel task will be garbage collected)
        if ($cached_intent !== false) {
            // Cancel/ignore the parallel task since we have the result
            unset($indicators_task);
            return $cached_intent;
        }
        
        // Cache miss: Wait for parallel keyword/regex task to complete
        $indicators = $indicators_task->value();
        
        // Ensure indicators have the expected structure (for backward compatibility with parallel version)
        if (!isset($indicators['matched_keywords'])) {
            $indicators['matched_keywords'] = array('db' => array(), 'semantic' => array());
        }
        if (!isset($indicators['reasoning'])) {
            $indicators['reasoning'] = array();
        }
        
        // Classify based on indicators from parallel task
        $intent = null;
        if ($indicators['db'] && $indicators['semantic']) {
            $intent = 'hybrid';
        } elseif ($indicators['db']) {
            $intent = 'database_query';
        } else {
            $intent = 'general_knowledge';
        }
        
        // Log classification reasoning
        $this->log_intent_classification($message_original, $intent, $indicators);
        
        // Cache the result
        $this->set_cache($cache_key, $intent, 600);
        return $intent;
    }
    
    /**
     * Classify intent with optimized parallelized pre-processing
     * Uses hash-based keyword lookup, compiled regex, and single-pass combined check
     * Sequential fallback version
     * 
     * @param string $message User message
     * @return string Intent classification
     */
    private function classify_intent($message) {
        $message_lower = strtolower(trim($message));
        $message_original = trim($message);
        $cache_key = 'intent_' . md5($message_original);
        
        // Check intent cache first (fast lookup)
        $cached_intent = $this->get_cache($cache_key);
        if ($cached_intent !== false) {
            return $cached_intent;
        }
        
        // Cache miss: Run optimized combined check (single pass, hash-based lookups)
        $indicators = $this->check_intent_indicators_optimized($message_lower, $message_original);
        
        // Classify based on indicators
        $intent = null;
        if ($indicators['db'] && $indicators['semantic']) {
            $intent = 'hybrid';
        } elseif ($indicators['db']) {
            $intent = 'hybrid';
        } else {
            $intent = 'general_knowledge';
        }
        
        // Log classification reasoning
        $this->log_intent_classification($message_original, $intent, $indicators);
        
        // Cache the result
        $this->set_cache($cache_key, $intent, 600);
        return $intent;
    }
    
    /**
     * Optimized combined check for both database and semantic indicators
     * Uses hash-based keyword lookup and single-pass processing
     * 
     * @param string $message_lower Lowercase message
     * @param string $message_original Original message
     * @return array ['db' => bool, 'semantic' => bool, 'matched_keywords' => array, 'reasoning' => array]
     */
    private function check_intent_indicators_optimized($message_lower, $message_original) {
        $result = array(
            'db' => false, 
            'semantic' => false,
            'matched_keywords' => array('db' => array(), 'semantic' => array()),
            'reasoning' => array()
        );
        
        // Special case: "show me all [boats/yachts/listings]" queries should be database_query only
        // These are simple listing requests without semantic descriptors, so no need for hybrid search
        if (preg_match('/\b(?:show|list|display|find|get)\s+(?:me\s+)?all\s+(?:boats?|yachts?|listings?|vessels?|crafts?|ships?)\b/i', $message_original)) {
            $result['reasoning'][] = 'Matched "show all" pattern';
            // Check if there are semantic descriptors (adjectives, features, etc.) that would require semantic search
            $semantic_descriptors = array('luxury', 'luxurious', 'comfortable', 'comfort', 'fast', 'spacious', 'roomy',
                                         'well-maintained', 'well maintained', 'maintained', 'family-friendly', 'family friendly',
                                         'modern', 'classic', 'vintage', 'contemporary', 'traditional', 'elegant', 'stylish',
                                         'beautiful', 'gorgeous', 'stunning', 'impressive', 'amazing', 'excellent', 'premium',
                                         'high-quality', 'high quality', 'quality', 'reliable', 'durable', 'safe', 'secure',
                                         'best', 'good', 'nice', 'great', 'perfect', 'ideal', 'suitable', 'popular', 'recommended',
                                         'with', 'has', 'have', 'includes', 'including', 'features', 'feature', 'for fishing',
                                         'for cruising', 'for racing', 'for charter', 'for sailing', 'for living', 'for weekend','looking',
                                         'for day trips', 'for long distance', 'for beginners', 'for families', 'for couples', 'looking for',
                                         'suitable for', 'good for', 'perfect for', 'ideal for');
            
            $has_semantic_descriptors = false;
            $matched_descriptors = array();
            foreach ($semantic_descriptors as $descriptor) {
                // Use word boundaries to match whole words only (not substrings)
                $escaped_descriptor = preg_quote($descriptor, '/');
                if (preg_match('/\b' . $escaped_descriptor . '\b/i', $message_lower)) {
                    $has_semantic_descriptors = true;
                    $matched_descriptors[] = $descriptor;
                }
            }
            
            // If no semantic descriptors, this is a simple "show all" query - use database_query only
            if (!$has_semantic_descriptors) {
                $result['db'] = true;
                $result['semantic'] = false; // Explicitly set to false to prevent hybrid classification
                $result['reasoning'][] = 'No semantic descriptors found - simple listing query';
                return $result;
            } else {
                $result['matched_keywords']['semantic'] = array_merge($result['matched_keywords']['semantic'], $matched_descriptors);
                $result['reasoning'][] = 'Found semantic descriptors: ' . implode(', ', $matched_descriptors);
            }
        }
        
        // Early exit: Check word count first (fastest check)
        $word_count = str_word_count($message_original);
        if ($word_count >= 10) {
            $result['semantic'] = true; // Long queries indicate complexity
            $result['reasoning'][] = "Query length ($word_count words) indicates complexity";
        }
        
        // Get keyword maps (lazy-loaded, cached)
        $db_keywords_map = $this->get_db_keywords_map();
        $semantic_keywords_map = $this->get_semantic_keywords_map();
        
        // Split message into words for hash-based lookup
        $words = preg_split('/\s+/', $message_lower);
        $message_words = array_flip($words); // O(1) lookup
        
        // Check database indicators using whole-word matching
        foreach ($db_keywords_map as $keyword => $type) {
            // Use word boundaries to match whole words only (not substrings)
            // Escape special regex characters in keyword
            $escaped_keyword = preg_quote($keyword, '/');
            // Match as whole word using word boundaries
            if (preg_match('/\b' . $escaped_keyword . '\b/i', $message_lower)) {
                $result['db'] = true;
                if (!in_array($keyword, $result['matched_keywords']['db'])) {
                    $result['matched_keywords']['db'][] = $keyword;
                }
                // Continue to find all matches for logging
            }
        }
        
        // Check semantic indicators using whole-word matching
        if (!$result['semantic']) {
            foreach ($semantic_keywords_map as $keyword => $type) {
                // Use word boundaries to match whole words only (not substrings)
                // Escape special regex characters in keyword
                $escaped_keyword = preg_quote($keyword, '/');
                // Match as whole word using word boundaries
                if (preg_match('/\b' . $escaped_keyword . '\b/i', $message_lower)) {
                    $result['semantic'] = true;
                    if (!in_array($keyword, $result['matched_keywords']['semantic'])) {
                        $result['matched_keywords']['semantic'][] = $keyword;
                    }
                    // Continue to find all matches for logging
                }
            }
        }
        
        // Check compiled regex patterns (only if not already determined)
        if (!$result['db']) {
            $price_match = $this->check_price_patterns_optimized($message_original);
            if ($price_match) {
                $result['db'] = true;
                $result['reasoning'][] = 'Matched price pattern (regex)';
            }
        }
        
        if (!$result['semantic']) {
            $semantic_match = $this->check_semantic_patterns_optimized($message_lower, $message_original);
            if ($semantic_match) {
                $result['semantic'] = true;
                $result['reasoning'][] = 'Matched semantic pattern (regex/question format)';
            }
        }
        
        return $result;
    }
    
    /**
     * Get database keywords as hash map (lazy-loaded, cached)
     * 
     * Database Query Keywords (triggers database_query or hybrid classification):
     * - Action words: list, show, find, search, display, view, browse, look, see
     * - Listing words: listing, available, inventory, stock
     * - Purchase words: buy, sale, purchase, acquire, get, obtain, shop, marketplace
     * - Price words: price, cost, afford, budget, expensive, cheap, value, worth, pricing
     * - Location words: where, location, area, region, city, place
     * - Boat type words: boat, yacht, vessel, craft, ship, sailboat, powerboat, catamaran
     * 
     * Also matches price patterns via regex (see check_price_patterns_optimized):
     * - Currency symbols: $, €, £, dollar, euro, pound
     * - Price ranges: under, below, over, above, between
     * - Price questions: "how much", "what price", "what cost"
     * - Affordability: affordable, budget, cheap, expensive
     * 
     * @return array Hash map of keywords
     */
    private static $db_keywords_map_cache = null;
    private function get_db_keywords_map() {
        if (self::$db_keywords_map_cache !== null) {
            return self::$db_keywords_map_cache;
        }
        
        $keywords = array(
            'list', 'show', 'find', 'search', 'price', 'cost', 'buy', 'sale', 'listing',
            'display', 'view', 'browse', 'look', 'see', 'available', 'inventory', 'stock',
            'purchase', 'acquire', 'get', 'obtain', 'shop', 'marketplace',
            'afford', 'budget', 'expensive', 'cheap', 'value', 'worth', 'pricing',
            'where', 'location', 'area', 'region', 'city', 'place',
            'boat', 'yacht', 'vessel', 'craft', 'ship', 'sailboat', 'powerboat', 'catamaran'
        );
        
        self::$db_keywords_map_cache = array_flip($keywords);
        return self::$db_keywords_map_cache;
    }
    
    /**
     * Get semantic keywords as hash map (lazy-loaded, cached)
     * 
     * Semantic Query Keywords (triggers hybrid or general_knowledge classification):
     * 
     * 1. Descriptive Adjectives:
     *    - Luxury: luxury, luxurious, premium, high-quality
     *    - Comfort: comfortable, comfort, spacious, roomy
     *    - Condition: well-maintained, maintained, reliable, durable
     *    - Style: modern, classic, vintage, contemporary, traditional, elegant, stylish
     *    - Appearance: beautiful, gorgeous, stunning, impressive, amazing
     *    - Safety: safe, secure
     * 
     * 2. Subjective/Evaluative Terms:
     *    - best, good, nice, great, excellent, perfect, ideal, suitable
     *    - popular, famous, renowned, well-known, recommended
     *    - top, superior, outstanding, exceptional
     * 
     * 3. Feature Words:
     *    - with, has, have, includes, including, features, feature
     *    - Facilities: galley, kitchen, bathroom, head, shower
     *    - Accommodation: sleeps, sleep, berth, berths, cabin, cabins
     *    - Equipment: generator, air conditioning, ac, heating, gps, radar, autopilot
     *    - Parts: anchor, winch, sail, engine, engines, motor, motors
     *    - Appliances: refrigerator, fridge, freezer
     * 
     * 4. Use Case Phrases:
     *    - for fishing, for cruising, for racing, for charter, for sailing
     *    - for living, for weekend, for day trips, for long distance
     *    - for beginners, for families, for couples, for solo
     *    - suitable for, good for, perfect for, ideal for
     * 
     * 5. Comparative Terms:
     *    - better, compared to, similar to, like, similar, comparable
     *    - versus, vs, different from, unlike, same as, equivalent
     * 
     * 6. Question Words (also matched via regex patterns):
     *    - what, which, who, how, where, when, why
     *    - are there, can you, could you, would you, tell me
     * 
     * Additional semantic indicators:
     * - Query length >= 10 words automatically triggers semantic indicator
     * - Multiple adjectives (2+) in query triggers semantic indicator
     * - Question format (starts with question word) triggers semantic indicator
     * 
     * @return array Hash map of keywords
     */
    private static $semantic_keywords_map_cache = null;
    private function get_semantic_keywords_map() {
        if (self::$semantic_keywords_map_cache !== null) {
            return self::$semantic_keywords_map_cache;
        }
        
        $keywords = array_merge(
            array('luxury', 'luxurious', 'comfortable', 'comfort', 'fast', 'spacious', 'roomy',
                  'well-maintained', 'well maintained', 'maintained', 'family-friendly', 'family friendly',
                  'modern', 'classic', 'vintage', 'contemporary', 'traditional', 'elegant', 'stylish',
                  'beautiful', 'gorgeous', 'stunning', 'impressive', 'amazing', 'excellent', 'premium',
                  'high-quality', 'high quality', 'quality', 'reliable', 'durable', 'safe', 'secure'),
            array('best', 'good', 'nice', 'great', 'excellent', 'perfect', 'ideal', 'suitable',
                  'popular', 'famous', 'renowned', 'well-known', 'well known', 'recommended',
                  'top', 'premium', 'superior', 'outstanding', 'exceptional'),
            array('with', 'has', 'have', 'includes', 'including', 'features', 'feature',
                  'galley', 'kitchen', 'sleeps', 'sleep', 'berth', 'berths', 'cabin', 'cabins',
                  'generator', 'air conditioning', 'ac', 'heating', 'gps', 'radar', 'autopilot',
                  'anchor', 'winch', 'sail', 'engine', 'engines', 'motor', 'motors',
                  'bathroom', 'head', 'shower', 'refrigerator', 'fridge', 'freezer'),
            array('for fishing', 'for cruising', 'for racing', 'for charter', 'for sailing',
                  'for living', 'for weekend', 'for day trips', 'for long distance',
                  'for beginners', 'for families', 'for couples', 'for solo',
                  'suitable for', 'good for', 'perfect for', 'ideal for'),
            array('better', 'compared to', 'similar to', 'like', 'similar', 'comparable',
                  'versus', 'vs', 'different from', 'unlike', 'same as', 'equivalent'),
            array('what', 'which', 'who', 'how', 'where', 'when', 'why',
                  'are there', 'can you', 'could you', 'would you', 'tell me')
        );
        
        self::$semantic_keywords_map_cache = array_flip($keywords);
        return self::$semantic_keywords_map_cache;
    }
    
    /**
     * Check price patterns using compiled regex (static, pre-compiled)
     * 
     * @param string $message_original Original message
     * @return bool True if price pattern found
     */
    private static $price_patterns_compiled = null;
    private function check_price_patterns_optimized($message_original) {
        if (self::$price_patterns_compiled === null) {
            self::$price_patterns_compiled = array(
                '/\b(how\s+much|what.*\s+(?:is|are|does|do).*\s+(?:the\s+)?(?:price|cost|value|worth))\b/i',
                '/\b(what.*\s+(?:price|cost|pricing|value))\b/i',
                '/\b(price|cost|pricing|priced|costs)\b/i',
                '/\b(\$|dollar|dollars|usd|€|euro|euros|£|pound|pounds)\b/i',
                '/\b(under|below|less\s+than|cheaper\s+than|under\s+\$|below\s+\$)\b/i',
                '/\b(over|above|more\s+than|greater\s+than|exceeding|over\s+\$|above\s+\$)\b/i',
                '/\b(between|from\s+\$|to\s+\$|up\s+to|around\s+\$|about\s+\$)\b/i',
                '/\b(affordable|afford|budget|cheap|inexpensive|expensive|costly|reasonably\s+priced)\b/i',
                '/\b(cheapest|most\s+expensive|lowest\s+price|highest\s+price|best\s+price|best\s+deal)\b/i',
                '/\b(value|worth|worth\s+it|good\s+value|worth\s+the\s+price)\b/i',
                '/\b(for\s+sale|selling\s+for|asking\s+price|list\s+price|market\s+price)\b/i',
                '/\b(how\s+.*\s+(?:price|cost)|what.*\s+(?:price|cost)|tell\s+me.*\s+(?:price|cost))\b/i'
            );
        }
        
        foreach (self::$price_patterns_compiled as $pattern) {
            if (preg_match($pattern, $message_original)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check semantic patterns using compiled regex and optimized logic
     * 
     * @param string $message_lower Lowercase message
     * @param string $message_original Original message
     * @return bool True if semantic pattern found
     */
    private static $question_patterns_compiled = null;
    private function check_semantic_patterns_optimized($message_lower, $message_original) {
        if (self::$question_patterns_compiled === null) {
            $question_indicators = array('what', 'which', 'who', 'how', 'where', 'when', 'why',
                                       'are there', 'can you', 'could you', 'would you', 'tell me');
            self::$question_patterns_compiled = array();
            foreach ($question_indicators as $indicator) {
                self::$question_patterns_compiled[] = '/^' . preg_quote($indicator, '/') . '\s+/i';
            }
        }
        
        foreach (self::$question_patterns_compiled as $pattern) {
            if (preg_match($pattern, $message_lower)) {
                return true;
            }
        }
        
        // Check for complex multi-criteria patterns
        $common_adjectives = array('luxury', 'comfortable', 'spacious', 'modern', 'classic', 'beautiful', 
                                  'well-maintained', 'family-friendly', 'fast', 'reliable', 'safe');
        $adjective_count = 0;
        foreach ($common_adjectives as $adj) {
            // Use word boundaries to match whole words only (not substrings)
            $escaped_adj = preg_quote($adj, '/');
            if (preg_match('/\b' . $escaped_adj . '\b/i', $message_lower)) {
                $adjective_count++;
                if ($adjective_count >= 2) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Log intent classification with detailed reasoning and matched keywords
     * 
     * @param string $message Original user message
     * @param string $intent Classified intent (hybrid, database_query, general_knowledge)
     * @param array $indicators Result from check_intent_indicators_optimized
     */
    private function log_intent_classification($message, $intent, $indicators) {
        $log_data = array(
            'query' => $message,
            'classified_as' => $intent,
            'db_indicator' => $indicators['db'] ? 'YES' : 'NO',
            'semantic_indicator' => $indicators['semantic'] ? 'YES' : 'NO',
            'matched_db_keywords' => !empty($indicators['matched_keywords']['db']) ? $indicators['matched_keywords']['db'] : array(),
            'matched_semantic_keywords' => !empty($indicators['matched_keywords']['semantic']) ? $indicators['matched_keywords']['semantic'] : array(),
            'reasoning' => !empty($indicators['reasoning']) ? $indicators['reasoning'] : array()
        );
        
        // Build detailed log message
        $log_message = "[Boat Chatbot] Intent Classification:\n";
        $log_message .= "  Query: \"$message\"\n";
        $log_message .= "  Classified as: $intent\n";
        $log_message .= "  DB Indicator: " . ($indicators['db'] ? 'YES' : 'NO') . "\n";
        $log_message .= "  Semantic Indicator: " . ($indicators['semantic'] ? 'YES' : 'NO') . "\n";
        
        if (!empty($indicators['matched_keywords']['db'])) {
            $log_message .= "  Matched DB Keywords: " . implode(', ', array_unique($indicators['matched_keywords']['db'])) . "\n";
        }
        
        if (!empty($indicators['matched_keywords']['semantic'])) {
            $log_message .= "  Matched Semantic Keywords: " . implode(', ', array_unique($indicators['matched_keywords']['semantic'])) . "\n";
        }
        
        if (!empty($indicators['reasoning'])) {
            $log_message .= "  Reasoning: " . implode('; ', $indicators['reasoning']) . "\n";
        }
        
        // List all available keywords for reference
        $log_message .= "\n  Available Keywords:\n";
        $db_keywords_list = array_keys($this->get_db_keywords_map());
        $semantic_keywords_list = array_keys($this->get_semantic_keywords_map());
        $log_message .= "    DB Keywords: " . implode(', ', $db_keywords_list) . "\n";
        $log_message .= "    Semantic Keywords: " . implode(', ', array_slice($semantic_keywords_list, 0, 30)) . (count($semantic_keywords_list) > 30 ? '... (+' . (count($semantic_keywords_list) - 30) . ' more)' : '') . "\n";
        
        error_log($log_message);
    }
    
    /**
     * Check if message has database query indicators
     * Returns true if message contains keywords indicating a database search
     */
    private function has_database_query_indicators($message_lower, $message_original) {
        // Expanded keyword map with synonyms and fuzzy matching
        $db_keywords = array(
            // Direct keywords
            'list', 'show', 'find', 'search', 'price', 'cost', 'buy', 'sale', 'listing',
            // Synonyms
            'display', 'view', 'browse', 'look', 'see', 'available', 'inventory', 'stock',
            'purchase', 'acquire', 'get', 'obtain', 'shop', 'marketplace',
            // Price-related
            'afford', 'budget', 'expensive', 'cheap', 'value', 'worth', 'pricing',
            // Location-related
            'where', 'location', 'area', 'region', 'city', 'place',
            // Type-related
            'boat', 'yacht', 'vessel', 'craft', 'ship', 'sailboat', 'powerboat', 'catamaran'
        );
        
        // Check if message contains any keyword
        foreach ($db_keywords as $keyword) {
            if (strpos($message_lower, $keyword) !== false) {
                return true;
            }
        }
        
        // Additional pattern matching for price/cost-related queries
        $price_patterns = array(
            // Direct price/cost questions
            '/\b(how\s+much|what.*\s+(?:is|are|does|do).*\s+(?:the\s+)?(?:price|cost|value|worth))\b/i',
            '/\b(what.*\s+(?:price|cost|pricing|value))\b/i',
            '/\b(price|cost|pricing|priced|costs)\b/i',
            
            // Currency mentions
            '/\b(\$|dollar|dollars|usd|€|euro|euros|£|pound|pounds)\b/i',
            
            // Price range queries
            '/\b(under|below|less\s+than|cheaper\s+than|under\s+\$|below\s+\$)\b/i',
            '/\b(over|above|more\s+than|greater\s+than|exceeding|over\s+\$|above\s+\$)\b/i',
            '/\b(between|from\s+\$|to\s+\$|up\s+to|around\s+\$|about\s+\$)\b/i',
            
            // Affordability and budget queries
            '/\b(affordable|afford|budget|cheap|inexpensive|expensive|costly|reasonably\s+priced)\b/i',
            
            // Comparative pricing
            '/\b(cheapest|most\s+expensive|lowest\s+price|highest\s+price|best\s+price|best\s+deal)\b/i',
            
            // Value and worth queries
            '/\b(value|worth|worth\s+it|good\s+value|worth\s+the\s+price)\b/i',
            
            // Sale and purchase price queries
            '/\b(for\s+sale|selling\s+for|asking\s+price|list\s+price|market\s+price)\b/i',
            
            // Price-related questions
            '/\b(how\s+.*\s+(?:price|cost)|what.*\s+(?:price|cost)|tell\s+me.*\s+(?:price|cost))\b/i'
        );
        
        foreach ($price_patterns as $pattern) {
            if (preg_match($pattern, $message_original)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if message has semantic/complex query indicators
     * Returns true if message contains descriptive, subjective, or complex language
     * that would benefit from semantic/vector search
     */
    private function has_semantic_query_indicators($message_lower, $message_original) {
        // Natural language descriptive words
        $descriptive_words = array(
            'luxury', 'luxurious', 'comfortable', 'comfort', 'fast', 'spacious', 'roomy',
            'well-maintained', 'well maintained', 'maintained', 'family-friendly', 'family friendly',
            'modern', 'classic', 'vintage', 'contemporary', 'traditional', 'elegant', 'stylish',
            'beautiful', 'gorgeous', 'stunning', 'impressive', 'amazing', 'excellent', 'premium',
            'high-quality', 'high quality', 'quality', 'reliable', 'durable', 'safe', 'secure'
        );
        
        // Subjective criteria
        $subjective_words = array(
            'best', 'good', 'nice', 'great', 'excellent', 'perfect', 'ideal', 'suitable',
            'popular', 'famous', 'renowned', 'well-known', 'well known', 'recommended',
            'top', 'premium', 'superior', 'outstanding', 'exceptional'
        );
        
        // Feature-based indicators
        $feature_indicators = array(
            'with', 'has', 'have', 'includes', 'including', 'features', 'feature',
            'galley', 'kitchen', 'sleeps', 'sleep', 'berth', 'berths', 'cabin', 'cabins',
            'generator', 'air conditioning', 'ac', 'heating', 'gps', 'radar', 'autopilot',
            'anchor', 'winch', 'sail', 'engine', 'engines', 'motor', 'motors',
            'bathroom', 'head', 'shower', 'refrigerator', 'fridge', 'freezer'
        );
        
        // Use case indicators
        $use_case_indicators = array(
            'for fishing', 'for cruising', 'for racing', 'for charter', 'for sailing',
            'for living', 'for weekend', 'for day trips', 'for long distance',
            'for beginners', 'for families', 'for couples', 'for solo',
            'suitable for', 'good for', 'perfect for', 'ideal for'
        );
        
        // Comparative indicators
        $comparative_indicators = array(
            'better', 'compared to', 'similar to', 'like', 'similar', 'comparable',
            'versus', 'vs', 'different from', 'unlike', 'same as', 'equivalent'
        );
        
        // Question format indicators
        $question_indicators = array(
            'what', 'which', 'who', 'how', 'where', 'when', 'why',
            'are there', 'can you', 'could you', 'would you', 'tell me'
        );
        
        // Check for descriptive words
        foreach ($descriptive_words as $word) {
            if (strpos($message_lower, $word) !== false) {
                return true;
            }
        }
        
        // Check for subjective words
        foreach ($subjective_words as $word) {
            if (strpos($message_lower, $word) !== false) {
                return true;
            }
        }
        
        // Check for feature indicators
        foreach ($feature_indicators as $indicator) {
            if (strpos($message_lower, $indicator) !== false) {
                return true;
            }
        }
        
        // Check for use case indicators
        foreach ($use_case_indicators as $indicator) {
            if (strpos($message_lower, $indicator) !== false) {
                return true;
            }
        }
        
        // Check for comparative indicators
        foreach ($comparative_indicators as $indicator) {
            if (strpos($message_lower, $indicator) !== false) {
                return true;
            }
        }
        
        // Check for question format (especially at the beginning)
        foreach ($question_indicators as $indicator) {
            if (preg_match('/^' . preg_quote($indicator, '/') . '\s+/i', $message_lower)) {
                return true;
            }
        }
        
        // Check for longer queries (10+ words typically indicate complex intent)
        $word_count = str_word_count($message_original);
        if ($word_count >= 10) {
            return true;
        }
        
        // Check for complex multi-criteria patterns (multiple adjectives/descriptors)
        $adjective_count = 0;
        $common_adjectives = array('luxury', 'comfortable', 'spacious', 'modern', 'classic', 'beautiful', 
                                  'well-maintained', 'family-friendly', 'fast', 'reliable', 'safe');
        foreach ($common_adjectives as $adj) {
            if (strpos($message_lower, $adj) !== false) {
                $adjective_count++;
            }
        }
        if ($adjective_count >= 2) {
            return true; // Multiple descriptive criteria indicates semantic complexity
        }
        
        return false;
    }
    
    private function handle_database_query_optimized($user_message, &$performance_log, $conversation_history = array(), $offset = 0) {
        // Ensure all required dependencies are loaded
        $this->ensure_core_dependencies();
        
        $db_start = microtime(true);
        $db_manager = new Boat_Chatbot_Database_Manager();
        
        // Extract requested item count from user message
        $requested_count = $db_manager->extract_item_count($user_message);
        
        // Sanitize offset
        $offset = max(0, intval($offset));
        
        // Get total count first to determine pagination behavior
        $total_listings = $db_manager->get_total_count($user_message);
        
        // Debug logging
        error_log('[Boat Chatbot] handle_database_query_optimized: Query="' . $user_message . '", Total=' . $total_listings . ', RequestedCount=' . ($requested_count !== null ? $requested_count : 'null'));
        
        // Determine query limit and pagination behavior:
        // - If specific count requested: show only that many items (no pagination)
        // - If no count specified: show 5 items per page (with pagination)
        if ($requested_count !== null) {
            // Specific count requested: show only that many items, no pagination
            $query_limit = min($requested_count, $total_listings); // Don't exceed total
            $enable_pagination = false;
        } else {
            // No count specified: show 5 items per page with pagination
            // If offset > 0, this is a "Load More" request, so always use 5 items per page
            $query_limit = 5; // Always 5 for pagination
            $enable_pagination = true;
        }
        
        // Debug logging for limit
        error_log('[Boat Chatbot] handle_database_query_optimized: QueryLimit=' . $query_limit . ', Offset=' . $offset . ', EnablePagination=' . ($enable_pagination ? 'true' : 'false'));
        
        $initial_listings = $db_manager->query_listings($user_message, $query_limit, $offset);
        
        // Debug logging for results
        error_log('[Boat Chatbot] handle_database_query_optimized: Returned ' . count($initial_listings) . ' listings (expected ' . $query_limit . ')');
        
        $performance_log['db_time'] = microtime(true) - $db_start;
        
        // If no results found with specific search, try a broader search (no filters)
        if (empty($initial_listings)) {
            // Try getting all listings as fallback to see if database has any data
            $all_listings = $db_manager->query_listings('', 5, 0);
            
            if (empty($all_listings)) {
                // Database appears to be empty or connection issue
                return array(
                    'response' => "I couldn't find any listings in the database. Please check the database connection and ensure there are listings available.",
                    'listings' => array(),
                    'total_listings' => 0
                );
            } else {
                // Database has listings but search didn't match - show all listings and let AI help
                // Process results: deduplication, relevance scoring, and sorting
                $processed_listings = $this->process_results_for_ai($all_listings, $user_message);
                $total_all = $db_manager->get_total_count('');
                
                // Get AI response - only pass count, not the full listing data to reduce tokens
                // Track Grok API time
                $grok_api_start = microtime(true);
                $ai_response = $this->get_ai_response_optimized($user_message, $total_all, true, $conversation_history);
                $performance_log['grok_api_time'] = microtime(true) - $grok_api_start;
                // Keep ai_time for backward compatibility
                $performance_log['ai_time'] = $performance_log['grok_api_time'];
                
                // For fallback search, enable pagination (no specific count requested)
                return array(
                    'response' => $ai_response,
                    'listings' => $processed_listings,
                    'total_listings' => $total_all,
                    'has_more' => $total_all > count($processed_listings),
                    'enable_pagination' => true,
                    'requested_count' => null,
                    'offset' => 0, // Fallback always starts at offset 0
                    'limit' => 5 // Fallback uses default limit of 5
                );
            }
        }
        
        // Process results FIRST (deduplication, relevance scoring, and sorting)
        // This ensures Grok sees the same boat listings in the same order as displayed to users
        error_log('[Boat Chatbot] handle_database_query_optimized: Before processing - ' . count($initial_listings) . ' listings from database');
        $processed_listings = $this->process_results_for_ai($initial_listings, $user_message);
        error_log('[Boat Chatbot] handle_database_query_optimized: After processing - ' . count($processed_listings) . ' listings (expected ' . $query_limit . ')');
        
        // Format processed listings for AI
        $formatted_results = $this->format_listings_for_ai($processed_listings, $user_message);
        $performance_log['formatted_results'] = $formatted_results;
        // Get AI response with formatted listings (so Grok can reference specific boats)
        // Track Grok API time
        $grok_api_start = microtime(true);
        $ai_response = $this->get_ai_response_optimized($user_message, $formatted_results, true, $conversation_history);
        $performance_log['grok_api_time'] = microtime(true) - $grok_api_start;
        // Keep ai_time for backward compatibility
        $performance_log['ai_time'] = $performance_log['grok_api_time'];

        // Validate that AI response only references boats from our data
        $ai_response = $this->validate_ai_response_boats($ai_response, $processed_listings);
        
        // IMPORTANT: Re-query total count after processing to ensure accuracy
        // Sometimes processing can filter out results, so we need the actual total
        // Also handles cases where "all" keyword might affect the count
        $actual_total = $db_manager->get_total_count($user_message);
        if ($actual_total > $total_listings) {
            // If actual total is higher, use it (this handles "all" keyword cases)
            $total_listings = $actual_total;
            error_log('[Boat Chatbot] Total count updated: Original=' . $total_listings . ', Actual=' . $actual_total . ' for query="' . $user_message . '"');
        }
        
        // Determine if there are more listings based on pagination mode
        if ($enable_pagination) {
            // Pagination enabled: show "More View" button if there are more items
            $has_more = $total_listings > count($processed_listings);
        } else {
            // Specific count requested: no pagination, show all requested items
            $has_more = false;
        }
        
        return array(
            'response' => $ai_response,
            'listings' => $processed_listings,
            'total_listings' => $total_listings,
            'has_more' => $has_more,
            'enable_pagination' => $enable_pagination, // Flag to indicate if pagination is enabled
            'requested_count' => $requested_count, // Include requested count for frontend reference
            'offset' => $offset, // Current offset for pagination
            'limit' => $query_limit // Current limit (items per page)
        );
    }
    
    private function handle_general_query_optimized($user_message, &$performance_log, $conversation_history = array()) {
        // Track Grok API time
        $grok_api_start = microtime(true);
        $response = $this->get_ai_response_optimized($user_message, '', false, $conversation_history);
        $performance_log['grok_api_time'] = microtime(true) - $grok_api_start;
        // Keep ai_time for backward compatibility
        $performance_log['ai_time'] = $performance_log['grok_api_time'];
        
        return array(
            'response' => $response,
            'listings' => array(),
            'total_listings' => 0
        );
    }
    
    /**
     * Handle hybrid queries using vector search + SQL filters
     * 
     * @param string $user_message User query
     * @param array $performance_log Performance tracking array
     * @param array $conversation_history Conversation history for context
     * @return array Response data
     */
    private function handle_hybrid_query_optimized($user_message, &$performance_log, $conversation_history = array(), $offset = 0) {
        // Ensure all required dependencies are loaded
        $this->ensure_core_dependencies();
        
        // Sanitize offset
        $offset = max(0, intval($offset));
        
        // Extract filters from user query using reflection or public method
        // Since extract_search_terms is private, we'll use the database manager's query_listings
        // which internally extracts search terms, but we need direct access
        // For now, we'll create a simple extraction here or make the method public
        $db_manager = new Boat_Chatbot_Database_Manager();
        
        // Use reflection to access private method (temporary solution)
        // Better: make extract_search_terms public in Database_Manager
        $reflection = new ReflectionClass($db_manager);
        $method = $reflection->getMethod('extract_search_terms');
        $method->setAccessible(true);
        $search_terms = $method->invoke($db_manager, $user_message);
        $performance_log['search_terms'] = $search_terms;
        // Extract requested item count from user message
        $requested_count = $db_manager->extract_item_count($user_message);
        // Determine pagination behavior:
        // - If specific count requested: show only that many (no pagination)
        // - If no count specified: show 5 items per page (with pagination)
        $enable_pagination = ($requested_count === null);
        $max_results = $requested_count !== null ? $requested_count : 5;
        
        // If a specific count is requested, offset must be 0 (no pagination allowed)
        if ($requested_count !== null && $offset > 0) {
            error_log('[Boat Chatbot] Hybrid Query Optimized - Offset > 0 not allowed when specific count requested. Query: "' . substr($user_message, 0, 100) . '", RequestedCount=' . $requested_count . ', Offset=' . $offset);
            $offset = 0; // Reset to 0 for specific count requests
        }
        
        // Check cache for sorted IDs (only for offset > 0 or to store results)
        $cache_key_sorted_ids = 'hybrid_optimized_sorted_ids_' . md5($user_message);
        $cached_sorted_ids = false;
        
        // If offset > 0, try to get cached sorted IDs first (only if pagination is enabled)
        if ($offset > 0 && $enable_pagination) {
            $cached_sorted_ids = $this->get_cache($cache_key_sorted_ids);
            error_log('[Boat Chatbot] Hybrid Query Optimized - Cache lookup: Key="' . $cache_key_sorted_ids . '", Query="' . substr($user_message, 0, 100) . '", Found=' . ($cached_sorted_ids !== false ? 'yes (' . (is_array($cached_sorted_ids) ? count($cached_sorted_ids) : 'not array') . ' IDs)' : 'no'));
            if ($cached_sorted_ids !== false && is_array($cached_sorted_ids) && !empty($cached_sorted_ids)) {
                // Use cached sorted IDs and apply offset/limit
                $total_cached = count($cached_sorted_ids);
                $sliced_ids = array_slice($cached_sorted_ids, $offset, $max_results);
                
                if (empty($sliced_ids)) {
                    // No more results
                    return array(
                        'response' => "No more listings available.",
                        'listings' => array(),
                        'total_listings' => $total_cached,
                        'has_more' => false,
                        'enable_pagination' => $enable_pagination,
                        'requested_count' => $requested_count,
                        'offset' => $offset,
                        'limit' => $max_results
                    );
                }
                
                // Get full listings from database using cached IDs
                $db_start = microtime(true);
                $listings = $this->get_listings_by_ids($sliced_ids);
                $performance_log['db_time'] = microtime(true) - $db_start;
                
                // Reorder listings according to sliced_ids order
                $listings_by_id = array();
                foreach ($listings as $listing) {
                    $id = is_object($listing) ? (isset($listing->ID) ? intval($listing->ID) : 0) : (isset($listing['ID']) ? intval($listing['ID']) : 0);
                    if ($id > 0) {
                        $listings_by_id[$id] = $listing;
                    }
                }
                
                $ordered_listings = array();
                foreach ($sliced_ids as $id) {
                    if (isset($listings_by_id[$id])) {
                        $ordered_listings[] = $listings_by_id[$id];
                    }
                }
                
                // Process results
                $processed_listings = $this->process_results_for_ai($ordered_listings, $user_message);
                $formatted_results = $this->format_listings_for_ai($processed_listings, $user_message);
                $performance_log['formatted_results'] = $formatted_results;
                // Get AI response
                $grok_api_start = microtime(true);
                $ai_response = $this->get_ai_response_optimized($user_message, $formatted_results, true, $conversation_history);
                $performance_log['grok_api_time'] = microtime(true) - $grok_api_start;
                $performance_log['ai_time'] = $performance_log['grok_api_time'];
                
                $has_more = ($offset + count($processed_listings)) < $total_cached;
                
                return array(
                    'response' => $ai_response,
                    'listings' => $processed_listings,
                    'total_listings' => $total_cached,
                    'has_more' => $has_more,
                    'enable_pagination' => $enable_pagination,
                    'requested_count' => $requested_count,
                    'offset' => $offset,
                    'limit' => $max_results
                );
            } else {
                // Cache not found for offset > 0 - fallback to database query
                error_log('[Boat Chatbot] Hybrid Query Optimized - Cache not found for offset > 0, falling back to database query. Query: "' . substr($user_message, 0, 100) . '", Offset=' . $offset);
                return $this->handle_database_query_optimized($user_message, $performance_log, $conversation_history, $offset);
            }
        }
        
        // Get vector search results (only for offset = 0 or when cache was not needed)
        $groq_manager = Boat_Chatbot_Groq_Embeddings_Manager::get_instance();
        $pinecone_manager = Boat_Chatbot_Pinecone_Manager::get_instance();
        
        // Get sparse vector generator for hybrid search
        $sparse_generator = null;
        if (class_exists('Boat_Chatbot_Sparse_Vector_Generator')) {
            $sparse_generator = Boat_Chatbot_Sparse_Vector_Generator::get_instance();
        }
        
        // Generate query embedding - track embedding time
        $embedding_start = microtime(true);
        $query_embedding = $groq_manager->generate_embedding($user_message);
        $performance_log['embedding_time'] = microtime(true) - $embedding_start;
        
        if ($query_embedding === false) {
            // Fallback to database query if embedding fails
            return $this->handle_database_query_optimized($user_message, $performance_log, $conversation_history);
        }
        
        // Build Pinecone metadata filter from structured search terms
        // This applies filters BEFORE similarity search, reducing vectors compared
        $pinecone_filter = $this->build_pinecone_filter($search_terms);
        $performance_log['search_terms'] = $search_terms;
        $performance_log['pinecone_filter'] = $pinecone_filter;
        // Reduced top_k for faster retrieval (filters applied at Pinecone level)
        // With metadata filters, we need fewer results since filtering happens before retrieval
        $top_k = 25; // Reduced from 50 - filters are applied at Pinecone level
        
        // Generate sparse vector for hybrid search if available
        $sparse_vector = null;
        $hybrid_alpha = floatval(get_option('boat_chatbot_hybrid_alpha', 0.7)); // Default 0.7 (70% dense, 30% sparse)
        $use_embedding_for_sparse = get_option('boat_chatbot_sparse_use_embedding', false);
        
        if ($sparse_generator && $sparse_generator->has_vocabulary()) {
            $sparse_vector = $sparse_generator->generate_sparse_vector($user_message);
            if ($sparse_vector !== false) {
                $method = $use_embedding_for_sparse ? 'embedding-based (same as dense)' : 'BM25';
                error_log('[Boat Chatbot] Hybrid Query Optimized - Sparse vector generated (' . $method . '): "' . substr($user_message, 0, 100) . '"'); 
                $performance_log['sparse_vector_generated'] = true;
                $performance_log['sparse_vector_method'] = $use_embedding_for_sparse ? 'embedding' : 'bm25';
                $performance_log['hybrid_alpha'] = $hybrid_alpha;
            } else {
                error_log('[Boat Chatbot] Hybrid Query Optimized - Sparse vector generation failed: "' . substr($user_message, 0, 100) . '"'); 
                $performance_log['sparse_vector_generated'] = false;
                $performance_log['sparse_vector_error'] = 'Generation failed';
            }
        } else {
            $reason = !$sparse_generator ? 'Sparse generator not available' : 'No vocabulary loaded';
            error_log('[Boat Chatbot] Hybrid Query Optimized - Sparse vector not used: ' . $reason . '. Query: "' . substr($user_message, 0, 100) . '"'); 
            $performance_log['sparse_vector_generated'] = false;
            $performance_log['sparse_vector_reason'] = $reason;
        }
        
        // Query Pinecone with metadata filters applied BEFORE similarity search - track vector search time
        $vector_search_start = microtime(true);
        // Use hybrid search if sparse vector is available
        if ($sparse_vector !== null && is_array($sparse_vector)) {
            $vector_results = $pinecone_manager->query($query_embedding, $top_k, $pinecone_filter, $sparse_vector, $hybrid_alpha);
            $performance_log['hybrid_search_used'] = true;
        } else {
            $vector_results = $pinecone_manager->query($query_embedding, $top_k, $pinecone_filter);
            $performance_log['hybrid_search_used'] = false;
        }
        $performance_log['vector_search_time'] = microtime(true) - $vector_search_start;
        $performance_log['pinecone_filter']  =$pinecone_filter;
        if ($vector_results === false || empty($vector_results)) {
            // Fallback to database query if vector search fails
            return $this->handle_database_query_optimized($user_message, $performance_log, $conversation_history);
        }
        
        // Apply minimum similarity threshold (default 0.7 for cosine similarity)
        // Pinecone returns scores between 0-1 for cosine similarity (higher is better)
        $min_similarity = 0.7; // Configurable threshold
        $filtered_vector_results = array();
        foreach ($vector_results as $result) {
            $score = isset($result['score']) ? floatval($result['score']) : 0;
            if ($score >= $min_similarity) {
                $filtered_vector_results[] = $result;
            }
        }
        
        if (empty($filtered_vector_results)) {
            // If no results meet similarity threshold, try with lower threshold
            $min_similarity = 0.4;
            foreach ($vector_results as $result) {
                $score = isset($result['score']) ? floatval($result['score']) : 0;
                if ($score >= $min_similarity) {
                    $filtered_vector_results[] = $result;
                }
            }
        }
        
        if (empty($filtered_vector_results)) {
            // Fallback to database query if no results meet similarity threshold
            return $this->handle_database_query_optimized($user_message, $performance_log, $conversation_history);
        }
        
        // Extract record IDs from filtered vector results
        $record_ids = array();
        $vector_scores = array(); // Store scores for later use
        foreach ($filtered_vector_results as $result) {
            if (isset($result['id'])) {
                $id = intval($result['id']);
                $record_ids[] = $id;
                $vector_scores[$id] = isset($result['score']) ? floatval($result['score']) : 0;
            }
        }
        
        if (empty($record_ids)) {
            return $this->handle_database_query_optimized($user_message, $performance_log, $conversation_history);
        }
        
        // Get full records from SQL database using the IDs from vector search
        $db_start = microtime(true);
        $listings = $this->get_listings_by_ids($record_ids);
        $performance_log['db_time'] = microtime(true) - $db_start;
        
        // Apply post-retrieval filters only for non-structured filters (text-based searches)
        // Structured filters (price, length, location, year, manufacturer, type, category, model) are already applied at Pinecone level
        $sql_filter_start = microtime(true);
        if (!empty($search_terms) && !empty($listings)) {
            // Create a filtered search_terms array excluding structured filters already applied at Pinecone
            $post_filter_terms = $search_terms;
            // Remove structured filters that were applied at Pinecone level
            unset($post_filter_terms['min_price']);
            unset($post_filter_terms['max_price']);
            unset($post_filter_terms['min_length']);
            unset($post_filter_terms['max_length']);
            unset($post_filter_terms['length']);
            unset($post_filter_terms['min_year']);
            unset($post_filter_terms['max_year']);
            unset($post_filter_terms['year']);
            unset($post_filter_terms['location']); // Location filter applied at Pinecone level
            unset($post_filter_terms['manufacturer']); // Manufacturer filter applied at Pinecone level
            unset($post_filter_terms['type']); // Type filter applied at Pinecone level
            unset($post_filter_terms['category']); // Category filter applied at Pinecone level
            unset($post_filter_terms['model']); // Model filter applied at Pinecone level
            
            // Only apply post-retrieval filtering if there are remaining non-structured filters
            if (!empty($post_filter_terms)) {
                $where_data = $db_manager->build_where_clause_prepared($post_filter_terms);
                
                if (!empty($where_data['conditions'])) {
                    // Filter listings in memory using the WHERE conditions
                    // This handles text-based filters like category, manufacturer, type
                    $filtered_listings = array();
                    foreach ($listings as $listing) {
                        if ($this->listing_matches_filters($listing, $post_filter_terms, $where_data)) {
                            $filtered_listings[] = $listing;
                        }
                    }
                    $listings = $filtered_listings;
                }
            }
        }
        $performance_log['sql_filter_time'] = microtime(true) - $sql_filter_start;
        
        // If no listings match filters, try fallback with broader search
        if (empty($listings)) {
            // Fallback 1: Try without category filter (if category was specified)
            if (!empty($search_terms['category'])) {
                $fallback_terms = $search_terms;
                unset($fallback_terms['category']);
                $where_data = $db_manager->build_where_clause_prepared($fallback_terms);
                
                // Re-fetch listings (we already have them, just re-filter)
                $all_listings = $this->get_listings_by_ids($record_ids);
                $filtered_listings = array();
                foreach ($all_listings as $listing) {
                    if ($this->listing_matches_filters($listing, $fallback_terms, $where_data)) {
                        $filtered_listings[] = $listing;
                    }
                }
                $listings = $filtered_listings;
            }
            
            // Fallback 2: If still no results, use database query
            if (empty($listings)) {
                return $this->handle_database_query_optimized($user_message, $performance_log, $conversation_history);
            }
        }
        
        // Add vector scores to listings for ranking
        foreach ($listings as &$listing) {
            if (isset($listing->ID) && isset($vector_scores[$listing->ID])) {
                $listing->vector_score = $vector_scores[$listing->ID];
            }
        }
        
        // Process and format results (this includes deduplication, scoring, and sorting)
        $processed_listings = $this->process_results_for_ai($listings, $user_message);
        
        // Extract sorted IDs from processed listings for caching
        $all_sorted_ids = array();
        foreach ($processed_listings as $listing) {
            $id = is_object($listing) ? (isset($listing->ID) ? intval($listing->ID) : 0) : (isset($listing['ID']) ? intval($listing['ID']) : 0);
            if ($id > 0) {
                $all_sorted_ids[] = $id;
            }
        }
        
        // Cache the full sorted IDs list for pagination (only if pagination is enabled and offset is 0)
        if ($enable_pagination && $offset === 0 && !empty($all_sorted_ids)) {
            // Cache for 1 hour (3600 seconds) - enough time for user to paginate
            $this->set_cache($cache_key_sorted_ids, $all_sorted_ids, 3600);
            error_log('[Boat Chatbot] Hybrid Query Optimized - Cached ' . count($all_sorted_ids) . ' sorted IDs for query: "' . substr($user_message, 0, 100) . '", CacheKey="' . $cache_key_sorted_ids . '"');
        }
        
        // Apply offset and limit to get the current page
        $total_available = count($all_sorted_ids);
        $sliced_listings = array_slice($processed_listings, $offset, $max_results);
        
        // Format results for AI (only the sliced listings)
        $formatted_results = $this->format_listings_for_ai($sliced_listings, $user_message);
        $performance_log['formatted_results'] = $formatted_results;
        // Get AI response - track Grok API time
        $grok_api_start = microtime(true);
        $ai_response = $this->get_ai_response_optimized($user_message, $formatted_results, true, $conversation_history);
        $performance_log['grok_api_time'] = microtime(true) - $grok_api_start;
        // Keep ai_time for backward compatibility
        $performance_log['ai_time'] = $performance_log['grok_api_time'];
        
        // Determine pagination behavior
        $has_more_hybrid = $enable_pagination && (($offset + count($sliced_listings)) < $total_available);
        
        return array(
            'response' => $ai_response,
            'listings' => $sliced_listings,
            'total_listings' => $total_available,
            'has_more' => $has_more_hybrid,
            'enable_pagination' => $enable_pagination,
            'requested_count' => $requested_count,
            'offset' => $offset,
            'limit' => $max_results
        );
    }
    
    /**
     * Handle parallel hybrid search: runs Pinecone semantic search and SQL keyword search in parallel
     * Normalizes and combines scores using weighted formula (α vector + β keyword)
     * Merges and de-duplicates listings, returns ranked final result list
     * 
     * @param string $user_message User query
     * @param array $performance_log Performance tracking array
     * @param array $conversation_history Conversation history for context
     * @return array Response data
     */
    private function handle_parallel_hybrid_search($user_message, &$performance_log, $conversation_history = array(), $offset = 0) {
        // Ensure all required dependencies are loaded
        $this->ensure_core_dependencies();
        
        // Sanitize offset
        $offset = max(0, intval($offset));
        
        // Get search mode from options (default: 'hybrid')
        $search_mode = get_option('boat_chatbot_search_mode', 'hybrid'); // 'semantic', 'keyword', or 'hybrid'
        
        // Get weight coefficients (default: equal weights)
        $alpha = floatval(get_option('boat_chatbot_vector_weight', 0.5)); // Vector search weight
        $beta = floatval(get_option('boat_chatbot_keyword_weight', 0.5)); // Keyword search weight
        
        // Normalize weights to sum to 1.0
        $total_weight = $alpha + $beta;
        if ($total_weight > 0) {
            $alpha = $alpha / $total_weight;
            $beta = $beta / $total_weight;
        } else {
            $alpha = 0.5;
            $beta = 0.5;
        }
        
        $db_manager = new Boat_Chatbot_Database_Manager();
        
        // Extract requested item count from user message
        $requested_count = $db_manager->extract_item_count($user_message);
        // Determine pagination behavior:
        // - If specific count requested: show only that many (no pagination)
        // - If no count specified: show 5 items per page (with pagination)
        $enable_pagination = ($requested_count === null);
        $max_results = $requested_count !== null ? $requested_count : 5;
        
        // If a specific count is requested, offset must be 0 (no pagination allowed)
        if ($requested_count !== null && $offset > 0) {
            error_log('[Boat Chatbot] Hybrid Search - Offset > 0 not allowed when specific count requested. Query: "' . substr($user_message, 0, 100) . '", RequestedCount=' . $requested_count . ', Offset=' . $offset);
            $offset = 0; // Reset to 0 for specific count requests
        }
        
        // Check cache for sorted IDs (only for offset > 0 or to store results)
        // Use only query message for cache key to ensure consistency between initial and pagination requests
        // Search mode and weights are options that shouldn't change between requests
        $cache_key_sorted_ids = 'hybrid_sorted_ids_' . md5(trim(strtolower($user_message)));
        $cached_sorted_ids = false;
        
        // If offset > 0, try to get cached sorted IDs first (only if pagination is enabled)
        if ($offset > 0 && $enable_pagination) {
            $cached_sorted_ids = $this->get_cache($cache_key_sorted_ids);
            error_log('[Boat Chatbot] Hybrid Search (Parallel) - Cache lookup: Key="' . $cache_key_sorted_ids . '", Query="' . substr($user_message, 0, 100) . '", Found=' . ($cached_sorted_ids !== false ? 'yes (' . (is_array($cached_sorted_ids) ? count($cached_sorted_ids) : 'not array') . ' IDs)' : 'no'));
            if ($cached_sorted_ids !== false && is_array($cached_sorted_ids) && !empty($cached_sorted_ids)) {
                // Use cached sorted IDs and apply offset/limit
                $total_cached = count($cached_sorted_ids);
                $sliced_ids = array_slice($cached_sorted_ids, $offset, $max_results);
                
                if (empty($sliced_ids)) {
                    // No more results
                    return array(
                        'response' => "No more listings available.",
                        'listings' => array(),
                        'total_listings' => $total_cached,
                        'has_more' => false,
                        'enable_pagination' => $enable_pagination,
                        'requested_count' => $requested_count,
                        'offset' => $offset,
                        'limit' => $max_results,
                        'search_mode' => $search_mode
                    );
                }
                
                // Get full listings from database using cached IDs
                $db_start = microtime(true);
                $listings = $this->get_listings_by_ids($sliced_ids);
                $performance_log['db_time'] = microtime(true) - $db_start;
                
                // Reorder listings according to sliced_ids order
                $listings_by_id = array();
                foreach ($listings as $listing) {
                    $id = is_object($listing) ? (isset($listing->ID) ? intval($listing->ID) : 0) : (isset($listing['ID']) ? intval($listing['ID']) : 0);
                    if ($id > 0) {
                        $listings_by_id[$id] = $listing;
                    }
                }
                
                $ordered_listings = array();
                foreach ($sliced_ids as $id) {
                    if (isset($listings_by_id[$id])) {
                        $ordered_listings[] = $listings_by_id[$id];
                    }
                }
                
                // Process results
                $processed_listings = $this->process_results_for_ai($ordered_listings, $user_message);
                $formatted_results = $this->format_listings_for_ai($processed_listings, $user_message);
                $performance_log['formatted_results'] = $formatted_results;
                // Get AI response (simplified for pagination - can skip if needed for performance)
                $grok_api_start = microtime(true);
                $ai_response = $this->get_ai_response_optimized($user_message, $formatted_results, true, $conversation_history);
                $performance_log['grok_api_time'] = microtime(true) - $grok_api_start;
                $performance_log['ai_time'] = $performance_log['grok_api_time'];
                
                $has_more = ($offset + count($processed_listings)) < $total_cached;
                
                return array(
                    'response' => $ai_response,
                    'listings' => $processed_listings,
                    'total_listings' => $total_cached,
                    'has_more' => $has_more,
                    'enable_pagination' => $enable_pagination,
                    'requested_count' => $requested_count,
                    'offset' => $offset,
                    'limit' => $max_results,
                    'search_mode' => $search_mode
                );
            } else {
                // Cache not found for offset > 0 - fallback to database query
                error_log('[Boat Chatbot] Hybrid Search (Parallel) - Cache not found for offset > 0, falling back to database query. Query: "' . substr($user_message, 0, 100) . '", Offset=' . $offset);
                return $this->handle_database_query_optimized($user_message, $performance_log, $conversation_history, $offset);
            }
        }
        $groq_manager = Boat_Chatbot_Groq_Embeddings_Manager::get_instance();
        $pinecone_manager = Boat_Chatbot_Pinecone_Manager::get_instance();
        
        // Get sparse vector generator for hybrid search
        $sparse_generator = null;
        if (class_exists('Boat_Chatbot_Sparse_Vector_Generator')) {
            $sparse_generator = Boat_Chatbot_Sparse_Vector_Generator::get_instance();
        }
        
        // Get reranking manager
        $rerank_manager = null;
        if (class_exists('Boat_Chatbot_Reranking_Manager')) {
            $rerank_manager = Boat_Chatbot_Reranking_Manager::get_instance();
        }
        
        // Extract search terms for both searches
        $reflection = new ReflectionClass($db_manager);
        $method = $reflection->getMethod('extract_search_terms');
        $method->setAccessible(true);
        $search_terms = $method->invoke($db_manager, $user_message);
        
        // Initialize result arrays
        $vector_results = array();
        $keyword_results = array();
        $vector_scores = array();
        $keyword_scores = array();
        
        // Run searches based on mode
        if ($search_mode === 'semantic' || $search_mode === 'hybrid') {
            // Run vector search
            $embedding_start = microtime(true);
            $query_embedding = $groq_manager->generate_embedding($user_message);
            $performance_log['embedding_time'] = microtime(true) - $embedding_start;
            
            if ($query_embedding === false) {
                $error_msg = $groq_manager->get_last_error();
                error_log('[Boat Chatbot] Hybrid Search - Embedding generation failed. Query: "' . substr($user_message, 0, 100) . '". Error: ' . ($error_msg ? $error_msg : 'Unknown error'));
                $performance_log['embedding_error'] = $error_msg ? $error_msg : 'Unknown error';
            } else {
                $pinecone_filter = $this->build_pinecone_filter($search_terms);
                $performance_log['search_terms'] = $search_terms;
                $performance_log['pinecone_filter']  =$pinecone_filter;
                $top_k = 50; // Get more results for better merging
                
                // Generate sparse vector for hybrid search if available
                $sparse_vector = null;
                $hybrid_alpha = floatval(get_option('boat_chatbot_hybrid_alpha', 0.7)); // Default 0.7 (70% dense, 30% sparse)
                $use_embedding_for_sparse = get_option('boat_chatbot_sparse_use_embedding', false);
                
                if ($sparse_generator && $sparse_generator->has_vocabulary()) {
                    $sparse_vector = $sparse_generator->generate_sparse_vector($user_message);
                    if ($sparse_vector !== false) {
                        $method = $use_embedding_for_sparse ? 'embedding-based (same as dense)' : 'BM25';
                        error_log('[Boat Chatbot] Hybrid Search - Sparse vector generated (' . $method . '): "' . substr($user_message, 0, 100) . '"'); 
                        $performance_log['sparse_vector_generated'] = true;
                        $performance_log['sparse_vector_method'] = $use_embedding_for_sparse ? 'embedding' : 'bm25';
                        $performance_log['hybrid_alpha'] = $hybrid_alpha;
                    } else {
                        error_log('[Boat Chatbot] Hybrid Search - Sparse vector generation failed: "' . substr($user_message, 0, 100) . '"'); 
                        $performance_log['sparse_vector_generated'] = false;
                        $performance_log['sparse_vector_error'] = 'Generation failed';
                    }
                } else {
                    $reason = !$sparse_generator ? 'Sparse generator not available' : 'No vocabulary loaded';
                    error_log('[Boat Chatbot] Hybrid Search - Sparse vector not used: ' . $reason . '. Query: "' . substr($user_message, 0, 100) . '"'); 
                    $performance_log['sparse_vector_generated'] = false;
                    $performance_log['sparse_vector_reason'] = $reason;
                }
                
                $vector_search_start = microtime(true);
                // Use hybrid search if sparse vector is available
                if ($sparse_vector !== null && is_array($sparse_vector)) {
                    $vector_results_raw = $pinecone_manager->query($query_embedding, $top_k, $pinecone_filter, $sparse_vector, $hybrid_alpha);
                    $performance_log['hybrid_search_used'] = true;
                } else {
                    $vector_results_raw = $pinecone_manager->query($query_embedding, $top_k, $pinecone_filter);
                    $performance_log['hybrid_search_used'] = false;
                }
                $performance_log['vector_search_time'] = microtime(true) - $vector_search_start;
                
                if ($vector_results_raw === false) {
                    $error_msg = $pinecone_manager->get_last_error();
                    error_log('[Boat Chatbot] Hybrid Search - Vector search query failed. Query: "' . substr($user_message, 0, 100) . '". Error: ' . ($error_msg ? $error_msg : 'Unknown error'));
                    $performance_log['vector_search_error'] = $error_msg ? $error_msg : 'Unknown error';
                } elseif (empty($vector_results_raw)) {
                    error_log('[Boat Chatbot] Hybrid Search - Vector search returned no results. Query: "' . substr($user_message, 0, 100) . '". Top-K: ' . $top_k . ', Filter: ' . json_encode($pinecone_filter));
                    $performance_log['vector_search_empty'] = true;
                } else {
                    // Apply minimum similarity threshold
                    $min_similarity = 0.4; // Lower threshold for parallel search
                    $results_before_threshold = count($vector_results_raw);
                    foreach ($vector_results_raw as $result) {
                        $score = isset($result['score']) ? floatval($result['score']) : 0;
                        if ($score >= $min_similarity && isset($result['id'])) {
                            $id = intval($result['id']);
                            $vector_results[] = $id;
                            $vector_scores[$id] = $score; // Already normalized 0-1
                        }
                    }
                    $results_after_threshold = count($vector_results);
                    if ($results_after_threshold === 0 && $results_before_threshold > 0) {
                        error_log('[Boat Chatbot] Hybrid Search - All vector results filtered out by similarity threshold. Query: "' . substr($user_message, 0, 100) . '". Results before threshold: ' . $results_before_threshold . ', Threshold: ' . $min_similarity);
                        $performance_log['vector_threshold_filtered'] = $results_before_threshold;
                    }
                }
            }
        }
        
        if ($search_mode === 'keyword' || $search_mode === 'hybrid') {
            // Run keyword search
            $keyword_search_start = microtime(true);
            $keyword_listings = $db_manager->query_listings($user_message, 50, 0);
            $performance_log['keyword_search_time'] = microtime(true) - $keyword_search_start;
            
            if (empty($keyword_listings)) {
                error_log('[Boat Chatbot] Hybrid Search - Keyword search returned no results. Query: "' . substr($user_message, 0, 100) . '". Search terms: ' . json_encode($search_terms));
                $performance_log['keyword_search_empty'] = true;
            } else {
                // Calculate relevance scores for keyword results
                $scored_keyword_listings = $this->score_relevance($keyword_listings, $user_message);
                
                // Extract IDs and scores, normalize keyword scores
                $keyword_score_values = array();
                $valid_listings_count = 0;
                foreach ($scored_keyword_listings as $listing) {
                    $id = is_object($listing) ? (isset($listing->ID) ? intval($listing->ID) : 0) : (isset($listing['ID']) ? intval($listing['ID']) : 0);
                    if ($id > 0) {
                        $score = is_object($listing) ? (isset($listing->relevance_score) ? floatval($listing->relevance_score) : 0) : (isset($listing['relevance_score']) ? floatval($listing['relevance_score']) : 0);
                        $keyword_results[] = $id;
                        $keyword_scores[$id] = $score;
                        $keyword_score_values[] = $score;
                        $valid_listings_count++;
                    }
                }
                
                if ($valid_listings_count === 0) {
                    error_log('[Boat Chatbot] Hybrid Search - Keyword search returned listings but no valid IDs found. Query: "' . substr($user_message, 0, 100) . '". Listings count: ' . count($keyword_listings));
                    $performance_log['keyword_no_valid_ids'] = true;
                }
                
                // Normalize keyword scores to 0-1 range using min-max normalization
                if (!empty($keyword_score_values)) {
                    $min_score = min($keyword_score_values);
                    $max_score = max($keyword_score_values);
                    $score_range = $max_score - $min_score;
                    
                    if ($score_range > 0) {
                        foreach ($keyword_scores as $id => $score) {
                            $keyword_scores[$id] = ($score - $min_score) / $score_range;
                        }
                    } else {
                        // All scores are the same, set to 0.5
                        foreach ($keyword_scores as $id => $score) {
                            $keyword_scores[$id] = 0.5;
                        }
                    }
                }
            }
        }
        
        // Merge results based on mode
        $merged_ids = array();
        $combined_scores = array();
        
        if ($search_mode === 'semantic') {
            // Only use vector results
            $merged_ids = $vector_results;
            foreach ($vector_scores as $id => $score) {
                $combined_scores[$id] = $score;
            }
        } elseif ($search_mode === 'keyword') {
            // Only use keyword results
            $merged_ids = $keyword_results;
            foreach ($keyword_scores as $id => $score) {
                $combined_scores[$id] = $score;
            }
        } else {
            // Hybrid mode: merge and combine scores
            $all_ids = array_unique(array_merge($vector_results, $keyword_results));
            
            foreach ($all_ids as $id) {
                $vector_score = isset($vector_scores[$id]) ? $vector_scores[$id] : 0;
                $keyword_score = isset($keyword_scores[$id]) ? $keyword_scores[$id] : 0;
                
                // Combined score: α * vector_score + β * keyword_score
                $combined_score = ($alpha * $vector_score) + ($beta * $keyword_score);
                $combined_scores[$id] = $combined_score;
                $merged_ids[] = $id;
            }
        }
        
        if (empty($merged_ids)) {
            // Log why hybrid search failed
            $failure_reasons = array();
            if ($search_mode === 'semantic' || $search_mode === 'hybrid') {
                if (empty($vector_results)) {
                    if (isset($performance_log['embedding_error'])) {
                        $failure_reasons[] = 'Embedding generation failed: ' . $performance_log['embedding_error'];
                    } elseif (isset($performance_log['vector_search_error'])) {
                        $failure_reasons[] = 'Vector search query failed: ' . $performance_log['vector_search_error'];
                    } elseif (isset($performance_log['vector_search_empty'])) {
                        $failure_reasons[] = 'Vector search returned no results';
                    } elseif (isset($performance_log['vector_threshold_filtered'])) {
                        $failure_reasons[] = 'All vector results filtered by similarity threshold (' . $performance_log['vector_threshold_filtered'] . ' results)';
                    } else {
                        $failure_reasons[] = 'Vector search returned no valid results';
                    }
                }
            }
            if ($search_mode === 'keyword' || $search_mode === 'hybrid') {
                if (empty($keyword_results)) {
                    if (isset($performance_log['keyword_search_empty'])) {
                        $failure_reasons[] = 'Keyword search returned no results';
                    } elseif (isset($performance_log['keyword_no_valid_ids'])) {
                        $failure_reasons[] = 'Keyword search returned listings but no valid IDs';
                    } else {
                        $failure_reasons[] = 'Keyword search returned no valid results';
                    }
                }
            }
            
            $failure_message = '[Boat Chatbot] Hybrid Search - No merged results. Query: "' . substr($user_message, 0, 100) . '". Mode: ' . $search_mode . '. Reasons: ' . implode('; ', $failure_reasons) . '. Vector results: ' . count($vector_results) . ', Keyword results: ' . count($keyword_results);
            error_log($failure_message);
            $performance_log['hybrid_search_failed'] = true;
            $performance_log['hybrid_failure_reasons'] = $failure_reasons;
            
            // Fallback to database query if no results
            return $this->handle_database_query_optimized($user_message, $performance_log, $conversation_history);
        }
        
        // Sort by combined score (highest first)
        arsort($combined_scores);
        $all_sorted_ids = array_keys($combined_scores); // Store all sorted IDs for caching
        
        // Cache the full sorted IDs list for pagination (only if pagination is enabled)
        if ($enable_pagination && $offset === 0) {
            // Cache for 1 hour (3600 seconds) - enough time for user to paginate
            $this->set_cache($cache_key_sorted_ids, $all_sorted_ids, 3600);
            error_log('[Boat Chatbot] Hybrid Search - Cached ' . count($all_sorted_ids) . ' sorted IDs for query: "' . substr($user_message, 0, 100) . '", CacheKey="' . $cache_key_sorted_ids . '"');
        }
        
        // Apply offset and limit to get the current page
        $total_available = count($all_sorted_ids);
        $sorted_ids = array_slice($all_sorted_ids, $offset, $max_results);
        
        if (empty($sorted_ids)) {
            // No results for this offset
            return array(
                'response' => "No more listings available.",
                'listings' => array(),
                'total_listings' => $total_available,
                'has_more' => false,
                'enable_pagination' => $enable_pagination,
                'requested_count' => $requested_count,
                'offset' => $offset,
                'limit' => $max_results,
                'search_mode' => $search_mode
            );
        }
        
        // Get full listings from database
        $db_start = microtime(true);
        $listings = $this->get_listings_by_ids($sorted_ids);
        $performance_log['db_time'] = microtime(true) - $db_start;
        
        if (empty($listings)) {
            error_log('[Boat Chatbot] Hybrid Search - Failed to retrieve listings from database. Query: "' . substr($user_message, 0, 100) . '". IDs requested: ' . count($sorted_ids) . ', IDs: ' . implode(', ', array_slice($sorted_ids, 0, 10)) . (count($sorted_ids) > 10 ? '...' : ''));
            $performance_log['db_retrieval_failed'] = true;
            $performance_log['ids_requested'] = count($sorted_ids);
        } elseif (count($listings) < count($sorted_ids)) {
            error_log('[Boat Chatbot] Hybrid Search - Partial listing retrieval. Query: "' . substr($user_message, 0, 100) . '". Requested: ' . count($sorted_ids) . ', Retrieved: ' . count($listings));
            $performance_log['db_partial_retrieval'] = true;
            $performance_log['ids_requested'] = count($sorted_ids);
            $performance_log['ids_retrieved'] = count($listings);
        }
        
        // Add combined scores to listings and maintain order
        $listings_by_id = array();
        foreach ($listings as $listing) {
            $id = is_object($listing) ? (isset($listing->ID) ? intval($listing->ID) : 0) : (isset($listing['ID']) ? intval($listing['ID']) : 0);
            if ($id > 0) {
                $listings_by_id[$id] = $listing;
                if (is_object($listing)) {
                    $listing->combined_score = isset($combined_scores[$id]) ? $combined_scores[$id] : 0;
                    $listing->vector_score = isset($vector_scores[$id]) ? $vector_scores[$id] : 0;
                    $listing->keyword_score = isset($keyword_scores[$id]) ? $keyword_scores[$id] : 0;
                } else {
                    $listing['combined_score'] = isset($combined_scores[$id]) ? $combined_scores[$id] : 0;
                    $listing['vector_score'] = isset($vector_scores[$id]) ? $vector_scores[$id] : 0;
                    $listing['keyword_score'] = isset($keyword_scores[$id]) ? $keyword_scores[$id] : 0;
                }
            }
        }
        
        // Reorder listings according to sorted_ids
        $ordered_listings = array();
        foreach ($sorted_ids as $id) {
            if (isset($listings_by_id[$id])) {
                $ordered_listings[] = $listings_by_id[$id];
            }
        }
        
        // Apply reranking if enabled
        if ($rerank_manager && $rerank_manager->is_enabled() && !empty($ordered_listings)) {
            $rerank_start = microtime(true);
            
            // Prepare documents for reranking (need text content)
            $rerank_documents = array();
            foreach ($ordered_listings as $listing) {
                $doc = array(
                    'id' => is_object($listing) ? (isset($listing->ID) ? $listing->ID : 0) : (isset($listing['ID']) ? $listing['ID'] : 0),
                    'text' => $this->build_text_for_reranking($listing),
                    'score' => is_object($listing) ? (isset($listing->combined_score) ? $listing->combined_score : 0) : (isset($listing['combined_score']) ? $listing['combined_score'] : 0),
                    'original_listing' => $listing // Keep original for reference
                );
                $rerank_documents[] = $doc;
            }
            
            // Rerank top N results (get from reranking manager or use default)
            $rerank_top_n = absint(get_option('boat_chatbot_rerank_top_n', 20));
            $rerank_top_n = min($rerank_top_n, count($rerank_documents));
            $reranked = $rerank_manager->rerank($user_message, $rerank_documents, $rerank_top_n);
            
            if ($reranked !== false && is_array($reranked) && !empty($reranked)) {
                // Replace ordered_listings with reranked results
                $ordered_listings = array();
                foreach ($reranked as $reranked_doc) {
                    if (isset($reranked_doc['original_listing'])) {
                        $listing = $reranked_doc['original_listing'];
                        // Add rerank score to listing
                        if (is_object($listing)) {
                            $listing->rerank_score = isset($reranked_doc['rerank_score']) ? $reranked_doc['rerank_score'] : 0;
                        } else {
                            $listing['rerank_score'] = isset($reranked_doc['rerank_score']) ? $reranked_doc['rerank_score'] : 0;
                        }
                        $ordered_listings[] = $listing;
                    }
                }
                
                // Add remaining non-reranked results (if any)
                $reranked_ids = array();
                foreach ($reranked as $doc) {
                    if (isset($doc['id'])) {
                        $reranked_ids[] = intval($doc['id']);
                    }
                }
                
                foreach ($sorted_ids as $id) {
                    if (!in_array($id, $reranked_ids) && isset($listings_by_id[$id])) {
                        $ordered_listings[] = $listings_by_id[$id];
                    }
                }
                
                $performance_log['rerank_time'] = microtime(true) - $rerank_start;
                $performance_log['rerank_used'] = true;
                $performance_log['rerank_count'] = count($reranked);
            } else {
                $performance_log['rerank_time'] = microtime(true) - $rerank_start;
                $performance_log['rerank_used'] = false;
                $performance_log['rerank_error'] = $rerank_manager->get_last_error();
            }
        } else {
            $performance_log['rerank_used'] = false;
        }
        
        // Process and format results
        $processed_listings = $this->process_results_for_ai($ordered_listings, $user_message);
        $formatted_results = $this->format_listings_for_ai($processed_listings, $user_message);
        $performance_log['formatted_results'] = $formatted_results;
        // Get AI response
        $grok_api_start = microtime(true);
        $ai_response = $this->get_ai_response_optimized($user_message, $formatted_results, true, $conversation_history);
        $performance_log['grok_api_time'] = microtime(true) - $grok_api_start;
        $performance_log['ai_time'] = $performance_log['grok_api_time'];
        
        // Determine if there are more listings (only if pagination is enabled)
        $total_listings = $total_available; // Use total available from sorted IDs
        $has_more = $enable_pagination && (($offset + count($processed_listings)) < $total_listings);
        
        return array(
            'response' => $ai_response,
            'listings' => $processed_listings,
            'total_listings' => $total_listings,
            'has_more' => $has_more,
            'enable_pagination' => $enable_pagination,
            'requested_count' => $requested_count,
            'offset' => $offset,
            'limit' => $max_results,
            'search_mode' => $search_mode,
            'vector_count' => count($vector_results),
            'keyword_count' => count($keyword_results)
        );
    }
    
    /**
     * Build Pinecone metadata filter from search terms
     * Converts structured filters to Pinecone filter format
     * 
     * Supported filters:
     * - Price (min/max)
     * - Length (min/max)
     * - Year (min/max or exact)
     * - Manufacturer (exact match, case-insensitive)
     * - Type (exact match)
     * - Category (exact match)
     * - Model (exact match)
     * - Location (City, State, Country with OR logic)
     * 
     * @param array $search_terms Extracted search terms
     * @return array|null Pinecone filter array or null if no structured filters
     */
    private function build_pinecone_filter($search_terms) {
        if (empty($search_terms) || !is_array($search_terms)) {
            return null;
        }
        
        $filter_conditions = array();
        
        // Price filters
        $price_conditions = array();
        if (!empty($search_terms['min_price'])) {
            $min_price = floatval($search_terms['min_price']);
            $price_conditions['$gte'] = $min_price;
        }
        if (!empty($search_terms['max_price'])) {
            $max_price = floatval($search_terms['max_price']);
            $price_conditions['$lte'] = $max_price;
        }
        if (!empty($price_conditions)) {
            if (count($price_conditions) === 1) {
                // Single condition, use it directly
                $filter_conditions['PriceUSD'] = $price_conditions;
            } else {
                // Multiple conditions, combine them
                $filter_conditions['PriceUSD'] = $price_conditions;
            }
        }
        
        // Length filters
        $length_conditions = array();
        if (!empty($search_terms['length'])) {
            // Single length value with range (±2 feet)
            $length = intval($search_terms['length']);
            $length_min = max(1, $length - 2);
            $length_max = min(1000, $length + 2);
            $length_conditions['$gte'] = $length_min;
            $length_conditions['$lte'] = $length_max;
        } else {
            if (!empty($search_terms['min_length'])) {
                $length_conditions['$gte'] = intval($search_terms['min_length']);
            }
            if (!empty($search_terms['max_length'])) {
                $length_conditions['$lte'] = intval($search_terms['max_length']);
            }
        }
        if (!empty($length_conditions)) {
            $filter_conditions['DisplayLengthFeet'] = $length_conditions;
        }
        
        // Year filters
        $year_conditions = array();
        if (!empty($search_terms['year'])) {
            // Single year value
            $year = intval($search_terms['year']);
            $filter_conditions['Year'] = $year;
        } else {
            if (!empty($search_terms['min_year'])) {
                $year_conditions['$gte'] = intval($search_terms['min_year']);
            }
            if (!empty($search_terms['max_year'])) {
                $year_conditions['$lte'] = intval($search_terms['max_year']);
            }
            if (!empty($year_conditions)) {
                $filter_conditions['Year'] = $year_conditions;
            }
        }
        
        // Manufacturer filter (case-insensitive matching)
        // Note: Pinecone metadata filters are case-sensitive, so we normalize both storage and query
        if (!empty($search_terms['manufacturer'])) {
            $manufacturer = trim($search_terms['manufacturer']);
            // Create case-insensitive filter by checking common capitalizations
            // Since Pinecone doesn't support LIKE, we need exact match
            // We'll use the capitalized version that should match stored metadata
            $manufacturer_normalized = ucwords(strtolower($manufacturer));
            $filter_conditions['Manufacturer'] = array('$eq' => $manufacturer_normalized);
        }
        
        // Type filter
        if (!empty($search_terms['type'])) {
            $type = trim($search_terms['type']);
            // Type is stored as-is in metadata
            $filter_conditions['Type_'] = array('$eq' => $type);
        }
        
        // Category filter
        if (!empty($search_terms['category'])) {
            $category = trim($search_terms['category']);
            $filter_conditions['Category'] = array('$eq' => $category);
        }
        
        // Model filter
        if (!empty($search_terms['model'])) {
            $model = trim($search_terms['model']);
            $filter_conditions['Model'] = array('$eq' => $model);
        }
        
        // Location filter (City, State, Country)
        if (!empty($search_terms['location'])) {
            $location_conditions = array();
            $location = $search_terms['location'];
            
            // Try exact match on City, State, or Country
            if (isset($location['city'])) {
                $location_conditions[] = array('City' => array('$eq' => $location['city']));
            }
            if (isset($location['state'])) {
                $location_conditions[] = array('State' => array('$eq' => $location['state']));
            }
            if (isset($location['country'])) {
                $location_conditions[] = array('Country' => array('$eq' => $location['country']));
            }
            
            // If we have multiple location conditions, use $or to match any of them
            if (count($location_conditions) > 1) {
                $filter_conditions['$or'] = $location_conditions;
            } elseif (count($location_conditions) === 1) {
                // If only one condition, add it directly to filter
                $filter_conditions = array_merge($filter_conditions, $location_conditions[0]);
            }
        }
        
        
        // Return null if no structured filters to apply
        if (empty($filter_conditions)) {
            return null;
        }
        
        // Pinecone automatically ANDs conditions at the root level
        // So if we have both PriceUSD conditions and $or, they will be combined with AND
        // Example: {"PriceUSD": {"$gte": 100000}, "$or": [{"City": "Miami"}, {"State": "Florida"}]}
        // This means: PriceUSD >= 100000 AND (City = "Miami" OR State = "Florida")
        error_log("Pinecone filter: " . print_r($filter_conditions, true));
        return $filter_conditions;
    }
    
    /**
     * Check if a listing matches the extracted filters
     * 
     * @param object $listing Listing object
     * @param array $search_terms Extracted search terms
     * @param array $where_data WHERE clause data
     * @return bool True if listing matches filters
     */
    private function listing_matches_filters($listing, $search_terms, $where_data) {
        // This is a simplified check - in production, you'd want to execute the WHERE clause
        // For now, we'll do basic field matching
        
        // Check price
        if (!empty($search_terms['max_price'])) {
            $price = isset($listing->PriceUSD) ? floatval($listing->PriceUSD) : 0;
            if ($price > floatval($search_terms['max_price'])) {
                return false;
            }
        }
        if (!empty($search_terms['min_price'])) {
            $price = isset($listing->PriceUSD) ? floatval($listing->PriceUSD) : 0;
            if ($price < floatval($search_terms['min_price'])) {
                return false;
            }
        }
        
        // Check year
        if (!empty($search_terms['min_year'])) {
            $year = isset($listing->Year) ? intval($listing->Year) : 0;
            if ($year < intval($search_terms['min_year'])) {
                return false;
            }
        }
        if (!empty($search_terms['max_year'])) {
            $year = isset($listing->Year) ? intval($listing->Year) : 0;
            if ($year > intval($search_terms['max_year'])) {
                return false;
            }
        }
        if (!empty($search_terms['year'])) {
            $year = isset($listing->Year) ? intval($listing->Year) : 0;
            if ($year != intval($search_terms['year'])) {
                return false;
            }
        }
        
        // Check category
        if (!empty($search_terms['category'])) {
            $category = isset($listing->Category) ? strtolower($listing->Category) : '';
            $type = isset($listing->Type_) ? strtolower($listing->Type_) : '';
            $description = isset($listing->Description) ? strtolower($listing->Description) : '';
            $search_category = strtolower($search_terms['category']);
            
            if (strpos($category, $search_category) === false && 
                strpos($type, $search_category) === false && 
                strpos($description, $search_category) === false) {
                return false;
            }
        }
        
        // Check manufacturer
        if (!empty($search_terms['manufacturer'])) {
            $manufacturer = isset($listing->Manufacturer) ? strtolower($listing->Manufacturer) : '';
            $search_manufacturer = strtolower($search_terms['manufacturer']);
            if (strpos($manufacturer, $search_manufacturer) === false) {
                return false;
            }
        }
        
        // Check type
        if (!empty($search_terms['type'])) {
            $type = isset($listing->Type_) ? strtolower($listing->Type_) : '';
            $vessel_name = isset($listing->VesselName) ? strtolower($listing->VesselName) : '';
            $description = isset($listing->Description) ? strtolower($listing->Description) : '';
            $search_type = strtolower($search_terms['type']);
            
            if (strpos($type, $search_type) === false && 
                strpos($vessel_name, $search_type) === false && 
                strpos($description, $search_type) === false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get listings by IDs from database
     * 
     * @param array $ids Array of record IDs
     * @return array Array of listing objects
     */
    private function get_listings_by_ids($ids) {
        if (empty($ids) || !is_array($ids)) {
            return array();
        }
        
        global $wpdb;
        
        $db_host = get_option('boat_chatbot_db_host', 'localhost');
        $db_name = get_option('boat_chatbot_db_name');
        $db_user = get_option('boat_chatbot_db_user');
        $db_password = get_option('boat_chatbot_db_password');
        $table_name = get_option('boat_chatbot_db_table', 'api_vessels');
        
        if (!$db_name || !$db_user) {
            return array();
        }
        
        try {
            $conn = new mysqli($db_host, $db_user, $db_password, $db_name);
            
            if ($conn->connect_error) {
                return array();
            }
            
            $conn->set_charset('utf8mb4');
            
            // Sanitize IDs
            $ids = array_map('intval', $ids);
            $ids = array_filter($ids, function($id) { return $id > 0; });
            
            if (empty($ids)) {
                $conn->close();
                return array();
            }
            
            // Build IN clause
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            // Add LEFT JOIN to API_gallery table to get Thumbnail (sorted by lowest sort value)
            $gallery_table = 'API_gallery';
            $sql = "SELECT `{$table_name}`.*, (
                SELECT `{$gallery_table}`.`Thumbnail` 
                FROM `{$gallery_table}` 
                WHERE `{$gallery_table}`.`vesselID` = `{$table_name}`.`ID` 
                ORDER BY `{$gallery_table}`.`sort` ASC 
                LIMIT 1
            ) AS `Thumbnail` FROM `{$table_name}`";
            $sql .= " WHERE `{$table_name}`.`ID` IN ({$placeholders})";
            
            $stmt = $conn->prepare($sql);
            $types = str_repeat('i', count($ids));
            $stmt->bind_param($types, ...$ids);
            $stmt->execute();
            
            $result = $stmt->get_result();
            $listings = array();
            
            while ($row = $result->fetch_object()) {
                $listings[] = $row;
            }
            
            $stmt->close();
            $conn->close();
            
            return $listings;
        } catch (Exception $e) {
            return array();
        }
    }
    
    /**
     * Process results before sending to AI: deduplication, relevance scoring, and sorting
     * 
     * @param array $listings Array of listing objects from SQL or vector DB
     * @param string $user_message The user's query message
     * @return array Processed and sorted listings
     */
    private function process_results_for_ai($listings, $user_message) {
        if (empty($listings)) {
            return array();
        }
        
        // Step 1: Deduplication
        $deduplicated = $this->deduplicate_results($listings);
        
        // Check if reranking has been applied (listings have rerank_score)
        $reranking_applied = false;
        foreach ($deduplicated as $listing) {
            if ((is_object($listing) && isset($listing->rerank_score)) || 
                (is_array($listing) && isset($listing['rerank_score']))) {
                $reranking_applied = true;
                break;
            }
        }
        
        // Check if listings are already sorted by search algorithm
        // This indicates they came from vector/hybrid/keyword search and are already in the correct order
        // Check for combined_score, vector_score, or keyword_score - any of these means listings are pre-sorted
        $already_sorted = false;
        foreach ($deduplicated as $listing) {
            $has_search_score = false;
            if (is_object($listing)) {
                $has_search_score = isset($listing->combined_score) || 
                                   isset($listing->vector_score) || 
                                   isset($listing->keyword_score);
            } elseif (is_array($listing)) {
                $has_search_score = isset($listing['combined_score']) || 
                                   isset($listing['vector_score']) || 
                                   isset($listing['keyword_score']);
            }
            if ($has_search_score) {
                $already_sorted = true;
                break;
            }
        }
        
        // Step 2: Relevance scoring (always add scores for reference)
        $scored = $this->score_relevance($deduplicated, $user_message);
        
        // Step 3: Only re-sort if listings are not already sorted (by reranking or hybrid search)
        if (!$reranking_applied && !$already_sorted) {
            // No existing sorting - sort by relevance score (highest first)
            $sorted = $this->sort_by_relevance($scored);
            return $sorted;
        } else {
            // Already sorted (by reranking or hybrid search) - preserve the existing order
            // Still add relevance scores for reference, but don't re-sort
            return $scored; // Return in original (already sorted) order
        }
    }
    
    /**
     * Remove duplicate listings based on ID
     * 
     * @param array $listings Array of listing objects
     * @return array Deduplicated listings
     */
    private function deduplicate_results($listings) {
        $seen_ids = array();
        $deduplicated = array();
        
        foreach ($listings as $listing) {
            // Get ID from object (could be ID, id, or listing_id depending on source)
            $id = null;
            if (isset($listing->ID)) {
                $id = $listing->ID;
            } elseif (isset($listing->id)) {
                $id = $listing->id;
            } elseif (isset($listing->listing_id)) {
                $id = $listing->listing_id;
            } elseif (is_array($listing) && isset($listing['ID'])) {
                $id = $listing['ID'];
            } elseif (is_array($listing) && isset($listing['id'])) {
                $id = $listing['id'];
            }
            
            // If no ID found, use a combination of key fields as unique identifier
            if ($id === null) {
                $unique_key = '';
                if (isset($listing->VesselName)) {
                    $unique_key .= $listing->VesselName;
                } elseif (is_array($listing) && isset($listing['VesselName'])) {
                    $unique_key .= $listing['VesselName'];
                }
                if (isset($listing->Manufacturer)) {
                    $unique_key .= '|' . $listing->Manufacturer;
                } elseif (is_array($listing) && isset($listing['Manufacturer'])) {
                    $unique_key .= '|' . $listing['Manufacturer'];
                }
                if (isset($listing->Year)) {
                    $unique_key .= '|' . $listing->Year;
                } elseif (is_array($listing) && isset($listing['Year'])) {
                    $unique_key .= '|' . $listing['Year'];
                }
                $id = md5($unique_key);
            }
            
            // Only add if we haven't seen this ID before
            if (!in_array($id, $seen_ids)) {
                $seen_ids[] = $id;
                $deduplicated[] = $listing;
            }
        }
        
        return $deduplicated;
    }
    
    /**
     * Calculate relevance score for each listing based on user query
     * 
     * @param array $listings Array of listing objects
     * @param string $user_message The user's query message
     * @return array Listings with relevance_score property added
     */
    private function score_relevance($listings, $user_message) {
        $user_message_lower = strtolower(trim($user_message));
        $query_words = array_filter(explode(' ', preg_replace('/[^\w\s]/', ' ', $user_message_lower)));
        
        // Remove common stop words
        $stop_words = array('the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by',
                           'from', 'up', 'about', 'into', 'through', 'during', 'including', 'under', 'over',
                           'show', 'find', 'search', 'list', 'display', 'boat', 'boats', 'yacht', 'yachts',
                           'vessel', 'vessels', 'i', 'want', 'need', 'looking', 'see', 'get', 'have', 'is', 'are',
                           'was', 'were', 'be', 'been', 'being', 'do', 'does', 'did', 'will', 'would', 'can',
                           'could', 'should', 'may', 'might', 'must', 'what', 'where', 'when', 'how', 'which');
        $query_words = array_filter($query_words, function($word) use ($stop_words) {
            return strlen($word) >= 3 && !in_array($word, $stop_words);
        });
        
        $scored_listings = array();
        
        foreach ($listings as $listing) {
            $score = 0.0;
            
            // Helper function to get field value
            $get_field = function($field) use ($listing) {
                if (isset($listing->$field)) {
                    return strtolower(trim($listing->$field));
                } elseif (is_array($listing) && isset($listing[$field])) {
                    return strtolower(trim($listing[$field]));
                }
                return '';
            };
            
            // Extract field values
            $vessel_name = $get_field('VesselName');
            $type = $get_field('Type_');
            $manufacturer = $get_field('Manufacturer');
            $model = $get_field('Model');
            $description = $get_field('Description');
            $city = $get_field('City');
            $state = $get_field('State');
            $country = $get_field('Country');
            $year = $get_field('Year');
            
            // Score based on exact matches in key fields (higher weight)
            foreach ($query_words as $word) {
                // Vessel name matches (highest weight)
                if (!empty($vessel_name) && strpos($vessel_name, $word) !== false) {
                    $score += 10.0;
                }
                
                // Type matches (high weight)
                if (!empty($type) && strpos($type, $word) !== false) {
                    $score += 8.0;
                }
                
                // Manufacturer matches (high weight)
                if (!empty($manufacturer) && strpos($manufacturer, $word) !== false) {
                    $score += 8.0;
                }
                
                // Model matches (medium-high weight)
                if (!empty($model) && strpos($model, $word) !== false) {
                    $score += 7.0;
                }
                
                // Location matches (medium weight)
                if (!empty($city) && strpos($city, $word) !== false) {
                    $score += 6.0;
                }
                if (!empty($state) && strpos($state, $word) !== false) {
                    $score += 6.0;
                }
                if (!empty($country) && strpos($country, $word) !== false) {
                    $score += 5.0;
                }
                
                // Description matches (lower weight, but still relevant)
                if (!empty($description) && strpos($description, $word) !== false) {
                    $score += 2.0;
                }
            }
            
            // Boost score for exact phrase matches in vessel name
            if (!empty($vessel_name) && strpos($vessel_name, $user_message_lower) !== false) {
                $score += 15.0;
            }
            
            // Boost score for exact phrase matches in description
            if (!empty($description) && strpos($description, $user_message_lower) !== false) {
                $score += 5.0;
            }
            
            // Price range matching (if query mentions price)
            if (preg_match('/\b(under|below|less\s+than|max|up\s+to)\s*\$?\s*(\d+(?:,\d{3})*(?:\.\d{2})?)/i', $user_message, $matches)) {
                $max_price = floatval(str_replace(',', '', $matches[2]));
                $listing_price = 0;
                if (isset($listing->PriceUSD)) {
                    $listing_price = floatval($listing->PriceUSD);
                } elseif (is_array($listing) && isset($listing['PriceUSD'])) {
                    $listing_price = floatval($listing['PriceUSD']);
                }
                
                if ($listing_price > 0 && $listing_price <= $max_price) {
                    // Closer to max price gets higher score (within budget is good)
                    $price_ratio = $listing_price / $max_price;
                    $score += 5.0 * (1.0 - $price_ratio); // Higher score for lower prices within budget
                }
            }
            
            // Length matching (if query mentions length)
            if (preg_match('/(\d+(?:\.\d+)?)\s*(?:ft|foot|feet|\'|meter|metre|m)\b/i', $user_message, $matches)) {
                $query_length = floatval($matches[1]);
                // Convert to feet if meters
                if (preg_match('/meter|metre|m\b/i', $user_message)) {
                    $query_length *= 3.28084;
                }
                
                $listing_length = 0;
                if (isset($listing->DisplayLengthFeet)) {
                    $listing_length = floatval($listing->DisplayLengthFeet);
                } elseif (isset($listing->LOAFeet)) {
                    $listing_length = floatval($listing->LOAFeet);
                } elseif (is_array($listing) && isset($listing['DisplayLengthFeet'])) {
                    $listing_length = floatval($listing['DisplayLengthFeet']);
                }
                
                if ($listing_length > 0) {
                    $length_diff = abs($listing_length - $query_length);
                    // Closer matches get higher scores
                    if ($length_diff <= 5) {
                        $score += 8.0 * (1.0 - ($length_diff / 5.0));
                    } elseif ($length_diff <= 10) {
                        $score += 4.0 * (1.0 - (($length_diff - 5) / 5.0));
                    }
                }
            }
            
            // Year matching (if query mentions year)
            if (preg_match('/\b(19\d{2}|20[0-9]{2})\b/', $user_message, $matches)) {
                $query_year = intval($matches[1]);
                $listing_year = 0;
                if (isset($listing->Year)) {
                    $listing_year = intval($listing->Year);
                } elseif (is_array($listing) && isset($listing['Year'])) {
                    $listing_year = intval($listing['Year']);
                }
                
                if ($listing_year > 0 && $listing_year == $query_year) {
                    $score += 6.0; // Exact year match
                } elseif ($listing_year > 0 && abs($listing_year - $query_year) <= 2) {
                    $score += 3.0; // Close year match
                }
            }
            
            // Add relevance_score to listing object
            if (is_object($listing)) {
                $listing->relevance_score = $score;
            } elseif (is_array($listing)) {
                $listing['relevance_score'] = $score;
            }
            
            $scored_listings[] = $listing;
        }
        
        return $scored_listings;
    }
    
    /**
     * Sort listings by relevance score (highest first)
     * 
     * @param array $listings Array of listings with relevance_score property
     * @return array Sorted listings
     */
    private function sort_by_relevance($listings) {
        usort($listings, function($a, $b) {
            $score_a = 0.0;
            $score_b = 0.0;
            
            if (is_object($a) && isset($a->relevance_score)) {
                $score_a = floatval($a->relevance_score);
            } elseif (is_array($a) && isset($a['relevance_score'])) {
                $score_a = floatval($a['relevance_score']);
            }
            
            if (is_object($b) && isset($b->relevance_score)) {
                $score_b = floatval($b->relevance_score);
            } elseif (is_array($b) && isset($b['relevance_score'])) {
                $score_b = floatval($b['relevance_score']);
            }
            
            // Sort descending (highest score first)
            if ($score_a == $score_b) {
                return 0;
            }
            return ($score_a > $score_b) ? -1 : 1;
        });
        
        return $listings;
    }
    
    /**
     * Build text representation from listing for reranking
     * 
     * @param object|array $listing Listing object or array
     * @return string Text content for reranking
     */
    private function build_text_for_reranking($listing) {
        $parts = array();
        
        // Key fields to include for reranking
        $fields = array('VesselName', 'Description', 'Manufacturer', 'Model', 'Type_', 'City', 'State', 'Country');
        
        foreach ($fields as $field) {
            $value = '';
            if (is_object($listing) && isset($listing->$field)) {
                $value = trim($listing->$field);
            } elseif (is_array($listing) && isset($listing[$field])) {
                $value = trim($listing[$field]);
            }
            
            if (!empty($value)) {
                $parts[] = $value;
            }
        }
        
        return implode(' ', $parts);
    }
    
    private function format_listings_for_ai($listings, $user_message) {
        // Get format template and token limit from settings
        $format_template = get_option('boat_chatbot_listing_format', '- {title} | {type} | {length}\' | ${price} | {location}');
        $max_tokens = absint(get_option('boat_chatbot_token_limit', 450));
        
        // Format as concise markdown bullets, optimized for token count
        $formatted = "Boat listings:\n";
        $token_count = 20; // Approximate base tokens
        
        foreach ($listings as $listing) {
            // Map placeholders to actual database field names
            // Title (VesselName)
            $title = isset($listing->VesselName) ? substr(trim($listing->VesselName), 0, 40) : 'N/A';
            
            // Type
            $type = isset($listing->Type_) ? trim($listing->Type_) : 'N/A';
            
            // Length (DisplayLengthFeet)
            $length = isset($listing->DisplayLengthFeet) ? $listing->DisplayLengthFeet : (isset($listing->LOAFeet) ? $listing->LOAFeet : 'N/A');
            if (is_numeric($length)) {
                $length = number_format($length, 0) . "'";
            }
            
            // Price (PriceUSD)
            $price = isset($listing->PriceUSD) ? number_format(floatval($listing->PriceUSD), 0) : '0';
            
            // Location (City, State, Country)
            $location_parts = array();
            if (isset($listing->City) && !empty(trim($listing->City))) {
                $location_parts[] = trim($listing->City);
            }
            if (isset($listing->State) && !empty(trim($listing->State))) {
                $location_parts[] = trim($listing->State);
            }
            if (empty($location_parts) && isset($listing->Country) && !empty(trim($listing->Country))) {
                $location_parts[] = trim($listing->Country);
            }
            $location = !empty($location_parts) ? substr(implode(', ', $location_parts), 0, 30) : 'N/A';
            
            // Description
            $description = isset($listing->Description) ? substr(trim($listing->Description), 0, 100) : 'N/A';
            
            // URL (construct from ID if available)
            $url = 'N/A';
            if (isset($listing->ID)) {
                // You can customize this URL pattern based on your site structure
                $url = '#' . $listing->ID;
            }
            
            // Manufacturer
            $manufacturer = isset($listing->Manufacturer) ? substr(trim($listing->Manufacturer), 0, 30) : 'N/A';
            
            // Model
            $model = isset($listing->Model) ? substr(trim($listing->Model), 0, 30) : 'N/A';
            
            // Year
            $year = isset($listing->Year) ? intval($listing->Year) : 'N/A';
            if (is_numeric($year) && $year > 0) {
                $year = (string)$year;
            } else {
                $year = 'N/A';
            }
            
            // Replace placeholders in template
            $line = $format_template;
            $line = str_replace('{title}', $title, $line);
            $line = str_replace('{type}', $type, $line);
            $line = str_replace('{length}', $length, $line);
            $line = str_replace('{price}', $price, $line);
            $line = str_replace('{location}', $location, $line);
            $line = str_replace('{description}', $description, $line);
            $line = str_replace('{url}', $url, $line);
            $line = str_replace('{manufacturer}', $manufacturer, $line);
            $line = str_replace('{model}', $model, $line);
            $line = str_replace('{year}', $year, $line);
            
            // Add newline if not present
            if (substr($line, -1) !== "\n") {
                $line .= "\n";
            }
            
            // Rough token estimation (1 token ≈ 4 characters)
            $line_tokens = strlen($line) / 4;
            
            // if ($token_count + $line_tokens > $max_tokens) {
            //     break; // Stop if we'd exceed token limit
            // }
            
            $formatted .= $line;
            $token_count += $line_tokens;
        }
        
        return $formatted;
    }
    
    private function get_ai_response_optimized($user_message, $data_context = '', $is_database_query = false, $conversation_history = array()) {
        // $data_context can be either:
        // - A number (count) when $is_database_query is true
        // - A string (formatted listings) for backward compatibility
        // - Empty string for general queries
        $api_key = get_option('boat_chatbot_grok_api_key');
        $api_url = get_option('boat_chatbot_grok_api_url');
        $tone = get_option('boat_chatbot_tone_of_voice');
        $blocked_websites = get_option('boat_chatbot_blocked_websites', '');
        
        if (!$api_key || !$api_url) {
            return "I'm currently undergoing maintenance. Please try again later.";
        }
        
        // Build the prompt (optimized for token count)
        // Ensure tone has a default value if empty
        if (empty($tone)) {
            $tone = "You are a helpful boat assistant. ";
        }
        
        $restrictions = '';
        if (!empty($blocked_websites)) {
            $websites = preg_split('/[,\n\r]+/', trim($blocked_websites));
            $websites = array_map('trim', $websites);
            $websites = array_filter($websites);
            $websites = array_map('sanitize_text_field', $websites);
            
            if (!empty($websites)) {
                $display_websites = array_map(function($site) {
                    return preg_replace('#^https?://#', '', $site);
                }, $websites);
                
                $websites_list = implode(', ', array_slice($display_websites, 0, 10)); // Limit to 10
                $restrictions = "\n\nRESTRICTION: Do not reference these sites: {$websites_list}\n";
            }
        }
        
        // Build messages array for conversation context
        $messages = array();
        
        // Add system message with tone and restrictions
        $system_content = $tone;
        if (!empty($restrictions)) {
            $system_content .= $restrictions;
        }
        $system_content .= "\n\nUse markdown formatting for better readability:\n- Use **bold** for important terms\n- Use *italic* for emphasis\n- Use bullet points (-) or numbered lists (1. 2. 3.) for multiple items\n- Use line breaks to separate paragraphs\n- Keep responses clear and well-structured.\n\nIMPORTANT: When generating search URLs with query parameters, always use 'manufacturer' (not 'make') as the parameter name for boat manufacturers. For example: /yachts-for-sale?manufacturer=everglades (not ?make=everglades).\n\nCRITICAL DATA INSTRUCTIONS:\n- When boat listings data is provided, ONLY reference and discuss boats from that exact data\n- Do NOT add, create, invent, or hallucinate any additional boat listings\n- Do NOT infer or suggest similar boats that are not in the provided data\n- If asked to list or show boats, only show boats that exist in the provided formatted_results\n- Always count and verify that any boats mentioned actually exist in the provided data\n- If no boats match criteria, clearly state that no matching boats were found in the provided listings";

        $messages[] = array('role' => 'system', 'content' => $system_content);
        
        // Extract and summarize key information from conversation history
        $user_context = '';
        if (method_exists($this, 'extract_user_context')) {
            try {
                $user_context = $this->extract_user_context($conversation_history);
            } catch (Exception $e) {
                // Silently fail if function has issues
                $user_context = '';
            }
        }
        
        // Add user context to system message if available
        if (!empty($user_context)) {
            $system_content .= "\n\nUSER CONTEXT (Important information to remember):\n" . $user_context;
            // Update the system message in the messages array
            $messages[0]['content'] = $system_content;
            $system_content .= "\n\nUSER CONTEXT (Important information to remember):\n" . $user_context;
        }
        
        // Add conversation history (increased limit to 20 messages for better context)
        if (!empty($conversation_history)) {
            // Take last 20 messages from history to maintain better context
            // This allows the AI to remember more of the conversation
            $recent_history = array_slice($conversation_history, -20);
            foreach ($recent_history as $hist_msg) {
                // Map 'assistant' role to 'assistant' for Grok API compatibility
                $role = ($hist_msg['role'] === 'assistant') ? 'assistant' : 'user';
                $messages[] = array(
                    'role' => $role,
                    'content' => $hist_msg['content']
                );
            }
        }
        
        // Build current user message with context
        if ($is_database_query) {
            // If $data_context is a number, it's the count; otherwise it's formatted listings (backward compatibility)
            if (is_numeric($data_context)) {
                $listing_count = intval($data_context);
                if ($listing_count > 0) {
                    $current_message = "Search Results: Found {$listing_count} boat listing(s) matching the query.\n\nQuestion: {$user_message}\n\nProvide a helpful summary about the search results. The listings will be displayed separately below your response.";
                } else {
                    $current_message = "Search Results: No boat listings found matching the query.\n\nQuestion: {$user_message}\n\nProvide a helpful response explaining that no listings were found and suggest alternative search terms or criteria.";
                }
            } else {
                // STRICT INSTRUCTIONS: Only use the exact boats provided in formatted_results
                $current_message = "AVAILABLE BOAT LISTINGS (ONLY use these - do not add or invent any others):\n{$data_context}\n\nQUESTION: {$user_message}\n\nIMPORTANT: Only reference boats from the listings above. Do not suggest or mention any boats that are not in this exact list. If asked to show boats, only show boats that appear in the AVAILABLE BOAT LISTINGS section above.";
            }
        } else {
            $current_message = "Question: {$user_message}\n\nProvide accurate boating information.";                                                                                                                                                                                               
        }
        
        $messages[] = array('role' => 'user', 'content' => $current_message);
        error_log(">>>>>>>>>".json_encode($current_message));
        // Make API request to Grok
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body' => json_encode(array(
                'messages' => $messages,
                'model' => 'grok-4-fast-reasoning',
                'temperature' => 0.5,
                'max_tokens' => 1000 // Increased to accommodate more context
            )),
            'timeout' => 30, // 30 seconds should be enough for Grok API
            'blocking' => true // Must be blocking to get response
        ));
        


        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $error_code = $response->get_error_code();
            
            // Provide more specific error messages based on error type
            if (strpos($error_message, 'timeout') !== false || strpos($error_message, 'timed out') !== false || $error_code === 'http_request_failed') {
                return "The request is taking longer than expected. The Grok API might be slow or unavailable. Please try again in a moment.";
            } elseif (strpos($error_message, 'resolve') !== false || strpos($error_message, 'could not resolve') !== false) {
                return "I'm unable to reach the Grok API server. Please check your API URL configuration.";
            } elseif (strpos($error_message, 'SSL') !== false || strpos($error_message, 'certificate') !== false) {
                return "There's an SSL certificate issue connecting to the Grok API. Please check your server's SSL configuration.";
            }
            
            return "I'm having trouble connecting right now. Please try again in a moment.";
        }
        
        // Check HTTP response code
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body_raw = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $response_message = wp_remote_retrieve_response_message($response);
            
            // Provide specific error messages based on HTTP status code
            if ($response_code === 401) {
                return "Authentication failed. Please check your Grok API key configuration.";
            } elseif ($response_code === 403) {
                return "Access denied. Your Grok API key may not have permission for this operation.";
            } elseif ($response_code === 429) {
                return "Rate limit exceeded. The Grok API is receiving too many requests. Please try again in a moment.";
            } elseif ($response_code === 500 || $response_code === 502 || $response_code === 503) {
                return "The Grok API server is experiencing issues. Please try again in a moment.";
            } elseif ($response_code === 504) {
                return "The request to Grok API timed out. Please try again.";
            }
            
            return "I'm having trouble connecting to the Grok API (HTTP {$response_code}). Please try again in a moment.";
        }
        error_log("response>>>>>>>>",$response_body_raw);
        $body = json_decode($response_body_raw, true);
        
        // Check if response is valid JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            return "I'm having trouble processing the response from Grok API. Please try again.";
        }
        
        // Check for API error messages in response
        if (isset($body['error'])) {
            $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown API error';
            $error_type = isset($body['error']['type']) ? $body['error']['type'] : '';
            $error_code = isset($body['error']['code']) ? $body['error']['code'] : '';
            
            // Provide more specific error messages
            if (strpos(strtolower($error_message), 'rate limit') !== false || strpos(strtolower($error_type), 'rate_limit') !== false) {
                return "Rate limit exceeded. The Grok API is receiving too many requests. Please try again in a moment.";
            } elseif (strpos(strtolower($error_message), 'invalid') !== false && strpos(strtolower($error_message), 'key') !== false) {
                return "Invalid API key. Please check your Grok API key configuration.";
            } elseif (strpos(strtolower($error_message), 'quota') !== false || strpos(strtolower($error_message), 'billing') !== false) {
                return "API quota exceeded. Please check your Grok account billing and quota limits.";
            }
            
            return "Grok API error: " . $error_message . " Please try again in a moment.";
        }
        
        // Try different possible response structures
        // Standard OpenAI/Grok format: choices[0].message.content
        if (isset($body['choices'][0]['message']['content'])) {
            $ai_response = $body['choices'][0]['message']['content'];
            $ai_response = $this->filter_blocked_websites_from_response($ai_response, $blocked_websites);
            return $ai_response;
        }
        
        // Alternative format: choices[0].text
        if (isset($body['choices'][0]['text'])) {
            $ai_response = $body['choices'][0]['text'];
            $ai_response = $this->filter_blocked_websites_from_response($ai_response, $blocked_websites);
            return $ai_response;
        }
        
        // Alternative format: content directly
        if (isset($body['content'])) {
            $ai_response = $body['content'];
            $ai_response = $this->filter_blocked_websites_from_response($ai_response, $blocked_websites);
            return $ai_response;
        }
        
        // Grok-specific format: response or text at root level
        if (isset($body['response'])) {
            $ai_response = $body['response'];
            $ai_response = $this->filter_blocked_websites_from_response($ai_response, $blocked_websites);
            return $ai_response;
        }
        
        if (isset($body['text'])) {
            $ai_response = $body['text'];
            $ai_response = $this->filter_blocked_websites_from_response($ai_response, $blocked_websites);
            return $ai_response;
        }
        
        // Try nested response structures
        if (isset($body['data']['content'])) {
            $ai_response = $body['data']['content'];
            $ai_response = $this->filter_blocked_websites_from_response($ai_response, $blocked_websites);
            return $ai_response;
        }
        
        if (isset($body['data']['response'])) {
            $ai_response = $body['data']['response'];
            $ai_response = $this->filter_blocked_websites_from_response($ai_response, $blocked_websites);
            return $ai_response;
        }
        
        if (isset($body['result']['content'])) {
            $ai_response = $body['result']['content'];
            $ai_response = $this->filter_blocked_websites_from_response($ai_response, $blocked_websites);
            return $ai_response;
        }
        
        if (isset($body['result']['text'])) {
            $ai_response = $body['result']['text'];
            $ai_response = $this->filter_blocked_websites_from_response($ai_response, $blocked_websites);
            return $ai_response;
        }
        
        return "I apologize, but I couldn't process your request at the moment. Please try again.";
    }
    
    private function filter_blocked_websites_from_response($response, $blocked_websites) {
        if (empty($blocked_websites) || empty($response)) {
            return $response;
        }
        
        $websites = preg_split('/[,\n\r]+/', trim($blocked_websites));
        $websites = array_map('trim', $websites);
        $websites = array_filter($websites);
        
        if (empty($websites)) {
            return $response;
        }
        
        $normalized_blocked = array();
        foreach ($websites as $site) {
            $normalized = strtolower($site);
            $normalized = preg_replace('#^https?://#', '', $normalized);
            $normalized = preg_replace('#^www\.#', '', $normalized);
            $normalized = rtrim($normalized, '/');
            $normalized_blocked[] = $normalized;
        }
        
        $response_lower = strtolower($response);
        
        foreach ($normalized_blocked as $blocked) {
            if (preg_match('/\b' . preg_quote($blocked, '/') . '\b/i', $response_lower) ||
                preg_match('/https?:\/\/(www\.)?' . preg_quote($blocked, '/') . '/i', $response_lower)) {
                $response = preg_replace('/https?:\/\/(www\.)?' . preg_quote($blocked, '/') . '[^\s]*/i', '[blocked website]', $response);
                $response = preg_replace('/\b' . preg_quote($blocked, '/') . '\b/i', '[blocked website]', $response);
            }
        }
        
        return $response;
    }
    
    /**
     * Extract and summarize key user information from conversation history
     * 
     * @param array $conversation_history Conversation history
     * @return string Extracted user context
     */
    private function extract_user_context($conversation_history) {
        if (empty($conversation_history) || !is_array($conversation_history)) {
            return '';
        }
        
        $context_items = array();
        $user_messages = array();
        
        // Extract user messages from history
        foreach ($conversation_history as $msg) {
            if (isset($msg['role']) && $msg['role'] === 'user' && isset($msg['content'])) {
                $user_messages[] = $msg['content'];
            }
        }
        
        if (empty($user_messages)) {
            return '';
        }
        
        // Look for key information patterns
        $patterns = array(
            'preferences' => array(
                '/prefer|like|want|looking for|interested in/i',
                '/budget|price|cost|afford/i',
                '/size|length|feet|ft/i',
                '/type|kind|model|make|manufacturer/i',
                '/year|age|new|used/i',
                '/location|area|region|city|state/i'
            )
        );
        
        // Extract relevant information from user messages
        foreach ($user_messages as $msg) {
            // Look for preferences
            if (preg_match('/prefer|like|want|looking for|interested in/i', $msg)) {
                // Extract the preference
                if (preg_match('/(?:prefer|like|want|looking for|interested in)[\s:]+(.+?)(?:\.|$|,)/i', $msg, $matches)) {
                    $preference = trim($matches[1]);
                    if (!empty($preference) && strlen($preference) < 200) {
                        $context_items[] = 'Preference: ' . $preference;
                    }
                }
            }
            
            // Look for budget/price information
            if (preg_match('/budget|price|cost|afford/i', $msg)) {
                if (preg_match('/(?:budget|price|cost|afford)[\s:]+(.+?)(?:\.|$|,)/i', $msg, $matches)) {
                    $budget = trim($matches[1]);
                    if (!empty($budget) && strlen($budget) < 100) {
                        $context_items[] = 'Budget: ' . $budget;
                    }
                }
            }
            
            // Look for size/length information
            if (preg_match('/size|length|feet|ft/i', $msg)) {
                if (preg_match('/(?:size|length)[\s:]+(.+?)(?:\.|$|,)/i', $msg, $matches)) {
                    $size = trim($matches[1]);
                    if (!empty($size) && strlen($size) < 100) {
                        $context_items[] = 'Size preference: ' . $size;
                    }
                }
            }
        }
        
        // Remove duplicates and limit to most recent/relevant
        $context_items = array_unique($context_items);
        $context_items = array_slice($context_items, -5); // Keep last 5 items
        
        if (empty($context_items)) {
            return '';
        }
        
        return implode("\n", $context_items);
    }
    
    private function log_interaction($user_message, $intent, $response, $response_time, $performance_log = array()) {
        // Log asynchronously using wp_schedule_single_event to avoid blocking response
        // This schedules the logging to happen after the response is sent
        if (function_exists('wp_schedule_single_event')) {
            wp_schedule_single_event(time() + 1, 'boat_chatbot_async_log', array(
                $user_message,
                $intent,
                $response,
                $response_time,
                $performance_log
            ));
        } else {
            // Fallback: log directly (synchronous but necessary if cron not available)
            global $wpdb;
            $wpdb->insert(
                $wpdb->prefix . 'boat_chatbot_logs',
                array(
                    'user_message' => $user_message,
                    'classified_intent' => $intent,
                    'ai_response_received' => $response,
                    'full_response_sent_to_user' => $response,
                    'response_time' => $response_time,
                    'performance_metrics' => json_encode($performance_log)
                ),
                array('%s', '%s', '%s', '%s', '%f', '%s')
            );
        }
    }
    
    // Cache helper methods
    private function get_cache($key) {
        // Try Redis first, fallback to transients
        if (class_exists('Boat_Chatbot_Redis_Cache_Manager')) {
            $redis_manager = Boat_Chatbot_Redis_Cache_Manager::get_instance();
            if ($redis_manager->is_enabled()) {
                return $redis_manager->get($key);
            }
        }
        return get_transient($this->cache_group . '_' . $key);
    }
    
    private function set_cache($key, $value, $expiration = null) {
        if ($expiration === null) {
            $expiration = $this->cache_expiration;
        }
        
        // Try Redis first, fallback to transients
        if (class_exists('Boat_Chatbot_Redis_Cache_Manager')) {
            $redis_manager = Boat_Chatbot_Redis_Cache_Manager::get_instance();
            if ($redis_manager->is_enabled()) {
                return $redis_manager->set($key, $value, $expiration);
            }
        }
        return set_transient($this->cache_group . '_' . $key, $value, $expiration);
    }
    
    private function delete_cache($key) {
        // Try Redis first, fallback to transients
        if (class_exists('Boat_Chatbot_Redis_Cache_Manager')) {
            $redis_manager = Boat_Chatbot_Redis_Cache_Manager::get_instance();
            if ($redis_manager->is_enabled()) {
                return $redis_manager->delete($key);
            }
        }
        return delete_transient($this->cache_group . '_' . $key);
    }
    
    /**
     * Handle Speech-to-Text request
     * 
     * @param WP_REST_Request $request REST request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_speech_to_text($request) {
        // Load speech handler
        if (!class_exists('Boat_Chatbot_Speech_Handler')) {
            require_once BOAT_CHATBOT_PLUGIN_PATH . 'includes/class-speech-handler.php';
        }
        
        $speech_handler = Boat_Chatbot_Speech_Handler::get_instance();
        
        // Check if STT is enabled
        if (!$speech_handler->is_stt_enabled()) {
            return new WP_Error(
                'stt_disabled',
                'Speech-to-Text is not enabled or not properly configured.',
                array('status' => 400)
            );
        }
        
        $audio_data = $request->get_param('audio');
        $audio_format = $request->get_param('format') ?: 'webm';
        
        if (empty($audio_data)) {
            return new WP_Error(
                'missing_audio',
                'Audio data is required.',
                array('status' => 400)
            );
        }
        
        // Remove data URL prefix if present (e.g., "data:audio/webm;base64,")
        $audio_data = preg_replace('/^data:audio\/[^;]+;base64,/', '', $audio_data);
        
        // Convert speech to text
        $result = $speech_handler->speech_to_text($audio_data, $audio_format);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'text' => $result['text'],
            'confidence' => $result['confidence']
        ));
    }
    
    /**
     * Handle Text-to-Speech request
     * 
     * @param WP_REST_Request $request REST request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_text_to_speech($request) {
        // Load speech handler
        if (!class_exists('Boat_Chatbot_Speech_Handler')) {
            require_once BOAT_CHATBOT_PLUGIN_PATH . 'includes/class-speech-handler.php';
        }
        
        $speech_handler = Boat_Chatbot_Speech_Handler::get_instance();
        
        // Check if TTS is enabled
        if (!$speech_handler->is_tts_enabled()) {
            return new WP_Error(
                'tts_disabled',
                'Text-to-Speech is not enabled or not properly configured.',
                array('status' => 400)
            );
        }
        
        $text = $request->get_param('text');
        $voice_id = $request->get_param('voice_id');
        
        if (empty($text)) {
            return new WP_Error(
                'missing_text',
                'Text is required.',
                array('status' => 400)
            );
        }
        
        // Limit text length to prevent excessive API costs
        $max_length = 5000; // Characters
        if (strlen($text) > $max_length) {
            $text = substr($text, 0, $max_length) . '...';
        }
        
        // Convert text to speech
        $result = $speech_handler->text_to_speech($text, $voice_id);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'audio' => $result['audio'],
            'format' => $result['format'],
            'size' => $result['size']
        ));
    }

    /**
     * Validate that AI response only references boats from the provided listings
     * This prevents hallucinated or invented boat listings
     */
    private function validate_ai_response_boats($ai_response, $original_listings) {
        if (empty($ai_response) || empty($original_listings)) {
            return $ai_response;
        }

        // Extract boat names from AI response using regex patterns
        $mentioned_boats = array();

        // Pattern 1: Look for boat names that appear to be specific models/manufacturers
        // This catches things like "Sea Ray 320", "Beneteau Oceanis", etc.
        if (preg_match_all('/\b([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*(?:\s+\d+(?:\.\d+)?)?(?:\s+[A-Z][a-z]+)*)\b/', $ai_response, $matches)) {
            foreach ($matches[1] as $potential_boat) {
                // Filter out common non-boat words
                $non_boat_words = array('Please', 'Search', 'Results', 'Found', 'Boats', 'Yachts', 'Show', 'List', 'Available', 'Contact', 'Price', 'Location', 'Length', 'Year', 'Type', 'Model', 'Make', 'Manufacturer', 'Description', 'Details', 'Information', 'Based', 'Your', 'The', 'And', 'For', 'Are', 'You', 'Can', 'Have', 'Will', 'Would', 'Could', 'Should', 'May', 'Might', 'This', 'That', 'These', 'Those', 'Here', 'There', 'Where', 'When', 'How', 'What', 'Why', 'Which');
                if (!in_array($potential_boat, $non_boat_words) && strlen($potential_boat) > 3) {
                    $mentioned_boats[] = $potential_boat;
                }
            }
        }

        // If no boats were mentioned, response is safe
        if (empty($mentioned_boats)) {
            return $ai_response;
        }

        // Check if mentioned boats actually exist in our original listings
        $valid_boats = array();
        foreach ($original_listings as $listing) {
            $boat_name = isset($listing->VesselName) ? trim($listing->VesselName) : '';
            $manufacturer = isset($listing->Manufacturer) ? trim($listing->Manufacturer) : '';
            $model = isset($listing->Model) ? trim($listing->Model) : '';

            if (!empty($boat_name)) {
                $valid_boats[] = $boat_name;
            }
            if (!empty($manufacturer) && !empty($model)) {
                $valid_boats[] = $manufacturer . ' ' . $model;
            }
            if (!empty($manufacturer)) {
                $valid_boats[] = $manufacturer;
            }
        }

        // Check for invalid boats (mentioned but not in our data)
        $invalid_boats = array();
        foreach ($mentioned_boats as $mentioned_boat) {
            $found = false;
            foreach ($valid_boats as $valid_boat) {
                // Case-insensitive partial match
                if (stripos($valid_boat, $mentioned_boat) !== false || stripos($mentioned_boat, $valid_boat) !== false) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $invalid_boats[] = $mentioned_boat;
            }
        }

        // If invalid boats were found, add a warning to the response
        if (!empty($invalid_boats)) {
            $warning = "\n\n⚠️ **Notice:** Some boat names mentioned may not be in our current listings. Please verify availability by checking our current inventory.";
            $ai_response .= $warning;

            // Log the issue for debugging
            error_log('[Boat Chatbot] AI mentioned invalid boats: ' . implode(', ', $invalid_boats));
            error_log('[Boat Chatbot] Valid boats in data: ' . implode(', ', array_slice($valid_boats, 0, 10)) . (count($valid_boats) > 10 ? '...and ' . (count($valid_boats) - 10) . ' more' : ''));
        }

        return $ai_response;
    }
}
?>


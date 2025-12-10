<?php

/**
 * Groq Embeddings Manager
 * Handles embedding generation using Groq API
 */
class Boat_Chatbot_Groq_Embeddings_Manager {
    
    private static $instance = null;
    private $api_key;
    private $api_url;
    private $model;
    private $dimensions;
    private $last_error = null; // Store last error message for better error reporting
    
    /**
     * Get last error message
     * 
     * @return string|null Last error message or null if no error
     */
    public function get_last_error() {
        return $this->last_error;
    }
    
    // Default Groq embedding model: nomic-embed-text-v1.5 (768 dimensions)
    private $default_model = 'nomic-embed-text-v1.5';
    private $default_dimensions = 768;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->api_key = get_option('boat_chatbot_groq_api_key');
        $this->api_url = get_option('boat_chatbot_groq_embeddings_url', 'https://api.groq.com/openai/v1/embeddings');
        $this->model = get_option('boat_chatbot_groq_embedding_model', $this->default_model);
        $this->dimensions = absint(get_option('boat_chatbot_groq_embedding_dimensions', $this->default_dimensions));
    }
    
    /**
     * Generate embedding for a single text
     * Caches embedding vectors for frequent user queries to reduce API latency
     * 
     * @param string $text Text to embed
     * @return array|false Embedding vector or false on error
     */
    public function generate_embedding($text) {
        try {
            if (empty($this->api_key)) {
                return false;
            }
            
            if (empty($this->api_url)) {
                return false;
            }
            
            if (empty($text)) {
                return false;
            }
            
            // Normalize text for consistent cache key generation
            $text_normalized = trim($text);
            
            // Check cache first - cache the embedding vector itself for frequent queries
            $cached_embedding = $this->get_cached_embedding($text_normalized);
            if ($cached_embedding !== false && is_array($cached_embedding)) {
                // Cache hit - return immediately (typically < 1ms vs 100-500ms API call)
                return $cached_embedding;
            }
            
            // Truncate text if too long (most embedding models have token limits)
            $max_length = 8192; // Conservative limit
            if (strlen($text_normalized) > $max_length) {
                $text_normalized = substr($text_normalized, 0, $max_length);
            }
            
            $request_body = array(
                'model' => $this->model,
                'input' => $text_normalized
            );
            
            $response = wp_remote_post($this->api_url, array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->api_key
                ),
                'body' => json_encode($request_body),
                'timeout' => 30,
                'blocking' => true
            ));
            
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $this->last_error = 'Connection error: ' . $error_message;
                return false;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                $body = wp_remote_retrieve_body($response);
                
                // Check if response is HTML (error page) instead of JSON
                $is_html = (strpos($body, '<!DOCTYPE') !== false || strpos($body, '<html') !== false);
                
                if ($is_html) {
                    // Extract error message from HTML if possible
                    $error_message = 'Server error (HTTP ' . $response_code . ')';
                    if (preg_match('/<title>(.*?)<\/title>/i', $body, $matches)) {
                        $error_message = strip_tags($matches[1]);
                    } elseif (preg_match('/<h1[^>]*>(.*?)<\/h1>/i', $body, $matches)) {
                        $error_message = strip_tags($matches[1]);
                    }
                    
                    // Store a more detailed error for the test_connection method
                    $this->last_error = $error_message . ' (HTTP ' . $response_code . ')';
                    return false;
                } else {
                    // Try to parse as JSON for more detailed error
                    $decoded_error = json_decode($body, true);
                    if (isset($decoded_error['error']['message'])) {
                        $error_message = $decoded_error['error']['message'];
                        $this->last_error = $error_message;
                    } else {
                        $this->last_error = 'HTTP ' . $response_code . ' error';
                    }
                }
                
                return false;
            }
            
            $body = wp_remote_retrieve_body($response);
            $decoded_body = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->last_error = 'Invalid JSON response: ' . json_last_error_msg();
                return false;
            }
            
            if (isset($decoded_body['error'])) {
                $error_message = isset($decoded_body['error']['message']) ? $decoded_body['error']['message'] : 'Unknown API error';
                $this->last_error = $error_message;
                return false;
            }
            
            // Extract embedding from response
            if (isset($decoded_body['data'][0]['embedding'])) {
                $embedding = $decoded_body['data'][0]['embedding'];
                // Clear error on success
                $this->last_error = null;
                
                // Cache the embedding vector for future frequent queries
                if (is_array($embedding)) {
                    $this->cache_embedding($text_normalized, $embedding);
                }
                
                return $embedding;
            }
            
            $this->last_error = 'No embedding found in response';
            return false;
        } catch (Exception $e) {
            $this->last_error = 'Exception: ' . $e->getMessage();
            return false;
        } catch (Error $e) {
            $this->last_error = 'Fatal error: ' . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Generate embeddings for multiple texts (batch)
     * 
     * @param array $texts Array of texts to embed
     * @return array Array of embeddings (same order as input)
     */
    public function generate_embeddings_batch($texts) {
        if (empty($texts) || !is_array($texts)) {
            return array();
        }
        
        $embeddings = array();
        
        // Process in batches to avoid rate limits
        $batch_size = 10; // Adjust based on API limits
        $batches = array_chunk($texts, $batch_size);
        
        foreach ($batches as $batch) {
            foreach ($batch as $text) {
                $embedding = $this->generate_embedding($text);
                if ($embedding !== false) {
                    $embeddings[] = $embedding;
                } else {
                    // Add null placeholder to maintain order
                    $embeddings[] = null;
                }
            }
            
            // Small delay between batches to respect rate limits
            if (count($batches) > 1) {
                usleep(100000); // 0.1 second
            }
        }
        
        return $embeddings;
    }
    
    /**
     * Get embedding dimensions
     * 
     * @return int Number of dimensions
     */
    public function get_dimensions() {
        return $this->dimensions;
    }
    
    /**
     * Get embedding model name
     * 
     * @return string Model name
     */
    public function get_model() {
        return $this->model;
    }
    
    /**
     * Test API connection
     * 
     * @return array Result with success status and message
     */
    public function test_connection() {
        try {
            if (empty($this->api_key)) {
                return array(
                    'success' => false,
                    'message' => 'Groq API key not configured'
                );
            }
            
            if (empty($this->api_url)) {
                return array(
                    'success' => false,
                    'message' => 'Groq API URL not configured'
                );
            }
            
            $test_text = 'This is a test embedding';
            $embedding = $this->generate_embedding($test_text);
            
            if ($embedding !== false && is_array($embedding)) {
                return array(
                    'success' => true,
                    'message' => 'Groq embeddings API connection successful. Generated ' . count($embedding) . ' dimensional vector.',
                    'dimensions' => count($embedding)
                );
            } else {
                // Get more detailed error information
                $error_details = '';
                if (empty($this->api_url)) {
                    $error_details = ' API URL is empty.';
                } elseif (empty($this->model)) {
                    $error_details = ' Model is not configured.';
                }
                
                // Include last error if available
                $error_message = 'Failed to generate test embedding.' . $error_details;
                if (!empty($this->last_error)) {
                    $error_message .= ' Error: ' . $this->last_error;
                }
                $error_message .= ' Please check your API key, URL, and model configuration.';
                
                // Add troubleshooting tips based on error type (using HTML for better display)
                if (strpos($this->last_error, '500') !== false || strpos($this->last_error, 'Internal server error') !== false) {
                    $error_message .= '<br><br><strong>Troubleshooting HTTP 500 Error:</strong>';
                    $error_message .= '<br>1. This is a server-side error from Groq\'s API';
                    $error_message .= '<br>2. Verify your API key is valid and has the correct format (starts with gsk_)';
                    $error_message .= '<br>3. Check if the model "nomic-embed-text-v1.5" is available in your Groq account';
                    $error_message .= '<br>4. The endpoint should be: https://api.groq.com/openai/v1/embeddings';
                    $error_message .= '<br>5. Try again in a few minutes - this may be a temporary server issue';
                    $error_message .= '<br>6. Check Groq\'s status page or documentation for known issues';
                    $error_message .= '<br>7. Verify your Groq account has access to embeddings API';
                } else {
                    $error_message .= '<br><br><strong>Common issues:</strong>';
                    $error_message .= '<br>1. Verify your Groq API key is correct and has embedding permissions';
                    $error_message .= '<br>2. Check that the API endpoint URL is correct';
                    $error_message .= '<br>3. Verify the model name is correct';
                    $error_message .= '<br>4. Check if you have reached rate limits';
                }
                
                return array(
                    'success' => false,
                    'message' => $error_message
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Exception occurred: ' . $e->getMessage()
            );
        } catch (Error $e) {
            return array(
                'success' => false,
                'message' => 'Fatal error: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Build text representation from SQL record for embedding
     * Combines all fields from the database record into a single text string
     * 
     * @param object|array $record Database record
     * @return string Combined text representation
     */
    public function build_text_from_record($record) {
        $parts = array();
        
        // Fields to exclude from embedding (internal IDs, timestamps, etc.)
        $excluded_fields = array(
            'ID',
            'ListingOwnerID',
            'SecondaryListingOwnerID',
            'ThirdListingOwnerID',
            'ListingOwnerBrokerageID',
            'SecondaryListingOwnerBrokerageID',
            'ThirdListingOwnerBrokerageID',
            'ListingOwnerOfficeID',
            'SecondaryListingOwnerOfficeID',
            'ThirdListingOwnerOfficeID',
            'CreatedTimestamp',
            'UpdatedTimestamp'
        );
        
        // Get all fields from the record dynamically
        $fields = array();
        if (is_object($record)) {
            // Get all object properties
            $fields = array_keys(get_object_vars($record));
        } elseif (is_array($record)) {
            // Get all array keys
            $fields = array_keys($record);
        }
        
        // Process all fields from the record
        foreach ($fields as $field) {
            // Skip excluded fields
            if (in_array($field, $excluded_fields)) {
                continue;
            }
            
            // Get field value
            $value = '';
            if (is_object($record) && isset($record->$field)) {
                $value = trim($record->$field);
            } elseif (is_array($record) && isset($record[$field])) {
                $value = trim($record[$field]);
            }
            
            // Only include non-empty values
            if (!empty($value)) {
                // Format field name for readability (replace underscores with spaces)
                $field_name = str_replace('_', ' ', $field);
                $parts[] = $field_name . ': ' . $value;
            }
        }
        
        // Join all parts with newlines for better context
        return implode("\n", $parts);
    }
    
    /**
     * Get cached embedding vector for a text
     * Checks both Redis (if available) and WordPress transients
     * 
     * @param string $text Normalized text
     * @return array|false Cached embedding vector or false if not cached
     */
    private function get_cached_embedding($text) {
        if (empty($text)) {
            return false;
        }
        
        // Generate cache key from normalized text
        $cache_key = 'embedding_' . md5($text);
        
        // Try Redis first (if available and enabled)
        if (class_exists('Boat_Chatbot_Redis_Cache_Manager')) {
            $redis_manager = Boat_Chatbot_Redis_Cache_Manager::get_instance();
            if ($redis_manager->is_enabled()) {
                $cached = $redis_manager->get($cache_key);
                if ($cached !== false && is_array($cached)) {
                    return $cached;
                }
            }
        }
        
        // Fallback to WordPress transients
        $transient_key = 'boat_chatbot_embedding_' . md5($text);
        $cached = get_transient($transient_key);
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }
        
        return false;
    }
    
    /**
     * Cache embedding vector for a text
     * Stores the full embedding vector for frequent user queries
     * 
     * @param string $text Normalized text
     * @param array $embedding Embedding vector (array of floats)
     * @return bool Success status
     */
    private function cache_embedding($text, $embedding) {
        if (empty($text) || !is_array($embedding) || empty($embedding)) {
            return false;
        }
        
        // Cache expiration: 1 hour (3600 seconds) for embedding vectors
        // This balances freshness with performance for frequent queries
        $expiration = 3600;
        
        // Generate cache key
        $cache_key = 'embedding_' . md5($text);
        
        // Try Redis first (if available and enabled)
        if (class_exists('Boat_Chatbot_Redis_Cache_Manager')) {
            $redis_manager = Boat_Chatbot_Redis_Cache_Manager::get_instance();
            if ($redis_manager->is_enabled()) {
                $result = $redis_manager->set($cache_key, $embedding, $expiration);
                if ($result) {
                    return true;
                }
            }
        }
        
        // Fallback to WordPress transients
        $transient_key = 'boat_chatbot_embedding_' . md5($text);
        return set_transient($transient_key, $embedding, $expiration);
    }
    
    /**
     * Clear cached embedding for a specific text
     * Useful for invalidating cache when needed
     * 
     * @param string $text Text to clear cache for
     * @return bool Success status
     */
    public function clear_cached_embedding($text) {
        if (empty($text)) {
            return false;
        }
        
        $text_normalized = trim($text);
        $cache_key = 'embedding_' . md5($text_normalized);
        $transient_key = 'boat_chatbot_embedding_' . md5($text_normalized);
        
        $cleared = false;
        
        // Clear from Redis (if available)
        if (class_exists('Boat_Chatbot_Redis_Cache_Manager')) {
            $redis_manager = Boat_Chatbot_Redis_Cache_Manager::get_instance();
            if ($redis_manager->is_enabled()) {
                $redis_manager->delete($cache_key);
                $cleared = true;
            }
        }
        
        // Clear from WordPress transients
        if (delete_transient($transient_key)) {
            $cleared = true;
        }
        
        return $cleared;
    }
}
?>


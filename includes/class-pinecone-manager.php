<?php

/**
 * Pinecone Vector Database Manager
 * Handles all interactions with Pinecone vector database
 */
class Boat_Chatbot_Pinecone_Manager {
    
    private static $instance = null;
    private $api_key;
    private $database_url;
    private $environment;
    private $prod_index_name;
    private $staging_index_name;
    private $current_environment; // 'prod' or 'staging'
    private $last_error = null; // Store last error message for better error reporting
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->api_key = get_option('boat_chatbot_pinecone_api_key');
        $this->database_url = get_option('boat_chatbot_pinecone_url');
        $this->environment = get_option('boat_chatbot_pinecone_environment', 'us-east1-aws');
        $this->prod_index_name = get_option('boat_chatbot_pinecone_prod_index', 'boat-chatbot-prod');
        $this->staging_index_name = get_option('boat_chatbot_pinecone_staging_index', 'boat-chatbot-staging');
        $this->current_environment = get_option('boat_chatbot_pinecone_current_env', 'prod');
    }
    
    /**
     * Get current index name based on environment
     * 
     * @return string Index name
     */
    private function get_current_index_name() {
        return ($this->current_environment === 'staging') ? $this->staging_index_name : $this->prod_index_name;
    }
    
    /**
     * Get last error message
     * 
     * @return string|null Last error message or null if no error
     */
    public function get_last_error() {
        return $this->last_error;
    }
    
    /**
     * Get Pinecone API base URL
     * 
     * @return string API base URL
     */
    private function get_api_base_url() {
        // If a custom database URL is provided, use it
        if (!empty($this->database_url)) {
            // Remove trailing slash if present
            return rtrim($this->database_url, '/');
        }
        
        // Otherwise, auto-generate URL based on index name and environment
        // Pinecone API format: https://{index-name}-{project-id}.svc.{environment}.pinecone.io
        // For serverless: https://{index-name}.svc.{environment}.pinecone.io
        $index_name = $this->get_current_index_name();
        return 'https://' . $index_name . '.svc.' . $this->environment . '.pinecone.io';
    }
    
    /**
     * Make API request to Pinecone
     * 
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param array $data Request data
     * @return array|false Response data or false on error
     */
    private function make_api_request($endpoint, $method = 'GET', $data = null) {
        if (empty($this->api_key)) {
            $this->last_error = 'Pinecone API key is not configured';
            return false;
        }
        
        $url = $this->get_api_base_url() . $endpoint;
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Api-Key' => $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30,
            'blocking' => true
        );
        
        // Only set body for POST/PUT requests with valid data
        if ($method === 'POST' || $method === 'PUT') {
            if ($data !== null && !is_bool($data)) {
                // Only set body if data is an array (which we'll JSON encode) or a non-empty string
                // Explicitly exclude boolean values (false/true) and other invalid types
                if (is_array($data)) {
                    $json_body = json_encode($data);
                    // json_encode can return false on failure, so check for that
                    if ($json_body !== false && is_string($json_body)) {
                        $args['body'] = $json_body;
                    } else {
                        // If JSON encoding failed, use empty object
                        $args['body'] = '{}';
                    }
                } elseif (is_string($data) && $data !== '') {
                    $args['body'] = $data;
                } else {
                    // If $data is empty string or other invalid type, set empty JSON object
                    $args['body'] = '{}';
                }
            } else {
                // For POST/PUT with null data or boolean data, set empty JSON object
                $args['body'] = '{}';
            }
        }
        
        // Final safety check: ensure body is never a boolean or false
        if (isset($args['body'])) {
            if (is_bool($args['body']) || $args['body'] === false || $args['body'] === true) {
                // Remove invalid body value
                unset($args['body']);
                // For POST/PUT, we need a body, so set empty JSON
                if ($method === 'POST' || $method === 'PUT') {
                    $args['body'] = '{}';
                }
            } elseif (!is_string($args['body']) && !is_array($args['body'])) {
                // Convert any other non-string, non-array to string
                $args['body'] = (string)$args['body'];
            }
        }
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->last_error = 'Connection error: ' . $error_message;
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code < 200 || $response_code >= 300) {
            // Log the raw response for debugging
            error_log('[Boat Chatbot Pinecone] API error response | HTTP Code: ' . $response_code . ' | Endpoint: ' . $endpoint . ' | Response body (first 500 chars): ' . substr($body, 0, 500));
            
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
                $this->last_error = $error_message . ' (HTTP ' . $response_code . ')';
            } else {
                // Try to parse as JSON for more detailed error
                $decoded_error = json_decode($body, true);
                
                if (isset($decoded_error['error']['message'])) {
                    $error_message = $decoded_error['error']['message'];
                    // Check if error contains dimension information
                    if (isset($decoded_error['error']['expected_dimensions'])) {
                        $this->last_error = $error_message . ' (HTTP ' . $response_code . ') | Expected dimensions: ' . $decoded_error['error']['expected_dimensions'];
                    } else {
                        $this->last_error = $error_message . ' (HTTP ' . $response_code . ')';
                    }
                } elseif (isset($decoded_error['message'])) {
                    // Some Pinecone errors use 'message' directly
                    $error_message = $decoded_error['message'];
                    // Check if error contains dimension information
                    if (isset($decoded_error['expected_dimensions'])) {
                        $this->last_error = $error_message . ' (HTTP ' . $response_code . ') | Expected dimensions: ' . $decoded_error['expected_dimensions'];
                    } else {
                        $this->last_error = $error_message . ' (HTTP ' . $response_code . ')';
                    }
                } elseif (is_string($decoded_error)) {
                    // Sometimes the error is just a string
                    $this->last_error = $decoded_error . ' (HTTP ' . $response_code . ')';
                } elseif (is_array($decoded_error) && !empty($decoded_error)) {
                    // If we have an array but no standard error format, try to extract useful info
                    $error_parts = array();
                    foreach (array('code', 'type', 'status', 'reason', 'message', 'details') as $key) {
                        if (isset($decoded_error[$key])) {
                            if (is_array($decoded_error[$key])) {
                                $error_parts[] = $key . ': ' . json_encode($decoded_error[$key]);
                            } else {
                                $error_parts[] = $key . ': ' . $decoded_error[$key];
                            }
                        }
                    }
                    if (!empty($error_parts)) {
                        $this->last_error = implode(', ', $error_parts) . ' (HTTP ' . $response_code . ')';
                    } else {
                        // Fallback: use the full JSON response
                        $this->last_error = 'HTTP ' . $response_code . ' error: ' . json_encode($decoded_error);
                    }
                    
                    // Try to extract dimension from error details
                    if (isset($decoded_error['expected_dimensions'])) {
                        $this->last_error .= ' | Expected dimensions: ' . $decoded_error['expected_dimensions'];
                    } elseif (preg_match('/expected[_\s]?dimensions?[:\s]+(\d+)/i', $body, $matches)) {
                        $this->last_error .= ' | Expected dimensions: ' . $matches[1];
                    }
                } else {
                    // Get error details for error message
                    $error_details = is_array($decoded_error) ? json_encode($decoded_error) : substr($body, 0, 1000);
                    // Try to extract dimension from error details
                    $dimension_info = '';
                    if (is_array($decoded_error) && isset($decoded_error['expected_dimensions'])) {
                        $dimension_info = ' | Expected dimensions: ' . $decoded_error['expected_dimensions'];
                    } elseif (preg_match('/expected[_\s]?dimensions?[:\s]+(\d+)/i', $body, $matches)) {
                        $dimension_info = ' | Expected dimensions: ' . $matches[1];
                    }
                    $this->last_error = 'HTTP ' . $response_code . ' error: ' . substr($body, 0, 200) . $dimension_info;
                }
            }
            
            return false;
        }
        
        $decoded = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->last_error = 'Invalid JSON response: ' . json_last_error_msg();
            return false;
        }
        
        // Clear error on success
        $this->last_error = null;
        return $decoded;
    }
    
    /**
     * Upsert vectors into Pinecone
     * Supports both dense vectors and hybrid (dense + sparse) vectors
     * 
     * @param array $vectors Array of vectors with format: 
     *   [['id' => 'id1', 'values' => [...], 'metadata' => {...}, 'sparseValues' => {...}], ...]
     *   sparseValues format: {'indices': [...], 'values': [...]}
     * @return bool Success status
     */
    public function upsert_vectors($vectors) {
        if (empty($vectors) || !is_array($vectors)) {
            $this->last_error = 'No vectors provided for upsert';
            return false;
        }
        
        // Try to get actual Pinecone index dimension first
        $pinecone_dimension = $this->get_index_dimension();
        
        // Fallback to configured dimension if we can't get it from Pinecone
        $expected_dimensions = $pinecone_dimension !== false 
            ? $pinecone_dimension 
            : absint(get_option('boat_chatbot_groq_embedding_dimensions', 1024));
        
        // Pinecone expects vectors in specific format
        $pinecone_vectors = array();
        $dimension_errors = array();
        
        foreach ($vectors as $vector) {
            if (!isset($vector['id']) || !isset($vector['values'])) {
                continue;
            }
            
            // Validate dimensions
            $actual_dimensions = count($vector['values']);
            if ($actual_dimensions !== $expected_dimensions) {
                $dimension_errors[] = "Vector ID '{$vector['id']}' has {$actual_dimensions} dimensions, expected {$expected_dimensions}";
                error_log('[Boat Chatbot Pinecone] Upsert - Dimension mismatch: Vector ID ' . $vector['id'] . ' has ' . $actual_dimensions . ' dimensions, expected ' . $expected_dimensions);
                continue; // Skip this vector
            }
            
            $pinecone_vector = array(
                'id' => (string)$vector['id'],
                'values' => $vector['values']
            );
            
            // Add sparse vector if provided (for hybrid search)
            if (isset($vector['sparseValues']) && is_array($vector['sparseValues'])) {
                if (isset($vector['sparseValues']['indices']) && isset($vector['sparseValues']['values'])) {
                    $pinecone_vector['sparseValues'] = $vector['sparseValues'];
                }
            }
            
            if (isset($vector['metadata'])) {
                // Clean and validate metadata - Pinecone only accepts strings, numbers, and booleans
                $cleaned_metadata = $this->clean_metadata($vector['metadata']);
                
                // Validate metadata size and reduce if needed
                if (!empty($cleaned_metadata)) {
                    // Calculate metadata size
                    $metadata_json = json_encode($cleaned_metadata);
                    $metadata_size = strlen($metadata_json);
                    
                    // Check for potential issues - use 30KB as safe limit (Pinecone limit is 40KB, but we want buffer)
                    $max_metadata_size = 30000; // Use 30KB as safe limit (Pinecone limit is 40KB)
                    if ($metadata_size > $max_metadata_size) {
                        // Try to reduce metadata size by removing largest fields
                        $field_sizes = array();
                        foreach ($cleaned_metadata as $key => $value) {
                            $field_sizes[$key] = strlen(is_string($value) ? $value : json_encode($value));
                        }
                        arsort($field_sizes);
                        
                        // Remove largest fields until under limit (prioritize keeping ID and essential fields)
                        // Keep only the most essential fields for search and display
                        $essential_fields = array(
                            'ID', 'VesselName', 'Manufacturer', 'Model', 'Year', 
                            'PriceUSD', 'Type_', 'Status', 'Condition', 'City', 
                            'State', 'Country', 'LOAFeet', 'DisplayLengthFeet', 
                            'Category', 'Currency', 'CurrencySymbol'
                        );
                        $reduced_metadata = array();
                        $reduced_size = 0;
                        
                        // First, add essential fields
                        foreach ($essential_fields as $essential_key) {
                            if (isset($cleaned_metadata[$essential_key])) {
                                $reduced_metadata[$essential_key] = $cleaned_metadata[$essential_key];
                                $reduced_size += strlen(is_string($cleaned_metadata[$essential_key]) ? $cleaned_metadata[$essential_key] : json_encode($cleaned_metadata[$essential_key]));
                            }
                        }
                        
                        // Then add other fields in order of size (smallest first) until we hit the limit
                        $remaining_fields = array_diff_key($cleaned_metadata, array_flip($essential_fields));
                        $remaining_sizes = array_intersect_key($field_sizes, $remaining_fields);
                        asort($remaining_sizes); // Sort by size, smallest first
                        
                        foreach ($remaining_sizes as $key => $field_size) {
                            // Account for JSON encoding overhead (key + value + separators)
                            $estimated_size = strlen($key) + $field_size + 10; // Add overhead for JSON structure
                            if ($reduced_size + $estimated_size <= $max_metadata_size) {
                                $reduced_metadata[$key] = $cleaned_metadata[$key];
                                $reduced_size += $estimated_size;
                            } else {
                                break; // Stop adding fields if we'd exceed limit
                            }
                        }
                        
                        // Recalculate actual size after reduction
                        $final_size = strlen(json_encode($reduced_metadata));
                        $cleaned_metadata = $reduced_metadata;
                    }
                    
                    $pinecone_vector['metadata'] = $cleaned_metadata;
                }
            }
            
            $pinecone_vectors[] = $pinecone_vector;
        }
        
        // If all vectors had dimension errors, return false
        if (empty($pinecone_vectors) && !empty($dimension_errors)) {
            $this->last_error = 'All vectors have dimension mismatches. ' . implode('; ', array_slice($dimension_errors, 0, 3));
            if (count($dimension_errors) > 3) {
                $this->last_error .= ' (and ' . (count($dimension_errors) - 3) . ' more)';
            }
            return false;
        }
        
        // If some vectors had errors, continue with valid ones
        // (dimension errors are already tracked in $dimension_errors array)
        
        if (empty($pinecone_vectors)) {
            $this->last_error = 'No valid vectors to upsert after validation';
            return false;
        }
        
        $data = array(
            'vectors' => $pinecone_vectors
        );
        
        $result = $this->make_api_request('/vectors/upsert', 'POST', $data);
        if ($result === false) {
            // Ensure we have an error message
            if (empty($this->last_error)) {
                $this->last_error = 'Unknown error from Pinecone API';
            }
            
            // Log the actual error for debugging
            error_log('[Boat Chatbot Pinecone] Upsert failed | Error: ' . $this->last_error . ' | Vectors count: ' . count($pinecone_vectors));
            
            // Check if the error is about dimension mismatch
            $error_lower = strtolower($this->last_error);
            if (strpos($error_lower, 'dimension') !== false || strpos($error_lower, '1536') !== false || strpos($error_lower, 'expected_dimensions') !== false) {
                // Try to extract the expected dimension from error message
                if (preg_match('/expected[_\s]?dimensions?[:\s]+(\d+)/i', $this->last_error, $matches)) {
                    $pinecone_expected = absint($matches[1]);
                    $this->last_error = 'Dimension mismatch: Pinecone index expects ' . $pinecone_expected . ' dimensions, but vectors have ' . $expected_dimensions . ' dimensions. Please update your embedding dimensions setting to ' . $pinecone_expected . '.';
                } else {
                    $this->last_error = 'Dimension mismatch: ' . $this->last_error . ' (Pinecone index expects ' . $expected_dimensions . ' dimensions)';
                }
            }
            
            if (!empty($dimension_errors)) {
                // If upsert failed and we had dimension errors, include that in the error message
                $this->last_error .= ' Also, ' . count($dimension_errors) . ' vectors were skipped due to dimension mismatches.';
            }
        } else {
            error_log('[Boat Chatbot Pinecone] Upsert successful | Vectors count: ' . count($pinecone_vectors));
        }
        
        return $result !== false;
    }
    
    /**
     * Clean and validate metadata for Pinecone
     * Pinecone only accepts: strings, numbers (int/float), and booleans
     * - Removes null values
     * - Removes or aggressively truncates large HTML/text fields (Description, Summary, etc.)
     * - Truncates strings to 500 characters (reduced from 1000 to keep metadata size down)
     * - Converts arrays/objects to JSON strings (max 500 chars)
     * - Removes invalid keys (non-string keys)
     * 
     * @param array $metadata Raw metadata array
     * @return array Cleaned metadata array
     */
    private function clean_metadata($metadata) {
        if (!is_array($metadata)) {
            return array();
        }
        
        // Fields to completely remove (too large and not essential for search)
        $fields_to_remove = array(
            'Description',           // HTML content, very large
            'Summary',               // Can be large
            'NotableUpgrades',       // Usually just whitespace
            'Tenders',              // Can be large
            'PriceHeadline'          // Not essential
        );
        
        // Fields to aggressively truncate (keep but make very short)
        $fields_to_truncate_aggressively = array(
            'ListingOwnerOfficeDisplayPicture' => 100,  // URL, keep short
        );
        
        // Standard truncation limit (reduced from 1000 to 500)
        $standard_truncate_limit = 500;
        
        $cleaned = array();
        
        foreach ($metadata as $key => $value) {
            // Skip non-string keys (Pinecone requires string keys)
            if (!is_string($key)) {
                continue;
            }
            
            // Skip null values (Pinecone doesn't accept null)
            if ($value === null) {
                continue;
            }
            
            // Remove fields that are too large and not essential
            if (in_array($key, $fields_to_remove)) {
                continue;
            }
            
            // Handle different value types
            if (is_string($value)) {
                // Check if this field should be aggressively truncated
                if (isset($fields_to_truncate_aggressively[$key])) {
                    $truncate_limit = $fields_to_truncate_aggressively[$key];
                } else {
                    $truncate_limit = $standard_truncate_limit;
                }
                
                // Strip HTML tags from strings to reduce size
                $stripped = strip_tags($value);
                // Truncate to limit
                $cleaned[$key] = strlen($stripped) > $truncate_limit ? substr($stripped, 0, $truncate_limit) : $stripped;
            } elseif (is_int($value) || is_float($value)) {
                // Numbers are allowed
                $cleaned[$key] = $value;
            } elseif (is_bool($value)) {
                // Booleans are allowed
                $cleaned[$key] = $value;
            } elseif (is_array($value) || is_object($value)) {
                // Convert arrays/objects to JSON string, then truncate to 500 chars
                $json = json_encode($value);
                if ($json !== false) {
                    $cleaned[$key] = strlen($json) > $standard_truncate_limit ? substr($json, 0, $standard_truncate_limit) : $json;
                }
            } else {
                // For any other type, convert to string and truncate
                $str = (string)$value;
                $cleaned[$key] = strlen($str) > $standard_truncate_limit ? substr($str, 0, $standard_truncate_limit) : $str;
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Query Pinecone for similar vectors
     * 
     * @param array $query_vector Query embedding vector (dense vector)
     * @param int $top_k Number of results to return
     * @param array $filter Optional metadata filter
     * @param array $sparse_vector Optional sparse vector for hybrid search
     * @param float $alpha Optional hybrid search alpha parameter (0-1, weight for dense vector)
     * @return array|false Array of results or false on error
     */
    public function query($query_vector, $top_k = 10, $filter = null, $sparse_vector = null, $alpha = 0.7) {
        if (empty($query_vector) || !is_array($query_vector)) {
            return false;
        }
        
        $data = array(
            'vector' => $query_vector,
            'topK' => min($top_k, 100), // Pinecone limit
            'includeMetadata' => true,
            'includeValues' => false
        );
        
        // Add sparse vector for hybrid search if provided
        if ($sparse_vector !== null && is_array($sparse_vector)) {
            if (isset($sparse_vector['indices']) && isset($sparse_vector['values'])) {
                $data['sparseVector'] = $sparse_vector;
                // Alpha parameter: weight for dense vector (1-alpha is weight for sparse)
                // Pinecone uses alpha: 0 = sparse only, 1 = dense only, 0.5 = equal weight
                $data['alpha'] = max(0, min(1, floatval($alpha)));
            }
        }
        
        if ($filter !== null) {
            $data['filter'] = $filter;
        }
        
        $result = $this->make_api_request('/query', 'POST', $data);
        
        if ($result === false || !isset($result['matches'])) {
            return false;
        }
        
        return $result['matches'];
    }
    
    /**
     * Delete vectors by IDs
     * 
     * @param array $ids Array of vector IDs to delete
     * @return bool Success status
     */
    public function delete_vectors($ids) {
        if (empty($ids) || !is_array($ids)) {
            $this->last_error = 'No IDs provided for deletion';
            return false;
        }
        
        // Convert IDs to strings
        $string_ids = array_map('strval', $ids);
        
        $data = array(
            'ids' => $string_ids
        );
        
        $result = $this->make_api_request('/vectors/delete', 'POST', $data);
        
        return $result !== false;
    }
    
    /**
     * Delete all vectors (use with caution)
     * 
     * @return bool Success status
     */
    public function delete_all() {
        $data = array(
            'deleteAll' => true
        );
        
        $result = $this->make_api_request('/vectors/delete', 'POST', $data);
        
        return $result !== false;
    }
    
    /**
     * Get index stats
     * 
     * @return array|false Index statistics or false on error
     */
    public function get_index_stats() {
        $result = $this->make_api_request('/describe_index_stats', 'GET');
        
        return $result;
    }
    
    /**
     * Get the dimension of the Pinecone index
     * 
     * @return int|false Index dimension or false on error
     */
    public function get_index_dimension() {
        $stats = $this->get_index_stats();
        
        if ($stats !== false && is_array($stats)) {
            // Pinecone returns dimension in the stats
            if (isset($stats['dimension'])) {
                return absint($stats['dimension']);
            }
            
            // If dimension is not directly in stats, try to get it from namespaces
            // Some Pinecone responses have dimension in namespaces
            if (isset($stats['namespaces']) && is_array($stats['namespaces'])) {
                foreach ($stats['namespaces'] as $namespace_data) {
                    if (isset($namespace_data['dimension'])) {
                        return absint($namespace_data['dimension']);
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if index exists and is accessible
     * 
     * @return array Result with success status and message
     */
    public function test_connection() {
        try {
            // Validate configuration first
            if (empty($this->api_key)) {
                return array(
                    'success' => false,
                    'message' => 'Pinecone API key not configured'
                );
            }
            
            if (empty($this->environment)) {
                return array(
                    'success' => false,
                    'message' => 'Pinecone environment not configured'
                );
            }
            
            $index_name = $this->get_current_index_name();
            if (empty($index_name)) {
                return array(
                    'success' => false,
                    'message' => 'Pinecone index name not configured for ' . $this->current_environment . ' environment'
                );
            }
            
            $stats = $this->get_index_stats();
            
            if ($stats !== false && is_array($stats)) {
                $vector_count = isset($stats['totalVectorCount']) ? $stats['totalVectorCount'] : 0;
                $message = 'Pinecone connection successful';
                if ($vector_count > 0) {
                    $message .= ' (Index contains ' . number_format($vector_count) . ' vectors)';
                }
                
                return array(
                    'success' => true,
                    'message' => $message,
                    'stats' => $stats
                );
            } else {
                // Get more detailed error information
                $error_message = 'Failed to connect to Pinecone index';
                if (!empty($this->last_error)) {
                    $error_message .= '. Error: ' . $this->last_error;
                }
                $error_message .= ' Please check your API key, environment, and index name configuration.';
                
                // Add troubleshooting tips
                $error_message .= '<br><br><strong>Troubleshooting:</strong>';
                $error_message .= '<br>1. Verify your Pinecone API key is correct';
                $error_message .= '<br>2. Check that the environment is correct (e.g., us-east1-aws)';
                $error_message .= '<br>3. Verify the index name "' . esc_html($index_name) . '" exists in your Pinecone project';
                $error_message .= '<br>4. Ensure the index is in the correct environment';
                $error_message .= '<br>5. Check that your API key has access to this index';
                $error_message .= '<br>6. Verify the API URL format is correct for your Pinecone deployment type (serverless vs pod-based)';
                
                if (!empty($this->last_error) && (strpos($this->last_error, '404') !== false || strpos($this->last_error, 'Not Found') !== false)) {
                    $error_message .= '<br><br><strong>Index Not Found:</strong> The index "' . esc_html($index_name) . '" may not exist. Please create it in your Pinecone dashboard or check the index name.';
                } elseif (!empty($this->last_error) && (strpos($this->last_error, '401') !== false || strpos($this->last_error, '403') !== false || strpos($this->last_error, 'Unauthorized') !== false)) {
                    $error_message .= '<br><br><strong>Authentication Error:</strong> Your API key may be invalid or expired. Please verify your API key in the Pinecone dashboard.';
                } elseif (!empty($this->last_error) && strpos($this->last_error, '500') !== false) {
                    $error_message .= '<br><br><strong>Server Error:</strong> This is a server-side error from Pinecone. Try again in a few minutes or check Pinecone\'s status page.';
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
     * Get current environment
     * 
     * @return string 'prod' or 'staging'
     */
    public function get_current_environment() {
        return $this->current_environment;
    }
    
    /**
     * Set current environment
     * 
     * @param string $env 'prod' or 'staging'
     */
    public function set_current_environment($env) {
        if (in_array($env, array('prod', 'staging'))) {
            $this->current_environment = $env;
            update_option('boat_chatbot_pinecone_current_env', $env);
        }
    }
}
?>


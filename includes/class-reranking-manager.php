<?php

/**
 * Reranking Manager
 * Handles reranking of search results using external reranking APIs
 * Supports Cohere Rerank API and can be extended for other services
 */
class Boat_Chatbot_Reranking_Manager {
    
    private static $instance = null;
    private $api_key;
    private $api_url;
    private $model;
    private $enabled;
    private $top_n; // Number of results to rerank
    private $last_error = null;
    
    // Supported providers
    const PROVIDER_COHERE = 'cohere';
    const PROVIDER_JINA = 'jina';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->api_key = get_option('boat_chatbot_rerank_api_key');
        $this->api_url = get_option('boat_chatbot_rerank_api_url', 'https://api.cohere.ai/v1/rerank');
        $this->model = get_option('boat_chatbot_rerank_model', 'rerank-english-v3.0');
        $this->enabled = get_option('boat_chatbot_rerank_enabled', false);
        $this->top_n = absint(get_option('boat_chatbot_rerank_top_n', 20));
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
     * Check if reranking is enabled
     * 
     * @return bool True if enabled
     */
    public function is_enabled() {
        return $this->enabled && !empty($this->api_key);
    }
    
    /**
     * Rerank search results
     * 
     * @param string $query User query
     * @param array $documents Array of documents to rerank. Each document should have:
     *   - 'id': Document ID
     *   - 'text': Document text for reranking
     *   - 'score': Original relevance score (optional)
     *   - Any other metadata
     * @param int $top_n Optional number of top results to return (overrides setting)
     * @return array|false Reranked documents array or false on error
     */
    public function rerank($query, $documents, $top_n = null) {
        if (!$this->is_enabled()) {
            return false;
        }
        
        if (empty($query) || empty($documents) || !is_array($documents)) {
            $this->last_error = 'Invalid query or documents';
            return false;
        }
        
        if ($top_n === null) {
            $top_n = $this->top_n;
        }
        
        // Limit documents to rerank (most APIs have limits)
        $max_docs = 100;
        if (count($documents) > $max_docs) {
            $documents = array_slice($documents, 0, $max_docs);
        }
        
        // Extract texts for reranking
        $texts = array();
        foreach ($documents as $doc) {
            $text = '';
            if (is_array($doc)) {
                $text = isset($doc['text']) ? $doc['text'] : (isset($doc['content']) ? $doc['content'] : '');
            } elseif (is_object($doc)) {
                $text = isset($doc->text) ? $doc->text : (isset($doc->content) ? $doc->content : '');
            }
            
            if (empty($text)) {
                // Try to build text from metadata/object properties
                if (is_object($doc)) {
                    $text = $this->build_text_from_object($doc);
                } elseif (is_array($doc)) {
                    $text = $this->build_text_from_array($doc);
                }
            }
            
            if (!empty($text)) {
                $texts[] = $text;
            } else {
                // Skip documents without text
                continue;
            }
        }
        
        if (empty($texts)) {
            $this->last_error = 'No valid document texts found for reranking';
            return false;
        }
        
        // Determine provider from API URL
        $provider = $this->detect_provider();
        
        // Call appropriate reranking API
        switch ($provider) {
            case self::PROVIDER_COHERE:
                return $this->rerank_cohere($query, $documents, $texts, $top_n);
            case self::PROVIDER_JINA:
                return $this->rerank_jina($query, $documents, $texts, $top_n);
            default:
                // Default to Cohere format
                return $this->rerank_cohere($query, $documents, $texts, $top_n);
        }
    }
    
    /**
     * Detect provider from API URL
     * 
     * @return string Provider name
     */
    private function detect_provider() {
        if (strpos($this->api_url, 'cohere.ai') !== false) {
            return self::PROVIDER_COHERE;
        } elseif (strpos($this->api_url, 'jina.ai') !== false) {
            return self::PROVIDER_JINA;
        }
        // Default to Cohere
        return self::PROVIDER_COHERE;
    }
    
    /**
     * Rerank using Cohere API
     * 
     * @param string $query User query
     * @param array $documents Original documents
     * @param array $texts Document texts
     * @param int $top_n Number of results to return
     * @return array|false Reranked documents or false on error
     */
    private function rerank_cohere($query, $documents, $texts, $top_n) {
        $request_data = array(
            'model' => $this->model,
            'query' => $query,
            'documents' => $texts,
            'top_n' => min($top_n, count($texts))
        );
        
        $response = wp_remote_post($this->api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
                'Accept' => 'application/json'
            ),
            'body' => json_encode($request_data),
            'timeout' => 30,
            'blocking' => true
        ));
        
        if (is_wp_error($response)) {
            $this->last_error = 'Connection error: ' . $response->get_error_message();
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code < 200 || $response_code >= 300) {
            $this->last_error = 'API error (HTTP ' . $response_code . '): ' . substr($body, 0, 200);
            return false;
        }
        
        $decoded = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !isset($decoded['results'])) {
            $this->last_error = 'Invalid API response';
            return false;
        }
        
        // Map reranked results back to original documents
        $reranked = array();
        foreach ($decoded['results'] as $result) {
            $index = isset($result['index']) ? intval($result['index']) : -1;
            $relevance_score = isset($result['relevance_score']) ? floatval($result['relevance_score']) : 0;
            
            if ($index >= 0 && $index < count($documents)) {
                $doc = $documents[$index];
                
                // Add rerank score
                if (is_array($doc)) {
                    $doc['rerank_score'] = $relevance_score;
                } elseif (is_object($doc)) {
                    $doc->rerank_score = $relevance_score;
                }
                
                $reranked[] = $doc;
            }
        }
        
        return $reranked;
    }
    
    /**
     * Rerank using Jina API
     * 
     * @param string $query User query
     * @param array $documents Original documents
     * @param array $texts Document texts
     * @param int $top_n Number of results to return
     * @return array|false Reranked documents or false on error
     */
    private function rerank_jina($query, $documents, $texts, $top_n) {
        // Jina API format is similar to Cohere
        // Adjust URL if needed
        $api_url = str_replace('/v1/rerank', '/v1/rerank', $this->api_url);
        
        $request_data = array(
            'model' => $this->model,
            'query' => $query,
            'documents' => $texts,
            'top_n' => min($top_n, count($texts))
        );
        
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
                'Accept' => 'application/json'
            ),
            'body' => json_encode($request_data),
            'timeout' => 30,
            'blocking' => true
        ));
        
        if (is_wp_error($response)) {
            $this->last_error = 'Connection error: ' . $response->get_error_message();
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code < 200 || $response_code >= 300) {
            $this->last_error = 'API error (HTTP ' . $response_code . '): ' . substr($body, 0, 200);
            return false;
        }
        
        $decoded = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !isset($decoded['results'])) {
            $this->last_error = 'Invalid API response';
            return false;
        }
        
        // Map reranked results back to original documents
        $reranked = array();
        foreach ($decoded['results'] as $result) {
            $index = isset($result['index']) ? intval($result['index']) : -1;
            $relevance_score = isset($result['relevance_score']) ? floatval($result['relevance_score']) : 0;
            
            if ($index >= 0 && $index < count($documents)) {
                $doc = $documents[$index];
                
                if (is_array($doc)) {
                    $doc['rerank_score'] = $relevance_score;
                } elseif (is_object($doc)) {
                    $doc->rerank_score = $relevance_score;
                }
                
                $reranked[] = $doc;
            }
        }
        
        return $reranked;
    }
    
    /**
     * Build text from object for reranking
     * 
     * @param object $obj Object to extract text from
     * @return string Text content
     */
    private function build_text_from_object($obj) {
        $text_parts = array();
        
        // Common fields that might contain text
        $text_fields = array('Description', 'Name', 'Title', 'Manufacturer', 'Model', 'Type', 'City', 'State', 'Country');
        
        foreach ($text_fields as $field) {
            if (isset($obj->$field) && !empty($obj->$field)) {
                $text_parts[] = $obj->$field;
            }
        }
        
        return implode(' ', $text_parts);
    }
    
    /**
     * Build text from array for reranking
     * 
     * @param array $arr Array to extract text from
     * @return string Text content
     */
    private function build_text_from_array($arr) {
        $text_parts = array();
        
        // Common fields that might contain text
        $text_fields = array('Description', 'Name', 'Title', 'Manufacturer', 'Model', 'Type', 'City', 'State', 'Country');
        
        foreach ($text_fields as $field) {
            if (isset($arr[$field]) && !empty($arr[$field])) {
                $text_parts[] = $arr[$field];
            }
        }
        
        return implode(' ', $text_parts);
    }
    
    /**
     * Test reranking API connection
     * 
     * @return array Result with success status and message
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => 'Reranking API key not configured'
            );
        }
        
        if (empty($this->api_url)) {
            return array(
                'success' => false,
                'message' => 'Reranking API URL not configured'
            );
        }
        
        // Test with a simple query
        $test_query = 'test query';
        $test_documents = array(
            array('id' => 1, 'text' => 'This is a test document about boats.'),
            array('id' => 2, 'text' => 'Another test document with yacht information.')
        );
        
        $result = $this->rerank($test_query, $test_documents, 2);
        
        if ($result !== false && is_array($result) && !empty($result)) {
            return array(
                'success' => true,
                'message' => 'Reranking API connection successful'
            );
        } else {
            $error = $this->get_last_error();
            return array(
                'success' => false,
                'message' => 'Reranking API test failed: ' . ($error ? $error : 'Unknown error')
            );
        }
    }
}
?>


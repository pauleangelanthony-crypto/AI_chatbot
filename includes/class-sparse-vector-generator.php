<?php

/**
 * Sparse Vector Generator
 * Generates sparse vectors for hybrid search with Pinecone
 * Supports both BM25 (statistical) and embedding-based methods
 * 
 * Sparse vectors represent keyword-based relevance
 * Combined with dense vectors (semantic embeddings) for hybrid search
 */
class Boat_Chatbot_Sparse_Vector_Generator {
    
    private static $instance = null;
    private $vocabulary = array(); // Global vocabulary (term -> index mapping)
    private $document_frequencies = array(); // Document frequency for each term
    private $total_documents = 0;
    private $average_doc_length = 0;
    private $k1 = 1.5; // BM25 parameter k1 (term frequency saturation)
    private $b = 0.75; // BM25 parameter b (length normalization)
    
    // Embedding-based sparse vector generation
    private $use_embedding_api = false; // Whether to use embedding API for sparse vectors
    private $groq_manager = null; // Groq embeddings manager for embedding-based generation
    private $sparse_embedding_model = null; // Separate embedding model for sparse vectors (optional)
    private $sparse_embedding_api_url = null; // Separate API URL for sparse embeddings (optional)
    private $sparsity_threshold = 0.1; // Threshold for keeping sparse vector values (top 10% by default)
    
    // Cache for vocabulary and statistics
    private $vocabulary_cache_key = 'boat_chatbot_sparse_vocab';
    private $stats_cache_key = 'boat_chatbot_sparse_stats';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_vocabulary();
        $this->load_statistics();
        
        // Check if embedding-based sparse vectors are enabled
        $this->use_embedding_api = get_option('boat_chatbot_sparse_use_embedding', false);
        $this->sparsity_threshold = floatval(get_option('boat_chatbot_sparse_threshold', 0.1));
        
        // Get sparse-specific embedding model and API URL (if configured)
        $this->sparse_embedding_model = get_option('boat_chatbot_sparse_embedding_model', '');
        $this->sparse_embedding_api_url = get_option('boat_chatbot_sparse_embedding_api_url', '');
        
        // Load Groq manager if embedding-based generation is enabled
        if ($this->use_embedding_api && class_exists('Boat_Chatbot_Groq_Embeddings_Manager')) {
            $this->groq_manager = Boat_Chatbot_Groq_Embeddings_Manager::get_instance();
        }
    }
    
    /**
     * Load vocabulary from cache or build it
     */
    private function load_vocabulary() {
        $cached = get_transient($this->vocabulary_cache_key);
        if ($cached !== false && is_array($cached)) {
            $this->vocabulary = $cached;
        }
    }
    
    /**
     * Save vocabulary to cache
     */
    private function save_vocabulary() {
        set_transient($this->vocabulary_cache_key, $this->vocabulary, DAY_IN_SECONDS * 7);
    }
    
    /**
     * Load statistics from cache
     */
    private function load_statistics() {
        $cached = get_transient($this->stats_cache_key);
        if ($cached !== false && is_array($cached)) {
            $this->document_frequencies = isset($cached['df']) ? $cached['df'] : array();
            $this->total_documents = isset($cached['total_docs']) ? intval($cached['total_docs']) : 0;
            $this->average_doc_length = isset($cached['avg_length']) ? floatval($cached['avg_length']) : 0;
        }
    }
    
    /**
     * Save statistics to cache
     */
    private function save_statistics() {
        $stats = array(
            'df' => $this->document_frequencies,
            'total_docs' => $this->total_documents,
            'avg_length' => $this->average_doc_length
        );
        set_transient($this->stats_cache_key, $stats, DAY_IN_SECONDS * 7);
    }
    
    /**
     * Tokenize text into terms
     * 
     * @param string $text Input text
     * @return array Array of terms
     */
    private function tokenize($text) {
        if (empty($text)) {
            return array();
        }
        
        // Convert to lowercase
        $text = strtolower($text);
        
        // Remove special characters, keep alphanumeric and spaces
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        
        // Split into words
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        // Remove stop words (common words that don't add meaning)
        $stop_words = array(
            'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from',
            'has', 'he', 'in', 'is', 'it', 'its', 'of', 'on', 'that', 'the',
            'to', 'was', 'will', 'with', 'the', 'this', 'but', 'they', 'have',
            'had', 'what', 'said', 'each', 'which', 'their', 'time', 'if',
            'up', 'out', 'many', 'then', 'them', 'these', 'so', 'some', 'her',
            'would', 'make', 'like', 'into', 'him', 'has', 'two', 'more', 'very',
            'after', 'words', 'long', 'than', 'first', 'been', 'call', 'who',
            'oil', 'sit', 'now', 'find', 'down', 'day', 'did', 'get', 'come',
            'made', 'may', 'part', 'boat', 'boats', 'yacht', 'yachts'
        );
        
        $terms = array();
        foreach ($words as $word) {
            $word = trim($word);
            // Skip stop words and very short words
            if (strlen($word) > 2 && !in_array($word, $stop_words)) {
                $terms[] = $word;
            }
        }
        
        return $terms;
    }
    
    /**
     * Build vocabulary from a collection of documents
     * This should be called periodically to update vocabulary
     * 
     * @param array $documents Array of document texts
     * @return bool Success status
     */
    public function build_vocabulary($documents) {
        if (empty($documents) || !is_array($documents)) {
            return false;
        }
        
        $this->total_documents = count($documents);
        $this->document_frequencies = array();
        $term_counts = array(); // term -> count across all documents
        $total_length = 0;
        
        foreach ($documents as $doc_text) {
            $terms = $this->tokenize($doc_text);
            $total_length += count($terms);
            
            $unique_terms = array_unique($terms);
            foreach ($unique_terms as $term) {
                if (!isset($this->document_frequencies[$term])) {
                    $this->document_frequencies[$term] = 0;
                }
                $this->document_frequencies[$term]++;
                
                if (!isset($term_counts[$term])) {
                    $term_counts[$term] = 0;
                }
                $term_counts[$term] += array_count_values($terms)[$term];
            }
        }
        
        // Calculate average document length
        $this->average_doc_length = $this->total_documents > 0 
            ? $total_length / $this->total_documents 
            : 0;
        
        // Build vocabulary (term -> index mapping)
        // Only include terms that appear in at least 2 documents (to avoid noise)
        $this->vocabulary = array();
        $index = 0;
        foreach ($this->document_frequencies as $term => $df) {
            if ($df >= 2) { // Minimum document frequency threshold
                $this->vocabulary[$term] = $index;
                $index++;
            }
        }
        
        $this->save_vocabulary();
        $this->save_statistics();
        
        return true;
    }
    
    /**
     * Generate sparse vector for a text query
     * Returns Pinecone-compatible sparse vector format: {indices: [], values: []}
     * Routes to either embedding-based or BM25 method based on configuration
     * 
     * @param string $text Query text
     * @return array|false Sparse vector or false on error
     */
    public function generate_sparse_vector($text) {
        // Use embedding-based method if enabled (same as dense vectors)
        if ($this->use_embedding_api && $this->groq_manager) {
            return $this->generate_embedding_based_sparse_vector($text);
        }
        
        // Otherwise use BM25 method
        return $this->generate_bm25_query_sparse_vector($text);
    }
    
    /**
     * Generate sparse vector for a text query using BM25
     * Returns Pinecone-compatible sparse vector format: {indices: [], values: []}
     * 
     * @param string $text Query text
     * @return array|false Sparse vector or false on error
     */
    private function generate_bm25_query_sparse_vector($text) {
        if (empty($text) || empty($this->vocabulary)) {
            return false;
        }
        
        $terms = $this->tokenize($text);
        if (empty($terms)) {
            return false;
        }
        
        // Calculate term frequencies in query
        $term_frequencies = array_count_values($terms);
        
        // Calculate BM25 scores for each term
        $scores = array();
        $query_length = count($terms);
        
        foreach ($term_frequencies as $term => $tf) {
            // Skip if term not in vocabulary
            if (!isset($this->vocabulary[$term])) {
                continue;
            }
            
            $term_index = $this->vocabulary[$term];
            $df = isset($this->document_frequencies[$term]) 
                ? $this->document_frequencies[$term] 
                : 1;
            
            // BM25 formula for query
            // IDF component
            $idf = log(($this->total_documents - $df + 0.5) / ($df + 0.5) + 1);
            
            // Term frequency component (for query, we use normalized frequency)
            $normalized_tf = $tf / $query_length;
            
            // BM25 score
            $score = $idf * (($this->k1 + 1) * $normalized_tf) / ($this->k1 * (1 - $this->b + $this->b * ($query_length / $this->average_doc_length)) + $normalized_tf);
            
            if ($score > 0) {
                $scores[$term_index] = $score;
            }
        }
        
        if (empty($scores)) {
            return false;
        }
        
        // Normalize scores (L2 normalization for sparse vectors)
        $norm = 0;
        foreach ($scores as $score) {
            $norm += $score * $score;
        }
        $norm = sqrt($norm);
        
        if ($norm > 0) {
            foreach ($scores as $index => $score) {
                $scores[$index] = $score / $norm;
            }
        }
        
        // Sort by index and return in Pinecone format
        ksort($scores);
        
        return array(
            'indices' => array_keys($scores),
            'values' => array_values($scores)
        );
    }
    
    /**
     * Generate sparse vector for a document (for indexing)
     * Routes to either embedding-based or BM25 method based on configuration
     * 
     * @param string $text Document text
     * @param int $doc_length Document length in terms
     * @return array|false Sparse vector or false on error
     */
    public function generate_document_sparse_vector($text, $doc_length = null) {
        // Use embedding-based method if enabled
        if ($this->use_embedding_api && $this->groq_manager) {
            return $this->generate_embedding_based_sparse_vector($text);
        }
        
        // Otherwise use BM25 method
        return $this->generate_bm25_sparse_vector($text, $doc_length);
    }
    
    /**
     * Generate sparse vector using embedding API
     * Uses embeddings to identify important terms and create sparse representation
     * 
     * @param string $text Document text
     * @return array|false Sparse vector or false on error
     */
    private function generate_embedding_based_sparse_vector($text) {
        if (empty($text) || empty($this->vocabulary)) {
            return false;
        }
        
        // Generate embedding for the document using sparse-specific model if configured
        $embedding = $this->generate_sparse_embedding($text);
        if ($embedding === false || !is_array($embedding)) {
            error_log('[Boat Chatbot Sparse Vector] Failed to generate embedding for sparse vector');
            return false;
        }
        
        // Tokenize text to get terms
        $terms = $this->tokenize($text);
        if (empty($terms)) {
            return false;
        }
        
        // Get term frequencies
        $term_frequencies = array_count_values($terms);
        
        // Calculate importance scores using embedding similarity
        $scores = array();
        
        foreach ($term_frequencies as $term => $tf) {
            if (!isset($this->vocabulary[$term])) {
                continue;
            }
            
            $term_index = $this->vocabulary[$term];
            
            // Generate embedding for the term using sparse-specific model if configured
            $term_embedding = $this->generate_sparse_embedding($term);
            if ($term_embedding !== false && is_array($term_embedding)) {
                // Calculate cosine similarity between document and term embeddings
                $similarity = $this->cosine_similarity($embedding, $term_embedding);
                
                // Combine term frequency with embedding similarity
                // Higher similarity = more important term
                $score = $tf * (1 + $similarity); // Weight by both TF and semantic similarity
                
                // Apply IDF weighting
                $df = isset($this->document_frequencies[$term]) 
                    ? $this->document_frequencies[$term] 
                    : 1;
                $idf = log(($this->total_documents - $df + 0.5) / ($df + 0.5) + 1);
                
                $final_score = $score * $idf;
                
                if ($final_score > 0) {
                    $scores[$term_index] = $final_score;
                }
            } else {
                // Fallback: use term frequency with IDF if embedding fails
                $df = isset($this->document_frequencies[$term]) 
                    ? $this->document_frequencies[$term] 
                    : 1;
                $idf = log(($this->total_documents - $df + 0.5) / ($df + 0.5) + 1);
                $scores[$term_index] = $tf * $idf;
            }
        }
        
        if (empty($scores)) {
            return false;
        }
        
        // Apply sparsity: keep only top N% of values
        $this->apply_sparsity($scores);
        
        // Normalize scores
        $norm = 0;
        foreach ($scores as $score) {
            $norm += $score * $score;
        }
        $norm = sqrt($norm);
        
        if ($norm > 0) {
            foreach ($scores as $index => $score) {
                $scores[$index] = $score / $norm;
            }
        }
        
        ksort($scores);
        
        return array(
            'indices' => array_keys($scores),
            'values' => array_values($scores)
        );
    }
    
    /**
     * Calculate cosine similarity between two vectors
     * 
     * @param array $vec1 First vector
     * @param array $vec2 Second vector
     * @return float Cosine similarity (-1 to 1)
     */
    private function cosine_similarity($vec1, $vec2) {
        if (count($vec1) !== count($vec2)) {
            return 0;
        }
        
        $dot_product = 0;
        $norm1 = 0;
        $norm2 = 0;
        
        for ($i = 0; $i < count($vec1); $i++) {
            $dot_product += $vec1[$i] * $vec2[$i];
            $norm1 += $vec1[$i] * $vec1[$i];
            $norm2 += $vec2[$i] * $vec2[$i];
        }
        
        if ($norm1 == 0 || $norm2 == 0) {
            return 0;
        }
        
        return $dot_product / (sqrt($norm1) * sqrt($norm2));
    }
    
    /**
     * Apply sparsity threshold - keep only top N% of values
     * 
     * @param array $scores Scores array (modified in place)
     */
    private function apply_sparsity(&$scores) {
        if (empty($scores)) {
            return;
        }
        
        // Sort scores in descending order
        arsort($scores);
        
        // Calculate how many to keep
        $total = count($scores);
        $keep_count = max(1, intval($total * $this->sparsity_threshold));
        
        // Keep only top N values
        $scores = array_slice($scores, 0, $keep_count, true);
    }
    
    /**
     * Generate sparse vector using BM25 algorithm (original method)
     * 
     * @param string $text Document text
     * @param int $doc_length Document length in terms
     * @return array|false Sparse vector or false on error
     */
    private function generate_bm25_sparse_vector($text, $doc_length = null) {
        if (empty($text) || empty($this->vocabulary)) {
            return false;
        }
        
        $terms = $this->tokenize($text);
        if (empty($terms)) {
            return false;
        }
        
        if ($doc_length === null) {
            $doc_length = count($terms);
        }
        
        // Calculate term frequencies
        $term_frequencies = array_count_values($terms);
        
        // Calculate BM25 scores
        $scores = array();
        
        foreach ($term_frequencies as $term => $tf) {
            if (!isset($this->vocabulary[$term])) {
                continue;
            }
            
            $term_index = $this->vocabulary[$term];
            $df = isset($this->document_frequencies[$term]) 
                ? $this->document_frequencies[$term] 
                : 1;
            
            // BM25 formula for document
            $idf = log(($this->total_documents - $df + 0.5) / ($df + 0.5) + 1);
            $score = $idf * (($this->k1 + 1) * $tf) / ($this->k1 * (1 - $this->b + $this->b * ($doc_length / $this->average_doc_length)) + $tf);
            
            if ($score > 0) {
                $scores[$term_index] = $score;
            }
        }
        
        if (empty($scores)) {
            return false;
        }
        
        // Normalize scores
        $norm = 0;
        foreach ($scores as $score) {
            $norm += $score * $score;
        }
        $norm = sqrt($norm);
        
        if ($norm > 0) {
            foreach ($scores as $index => $score) {
                $scores[$index] = $score / $norm;
            }
        }
        
        ksort($scores);
        
        return array(
            'indices' => array_keys($scores),
            'values' => array_values($scores)
        );
    }
    
    /**
     * Get vocabulary size
     * 
     * @return int Vocabulary size
     */
    public function get_vocabulary_size() {
        return count($this->vocabulary);
    }
    
    /**
     * Check if vocabulary is built
     * 
     * @return bool True if vocabulary exists
     */
    public function has_vocabulary() {
        return !empty($this->vocabulary);
    }
    
    /**
     * Generate embedding using sparse-specific model if configured, otherwise use default
     * 
     * @param string $text Text to embed
     * @return array|false Embedding vector or false on error
     */
    private function generate_sparse_embedding($text) {
        if (!$this->groq_manager) {
            return false;
        }
        
        // If sparse-specific model or API URL is configured, use custom embedding
        if (!empty($this->sparse_embedding_model) || !empty($this->sparse_embedding_api_url)) {
            return $this->generate_custom_embedding($text);
        }
        
        // Otherwise use default Groq manager (uses dense vector model)
        return $this->groq_manager->generate_embedding($text);
    }
    
    /**
     * Generate embedding using custom model/API for sparse vectors
     * 
     * @param string $text Text to embed
     * @return array|false Embedding vector or false on error
     */
    private function generate_custom_embedding($text) {
        if (empty($text)) {
            return false;
        }
        
        // Get API key from Groq manager or options
        $api_key = get_option('boat_chatbot_groq_api_key');
        if (empty($api_key)) {
            // Fallback: try to use the Groq manager's default method
            if ($this->groq_manager) {
                return $this->groq_manager->generate_embedding($text);
            }
            return false;
        }
        
        // Use sparse-specific API URL if configured, otherwise use default
        $api_url = !empty($this->sparse_embedding_api_url) 
            ? $this->sparse_embedding_api_url 
            : get_option('boat_chatbot_groq_embeddings_url', 'https://api.groq.com/openai/v1/embeddings');
        
        // Use sparse-specific model if configured, otherwise use default
        $model = !empty($this->sparse_embedding_model) 
            ? $this->sparse_embedding_model 
            : get_option('boat_chatbot_groq_embedding_model', 'nomic-embed-text-v1.5');
        
        // Truncate text if too long
        $text_normalized = trim($text);
        $max_length = 8192;
        if (strlen($text_normalized) > $max_length) {
            $text_normalized = substr($text_normalized, 0, $max_length);
        }
        
        $request_body = array(
            'model' => $model,
            'input' => $text_normalized
        );
        
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body' => json_encode($request_body),
            'timeout' => 30,
            'blocking' => true
        ));
        
        if (is_wp_error($response)) {
            error_log('[Boat Chatbot Sparse Vector] Embedding API error: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            error_log('[Boat Chatbot Sparse Vector] Embedding API HTTP error ' . $response_code . ': ' . substr($body, 0, 200));
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $decoded_body = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('[Boat Chatbot Sparse Vector] Invalid JSON response from embedding API');
            return false;
        }
        
        if (isset($decoded_body['error'])) {
            $error_message = isset($decoded_body['error']['message']) ? $decoded_body['error']['message'] : 'Unknown API error';
            error_log('[Boat Chatbot Sparse Vector] Embedding API error: ' . $error_message);
            return false;
        }
        
        // Extract embedding from response
        if (isset($decoded_body['data'][0]['embedding'])) {
            return $decoded_body['data'][0]['embedding'];
        }
        
        error_log('[Boat Chatbot Sparse Vector] No embedding found in API response');
        return false;
    }
    
    /**
     * Clear vocabulary and statistics (for rebuilding)
     */
    public function clear_vocabulary() {
        $this->vocabulary = array();
        $this->document_frequencies = array();
        $this->total_documents = 0;
        $this->average_doc_length = 0;
        
        delete_transient($this->vocabulary_cache_key);
        delete_transient($this->stats_cache_key);
    }
}
?>


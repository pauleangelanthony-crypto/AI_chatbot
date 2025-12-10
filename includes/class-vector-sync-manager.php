<?php

/**
 * Vector Sync Manager
 * Handles synchronization between SQL database and Pinecone vector database
 */
class Boat_Chatbot_Vector_Sync_Manager {
    
    private static $instance = null;
    private $db_manager;
    private $groq_manager;
    private $pinecone_manager;
    private $sparse_generator;
    private $column_types_cache = null; // Cache for column types
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->db_manager = new Boat_Chatbot_Database_Manager();
        $this->groq_manager = Boat_Chatbot_Groq_Embeddings_Manager::get_instance();
        $this->pinecone_manager = Boat_Chatbot_Pinecone_Manager::get_instance();
        
        // Load sparse vector generator if class exists
        if (class_exists('Boat_Chatbot_Sparse_Vector_Generator')) {
            $this->sparse_generator = Boat_Chatbot_Sparse_Vector_Generator::get_instance();
        }
    }
    
    /**
     * Log error message to WordPress error log
     * 
     * @param string $message Error message
     * @param array $context Additional context data
     */
    private function log_error($message, $context = array()) {
        $log_message = '[Boat Chatbot Vector Sync] ' . $message;
        
        if (!empty($context)) {
            $log_message .= ' | Context: ' . json_encode($context);
        }
    }
    
    /**
     * Validate that all necessary API keys and configurations are present
     * 
     * @return array Validation result with 'valid' boolean and 'missing' array
     */
    public function validate_required_keys() {
        $missing = array();
        $valid = true;
        
        // Check Groq API key
        $groq_api_key = get_option('boat_chatbot_groq_api_key');
        if (empty($groq_api_key)) {
            $missing[] = 'Groq API key';
            $valid = false;
        }
        
        // Check Pinecone API key
        $pinecone_api_key = get_option('boat_chatbot_pinecone_api_key');
        if (empty($pinecone_api_key)) {
            $missing[] = 'Pinecone API key';
            $valid = false;
        }
        
        // Check Pinecone index name
        $current_env = get_option('boat_chatbot_pinecone_current_env', 'prod');
        $index_name = ($current_env === 'staging') 
            ? get_option('boat_chatbot_pinecone_staging_index', 'boat-chatbot-staging')
            : get_option('boat_chatbot_pinecone_prod_index', 'boat-chatbot-prod');
        if (empty($index_name)) {
            $missing[] = 'Pinecone index name';
            $valid = false;
        }
        
        // Check database credentials
        $db_name = get_option('boat_chatbot_db_name');
        $db_user = get_option('boat_chatbot_db_user');
        if (empty($db_name) || empty($db_user)) {
            $missing[] = 'Database credentials';
            $valid = false;
        }
        
        return array(
            'valid' => $valid,
            'missing' => $missing,
            'message' => $valid 
                ? 'All required keys are configured' 
                : 'Missing required configuration: ' . implode(', ', $missing)
        );
    }
    
    /**
     * Sync a single record to Pinecone
     * 
     * @param int $record_id Database record ID
     * @return bool Success status
     */
    public function sync_record($record_id) {
        $this->log_error('Starting sync for record', array('record_id' => $record_id));
        
        // Get record from database
        $record = $this->get_record_by_id($record_id);
        
        if (!$record) {
            $this->log_error('Record not found in database', array('record_id' => $record_id));
            return false;
        }
        
        // Build text representation for embedding
        $text = $this->groq_manager->build_text_from_record($record);
        
        if (empty($text)) {
            $this->log_error('Empty text content generated from record', array('record_id' => $record_id));
            return false;
        }
        
        // Generate embedding
        $embedding = $this->groq_manager->generate_embedding($text);
        
        if ($embedding === false) {
            $groq_error = $this->groq_manager->get_last_error();
            $this->log_error('Failed to generate embedding', array(
                'record_id' => $record_id,
                'groq_error' => $groq_error,
                'text_length' => strlen($text)
            ));
            return false;
        }
        
        // Validate embedding dimensions - try to get actual Pinecone dimension first
        $pinecone_dimension = $this->pinecone_manager->get_index_dimension();
        $expected_dimensions = $pinecone_dimension !== false 
            ? $pinecone_dimension 
            : absint(get_option('boat_chatbot_groq_embedding_dimensions', 1024));
        $actual_dimensions = count($embedding);
        
        if ($actual_dimensions !== $expected_dimensions) {
            $this->log_error('Embedding dimension mismatch', array(
                'record_id' => $record_id,
                'expected_dimensions' => $expected_dimensions,
                'actual_dimensions' => $actual_dimensions,
                'pinecone_dimension' => $pinecone_dimension !== false ? $pinecone_dimension : 'unknown',
                'configured_dimension' => absint(get_option('boat_chatbot_groq_embedding_dimensions', 1024))
            ));
            return false;
        }
        
        // Prepare metadata (store original record data)
        $metadata = $this->prepare_metadata($record);
        
        // Generate sparse vector from SQL database record text
        $sparse_vector = null;
        if ($this->sparse_generator) {
            if (!$this->sparse_generator->has_vocabulary()) {
                // Vocabulary not available - sparse vectors require vocabulary to be built first
                error_log('[Boat Chatbot Vector Sync] Vocabulary not available for sparse vector generation. Record ID: ' . $record_id . '. Please build vocabulary first.');
            } else {
                // Generate sparse vector from the text extracted from SQL database
                $sparse_vector = $this->sparse_generator->generate_document_sparse_vector($text);
                if ($sparse_vector === false) {
                    error_log('[Boat Chatbot Vector Sync] Failed to generate sparse vector for record ID: ' . $record_id);
                }
            }
        }
        
        // Upsert to Pinecone
        $vector = array(
            'id' => (string)$record_id,
            'values' => $embedding,
            'metadata' => $metadata
        );
        
        // Add sparse vector if generated
        if ($sparse_vector !== null && is_array($sparse_vector)) {
            $vector['sparseValues'] = $sparse_vector;
        }
        
        $success = $this->pinecone_manager->upsert_vectors(array($vector));
        
        if ($success) {
            // Update sync status in database
            $this->update_sync_status($record_id, 'synced');
            $this->log_error('Successfully synced record', array('record_id' => $record_id));
            return true;
        } else {
            $pinecone_error = $this->pinecone_manager->get_last_error();
            $this->log_error('Failed to upsert vector to Pinecone', array(
                'record_id' => $record_id,
                'pinecone_error' => $pinecone_error,
                'embedding_dimensions' => $actual_dimensions,
                'metadata_fields_count' => count($metadata)
            ));
            $this->update_sync_status($record_id, 'failed');
            return false;
        }
    }
    
    /**
     * Sync multiple records in batch
     * 
     * @param array $record_ids Array of record IDs
     * @return array Results with success/failure counts
     */
    public function sync_records_batch($record_ids) {
        // Log the record IDs being processed for debugging
        error_log('[Boat Chatbot Vector Sync] Starting batch sync | Total records: ' . count($record_ids) . ' | First 20 IDs: ' . json_encode(array_slice($record_ids, 0, 20)));
        
        // Validate that record_ids is an array and contains valid integers
        if (!is_array($record_ids)) {
            error_log('[Boat Chatbot Vector Sync] ERROR: record_ids is not an array. Type: ' . gettype($record_ids) . ' | Value: ' . json_encode($record_ids));
            return array(
                'success' => 0,
                'failed' => 0,
                'total' => 0,
                'error' => true,
                'message' => 'Invalid record_ids parameter: must be an array',
                'error_details' => array('Invalid record_ids parameter: must be an array')
            );
        }
        
        // Filter out any invalid IDs
        $valid_ids = array();
        foreach ($record_ids as $id) {
            if (is_numeric($id) && intval($id) > 0) {
                $valid_ids[] = intval($id);
            } else {
                error_log('[Boat Chatbot Vector Sync] WARNING: Invalid record ID found: ' . json_encode($id) . ' (type: ' . gettype($id) . ')');
            }
        }
        
        if (count($valid_ids) !== count($record_ids)) {
            error_log('[Boat Chatbot Vector Sync] WARNING: Filtered out ' . (count($record_ids) - count($valid_ids)) . ' invalid IDs. Original count: ' . count($record_ids) . ', Valid count: ' . count($valid_ids));
        }
        
        $record_ids = $valid_ids;
        
        if (empty($record_ids)) {
            error_log('[Boat Chatbot Vector Sync] ERROR: No valid record IDs to sync');
            return array(
                'success' => 0,
                'failed' => 0,
                'total' => 0,
                'error' => true,
                'message' => 'No valid record IDs provided',
                'error_details' => array('No valid record IDs provided')
            );
        }
        
        $this->log_error('Starting batch sync', array(
            'total_records' => count($record_ids),
            'record_ids' => array_slice($record_ids, 0, 10) // Log first 10 IDs
        ));
        
        // Validate required keys first
        $validation = $this->validate_required_keys();
        if (!$validation['valid']) {
            $this->log_error('Configuration validation failed', array(
                'missing_keys' => $validation['missing'],
                'message' => $validation['message']
            ));
            return array(
                'success' => 0,
                'failed' => count($record_ids),
                'total' => count($record_ids),
                'error' => true,
                'message' => $validation['message']
            );
        }
        
        $results = array(
            'success' => 0,
            'failed' => 0,
            'total' => count($record_ids),
            'error_details' => array(),
            'error_summary' => array()
        );
        
        // Process in batches to avoid memory issues and Pinecone request size limits
        // Reduced batch size to avoid HTTP 400 errors from large metadata
        $batch_size = 10; // Reduced from 50 to handle large metadata better
        $batches = array_chunk($record_ids, $batch_size);
        error_log('[Boat Chatbot Vector Sync] Processing ' . count($record_ids) . ' records in ' . count($batches) . ' batches (batch size: ' . $batch_size . ')');
        
        $batch_number = 0;
        foreach ($batches as $batch) {
            $batch_number++;
            error_log('[Boat Chatbot Vector Sync] Processing batch ' . $batch_number . '/' . count($batches) . ' | Batch size: ' . count($batch) . ' | Record IDs: ' . json_encode($batch));
            $vectors = array();
            
            foreach ($batch as $record_id) {
                $record = $this->get_record_by_id($record_id);
                
                if (!$record) {
                    $results['failed']++;
                    $error_reason = "Record {$record_id} not found in database";
                    $results['error_details'][] = $error_reason;
                    error_log('[Boat Chatbot Vector Sync] FAILED SYNC | Record ID: ' . $record_id . ' | Reason: Record not found in database');
                    $this->log_error('Record not found in database', array('record_id' => $record_id));
                    if (!isset($results['error_summary']['record_not_found'])) {
                        $results['error_summary']['record_not_found'] = 0;
                    }
                    $results['error_summary']['record_not_found']++;
                    continue;
                }
                
                $text = $this->groq_manager->build_text_from_record($record);
                
                if (empty($text)) {
                    $results['failed']++;
                    $error_reason = "Record {$record_id}: Empty text content (no data to embed)";
                    $results['error_details'][] = $error_reason;
                    error_log('[Boat Chatbot Vector Sync] FAILED SYNC | Record ID: ' . $record_id . ' | Reason: Empty text content (no data to embed)');
                    $this->log_error('Empty text content generated from record', array('record_id' => $record_id));
                    if (!isset($results['error_summary']['empty_text'])) {
                        $results['error_summary']['empty_text'] = 0;
                    }
                    $results['error_summary']['empty_text']++;
                    continue;
                }
                
                $embedding = $this->groq_manager->generate_embedding($text);
                
                if ($embedding === false) {
                    $results['failed']++;
                    $groq_error = $this->groq_manager->get_last_error();
                    $error_reason = "Record {$record_id}: Failed to generate embedding (check Groq API key and connection)";
                    if ($groq_error) {
                        $error_reason .= " - " . $groq_error;
                    }
                    $results['error_details'][] = $error_reason;
                    error_log('[Boat Chatbot Vector Sync] FAILED SYNC | Record ID: ' . $record_id . ' | Reason: Failed to generate embedding | Groq Error: ' . ($groq_error ? $groq_error : 'Unknown error') . ' | Text Length: ' . strlen($text));
                    $this->log_error('Failed to generate embedding', array(
                        'record_id' => $record_id,
                        'groq_error' => $groq_error,
                        'text_length' => strlen($text)
                    ));
                    if (!isset($results['error_summary']['embedding_failed'])) {
                        $results['error_summary']['embedding_failed'] = 0;
                    }
                    $results['error_summary']['embedding_failed']++;
                    continue;
                }
                
                // Validate embedding dimensions - try to get actual Pinecone dimension first
                $pinecone_dimension = $this->pinecone_manager->get_index_dimension();
                $expected_dimensions = $pinecone_dimension !== false 
                    ? $pinecone_dimension 
                    : absint(get_option('boat_chatbot_groq_embedding_dimensions', 1024));
                $actual_dimensions = count($embedding);
                
                if ($actual_dimensions !== $expected_dimensions) {
                    $results['failed']++;
                    $error_reason = "Record {$record_id}: Embedding dimension mismatch (expected {$expected_dimensions}, got {$actual_dimensions}). ";
                    if ($pinecone_dimension !== false) {
                        $error_reason .= "Pinecone index requires {$pinecone_dimension} dimensions. ";
                    }
                    $error_reason .= "Please check your Groq embedding model configuration and update the embedding dimensions setting to match.";
                    $results['error_details'][] = $error_reason;
                    error_log('[Boat Chatbot Vector Sync] FAILED SYNC | Record ID: ' . $record_id . ' | Reason: Embedding dimension mismatch | Expected: ' . $expected_dimensions . ' | Got: ' . $actual_dimensions . ' | Pinecone Dimension: ' . ($pinecone_dimension !== false ? $pinecone_dimension : 'unknown'));
                    $this->log_error('Embedding dimension mismatch', array(
                        'record_id' => $record_id,
                        'expected_dimensions' => $expected_dimensions,
                        'actual_dimensions' => $actual_dimensions,
                        'pinecone_dimension' => $pinecone_dimension !== false ? $pinecone_dimension : 'unknown',
                        'configured_dimension' => absint(get_option('boat_chatbot_groq_embedding_dimensions', 1024))
                    ));
                    if (!isset($results['error_summary']['dimension_mismatch'])) {
                        $results['error_summary']['dimension_mismatch'] = 0;
                    }
                    $results['error_summary']['dimension_mismatch']++;
                    continue;
                }
                
                $metadata = $this->prepare_metadata($record);
                
                // Generate sparse vector from SQL database record text
                $sparse_vector = null;
                if ($this->sparse_generator) {
                    if (!$this->sparse_generator->has_vocabulary()) {
                        // Vocabulary not available - will skip sparse vector for this batch
                        // Should be built before batch sync
                    } else {
                        // Generate sparse vector from the text extracted from SQL database
                        $sparse_vector = $this->sparse_generator->generate_document_sparse_vector($text);
                        if ($sparse_vector === false) {
                            // Log but don't fail - sparse vector is optional
                            error_log('[Boat Chatbot Vector Sync] Failed to generate sparse vector for record ID: ' . $record_id);
                        }
                    }
                }
                
                $vector_data = array(
                    'id' => (string)$record_id,
                    'values' => $embedding,
                    'metadata' => $metadata
                );
                
                // Add sparse vector if generated
                if ($sparse_vector !== null && is_array($sparse_vector)) {
                    $vector_data['sparseValues'] = $sparse_vector;
                }
                
                $vectors[] = $vector_data;
            }
            
            // Upsert batch
            if (!empty($vectors)) {
                error_log('[Boat Chatbot Vector Sync] Upserting batch ' . $batch_number . ' to Pinecone | Vectors count: ' . count($vectors) . ' | Record IDs in batch: ' . json_encode($batch));
                $this->log_error('Upserting batch to Pinecone', array(
                    'batch_size' => count($vectors),
                    'record_ids' => array_slice($batch, 0, 10)
                ));
                
                $success = $this->pinecone_manager->upsert_vectors($vectors);
                
                // Immediately capture the error after upsert call
                $immediate_error = $this->pinecone_manager->get_last_error();
                error_log('[Boat Chatbot Vector Sync] After upsert_vectors call | Success: ' . ($success ? 'true' : 'false') . ' | Immediate error: ' . ($immediate_error ? $immediate_error : 'null/empty'));
                
                if ($success) {
                    $results['success'] += count($vectors);
                    error_log('[Boat Chatbot Vector Sync] SUCCESS: Batch ' . $batch_number . ' upserted to Pinecone | Successfully synced ' . count($vectors) . ' records | Record IDs: ' . json_encode($batch));
                    $this->log_error('Successfully upserted batch to Pinecone', array(
                        'batch_size' => count($vectors),
                        'record_ids' => array_slice($batch, 0, 10)
                    ));
                    // Update sync status for all records in batch
                    foreach ($batch as $record_id) {
                        $this->update_sync_status($record_id, 'synced');
                    }
                } else {
                    $results['failed'] += count($vectors);
                    
                    // Get more detailed error message from Pinecone manager
                    $base_error_reason = "Failed to upsert to Pinecone";
                    
                    // Try to get actual Pinecone dimension
                    $pinecone_dimension = $this->pinecone_manager->get_index_dimension();
                    $expected_dimensions = $pinecone_dimension !== false 
                        ? $pinecone_dimension 
                        : absint(get_option('boat_chatbot_groq_embedding_dimensions', 1024));
                    
                    // Get specific error from Pinecone manager
                    $pinecone_error = $this->pinecone_manager->get_last_error();
                    
                    // Build detailed error reason
                    $detailed_error = $base_error_reason;
                    if ($pinecone_error && !empty(trim($pinecone_error))) {
                        $detailed_error .= " - " . $pinecone_error;
                    } else {
                        $detailed_error .= " (check Pinecone API key, index name, connection, and ensure embeddings have {$expected_dimensions} dimensions)";
                    }
                    
                    // Log with actual error message
                    $error_message_for_log = ($pinecone_error && !empty(trim($pinecone_error))) ? $pinecone_error : 'No error message available from Pinecone';
                    error_log('[Boat Chatbot Vector Sync] FAILED: Batch ' . $batch_number . ' failed to upsert to Pinecone | Vectors count: ' . count($vectors) . ' | Record IDs: ' . json_encode($batch) . ' | Pinecone Error: ' . $error_message_for_log . ' | Expected Dimensions: ' . $expected_dimensions);
                    $this->log_error('Failed to upsert batch to Pinecone', array(
                        'batch_size' => count($vectors),
                        'record_ids' => array_slice($batch, 0, 10),
                        'pinecone_error' => $pinecone_error,
                        'expected_dimensions' => $expected_dimensions
                    ));
                    
                    // Log each individual record in the failed batch with its ID and error
                    $error_message_for_log = ($pinecone_error && !empty(trim($pinecone_error))) ? $pinecone_error : 'No error message available from Pinecone';
                    foreach ($batch as $record_id) {
                        $record_error = "Record {$record_id}: {$detailed_error}";
                        $results['error_details'][] = $record_error;
                        error_log('[Boat Chatbot Vector Sync] FAILED SYNC | Record ID: ' . $record_id . ' | Reason: ' . $detailed_error . ' | Pinecone Error: ' . $error_message_for_log);
                        $this->update_sync_status($record_id, 'failed');
                    }
                    
                    if (!isset($results['error_summary']['pinecone_upsert_failed'])) {
                        $results['error_summary']['pinecone_upsert_failed'] = 0;
                    }
                    $results['error_summary']['pinecone_upsert_failed'] += count($vectors);
                }
            } else {
                error_log('[Boat Chatbot Vector Sync] WARNING: Batch ' . $batch_number . ' has no vectors to upsert | Original batch size: ' . count($batch) . ' | Record IDs: ' . json_encode($batch));
            }
            
            // Small delay between batches
            if (count($batches) > 1) {
                usleep(500000); // 0.5 second
            }
        }
        
        $this->log_error('Batch sync completed', array(
            'total' => $results['total'],
            'success' => $results['success'],
            'failed' => $results['failed'],
            'error_summary' => $results['error_summary']
        ));
        
        // Log final summary
        error_log('[Boat Chatbot Vector Sync] Batch sync completed | Total: ' . $results['total'] . ' | Success: ' . $results['success'] . ' | Failed: ' . $results['failed'] . ' | Error Summary: ' . json_encode($results['error_summary']));
        
        return $results;
    }
    
    /**
     * Build vocabulary from SQL database records for sparse vector generation
     * This should be called before syncing records to enable sparse vectors
     * 
     * @param int $limit Optional limit for building vocabulary (default: 1000 for performance)
     * @return array Result with success status and vocabulary size
     */
    public function build_vocabulary_from_database($limit = 1000) {
        if (!$this->sparse_generator) {
            return array(
                'success' => false,
                'message' => 'Sparse vector generator not available'
            );
        }
        
        $this->log_error('Starting vocabulary build from database', array('limit' => $limit));
        
        // Get sample of records from database
        $record_ids = $this->get_all_record_ids($limit, 0);
        
        if (empty($record_ids)) {
            return array(
                'success' => false,
                'message' => 'No records found in database to build vocabulary'
            );
        }
        
        error_log('[Boat Chatbot Vector Sync] Building vocabulary from ' . count($record_ids) . ' database records');
        
        // Extract text from all records
        $documents = array();
        foreach ($record_ids as $record_id) {
            $record = $this->get_record_by_id($record_id);
            if ($record) {
                $text = $this->groq_manager->build_text_from_record($record);
                if (!empty($text)) {
                    $documents[] = $text;
                }
            }
        }
        
        if (empty($documents)) {
            return array(
                'success' => false,
                'message' => 'No valid text content found in records to build vocabulary'
            );
        }
        
        // Build vocabulary
        $success = $this->sparse_generator->build_vocabulary($documents);
        
        if ($success) {
            $vocab_size = $this->sparse_generator->get_vocabulary_size();
            error_log('[Boat Chatbot Vector Sync] Vocabulary built successfully | Size: ' . $vocab_size . ' | Documents: ' . count($documents));
            return array(
                'success' => true,
                'message' => 'Vocabulary built successfully from ' . count($documents) . ' documents. Vocabulary size: ' . $vocab_size,
                'vocabulary_size' => $vocab_size,
                'documents_processed' => count($documents)
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Failed to build vocabulary'
            );
        }
    }
    
    /**
     * Sync all records from database
     * Useful for initial sync or full re-sync
     * Automatically builds vocabulary if it doesn't exist
     * 
     * @param int $limit Optional limit for batch processing
     * @param int $offset Optional offset for batch processing
     * @param bool $auto_build_vocab Whether to automatically build vocabulary if missing (default: true)
     * @return array Results
     */
    public function sync_all_records($limit = null, $offset = 0, $auto_build_vocab = true) {
        $this->log_error('Starting sync all records', array(
            'limit' => $limit,
            'offset' => $offset,
            'auto_build_vocab' => $auto_build_vocab
        ));
        
        // Validate required keys first
        $validation = $this->validate_required_keys();
        if (!$validation['valid']) {
            $this->log_error('Configuration validation failed for sync_all_records', array(
                'missing_keys' => $validation['missing'],
                'message' => $validation['message']
            ));
            return array(
                'success' => 0,
                'failed' => 0,
                'total' => 0,
                'error' => true,
                'message' => $validation['message']
            );
        }
        
        // Build vocabulary if needed and sparse generator is available
        if ($auto_build_vocab && $this->sparse_generator && !$this->sparse_generator->has_vocabulary()) {
            error_log('[Boat Chatbot Vector Sync] Vocabulary not found, building from database...');
            $vocab_result = $this->build_vocabulary_from_database(1000); // Build from first 1000 records
            if ($vocab_result['success']) {
                error_log('[Boat Chatbot Vector Sync] Vocabulary built: ' . $vocab_result['message']);
            } else {
                error_log('[Boat Chatbot Vector Sync] Vocabulary build failed: ' . $vocab_result['message']);
            }
        }
        
        $all_record_ids = $this->get_all_record_ids($limit, $offset);
        
        if (empty($all_record_ids)) {
            error_log('[Boat Chatbot Vector Sync] ERROR: No records found to sync | Limit: ' . ($limit ? $limit : 'null') . ' | Offset: ' . $offset);
            $this->log_error('No records found to sync', array(
                'limit' => $limit,
                'offset' => $offset
            ));
            return array(
                'success' => 0,
                'failed' => 0,
                'total' => 0,
                'error' => true,
                'message' => 'No records found to sync. Please check: 1) Database connection settings, 2) Table name is correct, 3) Database contains records',
                'error_details' => array('No records found in database table')
            );
        }
        
        error_log('[Boat Chatbot Vector Sync] Found ' . count($all_record_ids) . ' records to sync | First 20 IDs: ' . json_encode(array_slice($all_record_ids, 0, 20)));
        $this->log_error('Found records to sync', array(
            'total_records' => count($all_record_ids)
        ));
        
        return $this->sync_records_batch($all_record_ids);
    }
    
    /**
     * Delete record from Pinecone
     * 
     * @param int $record_id Database record ID
     * @return bool Success status
     */
    public function delete_record($record_id) {
        $this->log_error('Starting delete for record', array('record_id' => $record_id));
        
        $success = $this->pinecone_manager->delete_vectors(array($record_id));
        
        if ($success) {
            $this->update_sync_status($record_id, 'deleted');
            $this->log_error('Successfully deleted record from Pinecone', array('record_id' => $record_id));
        } else {
            $pinecone_error = $this->pinecone_manager->get_last_error();
            $this->log_error('Failed to delete record from Pinecone', array(
                'record_id' => $record_id,
                'pinecone_error' => $pinecone_error
            ));
        }
        
        return $success;
    }
    
    /**
     * Get record by ID from database
     * 
     * @param int $record_id Record ID
     * @return object|false Record object or false
     */
    private function get_record_by_id($record_id) {
        global $wpdb;
        
        // We need to use the database manager's connection
        // For now, we'll query directly
        $db_host = get_option('boat_chatbot_db_host', 'localhost');
        $db_name = get_option('boat_chatbot_db_name');
        $db_user = get_option('boat_chatbot_db_user');
        $db_password = get_option('boat_chatbot_db_password');
        $table_name = get_option('boat_chatbot_db_table', 'api_vessels');
        
        if (!$db_name || !$db_user) {
            $this->log_error('Database credentials missing', array(
                'record_id' => $record_id,
                'has_db_name' => !empty($db_name),
                'has_db_user' => !empty($db_user)
            ));
            return false;
        }
        
        try {
            $conn = new mysqli($db_host, $db_user, $db_password, $db_name);
            
            if ($conn->connect_error) {
                $this->log_error('Database connection failed', array(
                    'record_id' => $record_id,
                    'db_host' => $db_host,
                    'db_name' => $db_name,
                    'error' => $conn->connect_error
                ));
                return false;
            }
            
            $conn->set_charset('utf8mb4');
            
            $stmt = $conn->prepare("SELECT * FROM `{$table_name}` WHERE `ID` = ?");
            if (!$stmt) {
                $this->log_error('Failed to prepare database statement', array(
                    'record_id' => $record_id,
                    'table_name' => $table_name,
                    'error' => $conn->error
                ));
                $conn->close();
                return false;
            }
            
            $stmt->bind_param('i', $record_id);
            $stmt->execute();
            
            $result = $stmt->get_result();
            $record = $result->fetch_object();
            
            if (!$record) {
                error_log('[Boat Chatbot Vector Sync] Record not found | Record ID: ' . $record_id . ' | Table: ' . $table_name . ' | Query: SELECT * FROM `' . $table_name . '` WHERE `ID` = ' . $record_id);
            }
            
            $stmt->close();
            $conn->close();
            
            return $record;
        } catch (Exception $e) {
            $this->log_error('Exception while fetching record', array(
                'record_id' => $record_id,
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString()
            ));
            return false;
        }
    }
    
    /**
     * Get all record IDs from database
     * 
     * @param int $limit Optional limit
     * @param int $offset Optional offset
     * @return array Array of record IDs
     */
    private function get_all_record_ids($limit = null, $offset = 0) {
        global $wpdb;
        
        $db_host = get_option('boat_chatbot_db_host', 'localhost');
        $db_name = get_option('boat_chatbot_db_name');
        $db_user = get_option('boat_chatbot_db_user');
        $db_password = get_option('boat_chatbot_db_password');
        $table_name = get_option('boat_chatbot_db_table', 'api_vessels');
        
        if (!$db_name || !$db_user) {
            $this->log_error('Database credentials missing for get_all_record_ids', array(
                'has_db_name' => !empty($db_name),
                'has_db_user' => !empty($db_user)
            ));
            return array();
        }
        
        try {
            $conn = new mysqli($db_host, $db_user, $db_password, $db_name);
            
            if ($conn->connect_error) {
                $this->log_error('Database connection failed in get_all_record_ids', array(
                    'db_host' => $db_host,
                    'db_name' => $db_name,
                    'error' => $conn->connect_error
                ));
                return array();
            }
            
            $conn->set_charset('utf8mb4');
            
            $sql = "SELECT `ID` FROM `{$table_name}`";
            
            if ($limit !== null) {
                $limit = intval($limit);
                $offset = intval($offset);
                $sql .= " LIMIT {$limit} OFFSET {$offset}";
            }
            
            $result = $conn->query($sql);
            $ids = array();
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $id = intval($row['ID']);
                    if ($id > 0) {
                        $ids[] = $id;
                    } else {
                        error_log('[Boat Chatbot Vector Sync] WARNING: Invalid ID retrieved from database: ' . json_encode($row['ID']) . ' (converted to: ' . $id . ')');
                    }
                }
                error_log('[Boat Chatbot Vector Sync] Retrieved ' . count($ids) . ' record IDs from database | SQL: ' . $sql . ' | First 20 IDs: ' . json_encode(array_slice($ids, 0, 20)));
            } else {
                error_log('[Boat Chatbot Vector Sync] ERROR: Failed to execute query in get_all_record_ids | SQL: ' . $sql . ' | Error: ' . $conn->error);
                $this->log_error('Failed to execute query in get_all_record_ids', array(
                    'sql' => $sql,
                    'error' => $conn->error
                ));
            }
            
            $conn->close();
            
            return $ids;
        } catch (Exception $e) {
            $this->log_error('Exception in get_all_record_ids', array(
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString()
            ));
            return array();
        }
    }
    
    /**
     * Get column types from database schema
     * Uses INFORMATION_SCHEMA to get actual column data types
     * Results are cached for performance
     * 
     * @return array Associative array of column_name => type_info
     */
    private function get_column_types() {
        // Return cached result if available
        if ($this->column_types_cache !== null) {
            return $this->column_types_cache;
        }
        
        $db_host = get_option('boat_chatbot_db_host', 'localhost');
        $db_name = get_option('boat_chatbot_db_name');
        $db_user = get_option('boat_chatbot_db_user');
        $db_password = get_option('boat_chatbot_db_password');
        $table_name = get_option('boat_chatbot_db_table', 'api_vessels');
        
        if (!$db_name || !$db_user) {
            $this->log_error('Database credentials missing for get_column_types');
            $this->column_types_cache = array();
            return array();
        }
        
        $column_types = array();
        
        try {
            $conn = new mysqli($db_host, $db_user, $db_password, $db_name);
            
            if ($conn->connect_error) {
                $this->log_error('Database connection failed in get_column_types', array(
                    'error' => $conn->connect_error
                ));
                $this->column_types_cache = array();
                return array();
            }
            
            $conn->set_charset('utf8mb4');
            
            // Query INFORMATION_SCHEMA to get column types
            $sql = "SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                $this->log_error('Failed to prepare statement for get_column_types', array(
                    'error' => $conn->error
                ));
                $conn->close();
                $this->column_types_cache = array();
                return array();
            }
            
            $stmt->bind_param('ss', $db_name, $table_name);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $column_name = $row['COLUMN_NAME'];
                $data_type = strtolower($row['DATA_TYPE']);
                $column_type = strtolower($row['COLUMN_TYPE']);
                
                // Store both base type and full type for better type detection
                $column_types[$column_name] = array(
                    'data_type' => $data_type,
                    'column_type' => $column_type,
                    'is_numeric' => in_array($data_type, array('int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'decimal', 'float', 'double', 'real')),
                    'is_integer' => in_array($data_type, array('int', 'tinyint', 'smallint', 'mediumint', 'bigint')),
                    'is_float' => in_array($data_type, array('decimal', 'float', 'double', 'real')),
                    'is_boolean' => ($data_type === 'tinyint' && (strpos($column_type, '(1)') !== false || strpos($column_type, 'bool') !== false))
                );
            }
            
            $stmt->close();
            $conn->close();
            
            // Cache the results
            $this->column_types_cache = $column_types;
            
            $this->log_error('Retrieved column types', array(
                'table' => $table_name,
                'column_count' => count($column_types)
            ));
            
        } catch (Exception $e) {
            $this->log_error('Exception in get_column_types', array(
                'error' => $e->getMessage()
            ));
            $this->column_types_cache = array();
        }
        
        return $column_types;
    }
    
    /**
     * Convert value based on SQL column type to Pinecone-supported types
     * 
     * Pinecone metadata supports only:
     * - string: Textual data (max 1000 chars per field)
     * - number: Integers and floats (64-bit float format)
     * - boolean: true/false values
     * 
     * Note: Arrays/objects are converted to JSON strings by clean_metadata() in Pinecone manager
     * 
     * @param mixed $value Original value
     * @param string $field_name Field/column name
     * @param array $column_types Column types from get_column_types()
     * @return mixed Converted value (string, int, float, or bool only)
     */
    private function convert_value_by_type($value, $field_name, $column_types) {
        // Handle null values - Pinecone doesn't support null, return null to skip
        if ($value === null) {
            return null;
        }
        
        // If we don't have type info for this field, convert to string (Pinecone-safe)
        if (!isset($column_types[$field_name])) {
            // Ensure we return a Pinecone-supported type (string)
            $str = (string)$value;
            return strlen($str) > 1000 ? substr($str, 0, 1000) : $str;
        }
        
        $type_info = $column_types[$field_name];
        
        // Handle boolean types - Pinecone supports boolean
        if ($type_info['is_boolean']) {
            if (is_bool($value)) {
                return $value; // Already boolean, Pinecone supports this
            }
            // Convert string/int to boolean
            if (is_string($value)) {
                $value_lower = strtolower(trim($value));
                return in_array($value_lower, array('1', 'true', 'yes', 'on'));
            }
            return (bool)$value; // Convert to boolean (Pinecone supports this)
        }
        
        // Handle numeric types - Pinecone supports numbers (int/float)
        if ($type_info['is_numeric']) {
            if (is_numeric($value)) {
                if ($type_info['is_integer']) {
                    return intval($value); // Return int (Pinecone supports this)
                } elseif ($type_info['is_float']) {
                    return floatval($value); // Return float (Pinecone supports this)
                }
            }
            // Try to convert string to number
            if (is_string($value) && is_numeric(trim($value))) {
                $num_value = trim($value);
                if ($type_info['is_integer']) {
                    return intval($num_value); // Return int (Pinecone supports this)
                } else {
                    return floatval($num_value); // Return float (Pinecone supports this)
                }
            }
            // If can't convert, return 0 for numeric fields (Pinecone supports numbers)
            return $type_info['is_integer'] ? 0 : 0.0;
        }
        
        // For non-numeric fields (VARCHAR, TEXT, DATE, etc.), convert to string
        // Pinecone supports strings, but we need to handle arrays/objects
        if (is_array($value) || is_object($value)) {
            // Convert arrays/objects to JSON string (Pinecone manager will handle this)
            $json = json_encode($value);
            if ($json !== false) {
                $str = $json;
            } else {
                $str = (string)$value;
            }
        } else {
            $str = (string)$value;
        }
        
        // Truncate to 1000 chars (Pinecone limit per field)
        return strlen($str) > 1000 ? substr($str, 0, 1000) : $str;
    }
    
    /**
     * Prepare metadata from record for Pinecone
     * Includes ALL columns from the record (except excluded internal fields)
     * Converts values based on actual SQL column types
     * 
     * @param object|array $record Database record
     * @return array Metadata array
     */
    private function prepare_metadata($record) {
        $metadata = array();
        
        // Fields to exclude from metadata (internal IDs, timestamps, etc.)
        // These are the same fields excluded from embeddings for consistency
        $excluded_fields = array(
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
        
        // Get column types from database schema
        $column_types = $this->get_column_types();
        
        // Helper to get field value with type conversion
        $get_field = function($field) use ($record, $column_types) {
            $value = null;
            
            if (is_object($record) && isset($record->$field)) {
                $value = $record->$field;
            } elseif (is_array($record) && isset($record[$field])) {
                $value = $record[$field];
            } else {
                return null;
            }
            
            // Convert value based on SQL column type
            return $this->convert_value_by_type($value, $field, $column_types);
        };
        
        // Get all fields from the record dynamically
        $fields = array();
        if (is_object($record)) {
            // Get all object properties
            $fields = array_keys(get_object_vars($record));
        } elseif (is_array($record)) {
            // Get all array keys
            $fields = array_keys($record);
        }
        
        // Include ALL fields in metadata (except excluded ones)
        foreach ($fields as $field) {
            // Skip excluded fields
            if (in_array($field, $excluded_fields)) {
                continue;
            }
            
            $value = $get_field($field);
            
            // Skip null values (Pinecone doesn't accept null)
            if ($value === null) {
                continue;
            }
            
            // Include field in metadata with proper type
            $metadata[$field] = $value;
        }
        
        return $metadata;
    }
    
    /**
     * Update sync status in WordPress database
     * 
     * @param int $record_id Record ID
     * @param string $status Status ('synced', 'failed', 'deleted', 'pending')
     */
    private function update_sync_status($record_id, $status) {
        global $wpdb;
        
        // Ensure table exists before querying
        if (!$this->ensure_sync_table_exists()) {
            $this->log_error('Failed to ensure sync table exists', array('record_id' => $record_id, 'status' => $status));
            return;
        }
        
        $table_name = $wpdb->prefix . 'boat_chatbot_vector_sync';
        
        // Check if record exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE record_id = %d",
            $record_id
        ));
        
        if ($exists) {
            // Update existing record
            $result = $wpdb->update(
                $table_name,
                array(
                    'status' => $status,
                    'last_synced' => current_time('mysql')
                ),
                array('record_id' => $record_id),
                array('%s', '%s'),
                array('%d')
            );
            
            if ($result === false) {
                $this->log_error('Failed to update sync status', array(
                    'record_id' => $record_id,
                    'status' => $status,
                    'wpdb_error' => $wpdb->last_error
                ));
            }
        } else {
            // Insert new record
            $result = $wpdb->insert(
                $table_name,
                array(
                    'record_id' => $record_id,
                    'status' => $status,
                    'last_synced' => current_time('mysql')
                ),
                array('%d', '%s', '%s')
            );
            
            if ($result === false) {
                $this->log_error('Failed to insert sync status', array(
                    'record_id' => $record_id,
                    'status' => $status,
                    'wpdb_error' => $wpdb->last_error
                ));
            }
        }
    }
    
    /**
     * Ensure the vector sync table exists, create it if it doesn't
     */
    private function ensure_sync_table_exists() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'boat_chatbot_vector_sync';
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));
        
        if ($table_exists === $table_name) {
            return true; // Table already exists
        }
        
        // Create the table
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
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
        
        if (defined('ABSPATH')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        } else {
            // Fallback: direct query if ABSPATH not defined (shouldn't happen in WordPress)
            $wpdb->query($sql);
        }
        
        // Verify table was created
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));
        
        return ($table_exists === $table_name);
    }
    
    /**
     * Get records that need syncing
     * 
     * @param int $limit Optional limit
     * @return array Array of record IDs that need syncing
     */
    public function get_records_needing_sync($limit = 100) {
        global $wpdb;
        
        // Ensure table exists before querying
        if (!$this->ensure_sync_table_exists()) {
            return array();
        }
        
        $table_name = $wpdb->prefix . 'boat_chatbot_vector_sync';
        
        // Get records that are pending, failed, or haven't been synced recently
        // Note: In MySQL/MariaDB, NULL values are sorted first with ASC by default
        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT record_id FROM {$table_name} 
             WHERE status IN ('pending', 'failed') 
             OR last_synced IS NULL 
             OR last_synced < DATE_SUB(NOW(), INTERVAL 1 DAY)
             ORDER BY last_synced ASC
             LIMIT %d",
            $limit
        ));
        
        return array_map('intval', $results);
    }
}
?>


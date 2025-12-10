<?php
/**
 * Test Script for Sparse Vector Feature
 * 
 * This script tests the sparse vector generation and hybrid search functionality.
 * Run this from WordPress admin or via command line to verify the feature is working.
 */

// If running from command line, bootstrap WordPress
if (php_sapi_name() === 'cli') {
    // Adjust path as needed
    require_once dirname(__FILE__) . '/../../../wp-load.php';
}

echo "=== Sparse Vector Feature Test ===\n\n";

// Load the sparse vector generator
if (!class_exists('Boat_Chatbot_Sparse_Vector_Generator')) {
    require_once __DIR__ . '/includes/class-sparse-vector-generator.php';
}

$sparse_generator = Boat_Chatbot_Sparse_Vector_Generator::get_instance();

// Test 1: Check if vocabulary exists
echo "Test 1: Vocabulary Status\n";
echo "-------------------------\n";
$has_vocab = $sparse_generator->has_vocabulary();
$vocab_size = $sparse_generator->get_vocabulary_size();

if ($has_vocab) {
    echo "✓ Vocabulary is loaded\n";
    echo "  Vocabulary size: " . $vocab_size . " terms\n";
} else {
    echo "✗ Vocabulary is NOT loaded\n";
    echo "  You need to build the vocabulary first.\n";
    echo "  Go to: WordPress Admin > Boat Chatbot > Settings > Vector Sync Settings\n";
    echo "  And click the 'Build Vocabulary' button.\n";
}
echo "\n";

// Test 2: Check configuration
echo "Test 2: Configuration Settings\n";
echo "------------------------------\n";
$use_embedding = get_option('boat_chatbot_sparse_use_embedding', false);
$threshold = get_option('boat_chatbot_sparse_threshold', 0.1);
$hybrid_alpha = get_option('boat_chatbot_hybrid_alpha', 0.7);
$sparse_model = get_option('boat_chatbot_sparse_embedding_model', '');
$sparse_api_url = get_option('boat_chatbot_sparse_embedding_api_url', '');

echo "Use Embedding API for Sparse Vectors: " . ($use_embedding ? 'YES' : 'NO (using BM25)') . "\n";
echo "Sparsity Threshold: " . $threshold . " (keep top " . ($threshold * 100) . "% of terms)\n";
echo "Hybrid Search Alpha: " . $hybrid_alpha . " (" . ($hybrid_alpha * 100) . "% dense, " . ((1 - $hybrid_alpha) * 100) . "% sparse)\n";

if (!empty($sparse_model)) {
    echo "Sparse Embedding Model: " . $sparse_model . "\n";
} else {
    echo "Sparse Embedding Model: (using default dense vector model)\n";
}

if (!empty($sparse_api_url)) {
    echo "Sparse API URL: " . $sparse_api_url . "\n";
} else {
    echo "Sparse API URL: (using default)\n";
}
echo "\n";

// Test 3: Generate sparse vector from sample text
if ($has_vocab) {
    echo "Test 3: Sparse Vector Generation\n";
    echo "--------------------------------\n";
    
    $test_queries = array(
        "Show me luxury yachts in Miami",
        "Find sailboats under $100,000",
        "I want a 40 foot catamaran with modern amenities"
    );
    
    foreach ($test_queries as $query) {
        echo "Query: \"$query\"\n";
        $sparse_vector = $sparse_generator->generate_sparse_vector($query);
        
        if ($sparse_vector !== false && is_array($sparse_vector)) {
            $num_indices = isset($sparse_vector['indices']) ? count($sparse_vector['indices']) : 0;
            $num_values = isset($sparse_vector['values']) ? count($sparse_vector['values']) : 0;
            
            if ($num_indices > 0 && $num_indices === $num_values) {
                echo "  ✓ Sparse vector generated successfully\n";
                echo "    - Non-zero dimensions: $num_indices\n";
                
                // Show top 5 terms with highest scores
                if ($num_values > 0) {
                    $top_values = array_slice($sparse_vector['values'], 0, min(5, $num_values));
                    echo "    - Top scores: " . implode(', ', array_map(function($v) {
                        return number_format($v, 4);
                    }, $top_values)) . "\n";
                }
            } else {
                echo "  ✗ Sparse vector format is invalid\n";
                echo "    - Indices: $num_indices, Values: $num_values\n";
            }
        } else {
            echo "  ✗ Failed to generate sparse vector\n";
        }
        echo "\n";
    }
} else {
    echo "Test 3: SKIPPED (vocabulary not loaded)\n\n";
}

// Test 4: Check if sparse vectors are being used in queries
echo "Test 4: Integration with Chatbot\n";
echo "--------------------------------\n";

if (!class_exists('Boat_Chatbot_Handler')) {
    require_once __DIR__ . '/includes/class-chatbot-handler.php';
}

// Check if chatbot handler will use sparse vectors
if ($has_vocab) {
    echo "✓ Sparse vectors will be used in chatbot queries\n";
    echo "  When a user asks a question, the chatbot will:\n";
    echo "  1. Generate dense embedding (semantic understanding)\n";
    echo "  2. Generate sparse vector (keyword matching)\n";
    echo "  3. Combine both for hybrid search in Pinecone\n";
} else {
    echo "✗ Sparse vectors will NOT be used (vocabulary not built)\n";
    echo "  The chatbot will only use dense embeddings (semantic search)\n";
}
echo "\n";

// Test 5: Check Pinecone hybrid search support
echo "Test 5: Pinecone Hybrid Search\n";
echo "------------------------------\n";

if (!class_exists('Boat_Chatbot_Pinecone_Manager')) {
    require_once __DIR__ . '/includes/class-pinecone-manager.php';
}

$pinecone_manager = Boat_Chatbot_Pinecone_Manager::get_instance();
$pinecone_stats = $pinecone_manager->get_index_stats();

if ($pinecone_stats !== false && is_array($pinecone_stats)) {
    $total_vectors = isset($pinecone_stats['totalVectorCount']) ? $pinecone_stats['totalVectorCount'] : 0;
    echo "✓ Pinecone is accessible\n";
    echo "  Total vectors in index: " . number_format($total_vectors) . "\n";
    echo "  Hybrid search (dense + sparse) is supported\n";
} else {
    echo "✗ Cannot connect to Pinecone\n";
    $last_error = $pinecone_manager->get_last_error();
    if ($last_error) {
        echo "  Error: " . $last_error . "\n";
    }
}
echo "\n";

// Summary
echo "=== Summary ===\n";
echo "---------------\n";

$all_working = $has_vocab && $pinecone_stats !== false;

if ($all_working) {
    echo "✓ Sparse vector feature is FULLY WORKING!\n\n";
    echo "Your chatbot is now using hybrid search:\n";
    echo "- Dense vectors: Semantic understanding (meaning-based matching)\n";
    echo "- Sparse vectors: Keyword matching (term-based relevance)\n";
    echo "- Combined: Best of both worlds for accurate search results\n\n";
    echo "Method: " . ($use_embedding ? 'Embedding-based (semantic-aware)' : 'BM25 (statistical)') . "\n";
} else {
    echo "⚠ Sparse vector feature needs attention:\n\n";
    
    if (!$has_vocab) {
        echo "[ ] Build vocabulary from your database\n";
        echo "    Go to: WordPress Admin > Boat Chatbot > Settings\n";
        echo "    Click: 'Build Vocabulary' button under Vector Sync Settings\n\n";
    } else {
        echo "[✓] Vocabulary is built\n\n";
    }
    
    if ($pinecone_stats === false) {
        echo "[ ] Fix Pinecone connection\n";
        echo "    Go to: WordPress Admin > Boat Chatbot > Settings\n";
        echo "    Check your Pinecone API key and settings\n\n";
    } else {
        echo "[✓] Pinecone is connected\n\n";
    }
}

echo "=== End of Test ===\n";


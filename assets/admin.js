jQuery(document).ready(function($) {
    // Verify buttons exist and handlers can be attached
    if ($('#test-groq-embeddings').length === 0) {
        console.warn('Test Groq Embeddings button not found');
    }
    if ($('#test-pinecone').length === 0) {
        console.warn('Test Pinecone button not found');
    }
    if ($('#sync-all-records').length === 0) {
        console.warn('Sync All Records button not found');
    }
    if ($('#sync-pending-records').length === 0) {
        console.warn('Sync Pending Records button not found');
    }
    if ($('#test-sync-100').length === 0) {
        console.warn('Test Sync 100 Records button not found');
    }
    if ($('#test-redis').length === 0) {
        console.warn('Test Redis button not found');
    }
    if ($('#test-rerank').length === 0) {
        console.warn('Test Reranking button not found');
    }
    if ($('#build-vocabulary').length === 0) {
        console.warn('Build Vocabulary button not found');
    }
    if (typeof boat_chatbot_admin === 'undefined') {
        console.error('boat_chatbot_admin object is not defined - AJAX handlers will not work');
    }
    
    // Test Database Connection
    $('#test-db-connection').on('click', function() {
        const button = $(this);
        const originalText = button.text();
        
        button.text('Testing...').prop('disabled', true);
        $('#test-results').html('<p>Testing database connection...</p>');
        
        $.ajax({
            url: boat_chatbot_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'boat_chatbot_test_db_connection',
                nonce: boat_chatbot_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#test-results').html(
                        '<div class="notice notice-success"><p>✅ ' + response.data.message + '</p></div>'
                    );
                } else {
                    $('#test-results').html(
                        '<div class="notice notice-error"><p>❌ ' + response.data.message + '</p></div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'Failed to test database connection.';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                } else if (xhr.status === 400) {
                    errorMessage = 'Bad Request - Please check your configuration.';
                } else if (xhr.status === 403) {
                    errorMessage = 'Permission denied.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error occurred.';
                }
                $('#test-results').html(
                    '<div class="notice notice-error"><p>❌ ' + errorMessage + '</p></div>'
                );
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Test API Connection
    $('#test-api-connection').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const button = $(this);
        const originalText = button.text();
        
        // Check if required objects exist
        if (typeof boat_chatbot_admin === 'undefined') {
            console.error('boat_chatbot_admin object is not defined');
            $('#test-results').html(
                '<div class="notice notice-error"><p>❌ JavaScript configuration error. Please refresh the page.</p></div>'
            );
            return;
        }
        
        button.text('Testing...').prop('disabled', true);
        $('#test-results').html('<p>Testing API connection...</p>');
        
        $.ajax({
            url: boat_chatbot_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'boat_chatbot_test_api_connection',
                nonce: boat_chatbot_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    let message = response.data.message;
                    if (response.data.response_time_formatted) {
                        message += ' <strong>(Response time: ' + response.data.response_time_formatted + ')</strong>';
                    }
                    $('#test-results').html(
                        '<div class="notice notice-success"><p>✅ ' + message + '</p></div>'
                    );
                } else {
                    let message = response.data.message;
                    if (response.data.response_time_formatted) {
                        message += ' <strong>(Response time: ' + response.data.response_time_formatted + ')</strong>';
                    }
                    $('#test-results').html(
                        '<div class="notice notice-error"><p>❌ ' + message + '</p></div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'Failed to test API connection.';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                    // Include response time if available
                    if (xhr.responseJSON.data.response_time_formatted) {
                        errorMessage += ' <strong>(Response time: ' + xhr.responseJSON.data.response_time_formatted + ')</strong>';
                    }
                } else if (xhr.status === 400) {
                    errorMessage = 'Bad Request - Please check your configuration.';
                } else if (xhr.status === 403) {
                    errorMessage = 'Permission denied.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error occurred.';
                }
                $('#test-results').html(
                    '<div class="notice notice-error"><p>❌ ' + errorMessage + '</p></div>'
                );
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Test Groq Embeddings Connection
    $('#test-groq-embeddings').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const button = $(this);
        const originalText = button.text();
        
        // Check if required objects exist
        if (typeof boat_chatbot_admin === 'undefined') {
            console.error('boat_chatbot_admin object is not defined');
            $('#test-results').html(
                '<div class="notice notice-error"><p>❌ JavaScript configuration error. Please refresh the page.</p></div>'
            );
            return;
        }
        
        button.text('Testing...').prop('disabled', true);
        $('#test-results').html('<p>Testing Groq Embeddings connection...</p>');
        
        $.ajax({
            url: boat_chatbot_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'boat_chatbot_test_groq_embeddings',
                nonce: boat_chatbot_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    let message = response.data.message;
                    if (response.data.dimensions) {
                        message += ' (Dimensions: ' + response.data.dimensions + ')';
                    }
                    $('#test-results').html(
                        '<div class="notice notice-success"><p>✅ ' + message + '</p></div>'
                    );
                } else {
                    $('#test-results').html(
                        '<div class="notice notice-error"><p>❌ ' + (response.data.message || 'Unknown error') + '</p></div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                console.error('Groq Embeddings test error:', status, error, xhr);
                let errorMessage = 'Failed to test Groq Embeddings connection.';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                } else if (xhr.status === 400) {
                    errorMessage = 'Bad Request - Please check your configuration.';
                } else if (xhr.status === 403) {
                    errorMessage = 'Permission denied.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error occurred.';
                } else if (xhr.status === 0) {
                    errorMessage = 'Request failed. Please check your connection.';
                }
                $('#test-results').html(
                    '<div class="notice notice-error"><p>❌ ' + errorMessage + '</p></div>'
                );
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Test Pinecone Connection
    $('#test-pinecone').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('Test Pinecone button clicked!'); // Debug log
        
        const button = $(this);
        const originalText = button.text();
        
        // Immediate visual feedback
        button.css('background-color', '#0073aa').css('color', '#fff');
        
        // Check if required objects exist
        if (typeof boat_chatbot_admin === 'undefined') {
            console.error('boat_chatbot_admin object is not defined');
            $('#test-results').html(
                '<div class="notice notice-error"><p>❌ JavaScript configuration error. Please refresh the page.</p></div>'
            );
            button.css('background-color', '').css('color', '');
            return;
        }
        
        console.log('Starting Pinecone test...', boat_chatbot_admin); // Debug log
        button.text('Testing...').prop('disabled', true);
        $('#test-results').html('<div class="notice notice-info"><p><strong>⏳ Testing Pinecone connection... Please wait.</strong></p></div>');
        
        $.ajax({
            url: boat_chatbot_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'boat_chatbot_test_pinecone',
                nonce: boat_chatbot_admin.nonce
            },
            success: function(response) {
                console.log('Pinecone test response received:', response);
                if (response.success) {
                    let message = response.data.message || 'Connection successful';
                    let details = '';
                    if (response.data.stats) {
                        details = '<br><br><strong>Index Stats:</strong><ul style="margin-left: 20px;">';
                        if (response.data.stats.totalVectorCount !== undefined) {
                            details += '<li>Total Vectors: ' + response.data.stats.totalVectorCount.toLocaleString() + '</li>';
                        }
                        if (response.data.stats.dimension) {
                            details += '<li>Dimension: ' + response.data.stats.dimension + '</li>';
                        }
                        if (response.data.stats.indexFullness !== undefined) {
                            details += '<li>Index Fullness: ' + (response.data.stats.indexFullness * 100).toFixed(2) + '%</li>';
                        }
                        details += '</ul>';
                    }
                    $('#test-results').html(
                        '<div class="notice notice-success"><p>✅ ' + message + details + '</p></div>'
                    );
                } else {
                    let errorMessage = response.data.message || 'Unknown error';
                    $('#test-results').html(
                        '<div class="notice notice-error"><p>❌ ' + errorMessage + '</p></div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                console.error('Pinecone test error:', status, error, xhr);
                let errorMessage = 'Failed to test Pinecone connection.';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                } else if (xhr.status === 400) {
                    errorMessage = 'Bad Request - Please check your configuration.';
                } else if (xhr.status === 403) {
                    errorMessage = 'Permission denied.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error occurred.';
                } else if (xhr.status === 0) {
                    errorMessage = 'Request failed. Please check your connection.';
                }
                $('#test-results').html(
                    '<div class="notice notice-error"><p>❌ ' + errorMessage + '</p></div>'
                );
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
                button.css('background-color', '').css('color', '');
            }
        });
    });
    
    // Test Redis Connection
    if ($('#test-redis').length > 0) {
        $('#test-redis').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('Redis button clicked'); // Debug log
            
            const button = $(this);
            const originalText = button.text();
            
            if (typeof boat_chatbot_admin === 'undefined') {
                console.error('boat_chatbot_admin object is not defined');
                $('#test-results').html(
                    '<div class="notice notice-error"><p>❌ JavaScript configuration error. Please refresh the page.</p></div>'
                );
                return;
            }
            
            button.text('Testing...').prop('disabled', true);
            $('#test-results').html('<p>Testing Redis connection...</p>');
            
            $.ajax({
                url: boat_chatbot_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'boat_chatbot_test_redis',
                    nonce: boat_chatbot_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#test-results').html(
                            '<div class="notice notice-success"><p>✅ ' + response.data.message + '</p></div>'
                        );
                    } else {
                        $('#test-results').html(
                            '<div class="notice notice-error"><p>❌ ' + response.data.message + '</p></div>'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Redis test error:', xhr, status, error); // Debug log
                    let errorMessage = 'Failed to test Redis connection.';
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMessage = xhr.responseJSON.data.message;
                    }
                    $('#test-results').html(
                        '<div class="notice notice-error"><p>❌ ' + errorMessage + '</p></div>'
                    );
                },
                complete: function() {
                    button.text(originalText).prop('disabled', false);
                }
            });
        });
        console.log('Test Redis button found and handler attached');
    } else {
        console.warn('Test Redis button NOT found on page');
    }
    
    if ($('#test-pinecone').length > 0) {
        console.log('Test Pinecone button found and handler attached');
    } else {
        console.warn('Test Pinecone button NOT found on page');
    }
    
    // Test Reranking
    $(document).on('click', '#test-rerank', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const button = $(this);
        const originalText = button.text();
        
        console.log('Test Reranking button clicked');
        
        if (typeof boat_chatbot_admin === 'undefined') {
            console.error('boat_chatbot_admin object is not defined');
            $('#test-results').html(
                '<div class="notice notice-error"><p>❌ JavaScript configuration error. Please refresh the page.</p></div>'
            );
            return;
        }
        
        button.text('Testing...').prop('disabled', true);
        $('#test-results').html('<p>Testing reranking API connection...</p>');
        
        console.log('Sending AJAX request for test_rerank', {
            action: 'boat_chatbot_test_rerank',
            ajax_url: boat_chatbot_admin.ajax_url
        });
        
        $.ajax({
            url: boat_chatbot_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'boat_chatbot_test_rerank',
                nonce: boat_chatbot_admin.nonce
            },
            success: function(response) {
                console.log('Test rerank response:', response);
                if (response.success) {
                    $('#test-results').html(
                        '<div class="notice notice-success"><p>✅ ' + (response.data.message || 'Reranking test successful') + '</p></div>'
                    );
                } else {
                    console.error('Reranking test failed:', response.data);
                    $('#test-results').html(
                        '<div class="notice notice-error"><p>❌ ' + (response.data.message || 'Reranking test failed') + '</p></div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                console.error('Reranking test error:', status, error, xhr);
                let errorMessage = 'Failed to test reranking connection.';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                } else if (xhr.responseText) {
                    console.error('Response text:', xhr.responseText);
                } else if (status === 'timeout') {
                    errorMessage = 'Request timed out. Please try again.';
                } else if (status === 'error') {
                    errorMessage = 'Server error occurred. Check server logs for details.';
                } else if (xhr.status === 0) {
                    errorMessage = 'Request failed. Please check your connection.';
                }
                $('#test-results').html(
                    '<div class="notice notice-error"><p>❌ ' + errorMessage + '</p></div>'
                );
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            },
            timeout: 30000 // 30 second timeout
        });
    });
    
    // Test Pinecone Upsert
    $('#test-pinecone-upsert').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const button = $(this);
        const originalText = button.text();
        
        // Check if required objects exist
        if (typeof boat_chatbot_admin === 'undefined') {
            console.error('boat_chatbot_admin object is not defined');
            $('#test-results').html(
                '<div class="notice notice-error"><p>❌ JavaScript configuration error. Please refresh the page.</p></div>'
            );
            return;
        }
        
        button.text('Testing...').prop('disabled', true);
        $('#test-results').html('<p>Testing Pinecone upsert: Generating embedding and upserting test data...</p>');
        
        $.ajax({
            url: boat_chatbot_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'boat_chatbot_test_pinecone_upsert',
                nonce: boat_chatbot_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    let message = response.data.message || 'Upsert test successful';
                    let details = '';
                    if (response.data.test_id) {
                        details += '<br><strong>Test Vector ID:</strong> ' + response.data.test_id;
                    }
                    if (response.data.embedding_dimensions) {
                        details += '<br><strong>Embedding Dimensions:</strong> ' + response.data.embedding_dimensions;
                    }
                    if (response.data.verification_status) {
                        let statusText = response.data.verification_status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                        details += '<br><strong>Verification:</strong> ' + statusText;
                        if (response.data.verification_details) {
                            details += ' - ' + response.data.verification_details;
                        }
                    }
                    $('#test-results').html(
                        '<div class="notice notice-success"><p>✅ ' + message + details + '</p></div>'
                    );
                } else {
                    let errorMessage = response.data.message || 'Upsert test failed';
                    let errorDetails = '';
                    if (response.data.details) {
                        errorDetails = '<br><br>' + response.data.details;
                    }
                    if (response.data.test_id) {
                        errorDetails += '<br><strong>Test Vector ID:</strong> ' + response.data.test_id;
                    }
                    if (response.data.embedding_dimensions) {
                        errorDetails += '<br><strong>Embedding Dimensions:</strong> ' + response.data.embedding_dimensions;
                    }
                    $('#test-results').html(
                        '<div class="notice notice-error"><p>❌ ' + errorMessage + errorDetails + '</p></div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                console.error('Pinecone upsert test error:', status, error, xhr);
                let errorMessage = 'Failed to test Pinecone upsert.';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                } else if (xhr.status === 400) {
                    errorMessage = 'Bad Request - Please check your configuration.';
                } else if (xhr.status === 403) {
                    errorMessage = 'Permission denied.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error occurred. Check server logs for details.';
                } else if (xhr.status === 0) {
                    errorMessage = 'Request failed. Please check your connection.';
                }
                $('#test-results').html(
                    '<div class="notice notice-error"><p>❌ ' + errorMessage + '</p></div>'
                );
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            },
            timeout: 60000 // 1 minute timeout
        });
    });
    
    // Build Vocabulary
    $(document).on('click', '#build-vocabulary', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const button = $(this);
        const originalText = button.text();
        
        console.log('Build Vocabulary button clicked');
        
        if (typeof boat_chatbot_admin === 'undefined') {
            console.error('boat_chatbot_admin object is not defined');
            $('#sync-results').html(
                '<div class="notice notice-error"><p>❌ JavaScript configuration error. Please refresh the page.</p></div>'
            );
            return;
        }
        
        if (!confirm('This will build vocabulary from your SQL database records (first 1000 records). This enables sparse vector generation for hybrid search. Continue?')) {
            return;
        }
        
        button.text('Building...').prop('disabled', true);
        $('#sync-results').html('<p>Building vocabulary from SQL database records... This may take a few moments.</p>');
        
        console.log('Sending AJAX request for build_vocabulary', {
            action: 'boat_chatbot_build_vocabulary',
            ajax_url: boat_chatbot_admin.ajax_url
        });
        
        $.ajax({
            url: boat_chatbot_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'boat_chatbot_build_vocabulary',
                nonce: boat_chatbot_admin.nonce
            },
            success: function(response) {
                console.log('Build vocabulary response:', response);
                if (response.success) {
                    let message = response.data.message || 'Vocabulary built successfully';
                    let details = '';
                    if (response.data.vocabulary_size) {
                        details += '<br><strong>Vocabulary Size:</strong> ' + response.data.vocabulary_size + ' terms';
                    }
                    if (response.data.documents_processed) {
                        details += '<br><strong>Documents Processed:</strong> ' + response.data.documents_processed;
                    }
                    $('#sync-results').html(
                        '<div class="notice notice-success"><p>✅ ' + message + details + '</p></div>'
                    );
                } else {
                    let errorMessage = response.data.message || 'Failed to build vocabulary';
                    console.error('Build vocabulary failed:', errorMessage);
                    $('#sync-results').html(
                        '<div class="notice notice-error"><p>❌ ' + errorMessage + '</p></div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                console.error('Build vocabulary error:', status, error, xhr);
                let errorMessage = 'Failed to build vocabulary.';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                } else if (xhr.responseText) {
                    console.error('Response text:', xhr.responseText);
                }
                $('#sync-results').html(
                    '<div class="notice notice-error"><p>❌ ' + errorMessage + '</p></div>'
                );
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            },
            timeout: 120000 // 2 minute timeout
        });
    });
    
    // Sync All Records
    $('#sync-all-records').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const button = $(this);
        const originalText = button.text();
        
        // Check if required objects exist
        if (typeof boat_chatbot_admin === 'undefined') {
            console.error('boat_chatbot_admin object is not defined');
            $('#sync-results').html(
                '<div class="notice notice-error"><p>❌ JavaScript configuration error. Please refresh the page.</p></div>'
            );
            return;
        }
        
        // Confirm before syncing all records (this can take a while)
        if (!confirm('This will sync ALL records from the database to Pinecone. This may take several minutes. Continue?')) {
            return;
        }
        
        button.text('Syncing...').prop('disabled', true);
        $('#sync-results').html('<p>Starting sync of all records. This may take several minutes...</p>');
        
        $.ajax({
            url: boat_chatbot_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'boat_chatbot_sync_all_records',
                nonce: boat_chatbot_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    let message = response.data.message;
                    let details = '';
                    if (response.data.results) {
                        details = '<br><strong>Details:</strong> ' + 
                            'Total: ' + response.data.results.total + ', ' +
                            'Successful: ' + response.data.results.success + ', ' +
                            'Failed: ' + response.data.results.failed;
                    }
                    $('#sync-results').html(
                        '<div class="notice notice-success"><p>✅ ' + message + details + '</p></div>'
                    );
                } else {
                    let errorMessage = response.data.message || 'Sync failed';
                    let missingKeys = '';
                    if (response.data.missing && response.data.missing.length > 0) {
                        missingKeys = '<br><strong>Missing configuration:</strong><ul style="margin-left: 20px;">';
                        response.data.missing.forEach(function(key) {
                            missingKeys += '<li>' + key + '</li>';
                        });
                        missingKeys += '</ul>';
                    }
                    $('#sync-results').html(
                        '<div class="notice notice-error"><p>❌ ' + errorMessage + missingKeys + '</p></div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                console.error('Sync all records error:', status, error, xhr);
                let errorMessage = 'Failed to sync records.';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                } else if (xhr.status === 400) {
                    errorMessage = 'Bad Request - Please check your configuration.';
                } else if (xhr.status === 403) {
                    errorMessage = 'Permission denied.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error occurred. Check server logs for details.';
                } else if (xhr.status === 0) {
                    errorMessage = 'Request timeout. The sync may still be processing. Please check again later.';
                }
                $('#sync-results').html(
                    '<div class="notice notice-error"><p>❌ ' + errorMessage + '</p></div>'
                );
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            },
            timeout: 300000 // 5 minutes timeout for large syncs
        });
    });
    
    // Sync Pending Records
    $('#sync-pending-records').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const button = $(this);
        const originalText = button.text();
        
        // Check if required objects exist
        if (typeof boat_chatbot_admin === 'undefined') {
            console.error('boat_chatbot_admin object is not defined');
            $('#sync-results').html(
                '<div class="notice notice-error"><p>❌ JavaScript configuration error. Please refresh the page.</p></div>'
            );
            return;
        }
        
        button.text('Syncing...').prop('disabled', true);
        $('#sync-results').html('<p>Syncing pending records...</p>');
        
        $.ajax({
            url: boat_chatbot_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'boat_chatbot_sync_pending_records',
                nonce: boat_chatbot_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    let message = response.data.message;
                    let details = '';
                    if (response.data.results) {
                        details = '<br><strong>Details:</strong> ' + 
                            'Total: ' + response.data.results.total + ', ' +
                            'Successful: ' + response.data.results.success + ', ' +
                            'Failed: ' + response.data.results.failed;
                    }
                    $('#sync-results').html(
                        '<div class="notice notice-success"><p>✅ ' + message + details + '</p></div>'
                    );
                } else {
                    let errorMessage = response.data.message || 'Sync failed';
                    let missingKeys = '';
                    if (response.data.missing && response.data.missing.length > 0) {
                        missingKeys = '<br><strong>Missing configuration:</strong><ul style="margin-left: 20px;">';
                        response.data.missing.forEach(function(key) {
                            missingKeys += '<li>' + key + '</li>';
                        });
                        missingKeys += '</ul>';
                    }
                    $('#sync-results').html(
                        '<div class="notice notice-error"><p>❌ ' + errorMessage + missingKeys + '</p></div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                console.error('Sync pending records error:', status, error, xhr);
                let errorMessage = 'Failed to sync pending records.';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                } else if (xhr.status === 400) {
                    errorMessage = 'Bad Request - Please check your configuration.';
                } else if (xhr.status === 403) {
                    errorMessage = 'Permission denied.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error occurred. Check server logs for details.';
                } else if (xhr.status === 0) {
                    errorMessage = 'Request timeout. The sync may still be processing. Please check again later.';
                }
                $('#sync-results').html(
                    '<div class="notice notice-error"><p>❌ ' + errorMessage + '</p></div>'
                );
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            },
            timeout: 300000 // 5 minutes timeout for large syncs
        });
    });
    
    // Test Sync 100 Records
    $('#test-sync-100').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('Test Sync 100 button clicked!'); // Debug log
        
        const button = $(this);
        const originalText = button.text();
        
        // Immediate visual feedback
        button.css('background-color', '#0073aa').css('color', '#fff');
        
        // Check if required objects exist
        if (typeof boat_chatbot_admin === 'undefined') {
            console.error('boat_chatbot_admin object is not defined');
            $('#sync-results').html(
                '<div class="notice notice-error"><p>❌ JavaScript configuration error. Please refresh the page.</p></div>'
            );
            button.css('background-color', '').css('color', '');
            return;
        }
        
        console.log('Starting test sync...', boat_chatbot_admin); // Debug log
        button.text('Syncing...').prop('disabled', true);
        $('#sync-results').html('<div class="notice notice-info"><p><strong>⏳ Syncing 100 test records... Please wait. This may take a few minutes.</strong></p></div>');
        
        $.ajax({
            url: boat_chatbot_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'boat_chatbot_test_sync_100',
                nonce: boat_chatbot_admin.nonce
            },
            success: function(response) {
                console.log('Test sync 100 response received:', response);
                if (response.success) {
                    let message = response.data.message;
                    let details = '';
                    if (response.data.results) {
                        details = '<br><strong>Details:</strong> ' + 
                            'Total: ' + response.data.results.total + ', ' +
                            'Successful: ' + response.data.results.success + ', ' +
                            'Failed: ' + response.data.results.failed;
                    }
                    
                    // Show error details if there were failures
                    let errorDetails = '';
                    if (response.data.results && response.data.results.failed > 0) {
                        if (response.data.error_summary && Object.keys(response.data.error_summary).length > 0) {
                            errorDetails = '<br><br><strong>Failure Reasons:</strong><ul style="margin-left: 20px; margin-top: 10px;">';
                            for (let reason in response.data.error_summary) {
                                let count = response.data.error_summary[reason];
                                let reasonText = reason.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                                errorDetails += '<li><strong>' + reasonText + ':</strong> ' + count + ' record(s)</li>';
                            }
                            errorDetails += '</ul>';
                        }
                        
                        // Show first few detailed errors
                        if (response.data.error_details && response.data.error_details.length > 0) {
                            errorDetails += '<br><strong>Error Details (first 10):</strong><ul style="margin-left: 20px; margin-top: 10px; max-height: 200px; overflow-y: auto;">';
                            let errorCount = Math.min(10, response.data.error_details.length);
                            for (let i = 0; i < errorCount; i++) {
                                errorDetails += '<li style="font-size: 12px; color: #d63638;">' + response.data.error_details[i] + '</li>';
                            }
                            if (response.data.error_details.length > 10) {
                                errorDetails += '<li style="font-size: 12px; color: #666;">... and ' + (response.data.error_details.length - 10) + ' more errors</li>';
                            }
                            errorDetails += '</ul>';
                        }
                    }
                    
                    let noticeClass = response.data.results.failed > 0 ? 'notice-warning' : 'notice-success';
                    $('#sync-results').html(
                        '<div class="notice ' + noticeClass + '"><p>✅ ' + message + details + errorDetails + '</p></div>'
                    );
                } else {
                    let errorMessage = response.data.message || 'Sync failed';
                    let missingKeys = '';
                    if (response.data.missing && response.data.missing.length > 0) {
                        missingKeys = '<br><br><strong>Missing configuration:</strong><ul style="margin-left: 20px;">';
                        response.data.missing.forEach(function(key) {
                            missingKeys += '<li>' + key + '</li>';
                        });
                        missingKeys += '</ul>';
                    }
                    
                    // Show error details
                    let errorDetails = '';
                    if (response.data.error_details && response.data.error_details.length > 0) {
                        errorDetails = '<br><br><strong>Error Details:</strong><ul style="margin-left: 20px; margin-top: 10px;">';
                        response.data.error_details.forEach(function(detail) {
                            errorDetails += '<li style="color: #d63638;">' + detail + '</li>';
                        });
                        errorDetails += '</ul>';
                    }
                    
                    if (response.data.error_summary && Object.keys(response.data.error_summary).length > 0) {
                        errorDetails += '<br><strong>Error Summary:</strong><ul style="margin-left: 20px; margin-top: 10px;">';
                        for (let reason in response.data.error_summary) {
                            let count = response.data.error_summary[reason];
                            let reasonText = reason.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                            errorDetails += '<li><strong>' + reasonText + ':</strong> ' + count + ' occurrence(s)</li>';
                        }
                        errorDetails += '</ul>';
                    }
                    
                    $('#sync-results').html(
                        '<div class="notice notice-error"><p>❌ <strong>' + errorMessage + '</strong>' + missingKeys + errorDetails + '</p></div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                console.error('Test sync 100 records error:', status, error, xhr);
                let errorMessage = 'Failed to sync test records.';
                let errorDetails = '';
                
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    if (xhr.responseJSON.data.message) {
                        errorMessage = xhr.responseJSON.data.message;
                    }
                    
                    // Show error details if available
                    if (xhr.responseJSON.data.error_details && xhr.responseJSON.data.error_details.length > 0) {
                        errorDetails = '<br><br><strong>Error Details:</strong><ul style="margin-left: 20px; margin-top: 10px;">';
                        xhr.responseJSON.data.error_details.forEach(function(detail) {
                            errorDetails += '<li style="color: #d63638;">' + detail + '</li>';
                        });
                        errorDetails += '</ul>';
                    }
                    
                    if (xhr.responseJSON.data.error_summary && Object.keys(xhr.responseJSON.data.error_summary).length > 0) {
                        errorDetails += '<br><strong>Error Summary:</strong><ul style="margin-left: 20px; margin-top: 10px;">';
                        for (let reason in xhr.responseJSON.data.error_summary) {
                            let count = xhr.responseJSON.data.error_summary[reason];
                            let reasonText = reason.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                            errorDetails += '<li><strong>' + reasonText + ':</strong> ' + count + ' occurrence(s)</li>';
                        }
                        errorDetails += '</ul>';
                    }
                } else if (xhr.status === 400) {
                    errorMessage = 'Bad Request (400) - Please check your configuration and API keys.';
                } else if (xhr.status === 403) {
                    errorMessage = 'Permission Denied (403) - You do not have permission to perform this action.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server Error (500) - An error occurred on the server. Check server error logs for details.';
                    errorDetails = '<br><br><strong>Troubleshooting:</strong><ul style="margin-left: 20px; margin-top: 10px;"><li>Check PHP error logs</li><li>Verify all API keys are correct</li><li>Ensure database connection is working</li><li>Check that all required classes are loaded</li></ul>';
                } else if (xhr.status === 0) {
                    errorMessage = 'Request Timeout - The sync request timed out. This may happen with large syncs.';
                    errorDetails = '<br><br><strong>Note:</strong> The sync may still be processing in the background. Please check again in a few minutes.';
                } else {
                    errorMessage = 'Network Error (' + xhr.status + ') - ' + (error || 'Unknown error occurred');
                }
                
                $('#sync-results').html(
                    '<div class="notice notice-error"><p>❌ <strong>' + errorMessage + '</strong>' + errorDetails + '</p></div>'
                );
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
                button.css('background-color', '').css('color', '');
            },
            timeout: 300000 // 5 minutes timeout
        });
    });
    
    // Debug: Log when button is found
    if ($('#test-sync-100').length > 0) {
        console.log('Test Sync 100 button found and handler attached');
    } else {
        console.warn('Test Sync 100 button NOT found on page');
    }
    
    // View Log Details
    $('.view-log-details').on('click', function() {
        const logId = $(this).data('log-id');
        
        $.ajax({
            url: boat_chatbot_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'boat_chatbot_get_log_details',
                log_id: logId,
                nonce: boat_chatbot_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Create modal display
                    const modal = $('<div class="boat-chatbot-modal"></div>');
                    const content = $('<div class="boat-chatbot-modal-content"></div>');
                    const close = $('<span class="boat-chatbot-modal-close">&times;</span>');
                    
                    content.html('<h3>Log Details #' + logId + '</h3><pre>' + JSON.stringify(response.data, null, 2) + '</pre>');
                    content.prepend(close);
                    modal.append(content);
                    
                    $('body').append(modal);
                    
                    // Show modal
                    modal.fadeIn();
                    
                    // Close modal handlers
                    close.on('click', function() {
                        modal.fadeOut(function() {
                            $(this).remove();
                        });
                    });
                    
                    modal.on('click', function(e) {
                        if (e.target === modal[0]) {
                            modal.fadeOut(function() {
                                $(this).remove();
                            });
                        }
                    });
                }
            }
        });
    });
    
    // Video Upload Handler - Desktop
    let videoUploader;
    $('#boat_chatbot_upload_video_btn').on('click', function(e) {
        e.preventDefault();
        
        // If the uploader object has already been created, reopen it
        if (videoUploader) {
            videoUploader.open();
            return;
        }
        
        // Create the media uploader
        videoUploader = wp.media({
            title: 'Choose Landing Page Video (Desktop)',
            button: {
                text: 'Use this video'
            },
            library: {
                type: 'video'
            },
            multiple: false
        });
        
        // When a video is selected, run a callback
        videoUploader.on('select', function() {
            const attachment = videoUploader.state().get('selection').first().toJSON();
            $('#boat_chatbot_landing_video').val(attachment.id);
            $('#boat_chatbot_landing_video_url').val(attachment.url);
            $('#boat_chatbot_remove_video_btn').show();
            
            // Update preview
            const previewDiv = $('#boat_chatbot_video_preview');
            if (previewDiv.length) {
                previewDiv.show();
                const video = previewDiv.find('video');
                if (video.length) {
                    video.find('source').attr('src', attachment.url);
                    video[0].load();
                } else {
                    previewDiv.html('<video controls style="max-width: 400px; max-height: 225px; background: #000;"><source src="' + attachment.url + '" type="video/mp4">Your browser does not support the video tag.</video>');
                }
            }
        });
        
        // Open the uploader
        videoUploader.open();
    });
    
    // Remove Video - Desktop
    $('#boat_chatbot_remove_video_btn').on('click', function(e) {
        e.preventDefault();
        $('#boat_chatbot_landing_video').val('');
        $('#boat_chatbot_landing_video_url').val('');
        $(this).hide();
        $('#boat_chatbot_video_preview').hide();
    });
    
    // Video Upload Handler - Mobile
    let videoMobileUploader;
    $('#boat_chatbot_upload_video_mobile_btn').on('click', function(e) {
        e.preventDefault();
        
        // If the uploader object has already been created, reopen it
        if (videoMobileUploader) {
            videoMobileUploader.open();
            return;
        }
        
        // Create the media uploader
        videoMobileUploader = wp.media({
            title: 'Choose Landing Page Video (Mobile)',
            button: {
                text: 'Use this video'
            },
            library: {
                type: 'video'
            },
            multiple: false
        });
        
        // When a video is selected, run a callback
        videoMobileUploader.on('select', function() {
            const attachment = videoMobileUploader.state().get('selection').first().toJSON();
            $('#boat_chatbot_landing_video_mobile').val(attachment.id);
            $('#boat_chatbot_landing_video_mobile_url').val(attachment.url);
            $('#boat_chatbot_remove_video_mobile_btn').show();
            
            // Update preview
            const previewDiv = $('#boat_chatbot_video_mobile_preview');
            if (previewDiv.length) {
                previewDiv.show();
                const video = previewDiv.find('video');
                if (video.length) {
                    video.find('source').attr('src', attachment.url);
                    video[0].load();
                } else {
                    previewDiv.html('<video controls style="max-width: 400px; max-height: 225px; background: #000;"><source src="' + attachment.url + '" type="video/mp4">Your browser does not support the video tag.</video>');
                }
            }
        });
        
        // Open the uploader
        videoMobileUploader.open();
    });
    
    // Remove Video - Mobile
    $('#boat_chatbot_remove_video_mobile_btn').on('click', function(e) {
        e.preventDefault();
        $('#boat_chatbot_landing_video_mobile').val('');
        $('#boat_chatbot_landing_video_mobile_url').val('');
        $(this).hide();
        $('#boat_chatbot_video_mobile_preview').hide();
    });
});
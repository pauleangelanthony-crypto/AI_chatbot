<?php

class Boat_Chatbot_Admin_Settings {
    
    private static $instance = null;
    private $settings_page;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Register AJAX handlers
        add_action('wp_ajax_boat_chatbot_test_db_connection', array($this, 'test_db_connection'));
        add_action('wp_ajax_boat_chatbot_test_api_connection', array($this, 'test_api_connection'));
        add_action('wp_ajax_boat_chatbot_get_log_details', array($this, 'get_log_details'));
        add_action('wp_ajax_boat_chatbot_test_groq_embeddings', array($this, 'test_groq_embeddings'));
        add_action('wp_ajax_boat_chatbot_test_pinecone', array($this, 'test_pinecone'));
        add_action('wp_ajax_boat_chatbot_test_pinecone_upsert', array($this, 'test_pinecone_upsert'));
        add_action('wp_ajax_boat_chatbot_test_rerank', array($this, 'test_rerank'));
        add_action('wp_ajax_boat_chatbot_sync_all_records', array($this, 'sync_all_records'));
        add_action('wp_ajax_boat_chatbot_sync_pending_records', array($this, 'sync_pending_records'));
        add_action('wp_ajax_boat_chatbot_test_sync_100', array($this, 'test_sync_100_records'));
        add_action('wp_ajax_boat_chatbot_build_vocabulary', array($this, 'build_vocabulary'));
        add_action('wp_ajax_boat_chatbot_test_redis', array($this, 'test_redis_connection'));
        add_action('wp_ajax_boat_chatbot_flush_rewrite_rules', array($this, 'flush_rewrite_rules_ajax'));
    }
    
    /**
     * AJAX handler to flush rewrite rules
     */
    public function flush_rewrite_rules_ajax() {
        check_ajax_referer('boat_chatbot_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        // Add rewrite rule
        add_rewrite_rule('^virtual-yachtbroker/?$', 'index.php?boat_chatbot_landing=1', 'top');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        $landing_url = home_url('/virtual-yachtbroker');
        
        wp_send_json_success(array(
            'message' => 'Rewrite rules flushed successfully!',
            'url' => $landing_url
        ));
    }
    
    public function add_admin_menu() {
        $this->settings_page = add_menu_page(
            'Boat Chatbot Settings',
            'Boat Chatbot',
            'manage_options',
            'boat-chatbot-settings',
            array($this, 'render_settings_page'),
            'dashicons-format-chat',
            100
        );
        
        // Submenus
        add_submenu_page(
            'boat-chatbot-settings',
            'Settings',
            'Settings',
            'manage_options',
            'boat-chatbot-settings',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'boat-chatbot-settings',
            'Chat Logs',
            'Chat Logs',
            'manage_options',
            'boat-chatbot-logs',
            array($this, 'render_logs_page')
        );
        
        // Landing Page link
        global $submenu;
        if (isset($submenu['boat-chatbot-settings'])) {
            $landing_url = home_url('/virtual-yachtbroker');
            $submenu['boat-chatbot-settings'][] = array(
                'View Landing Page',
                'manage_options',
                $landing_url
            );
        }
        
        // Also add as admin page for instructions
        add_submenu_page(
            'boat-chatbot-settings',
            'Landing Page Info',
            'Landing Page Info',
            'manage_options',
            'boat-chatbot-landing-page-info',
            array($this, 'render_landing_page_info')
        );
    }
    
    /**
     * Render landing page info page
     */
    public function render_landing_page_info() {
        $landing_url = home_url('/virtual-yachtbroker');
        ?>
        <div class="wrap">
            <h1>Virtual Yacht Broker Landing Page</h1>
            
            <div class="notice notice-info" style="margin: 20px 0;">
                <p style="font-size: 16px;">
                    <strong>Landing Page URL:</strong> 
                    <a href="<?php echo esc_url($landing_url); ?>" target="_blank" style="font-size: 18px; font-weight: bold; color: #2271b1;">
                        <?php echo esc_html($landing_url); ?>
                    </a>
                </p>
            </div>
            
            <div style="margin: 30px 0;">
                <a href="<?php echo esc_url($landing_url); ?>" target="_blank" 
                   class="button button-primary button-large" 
                   style="font-size: 18px; padding: 15px 30px; height: auto; line-height: 1.5;">
                    🚀 Open Landing Page in New Tab →
                </a>
            </div>
            
            <div class="card" style="max-width: 900px; margin-top: 30px;">
                <h2>📋 Quick Access Instructions</h2>
                <ol style="font-size: 15px; line-height: 1.8;">
                    <li><strong>From WordPress Admin:</strong> 
                        <ul>
                            <li>Go to <strong>Boat Chatbot → View Landing Page</strong> in the left menu (opens directly)</li>
                            <li>Or go to <strong>Boat Chatbot → Landing Page Info</strong> for this information page</li>
                        </ul>
                    </li>
                    <li><strong>Direct URL:</strong> Visit <code style="background: #f0f0f1; padding: 3px 8px; border-radius: 3px;"><?php echo esc_html($landing_url); ?></code> in your browser</li>
                    <li><strong>If URL doesn't work:</strong> 
                        <ul>
                            <li>Go to <strong>Settings → Permalinks</strong></li>
                            <li>Click <strong>"Save Changes"</strong> (this flushes rewrite rules)</li>
                            <li>Try accessing the URL again</li>
                        </ul>
                    </li>
                </ol>
                
                <h2 style="margin-top: 30px;">✨ Features</h2>
                <ul style="font-size: 15px; line-height: 1.8;">
                    <li>🎨 Bright, modern design with marina sunset theme</li>
                    <li>🎥 Full-width video section in center (stretched)</li>
                    <li>🔘 5 interactive action buttons with smooth animations</li>
                    <li>💬 Integrated chatbot input at bottom</li>
                    <li>📱 Fully responsive design for all devices</li>
                    <li>🍔 Hamburger menu for mobile navigation</li>
                    <li>⚡ Fast loading with optimized assets</li>
                </ul>
                
                <h2 style="margin-top: 30px;">🔧 Troubleshooting</h2>
                <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0;">
                    <p><strong>If the page shows 404 error:</strong></p>
                    <ol>
                        <li>Go to <strong>Settings → Permalinks</strong></li>
                        <li>Click <strong>"Save Changes"</strong> without changing anything</li>
                        <li>This will flush the rewrite rules and register the custom route</li>
                    </ol>
                </div>
                
                <div style="background: #d1ecf1; border-left: 4px solid #0c5460; padding: 15px; margin: 15px 0;">
                    <p><strong>To test the page:</strong></p>
                    <ol>
                        <li>Click the "Open Landing Page" button above</li>
                        <li>Or copy the URL and paste it in a new browser tab</li>
                        <li>The page should load with the video section, buttons, and chatbot input</li>
                    </ol>
                </div>
                
                <h2 style="margin-top: 30px;">🔧 Fix 404 Error</h2>
                <div style="background: #f8d7da; border-left: 4px solid #dc3545; padding: 20px; margin: 15px 0;">
                    <p><strong>If you're getting a 404 error, try these steps:</strong></p>
                    <ol style="margin: 10px 0;">
                        <li><strong>Method 1 (Recommended):</strong> Click the button below to flush rewrite rules</li>
                        <li><strong>Method 2:</strong> Go to <strong>Settings → Permalinks</strong> and click "Save Changes"</li>
                        <li><strong>Method 3:</strong> Deactivate and reactivate the plugin</li>
                    </ol>
                    <div style="margin-top: 15px;">
                        <button id="flush-rewrite-rules-btn" class="button button-secondary" style="font-size: 15px; padding: 10px 20px;">
                            🔄 Flush Rewrite Rules Now
                        </button>
                        <span id="flush-status" style="margin-left: 15px; font-weight: bold;"></span>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Ensure ajaxurl is available (WordPress provides this in admin)
            var ajaxUrl = typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
            
            $('#flush-rewrite-rules-btn').on('click', function() {
                var $btn = $(this);
                var $status = $('#flush-status');
                
                $btn.prop('disabled', true).text('Flushing...');
                $status.text('').removeClass('notice-success notice-error');
                
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'boat_chatbot_flush_rewrite_rules',
                        nonce: '<?php echo wp_create_nonce('boat_chatbot_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $status.text('✓ ' + response.data.message).addClass('notice-success').css('color', '#46b450');
                            $btn.text('✓ Flushed!').css('background', '#46b450').css('color', 'white');
                            setTimeout(function() {
                                window.open(response.data.url, '_blank');
                            }, 500);
                        } else {
                            $status.text('✗ Error: ' + (response.data.message || 'Unknown error')).addClass('notice-error').css('color', '#dc3232');
                            $btn.prop('disabled', false).text('🔄 Flush Rewrite Rules Now');
                        }
                    },
                    error: function() {
                        $status.text('✗ AJAX error occurred').addClass('notice-error').css('color', '#dc3232');
                        $btn.prop('disabled', false).text('🔄 Flush Rewrite Rules Now');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    public function register_settings() {
        register_setting('boat_chatbot_settings', 'boat_chatbot_grok_api_key');
        register_setting('boat_chatbot_settings', 'boat_chatbot_grok_api_url');
        register_setting('boat_chatbot_settings', 'boat_chatbot_tone_of_voice');
        register_setting('boat_chatbot_settings', 'boat_chatbot_blocked_websites', array(
            'sanitize_callback' => array($this, 'sanitize_blocked_websites')
        ));
        register_setting('boat_chatbot_settings', 'boat_chatbot_db_host');
        register_setting('boat_chatbot_settings', 'boat_chatbot_db_name');
        register_setting('boat_chatbot_settings', 'boat_chatbot_db_user');
        register_setting('boat_chatbot_settings', 'boat_chatbot_db_password');
        register_setting('boat_chatbot_settings', 'boat_chatbot_db_table');
        register_setting('boat_chatbot_settings', 'boat_chatbot_allowed_fields');
        register_setting('boat_chatbot_settings', 'boat_chatbot_listing_format', array(
            'sanitize_callback' => array($this, 'sanitize_listing_format')
        ));
        register_setting('boat_chatbot_settings', 'boat_chatbot_token_limit', array(
            'sanitize_callback' => 'absint'
        ));
        
        // Groq Embeddings settings
        register_setting('boat_chatbot_settings', 'boat_chatbot_groq_api_key');
        register_setting('boat_chatbot_settings', 'boat_chatbot_groq_embeddings_url');
        register_setting('boat_chatbot_settings', 'boat_chatbot_groq_embedding_model');
        register_setting('boat_chatbot_settings', 'boat_chatbot_groq_embedding_dimensions', array(
            'sanitize_callback' => 'absint'
        ));
        
        // Pinecone settings
        register_setting('boat_chatbot_settings', 'boat_chatbot_pinecone_api_key');
        register_setting('boat_chatbot_settings', 'boat_chatbot_pinecone_url');
        register_setting('boat_chatbot_settings', 'boat_chatbot_pinecone_environment');
        register_setting('boat_chatbot_settings', 'boat_chatbot_pinecone_prod_index');
        register_setting('boat_chatbot_settings', 'boat_chatbot_pinecone_staging_index');
        register_setting('boat_chatbot_settings', 'boat_chatbot_pinecone_current_env');
        
        // Sync API key for external access (Python scraper)
        register_setting('boat_chatbot_settings', 'boat_chatbot_sync_api_key');
        
        // Reranking settings
        register_setting('boat_chatbot_settings', 'boat_chatbot_rerank_enabled', array(
            'sanitize_callback' => function($value) { return $value === '1' || $value === true; }
        ));
        register_setting('boat_chatbot_settings', 'boat_chatbot_rerank_api_key');
        register_setting('boat_chatbot_settings', 'boat_chatbot_rerank_api_url');
        register_setting('boat_chatbot_settings', 'boat_chatbot_rerank_model');
        register_setting('boat_chatbot_settings', 'boat_chatbot_rerank_top_n', array(
            'sanitize_callback' => function($value) {
                $int = absint($value);
                return max(1, min(100, $int)); // Clamp between 1 and 100
            }
        ));
        
        // UI Settings
        register_setting('boat_chatbot_settings', 'boat_chatbot_input_placeholder', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('boat_chatbot_settings', 'boat_chatbot_landing_video', array(
            'sanitize_callback' => 'absint' // WordPress attachment ID
        ));
        register_setting('boat_chatbot_settings', 'boat_chatbot_landing_video_mobile', array(
            'sanitize_callback' => 'absint' // WordPress attachment ID
        ));
        
        // Redis cache settings
        register_setting('boat_chatbot_settings', 'boat_chatbot_redis_enabled', array(
            'sanitize_callback' => function($value) { return $value === '1' || $value === true; }
        ));
        register_setting('boat_chatbot_settings', 'boat_chatbot_redis_host');
        register_setting('boat_chatbot_settings', 'boat_chatbot_redis_port', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('boat_chatbot_settings', 'boat_chatbot_redis_password');
        register_setting('boat_chatbot_settings', 'boat_chatbot_redis_database', array(
            'sanitize_callback' => 'absint'
        ));
        
        // Parallel search settings
        register_setting('boat_chatbot_settings', 'boat_chatbot_use_parallel_search', array(
            'sanitize_callback' => function($value) { return $value === '1' || $value === true; }
        ));
        register_setting('boat_chatbot_settings', 'boat_chatbot_search_mode', array(
            'sanitize_callback' => function($value) {
                $allowed = array('semantic', 'keyword', 'hybrid');
                return in_array($value, $allowed) ? $value : 'hybrid';
            }
        ));
        register_setting('boat_chatbot_settings', 'boat_chatbot_vector_weight', array(
            'sanitize_callback' => function($value) {
                $float = floatval($value);
                return max(0.0, min(1.0, $float)); // Clamp between 0 and 1
            }
        ));
        register_setting('boat_chatbot_settings', 'boat_chatbot_keyword_weight', array(
            'sanitize_callback' => function($value) {
                $float = floatval($value);
                return max(0.0, min(1.0, $float)); // Clamp between 0 and 1
            }
        ));
        
        // Hybrid search settings (Pinecone dense + sparse vectors)
        register_setting('boat_chatbot_settings', 'boat_chatbot_hybrid_alpha', array(
            'sanitize_callback' => function($value) {
                return max(0, min(1, floatval($value)));
            }
        ));
        
        // Speech-to-Text and Text-to-Speech settings
        // Note: STT now uses Web Speech API (browser built-in, no API key needed)
        register_setting('boat_chatbot_settings', 'boat_chatbot_elevenlabs_api_key');
        register_setting('boat_chatbot_settings', 'boat_chatbot_elevenlabs_voice_id', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('boat_chatbot_settings', 'boat_chatbot_tts_enabled', array(
            'sanitize_callback' => function($value) { return $value === '1' || $value === true; }
        ));
        register_setting('boat_chatbot_settings', 'boat_chatbot_stt_enabled', array(
            'sanitize_callback' => function($value) { return $value === '1' || $value === true; }
        ));
        
        // Sparse vector generation settings
        register_setting('boat_chatbot_settings', 'boat_chatbot_sparse_use_embedding', array(
            'sanitize_callback' => function($value) { return $value === '1' || $value === true; }
        ));
        register_setting('boat_chatbot_settings', 'boat_chatbot_sparse_threshold', array(
            'sanitize_callback' => function($value) {
                return max(0.01, min(1.0, floatval($value)));
            }
        ));
        register_setting('boat_chatbot_settings', 'boat_chatbot_sparse_embedding_model');
        register_setting('boat_chatbot_settings', 'boat_chatbot_sparse_embedding_api_url');
        
        // UI Settings
        register_setting('boat_chatbot_settings', 'boat_chatbot_input_placeholder');
        register_setting('boat_chatbot_settings', 'boat_chatbot_help_description', array(
            'sanitize_callback' => array($this, 'sanitize_help_description')
        ));
    }
    
    public function sanitize_help_description($input) {
        // Allow HTML tags but sanitize them
        return wp_kses_post($input);
    }
    
    public function sanitize_listing_format($input) {
        // Allow safe characters for format template
        $sanitized = sanitize_text_field($input);
        // Allow placeholders like {title}, {type}, etc.
        $sanitized = preg_replace('/[^{}\w\s|\$,\'\.\-]/', '', $sanitized);
        return trim($sanitized);
    }
    
    public function sanitize_blocked_websites($input) {
        // Sanitize textarea input
        $sanitized = sanitize_textarea_field($input);
        // Remove any potentially dangerous characters but keep URLs/domains
        $sanitized = preg_replace('/[<>"\']/', '', $sanitized);
        return trim($sanitized);
    }
    
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Boat Chatbot Settings</h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('boat_chatbot_settings'); ?>
                <?php do_settings_sections('boat_chatbot_settings'); ?>
                
                <h2>API Configuration</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Grok API Key</th>
                        <td>
                            <input type="password" name="boat_chatbot_grok_api_key" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_grok_api_key')); ?>" 
                                   class="regular-text" />
                            <p class="description">Your Grok AI API key</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Grok API URL</th>
                        <td>
                            <input type="url" name="boat_chatbot_grok_api_url" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_grok_api_url', 'https://api.x.ai/v1/chat/completions')); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                </table>
                
                <h2>Tone of Voice</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Chatbot Persona</th>
                        <td>
                            <textarea name="boat_chatbot_tone_of_voice" rows="5" class="large-text"><?php 
                                echo esc_textarea(get_option('boat_chatbot_tone_of_voice', 
                                'You are a friendly, expert sailing enthusiast. Use casual language and nautical terms where appropriate. Always be enthusiastic about boating. Provide helpful, accurate information and be concise in your responses.')); 
                            ?></textarea>
                            <p class="description">Define the chatbot's personality and communication style</p>
                        </td>
                    </tr>
                </table>
                
                <h2>Website Restrictions</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Blocked Websites</th>
                        <td>
                            <textarea name="boat_chatbot_blocked_websites" rows="5" class="large-text" placeholder="example.com&#10;competitor.com&#10;another-competitor.com"><?php 
                                echo esc_textarea(get_option('boat_chatbot_blocked_websites', '')); 
                            ?></textarea>
                            <p class="description">List websites that Groq will not be allowed to check (e.g., competitor websites). Enter one website per line or separate with commas.<br><strong>Examples:</strong> example.com, competitor.com, another-competitor.com</p>
                        </td>
                    </tr>
                </table>
                
                <h2>AI Prompt Configuration</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Listing Format Template</th>
                        <td>
                            <input type="text" name="boat_chatbot_listing_format" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_listing_format', '- {title} | {type} | {length}\' | ${price} | {location}')); ?>" 
                                   class="large-text" placeholder="- {title} | {type} | {length}' | ${price} | {location}" />
                            <p class="description">
                                Format template for displaying listings to AI. Available placeholders:<br>
                                <code>{title}</code>, <code>{type}</code>, <code>{length}</code>, <code>{price}</code>, <code>{location}</code>, <code>{description}</code>, <code>{url}</code>, <code>{manufacturer}</code>, <code>{model}</code>, <code>{year}</code><br>
                                <strong>Default:</strong> <code>- {title} | {type} | {length}' | ${price} | {location}</code><br>
                                <strong>Example with all fields:</strong> <code>- {title} ({year} {manufacturer} {model}) | {type} | {length}' | ${price} | {location}</code>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Token Limit for Listings</th>
                        <td>
                            <input type="number" name="boat_chatbot_token_limit" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_token_limit', 450)); ?>" 
                                   class="small-text" min="100" max="2000" step="50" />
                            <p class="description">
                                Maximum tokens to use for listing data in AI prompt. Lower values = faster responses, less data.<br>
                                <strong>Recommended:</strong> 400-500 tokens. Total prompt should stay under ~500 tokens including overhead.
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h2>Database Configuration</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Database Host</th>
                        <td>
                            <input type="text" name="boat_chatbot_db_host" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_db_host', 'localhost')); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Database Name</th>
                        <td>
                            <input type="text" name="boat_chatbot_db_name" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_db_name')); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Database User</th>
                        <td>
                            <input type="text" name="boat_chatbot_db_user" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_db_user')); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Database Password</th>
                        <td>
                            <input type="password" name="boat_chatbot_db_password" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_db_password')); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Listings Table Name</th>
                        <td>
                            <input type="text" name="boat_chatbot_db_table" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_db_table', 'listings')); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Allowed Fields</th>
                        <td>
                            <textarea name="boat_chatbot_allowed_fields" rows="3" class="large-text"><?php 
                                echo esc_textarea(get_option('boat_chatbot_allowed_fields', 
                                'ID, VesselName, Type_, DisplayLengthFeet, PriceUSD, City, State, Description, Manufacturer, Model, Year')); 
                            ?></textarea>
                            <p class="description">Comma-separated list of database fields the AI can access. Fields used in the Listing Format Template will be automatically included.</p>
                        </td>
                    </tr>
                </table>
                
                <h2>Groq Embeddings Configuration</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Groq API Key</th>
                        <td>
                            <input type="password" name="boat_chatbot_groq_api_key" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_groq_api_key')); ?>" 
                                   class="regular-text" />
                            <p class="description">Your Groq API key for generating embeddings</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Embeddings API URL</th>
                        <td>
                            <input type="url" name="boat_chatbot_groq_embeddings_url" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_groq_embeddings_url', 'https://api.groq.com/openai/v1/embeddings')); ?>" 
                                   class="regular-text" />
                            <p class="description">Groq embeddings API endpoint URL</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Embedding Model</th>
                        <td>
                            <input type="text" name="boat_chatbot_groq_embedding_model" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_groq_embedding_model', 'nomic-embed-text-v1.5')); ?>" 
                                   class="regular-text" />
                            <p class="description">Groq embedding model name (e.g., nomic-embed-text-v1.5)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Embedding Dimensions</th>
                        <td>
                            <input type="number" name="boat_chatbot_groq_embedding_dimensions" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_groq_embedding_dimensions', 768)); ?>" 
                                   class="small-text" min="128" max="4096" step="128" />
                            <?php
                            // Try to get actual Pinecone dimension to show helpful message
                            $pinecone_manager = Boat_Chatbot_Pinecone_Manager::get_instance();
                            $pinecone_dimension = $pinecone_manager->get_index_dimension();
                            $description = 'Number of dimensions for embeddings (must match Pinecone index dimensions)';
                            if ($pinecone_dimension !== false) {
                                $configured_dim = absint(get_option('boat_chatbot_groq_embedding_dimensions', 768));
                                if ($configured_dim !== $pinecone_dimension) {
                                    $description .= '. <strong style="color: #d63638;">⚠️ Warning: Your Pinecone index requires ' . $pinecone_dimension . ' dimensions, but you have configured ' . $configured_dim . '. Please update this setting to ' . $pinecone_dimension . ' to avoid sync errors.</strong>';
                                } else {
                                    $description .= '. ✓ Your Pinecone index requires ' . $pinecone_dimension . ' dimensions (matches your configuration).';
                                }
                            }
                            ?>
                            <p class="description"><?php echo $description; ?></p>
                        </td>
                    </tr>
                </table>
                
                <h2>Pinecone Vector Database Configuration</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Pinecone API Key</th>
                        <td>
                            <input type="password" name="boat_chatbot_pinecone_api_key" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_pinecone_api_key')); ?>" 
                                   class="regular-text" />
                            <p class="description">Your Pinecone API key</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Pinecone Database URL</th>
                        <td>
                            <input type="url" name="boat_chatbot_pinecone_url" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_pinecone_url')); ?>" 
                                   class="large-text" placeholder="https://your-index.svc.environment.pinecone.io" />
                            <p class="description">Pinecone database URL (e.g., https://your-index.svc.us-east1-aws.pinecone.io). Leave empty to use auto-generated URL based on index name and environment.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Environment</th>
                        <td>
                            <input type="text" name="boat_chatbot_pinecone_environment" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_pinecone_environment', 'us-east1-aws')); ?>" 
                                   class="regular-text" placeholder="us-east1-aws" />
                            <p class="description">Pinecone environment/region (e.g., us-east1-aws, us-west1-gcp). Used for auto-generating URL if Database URL is not provided.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Production Index Name</th>
                        <td>
                            <input type="text" name="boat_chatbot_pinecone_prod_index" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_pinecone_prod_index', 'boat-chatbot-prod')); ?>" 
                                   class="regular-text" />
                            <p class="description">Name of the production Pinecone index</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Staging Index Name</th>
                        <td>
                            <input type="text" name="boat_chatbot_pinecone_staging_index" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_pinecone_staging_index', 'boat-chatbot-staging')); ?>" 
                                   class="regular-text" />
                            <p class="description">Name of the staging Pinecone index</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Current Environment</th>
                        <td>
                            <select name="boat_chatbot_pinecone_current_env">
                                <option value="prod" <?php selected(get_option('boat_chatbot_pinecone_current_env', 'prod'), 'prod'); ?>>Production</option>
                                <option value="staging" <?php selected(get_option('boat_chatbot_pinecone_current_env', 'prod'), 'staging'); ?>>Staging</option>
                            </select>
                            <p class="description">Which environment to use (prod or staging)</p>
                        </td>
                    </tr>
                </table>
                
                <h2>Parallel Search Configuration</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Parallel Search</th>
                        <td>
                            <label>
                                <input type="checkbox" name="boat_chatbot_use_parallel_search" value="1" 
                                       <?php checked(get_option('boat_chatbot_use_parallel_search', true), true); ?> />
                                Enable parallel hybrid search (runs Pinecone and SQL searches independently)
                            </label>
                            <p class="description">When enabled, the system will run semantic (Pinecone) and keyword (SQL) searches in parallel, then combine and rank results using weighted scores.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Search Mode</th>
                        <td>
                            <select name="boat_chatbot_search_mode">
                                <option value="hybrid" <?php selected(get_option('boat_chatbot_search_mode', 'hybrid'), 'hybrid'); ?>>Hybrid (Semantic + Keyword)</option>
                                <option value="semantic" <?php selected(get_option('boat_chatbot_search_mode', 'hybrid'), 'semantic'); ?>>Semantic Only (Pinecone)</option>
                                <option value="keyword" <?php selected(get_option('boat_chatbot_search_mode', 'hybrid'), 'keyword'); ?>>Keyword Only (SQL)</option>
                            </select>
                            <p class="description">Choose the search mode. Hybrid combines both semantic and keyword results with weighted scoring.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Vector Search Weight (α)</th>
                        <td>
                            <input type="number" name="boat_chatbot_vector_weight" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_vector_weight', 0.5)); ?>" 
                                   class="small-text" step="0.1" min="0" max="1" />
                            <p class="description">Weight for semantic/vector search scores (0.0 to 1.0). Used in formula: α × vector_score + β × keyword_score</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Keyword Search Weight (β)</th>
                        <td>
                            <input type="number" name="boat_chatbot_keyword_weight" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_keyword_weight', 0.5)); ?>" 
                                   class="small-text" step="0.1" min="0" max="1" />
                            <p class="description">Weight for keyword/SQL search scores (0.0 to 1.0). Used in formula: α × vector_score + β × keyword_score</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Hybrid Search Alpha (α)</th>
                        <td>
                            <input type="number" name="boat_chatbot_hybrid_alpha" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_hybrid_alpha', 0.7)); ?>" 
                                   class="small-text" step="0.1" min="0" max="1" />
                            <p class="description">Weight for dense vectors in Pinecone hybrid search (0.0 to 1.0). 0.7 means 70% dense vectors, 30% sparse vectors. Only used when sparse vectors are available.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Use Embedding API for Sparse Vectors</th>
                        <td>
                            <label>
                                <input type="checkbox" name="boat_chatbot_sparse_use_embedding" value="1" 
                                       <?php checked(get_option('boat_chatbot_sparse_use_embedding', false), true); ?> />
                                Use embedding API to generate sparse vectors (semantic-aware)
                            </label>
                            <p class="description">When enabled, sparse vectors are generated using embedding API (Groq) to identify semantically important terms. When disabled, uses BM25 statistical method (faster, no API cost).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Sparsity Threshold</th>
                        <td>
                            <input type="number" name="boat_chatbot_sparse_threshold" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_sparse_threshold', 0.1)); ?>" 
                                   class="small-text" step="0.01" min="0.01" max="1.0" />
                            <p class="description">Percentage of top terms to keep in sparse vector (0.01 to 1.0). Lower values = sparser vectors. Default: 0.1 (10% of terms). Only used with embedding-based generation.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Sparse Embedding Model</th>
                        <td>
                            <input type="text" name="boat_chatbot_sparse_embedding_model" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_sparse_embedding_model', '')); ?>" 
                                   class="regular-text" placeholder="Leave empty to use dense vector model" />
                            <p class="description">Optional: Specific embedding model for sparse vector generation (e.g., nomic-embed-text-v1.5). If empty, uses the same model as dense vectors. Useful for using models optimized for sparse retrieval.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Sparse Embedding API URL</th>
                        <td>
                            <input type="url" name="boat_chatbot_sparse_embedding_api_url" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_sparse_embedding_api_url', '')); ?>" 
                                   class="large-text" placeholder="Leave empty to use default Groq API URL" />
                            <p class="description">Optional: Custom API URL for sparse embedding generation. If empty, uses the same API URL as dense vectors. Useful for using different embedding services for sparse vectors.</p>
                        </td>
                    </tr>
                </table>
                
                <h2>Reranking Configuration</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Reranking</th>
                        <td>
                            <label>
                                <input type="checkbox" name="boat_chatbot_rerank_enabled" value="1" 
                                       <?php checked(get_option('boat_chatbot_rerank_enabled', false), true); ?> />
                                Enable reranking of search results
                            </label>
                            <p class="description">When enabled, search results will be reranked using a reranking API (e.g., Cohere) to improve relevance. This happens after initial retrieval from Pinecone.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Reranking API Key</th>
                        <td>
                            <input type="password" name="boat_chatbot_rerank_api_key" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_rerank_api_key')); ?>" 
                                   class="regular-text" />
                            <p class="description">API key for reranking service (e.g., Cohere API key)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Reranking API URL</th>
                        <td>
                            <input type="url" name="boat_chatbot_rerank_api_url" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_rerank_api_url', 'https://api.cohere.ai/v1/rerank')); ?>" 
                                   class="large-text" />
                            <p class="description">Reranking API endpoint URL (default: Cohere API)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Reranking Model</th>
                        <td>
                            <input type="text" name="boat_chatbot_rerank_model" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_rerank_model', 'rerank-english-v3.0')); ?>" 
                                   class="regular-text" />
                            <p class="description">Reranking model name (e.g., rerank-english-v3.0 for Cohere)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Top N Results to Rerank</th>
                        <td>
                            <input type="number" name="boat_chatbot_rerank_top_n" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_rerank_top_n', 20)); ?>" 
                                   class="small-text" min="1" max="100" />
                            <p class="description">Number of top results to rerank (1-100). Higher values improve accuracy but increase API costs and latency.</p>
                        </td>
                    </tr>
                </table>
                
                <h2>UI Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Chatbot Input Placeholder</th>
                        <td>
                            <input type="text" name="boat_chatbot_input_placeholder" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_input_placeholder', 'Type your query here...')); ?>" 
                                   class="regular-text" />
                            <p class="description">Placeholder text shown in the chatbot input field. This appears on the landing page, full chatbot page, and widget.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Landing Page Video (Desktop)</th>
                        <td>
                            <?php
                            $video_id = get_option('boat_chatbot_landing_video', 0);
                            $video_url = $video_id ? wp_get_attachment_url($video_id) : '';
                            ?>
                            <input type="hidden" name="boat_chatbot_landing_video" id="boat_chatbot_landing_video" value="<?php echo esc_attr($video_id); ?>" />
                            <input type="text" id="boat_chatbot_landing_video_url" value="<?php echo esc_url($video_url); ?>" class="regular-text" readonly />
                            <button type="button" class="button" id="boat_chatbot_upload_video_btn">Upload Video</button>
                            <button type="button" class="button" id="boat_chatbot_remove_video_btn" style="<?php echo $video_id ? '' : 'display:none;'; ?>">Remove Video</button>
                            <div id="boat_chatbot_video_preview" style="margin-top: 10px; <?php echo $video_id ? '' : 'display:none;'; ?>">
                                <?php if ($video_id): ?>
                                    <video controls style="max-width: 400px; max-height: 225px; background: #000;">
                                        <source src="<?php echo esc_url($video_url); ?>" type="video/mp4">
                                        Your browser does not support the video tag.
                                    </video>
                                <?php endif; ?>
                            </div>
                            <p class="description">Upload a video file for the landing page background (desktop). Recommended: MP4 format, 1920x1080 or higher resolution. If not set, will use default video.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Landing Page Video (Mobile)</th>
                        <td>
                            <?php
                            $video_mobile_id = get_option('boat_chatbot_landing_video_mobile', 0);
                            $video_mobile_url = $video_mobile_id ? wp_get_attachment_url($video_mobile_id) : '';
                            ?>
                            <input type="hidden" name="boat_chatbot_landing_video_mobile" id="boat_chatbot_landing_video_mobile" value="<?php echo esc_attr($video_mobile_id); ?>" />
                            <input type="text" id="boat_chatbot_landing_video_mobile_url" value="<?php echo esc_url($video_mobile_url); ?>" class="regular-text" readonly />
                            <button type="button" class="button" id="boat_chatbot_upload_video_mobile_btn">Upload Video</button>
                            <button type="button" class="button" id="boat_chatbot_remove_video_mobile_btn" style="<?php echo $video_mobile_id ? '' : 'display:none;'; ?>">Remove Video</button>
                            <div id="boat_chatbot_video_mobile_preview" style="margin-top: 10px; <?php echo $video_mobile_id ? '' : 'display:none;'; ?>">
                                <?php if ($video_mobile_id): ?>
                                    <video controls style="max-width: 400px; max-height: 225px; background: #000;">
                                        <source src="<?php echo esc_url($video_mobile_url); ?>" type="video/mp4">
                                        Your browser does not support the video tag.
                                    </video>
                                <?php endif; ?>
                            </div>
                            <p class="description">Upload a video file for the landing page background (mobile). Optional - if not set, desktop video will be used. Recommended: MP4 format, 720p or 1080p resolution.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Help Modal Description</th>
                        <td>
                            <?php 
                            $default_help = '<div class="boat-help-section">
    <h3>How to Use the Chatbot</h3>
    <p>Ask me anything about boats! I can help you find boats based on:</p>
    <ul>
        <li><strong>Type:</strong> "Show me sailboats" or "Find a yacht"</li>
        <li><strong>Price:</strong> "Boats under $200k" or "Budget is 200k"</li>
        <li><strong>Length:</strong> "Boats over 30 feet" or "30-40 feet"</li>
        <li><strong>Location:</strong> "Boats in Miami" or "Florida listings"</li>
        <li><strong>Year:</strong> "Boats from 2020" or "Newer than 2015"</li>
    </ul>
</div>
<div class="boat-help-section">
    <h3>Example Queries</h3>
    <ul>
        <li>"Find me a sailboat under $150k in Florida"</li>
        <li>"Show me yachts between 40-50 feet"</li>
        <li>"What boats are available in Miami?"</li>
        <li>"I\'m looking for a fishing boat around $80k"</li>
    </ul>
</div>
<div class="boat-help-section">
    <h3>Tips</h3>
    <ul>
        <li>You can combine multiple criteria in one question</li>
        <li>Use natural language - I understand conversational queries</li>
        <li>Click on any boat listing to see more details</li>
        <li>Start a new chat anytime to begin a fresh search</li>
    </ul>
</div>';
                            $help_content = get_option('boat_chatbot_help_description', $default_help);
                            ?>
                            <textarea name="boat_chatbot_help_description" rows="20" class="large-text code" style="font-family: monospace;"><?php echo esc_textarea($help_content); ?></textarea>
                            <p class="description">
                                HTML content displayed in the help modal when users click the help button. You can use HTML tags like <code>&lt;div&gt;</code>, <code>&lt;h3&gt;</code>, <code>&lt;ul&gt;</code>, <code>&lt;li&gt;</code>, <code>&lt;p&gt;</code>, and <code>&lt;strong&gt;</code>.<br>
                                <strong>Note:</strong> Use the class <code>boat-help-section</code> for each section div to maintain consistent styling.<br>
                                <strong>Example structure:</strong><br>
                                <code>&lt;div class="boat-help-section"&gt;&lt;h3&gt;Title&lt;/h3&gt;&lt;p&gt;Content&lt;/p&gt;&lt;/div&gt;</code>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h2>Redis Cache Configuration</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Redis Cache</th>
                        <td>
                            <label>
                                <input type="checkbox" name="boat_chatbot_redis_enabled" value="1" 
                                       <?php checked(get_option('boat_chatbot_redis_enabled', false), true); ?> />
                                Enable Redis caching (requires php-redis extension)
                            </label>
                            <p class="description">When enabled, cache will use Redis instead of WordPress transients. Falls back to transients if Redis is unavailable.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Redis Host</th>
                        <td>
                            <input type="text" name="boat_chatbot_redis_host" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_redis_host', 'localhost')); ?>" 
                                   class="regular-text" />
                            <p class="description">Redis server hostname or IP address</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Redis Port</th>
                        <td>
                            <input type="number" name="boat_chatbot_redis_port" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_redis_port', 6379)); ?>" 
                                   class="small-text" min="1" max="65535" />
                            <p class="description">Redis server port (default: 6379)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Redis Password</th>
                        <td>
                            <input type="password" name="boat_chatbot_redis_password" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_redis_password', '')); ?>" 
                                   class="regular-text" />
                            <p class="description">Redis password (leave empty if no password required)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Redis Database</th>
                        <td>
                            <input type="number" name="boat_chatbot_redis_database" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_redis_database', 0)); ?>" 
                                   class="small-text" min="0" max="15" />
                            <p class="description">Redis database number (0-15, default: 0)</p>
                        </td>
                    </tr>
                </table>
                
                <h2>Sync Configuration</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Sync API Key</th>
                        <td>
                            <input type="password" name="boat_chatbot_sync_api_key" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_sync_api_key')); ?>" 
                                   class="regular-text" />
                            <p class="description">API key for external sync requests (e.g., from Python scraper). Leave empty to disable external sync.</p>
                            <p class="description"><strong>Sync Endpoint:</strong> <code><?php echo esc_url(rest_url('boat-chatbot/v1/sync-records')); ?></code></p>
                        </td>
                    </tr>
                </table>
                
                <h2>Speech-to-Text & Text-to-Speech Configuration</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Speech-to-Text</th>
                        <td>
                            <label>
                                <input type="checkbox" name="boat_chatbot_stt_enabled" value="1" 
                                       <?php checked(get_option('boat_chatbot_stt_enabled', false), true); ?> />
                                Enable voice input (Speech-to-Text) using Web Speech API
                            </label>
                            <p class="description">When enabled, users can click the voice button to speak their queries instead of typing. Uses browser's built-in Web Speech API (free, no API key needed). Works best in Chrome, Edge, or Safari.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Enable Text-to-Speech</th>
                        <td>
                            <label>
                                <input type="checkbox" name="boat_chatbot_tts_enabled" value="1" 
                                       <?php checked(get_option('boat_chatbot_tts_enabled', false), true); ?> />
                                Enable voice output (Text-to-Speech) using ElevenLabs
                            </label>
                            <p class="description">When enabled, bot responses will be automatically read aloud using natural-sounding voices.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">ElevenLabs API Key</th>
                        <td>
                            <input type="password" name="boat_chatbot_elevenlabs_api_key" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_elevenlabs_api_key')); ?>" 
                                   class="regular-text" />
                            <p class="description">Your ElevenLabs API key for Text-to-Speech. Get it from <a href="https://elevenlabs.io/app/settings/api-keys" target="_blank">ElevenLabs Dashboard</a>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">ElevenLabs Voice ID</th>
                        <td>
                            <input type="text" name="boat_chatbot_elevenlabs_voice_id" 
                                   value="<?php echo esc_attr(get_option('boat_chatbot_elevenlabs_voice_id', '21m00Tcm4TlvDq8ikWAM')); ?>" 
                                   class="regular-text" />
                            <p class="description">Voice ID for Text-to-Speech (default: Rachel). Find available voices in your <a href="https://elevenlabs.io/app/voice-library" target="_blank">ElevenLabs Voice Library</a>.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <div class="card" style="margin-top: 20px;">
                <h2>Test Connections</h2>
                <p>Test the database and API connections:</p>
                <button type="button" id="test-db-connection" class="button">Test Database</button>
                <button type="button" id="test-api-connection" class="button">Test Grok API</button>
                <button type="button" id="test-groq-embeddings" class="button">Test Groq Embeddings</button>
                <button type="button" id="test-pinecone" class="button">Test Pinecone</button>
                <button type="button" id="test-pinecone-upsert" class="button button-secondary">Test Pinecone Upsert</button>
                <button type="button" id="test-rerank" class="button">Test Reranking</button>
                <button type="button" id="test-redis" class="button">Test Redis</button>
                <div id="test-results" style="margin-top: 10px; min-height: 50px;"></div>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2>Vector Database Sync</h2>
                <p>Sync SQL database records to Pinecone vector database:</p>
                <button type="button" id="build-vocabulary" class="button">Build Vocabulary (for Sparse Vectors)</button>
                <button type="button" id="sync-all-records" class="button button-primary">Sync All Records</button>
                <button type="button" id="sync-pending-records" class="button">Sync Pending Records</button>
                <button type="button" id="test-sync-100" class="button button-secondary">Test Sync (1 Record)</button>
                <p class="description" style="margin-top: 10px;">Note: Building vocabulary from your SQL database enables sparse vector generation for hybrid search. This should be done before syncing records for the first time.</p>
                <div id="sync-results" style="margin-top: 10px; min-height: 50px;"></div>
            </div>
        </div>
        <?php
    }
    
    public function render_logs_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'boat_chatbot_logs';
        
        // Get recent logs
        $logs = $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY timestamp DESC LIMIT 100"
        );
        ?>
        <div class="wrap">
            <h1>Chatbot Conversation Logs</h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Timestamp</th>
                        <th>User Message</th>
                        <th>Intent</th>
                        <th>Response Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6">No logs found yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo $log->id; ?></td>
                            <td><?php echo $log->timestamp; ?></td>
                            <td><?php echo esc_html(wp_trim_words($log->user_message, 10)); ?></td>
                            <td><?php echo esc_html($log->classified_intent); ?></td>
                            <td><?php echo number_format($log->response_time, 2); ?>s</td>
                            <td>
                                <button class="button view-log-details" data-log-id="<?php echo $log->id; ?>">
                                    View Details
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Modal for log details -->
        <div id="log-details-modal" style="display: none;">
            <div class="log-details-content"></div>
        </div>
        <?php
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'boat-chatbot') === false) {
            return;
        }
        
        wp_enqueue_script('boat-chatbot-admin', BOAT_CHATBOT_PLUGIN_URL . 'assets/admin.js', array('jquery'), BOAT_CHATBOT_VERSION . '.1', true);
        wp_enqueue_style('boat-chatbot-admin', BOAT_CHATBOT_PLUGIN_URL . 'assets/admin.css', array(), BOAT_CHATBOT_VERSION);
        
        // Enqueue WordPress media uploader
        wp_enqueue_media();
        
        // Localize script for AJAX (ajaxurl is available by default in admin, but we'll add it for clarity)
        wp_localize_script('boat-chatbot-admin', 'boat_chatbot_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'ajaxurl' => admin_url('admin-ajax.php'), // WordPress default, but ensure it's available
            'nonce' => wp_create_nonce('boat_chatbot_admin_nonce')
        ));
    }
    
    // Add these methods to the class
    public function test_db_connection() {
        check_ajax_referer('boat_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $db_manager = new Boat_Chatbot_Database_Manager();
        $result = $db_manager->test_connection();
        
        wp_send_json_success($result);
    }
    
    public function test_api_connection() {
        check_ajax_referer('boat_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $api_key = get_option('boat_chatbot_grok_api_key');
        $api_url = get_option('boat_chatbot_grok_api_url', 'https://api.x.ai/v1/chat/completions');
        
        if (!$api_key) {
            wp_send_json_error(array('message' => 'API key not configured'));
        }
        
        if (!$api_url) {
            wp_send_json_error(array('message' => 'API URL not configured'));
        }
        
        // Make actual API request to test connection
        $test_message = 'Hello, this is a connection test. Please respond with "Connection successful."';
        
        // Start timing
        $start_time = microtime(true);
        
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body' => json_encode(array(
                'messages' => array(
                    array('role' => 'user', 'content' => $test_message)
                ),
                'model' => 'grok-4-fast-reasoning',
                'temperature' => 0.7,
                'max_tokens' => 50,
                'stream' => false
            )),
            'timeout' => 15,
            // 'blocking' => true
        ));
        
        // Calculate response time
        $end_time = microtime(true);
        $response_time = round(($end_time - $start_time) * 1000, 2); // Convert to milliseconds
        
        // Check for WordPress HTTP errors
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => 'Failed to send request: ' . $response->get_error_message(),
                'response_time' => $response_time,
                'response_time_formatted' => number_format($response_time, 2) . ' ms'
            ));
        }
        
        // Check HTTP response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $response_message = wp_remote_retrieve_response_message($response);
            $body = wp_remote_retrieve_body($response);
            
            // Try to extract error message from response body
            $error_details = '';
            $response_data = json_decode($body, true);
            if (isset($response_data['error']['message'])) {
                $error_details = ': ' . $response_data['error']['message'];
            }
            
            wp_send_json_error(array(
                'message' => 'API returned error (HTTP ' . $response_code . ')' . $error_details,
                'response_time' => $response_time,
                'response_time_formatted' => number_format($response_time, 2) . ' ms'
            ));
        }
        
        // Parse response body
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Check if response is valid JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array(
                'message' => 'Invalid JSON response: ' . json_last_error_msg(),
                'response_time' => $response_time,
                'response_time_formatted' => number_format($response_time, 2) . ' ms'
            ));
        }
        
        // Check for API error messages in response
        if (isset($body['error'])) {
            $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown API error';
            wp_send_json_error(array(
                'message' => 'API error: ' . $error_message,
                'response_time' => $response_time,
                'response_time_formatted' => number_format($response_time, 2) . ' ms'
            ));
        }
        
        // Check if we got a valid response
        if (isset($body['choices'][0]['message']['content'])) {
            $ai_response = trim($body['choices'][0]['message']['content']);
            wp_send_json_success(array(
                'message' => 'API connection successful! Response received: "' . esc_html(wp_trim_words($ai_response, 10)) . '"',
                'response_time' => $response_time,
                'response_time_formatted' => number_format($response_time, 2) . ' ms'
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Unexpected response format. Response structure may have changed.',
                'response_time' => $response_time,
                'response_time_formatted' => number_format($response_time, 2) . ' ms'
            ));
        }
    }
    
    public function get_log_details() {
        check_ajax_referer('boat_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        global $wpdb;
        $log_id = intval($_POST['log_id']);
        $table_name = $wpdb->prefix . 'boat_chatbot_logs';
        
        $log = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $log_id)
        );
        
        if ($log) {
            wp_send_json_success($log);
        } else {
            wp_send_json_error(array('message' => 'Log not found'));
        }
    }
    
    public function test_groq_embeddings() {
        check_ajax_referer('boat_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        try {
            // Ensure the class file is loaded
            if (!class_exists('Boat_Chatbot_Groq_Embeddings_Manager')) {
                // Try to load the file directly
                if (defined('BOAT_CHATBOT_PLUGIN_PATH')) {
                    $groq_file = BOAT_CHATBOT_PLUGIN_PATH . 'includes/class-groq-embeddings-manager.php';
                } else {
                    // Fallback: try to determine path from current file location
                    $groq_file = dirname(dirname(__FILE__)) . '/includes/class-groq-embeddings-manager.php';
                }
                
                if (file_exists($groq_file)) {
                    require_once $groq_file;
                } else {
                    wp_send_json_error(array(
                        'message' => 'Groq Embeddings Manager file not found at: ' . $groq_file . '. Please ensure the plugin is properly installed.'
                    ));
                    return;
                }
                
                // Check again after attempting to load
                if (!class_exists('Boat_Chatbot_Groq_Embeddings_Manager')) {
                    wp_send_json_error(array(
                        'message' => 'Groq Embeddings Manager class not found after loading file. Please check for syntax errors in the class file.'
                    ));
                    return;
                }
            }
            
            $groq_manager = Boat_Chatbot_Groq_Embeddings_Manager::get_instance();
            
            if (!$groq_manager) {
                wp_send_json_error(array(
                    'message' => 'Failed to initialize Groq Embeddings Manager'
                ));
                return;
            }
            
            // Check if test_connection method exists
            if (!method_exists($groq_manager, 'test_connection')) {
                wp_send_json_error(array(
                    'message' => 'test_connection method not found in Groq Embeddings Manager'
                ));
                return;
            }
            
            $result = $groq_manager->test_connection();
            
            if (!is_array($result)) {
                wp_send_json_error(array(
                    'message' => 'Unexpected result format from test_connection method'
                ));
                return;
            }
            
            if (isset($result['success']) && $result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Error testing Groq Embeddings: ' . $e->getMessage()
            ));
        } catch (Error $e) {
            wp_send_json_error(array(
                'message' => 'Fatal error testing Groq Embeddings: ' . $e->getMessage()
            ));
        }
    }
    
    public function test_pinecone() {
        check_ajax_referer('boat_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        try {
            // Ensure the class file is loaded
            if (!class_exists('Boat_Chatbot_Pinecone_Manager')) {
                // Try to load the file directly
                if (defined('BOAT_CHATBOT_PLUGIN_PATH')) {
                    $pinecone_file = BOAT_CHATBOT_PLUGIN_PATH . 'includes/class-pinecone-manager.php';
                } else {
                    // Fallback: try to determine path from current file location
                    $pinecone_file = dirname(dirname(__FILE__)) . '/includes/class-pinecone-manager.php';
                }
                
                if (file_exists($pinecone_file)) {
                    require_once $pinecone_file;
                } else {
                    wp_send_json_error(array(
                        'message' => 'Pinecone Manager file not found at: ' . $pinecone_file . '. Please ensure the plugin is properly installed.'
                    ));
                    return;
                }
                
                // Check again after attempting to load
                if (!class_exists('Boat_Chatbot_Pinecone_Manager')) {
                    wp_send_json_error(array(
                        'message' => 'Pinecone Manager class not found after loading file. Please check for syntax errors in the class file.'
                    ));
                    return;
                }
            }
            
            $pinecone_manager = Boat_Chatbot_Pinecone_Manager::get_instance();
            
            if (!$pinecone_manager) {
                wp_send_json_error(array(
                    'message' => 'Failed to initialize Pinecone Manager'
                ));
                return;
            }
            
            // Check if test_connection method exists
            if (!method_exists($pinecone_manager, 'test_connection')) {
                wp_send_json_error(array(
                    'message' => 'test_connection method not found in Pinecone Manager'
                ));
                return;
            }
            
            $result = $pinecone_manager->test_connection();
            
            if (!is_array($result)) {
                wp_send_json_error(array(
                    'message' => 'Unexpected result format from test_connection method'
                ));
                return;
            }
            
            if (isset($result['success']) && $result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Error testing Pinecone: ' . $e->getMessage()
            ));
        } catch (Error $e) {
            wp_send_json_error(array(
                'message' => 'Fatal error testing Pinecone: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * Test Pinecone by upserting test data
     * This will generate a test embedding and upsert it to Pinecone
     */
    public function test_pinecone_upsert() {
        check_ajax_referer('boat_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        try {
            // Ensure dependencies are loaded
            if (!class_exists('Boat_Chatbot_Groq_Embeddings_Manager')) {
                $plugin_path = defined('BOAT_CHATBOT_PLUGIN_PATH') 
                    ? BOAT_CHATBOT_PLUGIN_PATH 
                    : plugin_dir_path(dirname(__FILE__));
                $groq_file = $plugin_path . 'includes/class-groq-embeddings-manager.php';
                if (file_exists($groq_file)) {
                    require_once $groq_file;
                }
            }
            
            if (!class_exists('Boat_Chatbot_Pinecone_Manager')) {
                $plugin_path = defined('BOAT_CHATBOT_PLUGIN_PATH') 
                    ? BOAT_CHATBOT_PLUGIN_PATH 
                    : plugin_dir_path(dirname(__FILE__));
                $pinecone_file = $plugin_path . 'includes/class-pinecone-manager.php';
                if (file_exists($pinecone_file)) {
                    require_once $pinecone_file;
                }
            }
            
            if (!class_exists('Boat_Chatbot_Groq_Embeddings_Manager')) {
                wp_send_json_error(array('message' => 'Groq Embeddings Manager class not found'));
                return;
            }
            
            if (!class_exists('Boat_Chatbot_Pinecone_Manager')) {
                wp_send_json_error(array('message' => 'Pinecone Manager class not found'));
                return;
            }
            
            $groq_manager = Boat_Chatbot_Groq_Embeddings_Manager::get_instance();
            $pinecone_manager = Boat_Chatbot_Pinecone_Manager::get_instance();
            
            // Step 1: Generate test embedding with 1024 dimensions
            $test_text = 'This is a test vector for Pinecone upsert verification. Boat chatbot test data.';
            $test_id = 'test-vector-' . time() . '-' . wp_rand(1000, 9999);
            
            // First, try to generate embedding using Groq
            $groq_embedding = $groq_manager->generate_embedding($test_text);
            
            // Target dimensions for Pinecone test
            $target_dimensions = 1024;
            
            // Check if we got a valid embedding with correct dimensions
            if ($groq_embedding !== false && count($groq_embedding) === $target_dimensions) {
                // Use the Groq embedding if it has the correct dimensions
                $embedding = $groq_embedding;
            } else {
                // Create a test vector with exactly 1024 dimensions
                // Use a simple pattern for testing purposes
                $embedding = array();
                for ($i = 0; $i < $target_dimensions; $i++) {
                    // Create a simple test pattern (normalized values between -1 and 1)
                    $value = sin($i * 0.1) * 0.5 + cos($i * 0.05) * 0.3;
                    $embedding[] = $value;
                }
                
                // Use test vector if Groq embedding generation failed or has wrong dimensions
            }
            
            // Verify we have exactly 1024 dimensions
            if (count($embedding) !== $target_dimensions) {
                wp_send_json_error(array(
                    'message' => 'Failed to create test embedding with 1024 dimensions',
                    'details' => 'Expected 1024 dimensions but got ' . count($embedding) . '. Please check your configuration.',
                    'actual_dimensions' => count($embedding),
                    'expected_dimensions' => $target_dimensions
                ));
                return;
            }
            
            // Step 2: Prepare test vector (with sparse vector if available)
            $test_vector = array(
                'id' => $test_id,
                'values' => $embedding,
                'metadata' => array(
                    'test' => 'true',
                    'timestamp' => current_time('mysql'),
                    'text' => $test_text,
                    'source' => 'pinecone_upsert_test'
                )
            );
            
            // Try to generate sparse vector for the test text (for hybrid search testing)
            $test_sparse_vector = null;
            if (class_exists('Boat_Chatbot_Sparse_Vector_Generator')) {
                $sparse_generator = Boat_Chatbot_Sparse_Vector_Generator::get_instance();
                if ($sparse_generator && $sparse_generator->has_vocabulary()) {
                    $test_sparse_vector = $sparse_generator->generate_sparse_vector($test_text);
                    if ($test_sparse_vector !== false && is_array($test_sparse_vector)) {
                        $test_vector['sparseValues'] = $test_sparse_vector;
                    }
                }
            }
            
            // Step 3: Upsert to Pinecone (with sparse vector if available)
            $upsert_result = $pinecone_manager->upsert_vectors(array($test_vector));
            
            if (!$upsert_result) {
                wp_send_json_error(array(
                    'message' => 'Failed to upsert test vector to Pinecone',
                    'details' => 'The embedding was generated successfully, but the upsert to Pinecone failed. Please check your Pinecone API key, environment, and index name.',
                    'test_id' => $test_id,
                    'embedding_dimensions' => count($embedding)
                ));
                return;
            }
            
            // Step 4: Optionally query it back to verify (using hybrid search if sparse vectors are available)
            $sparse_vector = null;
            $hybrid_alpha = floatval(get_option('boat_chatbot_hybrid_alpha', 0.7)); // Default 0.7 (70% dense, 30% sparse)
            $hybrid_search_used = false;
            
            // Use the sparse vector we generated for upsert (or generate a new one if not available)
            if ($test_sparse_vector !== null && is_array($test_sparse_vector)) {
                $sparse_vector = $test_sparse_vector;
                $hybrid_search_used = true;
            } else {
                // Try to generate sparse vector for hybrid search
                if (class_exists('Boat_Chatbot_Sparse_Vector_Generator')) {
                    $sparse_generator = Boat_Chatbot_Sparse_Vector_Generator::get_instance();
                    if ($sparse_generator && $sparse_generator->has_vocabulary()) {
                        $sparse_vector = $sparse_generator->generate_sparse_vector($test_text);
                        if ($sparse_vector !== false && is_array($sparse_vector)) {
                            $hybrid_search_used = true;
                        }
                    }
                }
            }
            
            // Use hybrid search if sparse vector is available, otherwise use dense-only search
            if ($sparse_vector !== null && is_array($sparse_vector)) {
                $query_result = $pinecone_manager->query($embedding, 1, null, $sparse_vector, $hybrid_alpha);
            } else {
                $query_result = $pinecone_manager->query($embedding, 1);
            }
            
            $verification_status = 'unknown';
            $verification_details = '';
            
            if ($query_result !== false && is_array($query_result)) {
                if (isset($query_result['matches']) && is_array($query_result['matches'])) {
                    $found = false;
                    foreach ($query_result['matches'] as $match) {
                        if (isset($match['id']) && $match['id'] === $test_id) {
                            $found = true;
                            $verification_status = 'verified';
                            $search_type = $hybrid_search_used ? 'hybrid (sparse + dense)' : 'dense-only';
                            $verification_details = 'Test vector was successfully retrieved from Pinecone using ' . $search_type . ' search. Score: ' . (isset($match['score']) ? number_format($match['score'], 4) : 'N/A');
                            break;
                        }
                    }
                    if (!$found) {
                        $verification_status = 'not_found';
                        $verification_details = 'Vector was upserted but not found in query results (may need a moment to index)';
                    }
                } else {
                    $verification_status = 'query_failed';
                    $verification_details = 'Query returned unexpected format';
                }
            } else {
                $verification_status = 'query_failed';
                $verification_details = 'Failed to query Pinecone to verify the upsert';
            }
            
            // Success response
            $message = 'Pinecone upsert test successful!';
            $message .= '<br><strong>Test Vector ID:</strong> ' . esc_html($test_id);
            $message .= '<br><strong>Embedding Dimensions:</strong> ' . count($embedding);
            $message .= '<br><strong>Search Type:</strong> ' . ($hybrid_search_used ? 'Hybrid (Sparse + Dense)' : 'Dense-only');
            if ($hybrid_search_used) {
                $message .= '<br><strong>Hybrid Alpha:</strong> ' . number_format($hybrid_alpha, 2) . ' (dense weight)';
            }
            $message .= '<br><strong>Verification Status:</strong> ' . esc_html($verification_status);
            if (!empty($verification_details)) {
                $message .= '<br><strong>Verification Details:</strong> ' . esc_html($verification_details);
            }
            
            wp_send_json_success(array(
                'message' => $message,
                'test_id' => $test_id,
                'embedding_dimensions' => count($embedding),
                'verification_status' => $verification_status,
                'verification_details' => $verification_details,
                'upsert_success' => true,
                'hybrid_search_used' => $hybrid_search_used,
                'hybrid_alpha' => $hybrid_search_used ? $hybrid_alpha : null
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Error testing Pinecone upsert: ' . $e->getMessage()
            ));
        } catch (Error $e) {
            wp_send_json_error(array(
                'message' => 'Fatal error testing Pinecone upsert: ' . $e->getMessage()
            ));
        }
    }
    
    public function test_rerank() {
        check_ajax_referer('boat_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        try {
            // Ensure dependencies are loaded
            if (!class_exists('Boat_Chatbot_Reranking_Manager')) {
                $plugin_path = defined('BOAT_CHATBOT_PLUGIN_PATH') 
                    ? BOAT_CHATBOT_PLUGIN_PATH 
                    : plugin_dir_path(dirname(__FILE__));
                $rerank_file = $plugin_path . 'includes/class-reranking-manager.php';
                if (file_exists($rerank_file)) {
                    require_once $rerank_file;
                }
            }
            
            if (!class_exists('Boat_Chatbot_Reranking_Manager')) {
                wp_send_json_error(array('message' => 'Reranking Manager class not found'));
                return;
            }
            
            $rerank_manager = Boat_Chatbot_Reranking_Manager::get_instance();
            $result = $rerank_manager->test_connection();
            
            if (is_array($result) && isset($result['success'])) {
                if ($result['success']) {
                    wp_send_json_success(array(
                        'message' => $result['message']
                    ));
                } else {
                    wp_send_json_error(array(
                        'message' => $result['message']
                    ));
                }
            } else {
                wp_send_json_error(array(
                    'message' => 'Unexpected result format from reranking test'
                ));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Error testing reranking: ' . $e->getMessage()
            ));
        } catch (Error $e) {
            wp_send_json_error(array(
                'message' => 'Fatal error testing reranking: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * Ensure all dependencies for Vector Sync Manager are loaded
     */
    private function ensure_vector_sync_dependencies() {
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
        
        if (!class_exists('Boat_Chatbot_Vector_Sync_Manager')) {
            $file = $includes_path . 'class-vector-sync-manager.php';
            if (file_exists($file)) {
                require_once $file;
            } else {
                return false;
            }
        }
        
        // Load sparse vector generator if not already loaded (needed for build_vocabulary)
        if (!class_exists('Boat_Chatbot_Sparse_Vector_Generator')) {
            $file = $includes_path . 'class-sparse-vector-generator.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
        
        return true;
    }
    
    public function sync_all_records() {
        check_ajax_referer('boat_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        // Ensure all dependencies are loaded
        if (!$this->ensure_vector_sync_dependencies()) {
            wp_send_json_error(array('message' => 'Vector Sync Manager dependencies could not be loaded'));
            return;
        }
        
        $sync_manager = Boat_Chatbot_Vector_Sync_Manager::get_instance();
        
        // Validate keys before syncing
        $validation = $sync_manager->validate_required_keys();
        if (!$validation['valid']) {
            wp_send_json_error(array(
                'message' => $validation['message'],
                'missing' => $validation['missing']
            ));
        }
        
        $results = $sync_manager->sync_all_records();
        
        // Check if there was an error during sync
        if (isset($results['error']) && $results['error']) {
            wp_send_json_error(array(
                'message' => isset($results['message']) ? $results['message'] : 'Sync failed',
                'results' => $results
            ));
        }
        
        // Check if sparse vectors were used
        $sparse_vectors_used = false;
        if (class_exists('Boat_Chatbot_Sparse_Vector_Generator')) {
            $sparse_generator = Boat_Chatbot_Sparse_Vector_Generator::get_instance();
            if ($sparse_generator && $sparse_generator->has_vocabulary()) {
                $sparse_vectors_used = true;
            }
        }
        
        $message = sprintf(
            'Sync completed: %d successful, %d failed out of %d total',
            $results['success'],
            $results['failed'],
            $results['total']
        );
        
        if ($sparse_vectors_used) {
            $message .= '<br><strong>Sparse Vectors:</strong> ✅ Generated and stored (hybrid search enabled)';
        } else {
            $message .= '<br><strong>Sparse Vectors:</strong> ⚠️ Not generated (vocabulary not built - build vocabulary first to enable hybrid search)';
        }
        
        wp_send_json_success(array(
            'message' => $message,
            'results' => $results,
            'sparse_vectors_used' => $sparse_vectors_used
        ));
    }
    
    public function sync_pending_records() {
        check_ajax_referer('boat_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        // Ensure all dependencies are loaded
        if (!$this->ensure_vector_sync_dependencies()) {
            wp_send_json_error(array('message' => 'Vector Sync Manager dependencies could not be loaded'));
            return;
        }
        
        $sync_manager = Boat_Chatbot_Vector_Sync_Manager::get_instance();
        
        // Validate keys before syncing
        $validation = $sync_manager->validate_required_keys();
        if (!$validation['valid']) {
            wp_send_json_error(array(
                'message' => $validation['message'],
                'missing' => $validation['missing']
            ));
        }
        
        $pending_ids = $sync_manager->get_records_needing_sync(100);
        
        if (empty($pending_ids)) {
            wp_send_json_success(array(
                'message' => 'No records need syncing',
                'results' => array('success' => 0, 'failed' => 0, 'total' => 0)
            ));
        }
        
        $results = $sync_manager->sync_records_batch($pending_ids);
        
        // Check if there was an error during sync
        if (isset($results['error']) && $results['error']) {
            wp_send_json_error(array(
                'message' => isset($results['message']) ? $results['message'] : 'Sync failed',
                'results' => $results
            ));
        }
        
        // Check if sparse vectors were used
        $sparse_vectors_used = false;
        if (class_exists('Boat_Chatbot_Sparse_Vector_Generator')) {
            $sparse_generator = Boat_Chatbot_Sparse_Vector_Generator::get_instance();
            if ($sparse_generator && $sparse_generator->has_vocabulary()) {
                $sparse_vectors_used = true;
            }
        }
        
        $message = sprintf(
            'Sync completed: %d successful, %d failed out of %d total',
            $results['success'],
            $results['failed'],
            $results['total']
        );
        
        if ($sparse_vectors_used) {
            $message .= '<br><strong>Sparse Vectors:</strong> ✅ Generated and stored (hybrid search enabled)';
        } else {
            $message .= '<br><strong>Sparse Vectors:</strong> ⚠️ Not generated (vocabulary not built - build vocabulary first to enable hybrid search)';
        }
        
        wp_send_json_success(array(
            'message' => $message,
            'results' => $results,
            'sparse_vectors_used' => $sparse_vectors_used
        ));
    }
    
    /**
     * Build vocabulary from SQL database for sparse vector generation
     */
    public function build_vocabulary() {
        check_ajax_referer('boat_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        // Ensure all dependencies are loaded
        if (!$this->ensure_vector_sync_dependencies()) {
            wp_send_json_error(array('message' => 'Vector Sync Manager dependencies could not be loaded'));
            return;
        }
        
        // Ensure sparse vector generator is loaded
        $plugin_path = defined('BOAT_CHATBOT_PLUGIN_PATH') 
            ? BOAT_CHATBOT_PLUGIN_PATH 
            : plugin_dir_path(dirname(__FILE__));
        
        // Load sparse vector generator if not already loaded
        if (!class_exists('Boat_Chatbot_Sparse_Vector_Generator')) {
            $sparse_file = $plugin_path . 'includes/class-sparse-vector-generator.php';
            if (file_exists($sparse_file)) {
                require_once $sparse_file;
            } else {
                wp_send_json_error(array('message' => 'Sparse Vector Generator file not found at: ' . $sparse_file));
                return;
            }
        }
        
        if (!class_exists('Boat_Chatbot_Sparse_Vector_Generator')) {
            wp_send_json_error(array('message' => 'Sparse Vector Generator class not found after loading file'));
            return;
        }
        
        $sync_manager = Boat_Chatbot_Vector_Sync_Manager::get_instance();
        
        // Validate keys before building vocabulary
        $validation = $sync_manager->validate_required_keys();
        if (!$validation['valid']) {
            wp_send_json_error(array(
                'message' => 'Cannot build vocabulary: ' . $validation['message'],
                'missing' => $validation['missing']
            ));
        }
        
        // Build vocabulary from database (use first 1000 records for performance)
        $result = $sync_manager->build_vocabulary_from_database(1000);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'vocabulary_size' => isset($result['vocabulary_size']) ? $result['vocabulary_size'] : 0,
                'documents_processed' => isset($result['documents_processed']) ? $result['documents_processed'] : 0
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['message']
            ));
        }
    }
    
    /**
     * Test sync - sync exactly 1 record from the database
     * Useful for testing the sync functionality and measuring sync time
     */
    public function test_sync_100_records() {
        check_ajax_referer('boat_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        // Ensure all dependencies are loaded
        if (!$this->ensure_vector_sync_dependencies()) {
            wp_send_json_error(array('message' => 'Vector Sync Manager dependencies could not be loaded'));
            return;
        }
        
        $sync_manager = Boat_Chatbot_Vector_Sync_Manager::get_instance();
        
        // Validate keys before syncing
        $validation = $sync_manager->validate_required_keys();
        if (!$validation['valid']) {
            wp_send_json_error(array(
                'message' => $validation['message'],
                'missing' => $validation['missing']
            ));
        }
        
        // Get the first record ID to sync using reflection (since get_all_record_ids is private)
        $reflection = new ReflectionClass($sync_manager);
        $method = $reflection->getMethod('get_all_record_ids');
        $method->setAccessible(true);
        $all_record_ids = $method->invoke($sync_manager, 1, 0);
        
        if (empty($all_record_ids)) {
            wp_send_json_error(array(
                'message' => 'No records found in database to sync'
            ));
            return;
        }
        
        $record_id = $all_record_ids[0];
        
        // Start timing
        $start_time = microtime(true);
        
        // Sync exactly 1 record
        $success = $sync_manager->sync_record($record_id);
        
        // Calculate elapsed time
        $end_time = microtime(true);
        $elapsed_time = $end_time - $start_time;
        
        // Prepare results
        $results = array(
            'success' => $success ? 1 : 0,
            'failed' => $success ? 0 : 1,
            'total' => 1,
            'record_id' => $record_id,
            'sync_time_seconds' => round($elapsed_time, 4),
            'sync_time_ms' => round($elapsed_time * 1000, 2)
        );
        
        // Check if sync failed
        if (!$success) {
            wp_send_json_error(array(
                'message' => 'Test sync failed for record ID: ' . $record_id,
                'results' => $results
            ));
            return;
        }
        
        // Check if sparse vectors were used
        $sparse_vectors_used = false;
        if (class_exists('Boat_Chatbot_Sparse_Vector_Generator')) {
            $sparse_generator = Boat_Chatbot_Sparse_Vector_Generator::get_instance();
            if ($sparse_generator && $sparse_generator->has_vocabulary()) {
                $sparse_vectors_used = true;
            }
        }
        
        $message = sprintf(
            'Test sync completed: %d successful, %d failed out of %d record',
            $results['success'],
            $results['failed'],
            $results['total']
        );
        
        $message .= '<br><strong>Record ID:</strong> ' . $record_id;
        $message .= '<br><strong>Sync Time:</strong> ' . $results['sync_time_seconds'] . ' seconds (' . $results['sync_time_ms'] . ' ms)';
        
        if ($sparse_vectors_used) {
            $message .= '<br><strong>Sparse Vectors:</strong> ✅ Generated and stored (hybrid search enabled)';
        } else {
            $message .= '<br><strong>Sparse Vectors:</strong> ⚠️ Not generated (vocabulary not built - build vocabulary first to enable hybrid search)';
        }
        
        wp_send_json_success(array(
            'message' => $message,
            'results' => $results,
            'sparse_vectors_used' => $sparse_vectors_used
        ));
    }
    
    /**
     * Test Redis connection
     */
    public function test_redis_connection() {
        check_ajax_referer('boat_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        try {
            // Ensure the class file is loaded
            if (!class_exists('Boat_Chatbot_Redis_Cache_Manager')) {
                $plugin_path = defined('BOAT_CHATBOT_PLUGIN_PATH') 
                    ? BOAT_CHATBOT_PLUGIN_PATH 
                    : plugin_dir_path(dirname(__FILE__));
                $redis_file = $plugin_path . 'includes/class-redis-cache-manager.php';
                
                if (file_exists($redis_file)) {
                    require_once $redis_file;
                } else {
                    wp_send_json_error(array(
                        'message' => 'Redis Cache Manager file not found. Please ensure the plugin is properly installed.'
                    ));
                    return;
                }
                
                if (!class_exists('Boat_Chatbot_Redis_Cache_Manager')) {
                    wp_send_json_error(array(
                        'message' => 'Redis Cache Manager class not found. Please check for syntax errors.'
                    ));
                    return;
                }
            }
            
            $redis_manager = Boat_Chatbot_Redis_Cache_Manager::get_instance();
            
            if (!$redis_manager) {
                wp_send_json_error(array(
                    'message' => 'Failed to initialize Redis Cache Manager'
                ));
                return;
            }
            
            $result = $redis_manager->test_connection();
            
            if (!is_array($result)) {
                wp_send_json_error(array(
                    'message' => 'Unexpected result format from test_connection method'
                ));
                return;
            }
            
            if (isset($result['success']) && $result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Error testing Redis: ' . $e->getMessage()
            ));
        } catch (Error $e) {
            wp_send_json_error(array(
                'message' => 'Fatal error testing Redis: ' . $e->getMessage()
            ));
        }
    }
}
?>
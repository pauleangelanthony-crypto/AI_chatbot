<?php

/**
 * Landing Page Handler for Virtual Yacht Broker
 * Creates a custom landing page at /virtual-yachtbroker
 */
class Boat_Chatbot_Landing_Page
{
    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Register custom rewrite rule for landing page
        add_action('init', array($this, 'add_rewrite_rules'));
        add_action('template_redirect', array($this, 'handle_landing_page'));
        add_filter('query_vars', array($this, 'add_query_vars'));

        // Register shortcode
        add_shortcode('boat_landing_page', array($this, 'add_shortcode'));

        // Enqueue styles and scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Prevent other plugins' CSS from loading on landing page
        add_action('wp_enqueue_scripts', array($this, 'dequeue_conflicting_styles'), 999);
    }

    /**
     * Add rewrite rule for /virtual-yachtbroker and /boat-chatbot
     */
    public function add_rewrite_rules()
    {
        add_rewrite_rule(
            '^virtual-yachtbroker/?$',
            'index.php?boat_chatbot_landing=1',
            'top'
        );
        add_rewrite_rule(
            '^boat-chatbot/?$',
            'index.php?boat_chatbot_full=1',
            'top'
        );
    }

    /**
     * Flush rewrite rules
     */
    public function flush_rewrite_rules()
    {
        $this->add_rewrite_rules();
        flush_rewrite_rules();
    }

    /**
     * Add query var
     */
    public function add_query_vars($vars)
    {
        $vars[] = 'boat_chatbot_landing';
        $vars[] = 'boat_chatbot_full';
        return $vars;
    }

    /**
     * Handle landing page template
     */
    public function handle_landing_page()
    {
        // Check for chatbot full page first
        if (get_query_var('boat_chatbot_full')) {
            $this->render_chatbot_full_page();
            exit;
        }

        // Check query var first (works when rewrite rules are properly set)
        if (get_query_var('boat_chatbot_landing')) {
            $this->render_landing_page();
            exit;
        }

        // Fallback: Check URL directly (works even if rewrite rules aren't flushed)
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $parsed_uri = parse_url($request_uri);
        $path = isset($parsed_uri['path']) ? $parsed_uri['path'] : '';

        // Remove leading/trailing slashes and compare
        $path = trim($path, '/');
        if ($path === 'boat-chatbot') {
            $this->render_chatbot_full_page();
            exit;
        } elseif ($path === 'virtual-yachtbroker') {
            $this->render_landing_page();
            exit;
        }
    }

    /**
     * Shortcode callback to display landing page content
     *
     * @param array $atts Shortcode attributes
     * @return string HTML content for the landing page
     */
    public function add_shortcode($atts = array())
    {
        error_log('call me');
        // Ensure assets are enqueued when shortcode is used
        $this->enqueue_shortcode_assets();

        // Start output buffering to capture the content
        ob_start();

        // Render the landing page content
        $this->render_shortcode_content();

        // Get the buffered content and return it
        return ob_get_clean();
    }

    /**
     * Enqueue assets when shortcode is used
     */
    private function enqueue_shortcode_assets()
    {
        wp_enqueue_style(
            'boat-chatbot-landing',
            BOAT_CHATBOT_PLUGIN_URL . 'assets/landing.css',
            array(),
            BOAT_CHATBOT_VERSION
        );

        wp_enqueue_script(
            'boat-chatbot-landing',
            BOAT_CHATBOT_PLUGIN_URL . 'assets/landing.js',
            array('jquery'),
            BOAT_CHATBOT_VERSION,
            true
        );

        // Enqueue chatbot frontend assets
        wp_enqueue_style(
            'boat-chatbot-frontend',
            BOAT_CHATBOT_PLUGIN_URL . 'assets/frontend.css',
            array(),
            BOAT_CHATBOT_VERSION
        );

        wp_enqueue_script(
            'boat-chatbot-frontend',
            BOAT_CHATBOT_PLUGIN_URL . 'assets/frontend.js',
            array('jquery'),
            BOAT_CHATBOT_VERSION,
            true
        );

        // Localize script for AJAX
        wp_localize_script('boat-chatbot-frontend', 'boatChatbot', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('boat_chatbot_nonce'),
            'restUrl' => rest_url('boat-chatbot/v1/'),
            'restNonce' => wp_create_nonce('wp_rest')
        ));
    }

    /**
     * Render landing page content for shortcode
     */
    private function render_shortcode_content()
    {
        ?>
        <div class="boat-landing-page-content">
            <!-- Top Bar -->
            <div class="boat-topbar">
                <div class="boat-topbar-content">
                    <div class="boat-topbar-left">
                        <a href="<?php echo esc_url(home_url('/')); ?>" class="boat-logo">
                            <?php
                            $logo_url = BOAT_CHATBOT_PLUGIN_URL . 'assets/images/boat-logo.png';
                            $logo_path = BOAT_CHATBOT_PLUGIN_PATH . 'assets/images/boat-logo.png';
                            if (file_exists($logo_path)):
                                ?>
                                <img src="<?php echo esc_url($logo_url); ?>" alt="BOAT.COM" class="boat-logo-img">
                            <?php else: ?>
                                <span class="boat-logo-boat">BOAT</span><span class="boat-logo-com">.com</span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Video Section -->
            <div class="boat-video-section" id="boat-landing-video-section">
                        <?php
                        // Get uploaded video from settings, fallback to default
                        $video_id = get_option('boat_chatbot_landing_video', 0);
                        $video_mobile_id = get_option('boat_chatbot_landing_video_mobile', 0);
                        
                        $video_url = '';
                        $video_mobile_url = '';
                        
                        if ($video_id) {
                            $video_url = wp_get_attachment_url($video_id);
                        }
                        if ($video_mobile_id) {
                            $video_mobile_url = wp_get_attachment_url($video_mobile_id);
                        }
                        
                        // Fallback to default video if no uploaded video
                        if (empty($video_url)) {
                            $video_url = BOAT_CHATBOT_PLUGIN_URL . 'assets/video/marina-sunset.mp4';
                            $video_path = BOAT_CHATBOT_PLUGIN_PATH . 'assets/video/marina-sunset.mp4';
                        } else {
                            $video_path = get_attached_file($video_id);
                        }
                        
                        // Fallback to desktop video for mobile if no mobile video uploaded
                        if (empty($video_mobile_url)) {
                            $video_mobile_url = $video_url;
                        }
                        
                        $image_url = BOAT_CHATBOT_PLUGIN_URL . 'assets/images/marina-sunset.jpg';
                        $image_path = BOAT_CHATBOT_PLUGIN_PATH . 'assets/images/marina-sunset.jpg';
                        ?>
                        <?php if (!empty($video_url) && (file_exists($video_path) || $video_id)): ?>
                            <video id="boat-main-video" class="boat-video" autoplay loop playsinline preload="auto" webkit-playsinline="true" x-webkit-airplay="allow"
                                   data-video-desktop="<?php echo esc_url($video_url); ?>"
                                   <?php if (!empty($video_mobile_url)): ?>
                                   data-video-mobile="<?php echo esc_url($video_mobile_url); ?>"
                                   <?php endif; ?>>
                                <source src="<?php echo esc_url($video_url); ?>" type="video/mp4" class="boat-video-source">
                                <?php if (file_exists($image_path)): ?>
                                    <img src="<?php echo esc_url($image_url); ?>" alt="Featured Boat">
                                <?php endif; ?>
                            </video>
                            <button type="button" class="boat-video-mute-btn" id="boat-video-mute-btn" aria-label="Unmute Video" title="Unmute Video">
                                <svg class="boat-mute-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/>
                                </svg>
                                <svg class="boat-unmute-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" style="display: none;">
                                    <path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/>
                                </svg>
                            </button>
                        <?php elseif (file_exists($image_path)): ?>
                            <div class="boat-video-placeholder" style="background-image: url('<?php echo esc_url($image_url); ?>'); background-size: cover; background-position: center; width: 100%; height: 100%;"></div>
                        <?php else: ?>
                            <!-- Default gradient background if no media files -->
                            <div class="boat-video-placeholder" style="background: linear-gradient(180deg, #1a3a5f 0%, #2d5a8a 30%, #4a7ba7 60%, #ff8c42 80%, #ffb347 100%); width: 100%; height: 100%;"></div>
                        <?php endif; ?>
                    </div>
            
            <!-- Chat Container (Hidden initially, shown when chat starts) -->
            <div class="boat-landing-chat-container" id="boat-landing-chat-container" style="display: none;">
                <div class="boat-chatbot-messages-container" id="boat-landing-messages-container">
                    <!-- Messages will be inserted here -->
                </div>
            </div>
            
            <!-- Chatbot Input Section (Bottom) -->
            <div class="boat-chatbot-section">
                <div class="boat-chatbot-container">
                    <div class="boat-chatbot-input-wrapper">
                        <button type="button" class="boat-chatbot-voice-btn" id="boat-chatbot-voice" aria-label="Voice Input" title="Voice Input">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
                                <path d="M12 14c1.66 0 2.99-1.34 2.99-3L15 5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5.3-3c0 3-2.54 5.1-5.3 5.1S6.7 14 6.7 11H5c0 3.41 2.72 6.23 6 6.72V21h2v-3.28c3.28-.48 6-3.3 6-6.72h-1.7z"/>
                            </svg>
                        </button>
                        <button type="button" class="boat-chatbot-image-btn" id="boat-chatbot-image" aria-label="Generate Image" title="Generate Image">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
                                <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                            </svg>
                        </button>
                        <div class="boat-chatbot-input-container">
                            <input 
                                type="text" 
                                id="message-input" 
                                class="boat-chatbot-input" 
                                style="margin: 0;"
                                placeholder="<?php echo esc_attr(get_option('boat_chatbot_input_placeholder', 'Type your query here...')); ?>" 
                                autocomplete="off"
                            >
                            <button type="button" class="boat-chatbot-send-btn" id="send-btn" aria-label="Send Message">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
                                    <path d="M20 4L4 12l6.5 2.5L15 20l5-16z"/>
                                </svg>
                            </button>
                        </div>
                        <div class="boat-chatbot-action-buttons">
                        <button type="button" class="boat-chatbot-action-btn boat-action-new-chat" id="new-chat-btn" aria-label="New Chat" title="New Chat">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                            </svg>
                            <span>New Chat</span>
                        </button>
                        <a href="<?php echo esc_url(home_url('/')); ?>" class="boat-chatbot-action-btn boat-action-home" aria-label="Home" title="Home">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                            </svg>
                        </a>
                        <a href="<?php echo esc_url(home_url('/yachts-for-sale')); ?>" class="boat-chatbot-action-btn boat-action-search" aria-label="Search" title="Search">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                            </svg>
                        </a>
                        <button type="button" class="boat-chatbot-action-btn boat-action-translate" data-action="translate" aria-label="Translate" title="Translate">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12.87 15.07l-2.54-2.51.03-.03c1.74-1.94 2.98-4.17 3.71-6.53H17V4h-7V2H8v2H1v1.99h11.17C11.5 7.92 10.44 9.75 9 11.35 8.07 10.32 7.3 9.19 6.69 8h-2c.73 1.63 1.73 3.17 2.98 4.56l-5.09 5.02L4 19l5-5 3.11 3.11.76-2.04zM18.5 10h-2L12 22h2l1.12-3h4.75L21 22h2l-4.5-12zm-2.62 7l1.62-4.33L19.12 17h-3.24z"/>
                            </svg>
                        </button>
                        <button type="button" class="boat-chatbot-action-btn boat-action-settings" data-action="settings" aria-label="Settings" title="Settings">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94L14.4 2.81c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.07.62-.07.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/>
                            </svg>
                        </button>
                        <button type="button" class="boat-chatbot-action-btn boat-action-help" data-action="help" aria-label="Help" title="Help">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"/>
                            </svg>
                        </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Help Modal -->
            <div id="boat-help-modal" class="boat-help-modal" style="display: none;">
                <div class="boat-help-modal-overlay"></div>
                <div class="boat-help-modal-content">
                    <div class="boat-help-modal-header">
                        <h2>Help & Guide</h2>
                        <button type="button" class="boat-help-modal-close" aria-label="Close Help Modal">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                            </svg>
                        </button>
                    </div>
                    <div class="boat-help-modal-body">
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
                        echo wp_kses_post($help_content);
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the full chatbot page (like ChatGPT)
     */
    private function render_chatbot_full_page()
    {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
            <title>Virtual Yacht Broker Chat - boat.com</title>
            <?php wp_head(); ?>
        </head>
        <body class="boat-chatbot-full-page">
            <div class="boat-chatbot-full-container">
                <!-- Sidebar -->
                <aside class="boat-chatbot-sidebar">
                    <div class="boat-chatbot-sidebar-header">
                        <div class="boat-chatbot-sidebar-logo">BOAT.COM</div>
                        <button type="button" class="boat-chatbot-new-chat" id="boat-new-chat">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                            </svg>
                            <span>New Chat</span>
                        </button>
                    </div>
                    <div class="boat-chatbot-sidebar-content">
                        <div class="boat-chatbot-history">
                            <!-- Chat history will be inserted here -->
                        </div>
                    </div>
                    <div class="boat-chatbot-sidebar-footer">
                        <a href="<?php echo esc_url(home_url('/virtual-yachtbroker')); ?>" class="boat-chatbot-back-link">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
                            </svg>
                            <span>Back to Landing</span>
                        </a>
                    </div>
                </aside>
                
                <!-- Main Chat Area -->
                <main class="boat-chatbot-main">
                    <div class="boat-chatbot-messages-container" id="boat-chatbot-messages-container">
                        <!-- Welcome State -->
                        <div class="boat-chatbot-welcome-state" id="boat-chatbot-welcome-state">
                            <svg class="boat-chatbot-welcome-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                            </svg>
                            <div class="boat-chatbot-welcome-text">How can I help you?</div>
                            <div class="boat-chatbot-welcome-subtext">BOAT.COM Broker is ready to assist you with boat listings and inquiries</div>
                        </div>
                        <!-- Messages will be inserted here -->
                    </div>
                    
                    <!-- Input Area -->
                    <div class="boat-chatbot-input-area">
                        <div class="boat-chatbot-input-wrapper-full">
                            <button type="button" class="boat-chatbot-voice-btn-full" id="boat-chatbot-voice-full" aria-label="Voice Input">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 14c1.66 0 2.99-1.34 2.99-3L15 5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5.3-3c0 3-2.54 5.1-5.3 5.1S6.7 14 6.7 11H5c0 3.41 2.72 6.23 6 6.72V21h2v-3.28c3.28-.48 6-3.3 6-6.72h-1.7z"/>
                                </svg>
                            </button>
                            <textarea 
                                id="boat-chatbot-input-full" 
                                class="boat-chatbot-input-full" 
                                placeholder="<?php echo esc_attr(get_option('boat_chatbot_input_placeholder', 'Type your query here...')); ?>"
                                rows="1"
                            ></textarea>
                            <button type="button" class="boat-chatbot-send-btn-full" id="boat-chatbot-send-full" aria-label="Send Message">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                                </svg>
                            </button>
                        </div>
                        <div class="boat-chatbot-input-footer">
                            <p class="boat-chatbot-disclaimer">Virtual Yacht Broker can make mistakes. Check important info.</p>
                        </div>
                    </div>
                </main>
            </div>
            
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }

    /**
     * Dequeue conflicting styles from other plugins on landing page
     */
    public function dequeue_conflicting_styles()
    {
        // Check if we're on the landing page
        $is_landing_page = false;
        if (get_query_var('boat_chatbot_landing')) {
            $is_landing_page = true;
        }
        
        // Fallback: Check URL path directly
        if (!$is_landing_page) {
            $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            $parsed_uri = parse_url($request_uri);
            $path = isset($parsed_uri['path']) ? trim($parsed_uri['path'], '/') : '';
            if ($path === 'virtual-yachtbroker') {
                $is_landing_page = true;
            }
        }
        
        if ($is_landing_page) {
            // List of common plugin handles to dequeue (add more as needed)
            $plugins_to_dequeue = array(
                'contact-form-7',
                'woocommerce',
                'elementor',
                'wpforms',
                'yoast',
                'jetpack',
                'cookieyes',
                'cookie-law-info',
            );
            
            // Dequeue known conflicting plugins
            foreach ($plugins_to_dequeue as $plugin_prefix) {
                global $wp_styles;
                if (isset($wp_styles->registered)) {
                    foreach ($wp_styles->registered as $handle => $style) {
                        if (strpos($handle, $plugin_prefix) !== false) {
                            wp_dequeue_style($handle);
                        }
                    }
                }
            }
        }
    }

    /**
     * Enqueue landing page assets
     */
    public function enqueue_assets()
    {
        // Check if we're on the chatbot full page
        $is_chatbot_full = false;
        if (get_query_var('boat_chatbot_full')) {
            $is_chatbot_full = true;
        }

        if (!$is_chatbot_full) {
            $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            $parsed_uri = parse_url($request_uri);
            $path = isset($parsed_uri['path']) ? trim($parsed_uri['path'], '/') : '';
            if ($path === 'boat-chatbot') {
                $is_chatbot_full = true;
            }
        }

        if ($is_chatbot_full) {
            wp_enqueue_style(
                'boat-chatbot-full',
                BOAT_CHATBOT_PLUGIN_URL . 'assets/chatbot-full.css',
                array(),
                BOAT_CHATBOT_VERSION
            );

            wp_enqueue_script(
                'boat-chatbot-full',
                BOAT_CHATBOT_PLUGIN_URL . 'assets/chatbot-full.js',
                array('jquery'),
                BOAT_CHATBOT_VERSION,
                true
            );

            // Enqueue chatbot frontend assets
            wp_enqueue_style(
                'boat-chatbot-frontend',
                BOAT_CHATBOT_PLUGIN_URL . 'assets/frontend.css',
                array(),
                BOAT_CHATBOT_VERSION
            );

            wp_enqueue_script(
                'boat-chatbot-frontend',
                BOAT_CHATBOT_PLUGIN_URL . 'assets/frontend.js',
                array('jquery'),
                BOAT_CHATBOT_VERSION,
                true
            );

            // Localize script for AJAX
            wp_localize_script('boat-chatbot-frontend', 'boatChatbot', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('boat_chatbot_nonce'),
                'restUrl' => rest_url('boat-chatbot/v1/'),
                'restNonce' => wp_create_nonce('wp_rest')
            ));

            return;
        }

        // Check if we're on the landing page
        $is_landing_page = false;

        // Check query var (works when rewrite rules are set)
        if (get_query_var('boat_chatbot_landing')) {
            $is_landing_page = true;
        }

        // Fallback: Check URL path directly
        if (!$is_landing_page) {
            $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            $parsed_uri = parse_url($request_uri);
            $path = isset($parsed_uri['path']) ? trim($parsed_uri['path'], '/') : '';
            if ($path === 'virtual-yachtbroker') {
                $is_landing_page = true;
            }
        }

        if ($is_landing_page) {
            wp_enqueue_style(
                'boat-chatbot-landing',
                BOAT_CHATBOT_PLUGIN_URL . 'assets/landing.css',
                array(),
                BOAT_CHATBOT_VERSION
            );

            wp_enqueue_script(
                'boat-chatbot-landing',
                BOAT_CHATBOT_PLUGIN_URL . 'assets/landing.js',
                array('jquery'),
                BOAT_CHATBOT_VERSION,
                true
            );

            // Enqueue chatbot frontend assets
            wp_enqueue_style(
                'boat-chatbot-frontend',
                BOAT_CHATBOT_PLUGIN_URL . 'assets/frontend.css',
                array(),
                BOAT_CHATBOT_VERSION
            );

            wp_enqueue_script(
                'boat-chatbot-frontend',
                BOAT_CHATBOT_PLUGIN_URL . 'assets/frontend.js',
                array('jquery'),
                BOAT_CHATBOT_VERSION,
                true
            );

            // Localize script for AJAX
            wp_localize_script('boat-chatbot-frontend', 'boatChatbot', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('boat_chatbot_nonce'),
                'restUrl' => rest_url('boat-chatbot/v1/'),
                'restNonce' => wp_create_nonce('wp_rest')
            ));
        }
    }

    /**
     * Render the landing page
     */
    private function render_landing_page()
    {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
            <title>Virtual Yacht Broker - boat.com</title>
        </head>
        <body class="boat-landing-page">
            <!-- Top Bar -->
            <div class="boat-topbar">
                <div class="boat-topbar-content">
                    <div class="boat-topbar-left">
                        <a href="<?php echo esc_url(home_url('/')); ?>" class="boat-logo">
                            <?php
                            $logo_url = BOAT_CHATBOT_PLUGIN_URL . 'assets/images/boat-logo.png';
                            $logo_path = BOAT_CHATBOT_PLUGIN_PATH . 'assets/images/boat-logo.png';
                            if (file_exists($logo_path)):
                                ?>
                                <img src="<?php echo esc_url($logo_url); ?>" alt="BOAT.COM" class="boat-logo-img">
                            <?php else: ?>
                                <span class="boat-logo-boat">BOAT</span><span class="boat-logo-com">.com</span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Main Layout -->
            <main class="boat-main-content">
                <!-- Video Section (Full Content Area) -->
                <div class="boat-video-section" id="boat-landing-video-section">
                    <div class="boat-video-container">
                        <?php
                        // Get uploaded video from settings, fallback to default
                        $video_id = get_option('boat_chatbot_landing_video', 0);
                        $video_mobile_id = get_option('boat_chatbot_landing_video_mobile', 0);
                        
                        $video_url = '';
                        $video_mobile_url = '';
                        
                        if ($video_id) {
                            $video_url = wp_get_attachment_url($video_id);
                        }
                        if ($video_mobile_id) {
                            $video_mobile_url = wp_get_attachment_url($video_mobile_id);
                        }
                        
                        // Fallback to default video if no uploaded video
                        if (empty($video_url)) {
                            $video_url = BOAT_CHATBOT_PLUGIN_URL . 'assets/video/marina-sunset.mp4';
                            $video_path = BOAT_CHATBOT_PLUGIN_PATH . 'assets/video/marina-sunset.mp4';
                        } else {
                            $video_path = get_attached_file($video_id);
                        }
                        
                        // Fallback to desktop video for mobile if no mobile video uploaded
                        if (empty($video_mobile_url)) {
                            $video_mobile_url = $video_url;
                        }
                        
                        $image_url = BOAT_CHATBOT_PLUGIN_URL . 'assets/images/marina-sunset.jpg';
                        $image_path = BOAT_CHATBOT_PLUGIN_PATH . 'assets/images/marina-sunset.jpg';
                        ?>
                        <?php if (!empty($video_url) && (file_exists($video_path) || $video_id)): ?>
                            <video id="boat-main-video" class="boat-video" autoplay loop playsinline preload="auto" webkit-playsinline="true" x-webkit-airplay="allow"
                                   data-video-desktop="<?php echo esc_url($video_url); ?>"
                                   <?php if (!empty($video_mobile_url)): ?>
                                   data-video-mobile="<?php echo esc_url($video_mobile_url); ?>"
                                   <?php endif; ?>>
                                <source src="<?php echo esc_url($video_url); ?>" type="video/mp4" class="boat-video-source">
                                <?php if (file_exists($image_path)): ?>
                                    <img src="<?php echo esc_url($image_url); ?>" alt="Marina at Sunset">
                                <?php endif; ?>
                            </video>
                            <button type="button" class="boat-video-mute-btn" id="boat-video-mute-btn" aria-label="Unmute Video" title="Unmute Video">
                                <svg class="boat-mute-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/>
                                </svg>
                                <svg class="boat-unmute-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" style="display: none;">
                                    <path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/>
                                </svg>
                            </button>
                        <?php elseif (file_exists($image_path)): ?>
                            <div class="boat-video-placeholder" style="background-image: url('<?php echo esc_url($image_url); ?>'); background-size: cover; background-position: center; width: 100%; height: 100%;"></div>
                        <?php else: ?>
                            <!-- Default gradient background if no media files -->
                            <div class="boat-video-placeholder" style="background: linear-gradient(180deg, #1a3a5f 0%, #2d5a8a 30%, #4a7ba7 60%, #ff8c42 80%, #ffb347 100%); width: 100%; height: 100%;"></div>
                        <?php endif; ?>
                        <div class="boat-video-overlay"></div>
                    </div>
                </div>
                
            </main>
            
            <!-- Help Modal -->
            <div id="boat-help-modal" class="boat-help-modal" style="display: none;">
                <div class="boat-help-modal-overlay"></div>
                <div class="boat-help-modal-content">
                    <div class="boat-help-modal-header">
                        <h2>Help & Guide</h2>
                        <button type="button" class="boat-help-modal-close" aria-label="Close Help Modal">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                            </svg>
                        </button>
                    </div>
                    <div class="boat-help-modal-body">
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
                        echo wp_kses_post($help_content);
                        ?>
                    </div>
                </div>
            </div>
            <!-- Chat Container (Hidden initially, shown when chat starts) -->
            <div class="boat-landing-chat-container" id="boat-landing-chat-container" style="display: none;">
                <div class="boat-chatbot-messages-container" id="boat-landing-messages-container">
                    <!-- Messages will be inserted here -->
                </div>
            </div>
            
            <!-- Chatbot Input Section (Bottom) -->
            <div class="boat-chatbot-section">
                <div class="boat-chatbot-container">
                    <div class="boat-chatbot-input-wrapper">
                        <button type="button" class="boat-chatbot-voice-btn" id="boat-chatbot-voice" aria-label="Voice Input" title="Voice Input">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
                                <path d="M12 14c1.66 0 2.99-1.34 2.99-3L15 5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5.3-3c0 3-2.54 5.1-5.3 5.1S6.7 14 6.7 11H5c0 3.41 2.72 6.23 6 6.72V21h2v-3.28c3.28-.48 6-3.3 6-6.72h-1.7z"/>
                            </svg>
                        </button>
                        <button type="button" class="boat-chatbot-image-btn" id="boat-chatbot-image" aria-label="Generate Image" title="Generate Image">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
                                <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                            </svg>
                        </button>
                        <div class="boat-chatbot-input-container">
                            <input 
                                type="text" 
                                id="boat-chatbot-message" 
                                class="boat-chatbot-input" 
                                placeholder="<?php echo esc_attr(get_option('boat_chatbot_input_placeholder', 'Type your query here...')); ?>"
                                autocomplete="off"
                            >
                            <button type="button" class="boat-chatbot-send-btn" id="boat-chatbot-send" aria-label="Send Message">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                                </svg>
                            </button>
                        </div>
                        <div class="boat-chatbot-action-buttons">
                        <a href="<?php echo esc_url(home_url('/')); ?>" class="boat-chatbot-action-btn boat-action-home" aria-label="Home" title="Home">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                            </svg>
                        </a>
                        <a href="<?php echo esc_url(home_url('/yachts-for-sale')); ?>" class="boat-chatbot-action-btn boat-action-search" aria-label="Search" title="Search">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                            </svg>
                        </a>
                        <button type="button" class="boat-chatbot-action-btn boat-action-translate" data-action="translate" aria-label="Translate" title="Translate">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12.87 15.07l-2.54-2.51.03-.03c1.74-1.94 2.98-4.17 3.71-6.53H17V4h-7V2H8v2H1v1.99h11.17C11.5 7.92 10.44 9.75 9 11.35 8.07 10.32 7.3 9.19 6.69 8h-2c.73 1.63 1.73 3.17 2.98 4.56l-5.09 5.02L4 19l5-5 3.11 3.11.76-2.04zM18.5 10h-2L12 22h2l1.12-3h4.75L21 22h2l-4.5-12zm-2.62 7l1.62-4.33L19.12 17h-3.24z"/>
                            </svg>
                        </button>
                        <button type="button" class="boat-chatbot-action-btn boat-action-settings" data-action="settings" aria-label="Settings" title="Settings">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94L14.4 2.81c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.07.62-.07.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/>
                            </svg>
                        </button>
                        <button type="button" class="boat-chatbot-action-btn boat-action-help" data-action="help" aria-label="Help" title="Help">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"/>
                            </svg>
                        </button>
                        </div>
                    </div>
                </div>
            </div>
            
            
            
        </body>
        </html>
        <?php
    }
}

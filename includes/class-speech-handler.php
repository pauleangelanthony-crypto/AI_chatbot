<?php

/**
 * Speech-to-Text and Text-to-Speech Handler
 * Handles Google Cloud Speech-to-Text and ElevenLabs TTS integration
 */
class Boat_Chatbot_Speech_Handler {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor
    }
    
    /**
     * Convert speech audio to text using Google Cloud Speech-to-Text
     * 
     * @param string $audio_data Base64 encoded audio data
     * @param string $audio_format Audio format (e.g., 'webm', 'wav', 'mp3')
     * @return array|WP_Error Response with transcribed text or error
     */
    public function speech_to_text($audio_data, $audio_format = 'webm') {
        $api_key = get_option('boat_chatbot_google_speech_api_key');
        $project_id = get_option('boat_chatbot_google_speech_project_id');
        
        if (empty($api_key) || empty($project_id)) {
            return new WP_Error('missing_credentials', 'Google Cloud Speech API credentials are not configured.');
        }
        
        // Determine encoding based on format
        $encoding_map = array(
            'webm' => 'WEBM_OPUS',
            'wav' => 'LINEAR16',
            'mp3' => 'MP3',
            'flac' => 'FLAC',
            'ogg' => 'OGG_OPUS'
        );
        
        $encoding = isset($encoding_map[strtolower($audio_format)]) 
            ? $encoding_map[strtolower($audio_format)] 
            : 'WEBM_OPUS';
        
        // Sample rate - default to 48000 for webm, 16000 for others
        $sample_rate = (strtolower($audio_format) === 'webm') ? 48000 : 16000;
        
        // Prepare request body
        $request_body = array(
            'config' => array(
                'encoding' => $encoding,
                'sampleRateHertz' => $sample_rate,
                'languageCode' => 'en-US',
                'enableAutomaticPunctuation' => true,
                'model' => 'latest_long', // Use latest_long for better accuracy
            ),
            'audio' => array(
                'content' => $audio_data
            )
        );
        
        // Google Cloud Speech-to-Text API endpoint
        $api_url = sprintf(
            'https://speech.googleapis.com/v1/speech:recognize?key=%s',
            urlencode($api_key)
        );
        
        // Make API request
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($request_body),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($response_code !== 200) {
            $error_message = isset($response_body['error']['message']) 
                ? $response_body['error']['message'] 
                : 'Unknown error from Google Cloud Speech API';
            return new WP_Error('api_error', $error_message, array('status' => $response_code));
        }
        
        // Extract transcribed text
        if (isset($response_body['results']) && !empty($response_body['results'])) {
            $transcript = '';
            foreach ($response_body['results'] as $result) {
                if (isset($result['alternatives'][0]['transcript'])) {
                    $transcript .= $result['alternatives'][0]['transcript'] . ' ';
                }
            }
            return array(
                'success' => true,
                'text' => trim($transcript),
                'confidence' => isset($response_body['results'][0]['alternatives'][0]['confidence']) 
                    ? $response_body['results'][0]['alternatives'][0]['confidence'] 
                    : null
            );
        }
        
        return new WP_Error('no_transcript', 'No transcript found in API response.');
    }
    
    /**
     * Convert text to speech using ElevenLabs
     * 
     * @param string $text Text to convert to speech
     * @param string $voice_id Voice ID (optional, uses default from settings if not provided)
     * @return array|WP_Error Response with audio data or error
     */
    public function text_to_speech($text, $voice_id = null) {
        $api_key = get_option('boat_chatbot_elevenlabs_api_key');
        
        if (empty($api_key)) {
            return new WP_Error('missing_credentials', 'ElevenLabs API key is not configured.');
        }
        
        // Use provided voice_id or get from settings
        if (empty($voice_id)) {
            $voice_id = get_option('boat_chatbot_elevenlabs_voice_id', '21m00Tcm4TlvDq8ikWAM');
        }
        
        // ElevenLabs API endpoint
        $api_url = sprintf(
            'https://api.elevenlabs.io/v1/text-to-speech/%s',
            urlencode($voice_id)
        );
        
        // Prepare request body
        $request_body = array(
            'text' => $text,
            'model_id' => 'eleven_multilingual_v2', // Use multilingual model for better quality
            'voice_settings' => array(
                'stability' => 0.5,
                'similarity_boost' => 0.75,
                'style' => 0.0,
                'use_speaker_boost' => true
            )
        );
        
        // Make API request
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Accept' => 'audio/mpeg',
                'Content-Type' => 'application/json',
                'xi-api-key' => $api_key
            ),
            'body' => json_encode($request_body),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            $response_body = json_decode(wp_remote_retrieve_body($response), true);
            $error_message = isset($response_body['detail']['message']) 
                ? $response_body['detail']['message'] 
                : 'Unknown error from ElevenLabs API';
            return new WP_Error('api_error', $error_message, array('status' => $response_code));
        }
        
        // Get audio data
        $audio_data = wp_remote_retrieve_body($response);
        
        if (empty($audio_data)) {
            return new WP_Error('no_audio', 'No audio data received from ElevenLabs API.');
        }
        
        // Convert to base64 for JSON response
        $audio_base64 = base64_encode($audio_data);
        
        return array(
            'success' => true,
            'audio' => $audio_base64,
            'format' => 'mp3',
            'size' => strlen($audio_data)
        );
    }
    
    /**
     * Check if STT is enabled
     * Note: STT now uses Web Speech API (browser built-in, no API key needed)
     * 
     * @return bool
     */
    public function is_stt_enabled() {
        return get_option('boat_chatbot_stt_enabled', false) === true;
    }
    
    /**
     * Check if TTS is enabled and configured
     * 
     * @return bool
     */
    public function is_tts_enabled() {
        if (!get_option('boat_chatbot_tts_enabled', false)) {
            return false;
        }
        
        $api_key = get_option('boat_chatbot_elevenlabs_api_key');
        
        return !empty($api_key);
    }
}


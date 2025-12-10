<?php

/**
 * Redis Cache Manager
 * 
 * Provides direct Redis caching functionality with fallback to WordPress transients
 */
class Boat_Chatbot_Redis_Cache_Manager {
    
    private static $instance = null;
    private $redis = null;
    private $enabled = false;
    private $cache_prefix = 'boat_chatbot:';
    private $default_expiration = 300; // 5 minutes
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_redis();
    }
    
    /**
     * Initialize Redis connection
     */
    private function init_redis() {
        // Check if Redis extension is available
        if (!class_exists('Redis')) {
            $this->enabled = false;
            return;
        }
        
        // Get Redis configuration
        $host = get_option('boat_chatbot_redis_host', 'localhost');
        $port = absint(get_option('boat_chatbot_redis_port', 6379));
        $password = get_option('boat_chatbot_redis_password', '');
        $database = absint(get_option('boat_chatbot_redis_database', 0));
        $enabled = get_option('boat_chatbot_redis_enabled', false);
        
        // Check if Redis is enabled
        if (!$enabled) {
            $this->enabled = false;
            return;
        }
        
        try {
            $this->redis = new Redis();
            
            // Set connection timeout (2 seconds)
            $connected = @$this->redis->connect($host, $port, 2.0);
            
            if (!$connected) {
                $this->enabled = false;
                return;
            }
            
            // Authenticate if password is set
            if (!empty($password)) {
                $auth_result = $this->redis->auth($password);
                if (!$auth_result) {
                    $this->enabled = false;
                    $this->redis->close();
                    $this->redis = null;
                    return;
                }
            }
            
            // Select database
            if ($database > 0) {
                $this->redis->select($database);
            }
            
            // Test connection with a ping
            $ping_result = $this->redis->ping();
            if ($ping_result !== '+PONG') {
                $this->enabled = false;
                $this->redis->close();
                $this->redis = null;
                return;
            }
            
            $this->enabled = true;
            
        } catch (Exception $e) {
            $this->enabled = false;
            if ($this->redis) {
                try {
                    $this->redis->close();
                } catch (Exception $close_ex) {
                    // Ignore close errors
                }
                $this->redis = null;
            }
        } catch (Error $e) {
            $this->enabled = false;
            if ($this->redis) {
                try {
                    $this->redis->close();
                } catch (Exception $close_ex) {
                    // Ignore close errors
                }
                $this->redis = null;
            }
        }
    }
    
    /**
     * Get cached value
     * 
     * @param string $key Cache key
     * @return mixed|false Cached value or false if not found
     */
    public function get($key) {
        if (!$this->enabled || !$this->redis) {
            // Fallback to WordPress transients
            return get_transient($this->cache_prefix . $key);
        }
        
        try {
            $full_key = $this->cache_prefix . $key;
            $value = $this->redis->get($full_key);
            
            if ($value === false) {
                return false;
            }
            
            // Unserialize the value
            $unserialized = @unserialize($value);
            if ($unserialized === false && $value !== serialize(false)) {
                // If unserialize failed and it's not a serialized false, return the raw value
                return $value;
            }
            
            return $unserialized;
            
        } catch (Exception $e) {
            // Fallback to WordPress transients
            return get_transient($this->cache_prefix . $key);
        }
    }
    
    /**
     * Set cached value
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $expiration Expiration time in seconds (null uses default)
     * @return bool Success status
     */
    public function set($key, $value, $expiration = null) {
        if ($expiration === null) {
            $expiration = $this->default_expiration;
        }
        
        if (!$this->enabled || !$this->redis) {
            // Fallback to WordPress transients
            return set_transient($this->cache_prefix . $key, $value, $expiration);
        }
        
        try {
            $full_key = $this->cache_prefix . $key;
            $serialized = serialize($value);
            
            if ($expiration > 0) {
                $result = $this->redis->setex($full_key, $expiration, $serialized);
            } else {
                $result = $this->redis->set($full_key, $serialized);
            }
            
            return $result !== false;
            
        } catch (Exception $e) {
            // Fallback to WordPress transients
            return set_transient($this->cache_prefix . $key, $value, $expiration);
        }
    }
    
    /**
     * Delete cached value
     * 
     * @param string $key Cache key
     * @return bool Success status
     */
    public function delete($key) {
        if (!$this->enabled || !$this->redis) {
            // Fallback to WordPress transients
            return delete_transient($this->cache_prefix . $key);
        }
        
        try {
            $full_key = $this->cache_prefix . $key;
            $result = $this->redis->del($full_key);
            return $result > 0;
            
        } catch (Exception $e) {
            // Fallback to WordPress transients
            return delete_transient($this->cache_prefix . $key);
        }
    }
    
    /**
     * Delete all cache entries with prefix
     * 
     * @return int Number of keys deleted
     */
    public function flush() {
        if (!$this->enabled || !$this->redis) {
            // Fallback: delete WordPress transients (this is more complex, so we'll just return 0)
            return 0;
        }
        
        try {
            $pattern = $this->cache_prefix . '*';
            $keys = $this->redis->keys($pattern);
            
            if (empty($keys)) {
                return 0;
            }
            
            $deleted = $this->redis->del($keys);
            return $deleted;
            
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Check if Redis is enabled and connected
     * 
     * @return bool True if Redis is enabled and connected
     */
    public function is_enabled() {
        return $this->enabled && $this->redis !== null;
    }
    
    /**
     * Test Redis connection
     * 
     * @return array Result with success status and message
     */
    public function test_connection() {
        // Check if Redis extension is available
        if (!class_exists('Redis')) {
            return array(
                'success' => false,
                'message' => 'Redis PHP extension is not installed. Please install php-redis extension.'
            );
        }
        
        $host = get_option('boat_chatbot_redis_host', 'localhost');
        $port = absint(get_option('boat_chatbot_redis_port', 6379));
        $password = get_option('boat_chatbot_redis_password', '');
        $database = absint(get_option('boat_chatbot_redis_database', 0));
        
        try {
            $redis = new Redis();
            $connected = @$redis->connect($host, $port, 2.0);
            
            if (!$connected) {
                return array(
                    'success' => false,
                    'message' => 'Failed to connect to Redis server at ' . $host . ':' . $port
                );
            }
            
            // Authenticate if password is set
            if (!empty($password)) {
                $auth_result = $redis->auth($password);
                if (!$auth_result) {
                    $redis->close();
                    return array(
                        'success' => false,
                        'message' => 'Redis authentication failed. Please check your password.'
                    );
                }
            }
            
            // Select database
            if ($database > 0) {
                $redis->select($database);
            }
            
            // Test with ping
            $ping_result = $redis->ping();
            if ($ping_result !== '+PONG') {
                $redis->close();
                return array(
                    'success' => false,
                    'message' => 'Redis ping failed. Connection may be unstable.'
                );
            }
            
            // Test write/read
            $test_key = $this->cache_prefix . 'test_' . time();
            $test_value = 'test_value_' . rand(1000, 9999);
            $set_result = $redis->setex($test_key, 10, $test_value);
            $get_result = $redis->get($test_key);
            $redis->del($test_key);
            
            if (!$set_result || $get_result !== $test_value) {
                $redis->close();
                return array(
                    'success' => false,
                    'message' => 'Redis read/write test failed.'
                );
            }
            
            // Get server info
            $info = $redis->info('server');
            $redis_version = isset($info['redis_version']) ? $info['redis_version'] : 'unknown';
            
            $redis->close();
            
            return array(
                'success' => true,
                'message' => 'Redis connection successful! (Redis version: ' . $redis_version . ')'
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
            );
        } catch (Error $e) {
            return array(
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Get cache statistics
     * 
     * @return array Cache statistics
     */
    public function get_stats() {
        if (!$this->enabled || !$this->redis) {
            return array(
                'enabled' => false,
                'keys' => 0,
                'memory_usage' => 0
            );
        }
        
        try {
            $pattern = $this->cache_prefix . '*';
            $keys = $this->redis->keys($pattern);
            $key_count = is_array($keys) ? count($keys) : 0;
            
            $info = $this->redis->info('memory');
            $memory_used = isset($info['used_memory_human']) ? $info['used_memory_human'] : '0B';
            
            return array(
                'enabled' => true,
                'keys' => $key_count,
                'memory_usage' => $memory_used
            );
            
        } catch (Exception $e) {
            return array(
                'enabled' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Close Redis connection
     */
    public function __destruct() {
        if ($this->redis) {
            try {
                $this->redis->close();
            } catch (Exception $e) {
                // Ignore close errors
            }
        }
    }
}


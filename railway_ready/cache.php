<?php
class Cache {
    private static $redis = null;
    private static $enabled = false;
    
    public static function init() {
        // Simple Redis connection only
        try {
            // Check if Redis class/extension is available
            if (!class_exists('Redis')) {
                error_log('Redis extension not installed');
                self::$enabled = false;
                return false;
            }
            
            self::$redis = new Redis();
            if (self::$redis->connect('127.0.0.1', 6379, 2)) {
                self::$enabled = true;
                self::$redis->setOption(Redis::OPT_PREFIX, 'bus:');
                return true;
            }
        } catch (Throwable $e) {
            // Catch both Exception and Error (e.g., missing Redis class/extension)
            error_log('Redis initialization failed: ' . $e->getMessage());
        }
        self::$enabled = false;
        return false;
    }
    
    public static function get($key, $default = null) {
        if (!self::$enabled || !self::$redis) return $default;
        try {
            $value = self::$redis->get($key);
            return $value !== false ? unserialize($value) : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
    
    public static function set($key, $value, $ttl = 300) {
        if (!self::$enabled || !self::$redis) return false;
        try {
            return self::$redis->setex($key, $ttl, serialize($value));
        } catch (Exception $e) {
            return false;
        }
    }
    
    public static function delete($key) {
        if (!self::$enabled || !self::$redis) return false;
        return self::$redis->del($key) > 0;
    }
}
Cache::init();
?>
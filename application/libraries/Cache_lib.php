<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Cache_lib
{
    public $redis = null;
    protected $enabled = false;
    protected $host = '127.0.0.1';
    protected $port = 6379;
    protected $timeout = 1.5;

    public function __construct()
    {
        if (!class_exists('Redis')) {
            $this->enabled = false;
            return;
        }
        $this->redis = new Redis();
        try {
            $this->redis->connect($this->host, $this->port, $this->timeout);
            $this->enabled = true;
        } catch (Exception $e) {
            $this->enabled = false;
        }
    }

    public function is_enabled()
    {
        return $this->enabled;
    }

    public function get($key) {
        if (!$this->enabled) return false;
        $v = $this->redis->get($key);
        return ($v === false) ? false : $v;
    }

    public function set($key, $value, $ttl = 300) {
        if (!$this->enabled) return false;
        return $this->redis->set($key, $value, $ttl);
    }

    public function delete($key) {
        if (!$this->enabled) return false;
        return $this->redis->del($key);
    }

    // scan helper returning array of keys (non-blocking SCAN)
    public function scan_keys($pattern = '*', $count = 100) {
        if (!$this->enabled) return [];
        $it = null;
        $keys = [];
        do {
            $res = $this->redis->scan($it, $pattern, $count);
            if ($res !== false) $keys = array_merge($keys, $res);
        } while ($it != 0);
        return $keys;
    }
}

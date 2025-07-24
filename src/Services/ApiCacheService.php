<?php

namespace ApiServerPackage\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;

class ApiCacheService
{
    /**
     * Cache prefix for API responses.
     *
     * @var string
     */
    protected $prefix;

    /**
     * Cache TTL in seconds.
     *
     * @var int
     */
    protected $ttl;

    /**
     * Whether caching is enabled.
     *
     * @var bool
     */
    protected $enabled;

    /**
     * Create a new API cache service instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->prefix = config('api-server.cache_prefix', 'api_server_');
        $this->ttl = config('api-server.cache_ttl', 300); // 5 minutes default
        $this->enabled = config('api-server.use_cache', false);
    }

    /**
     * Get a cached response or execute the callback to generate and cache it.
     *
     * @param  string  $resourceType
     * @param  string  $method
     * @param  array  $params
     * @param  \Closure  $callback
     * @return mixed
     */
    public function remember(string $resourceType, string $method, array $params, \Closure $callback)
    {
        if (!$this->enabled || !$this->isCacheable($method)) {
            return $callback();
        }

        $key = $this->generateCacheKey($resourceType, $method, $params);
        
        return Cache::remember($key, $this->ttl, $callback);
    }

    /**
     * Flush cache for a specific resource or all resources.
     *
     * @param  string|null  $resourceType
     * @param  mixed  $resourceId
     * @return bool
     */
    public function flush(?string $resourceType = null, $resourceId = null): bool
    {
        if ($resourceType && $resourceId) {
            // Flush specific resource
            $key = $this->prefix . $resourceType . '_' . $resourceId;
            Cache::forget($key);
            
            // Also flush collection caches that might include this resource
            $collectionKey = $this->prefix . $resourceType . '_collection';
            Cache::forget($collectionKey);
            
            return true;
        } elseif ($resourceType) {
            // Flush all caches for a resource type
            $pattern = $this->prefix . $resourceType . '_*';
            $this->forgetPattern($pattern);
            return true;
        } else {
            // Flush all API caches
            $pattern = $this->prefix . '*';
            $this->forgetPattern($pattern);
            return true;
        }
    }

    /**
     * Generate a cache key for the request.
     *
     * @param  string  $resourceType
     * @param  string  $method
     * @param  array  $params
     * @return string
     */
    protected function generateCacheKey(string $resourceType, string $method, array $params): string
    {
        // For collection endpoints
        if ($method === 'index') {
            $paramsHash = md5(json_encode($params));
            return $this->prefix . $resourceType . '_collection_' . $paramsHash;
        }
        
        // For single resource endpoints
        if (isset($params['id'])) {
            return $this->prefix . $resourceType . '_' . $params['id'];
        }
        
        // For other endpoints
        $paramsHash = md5(json_encode($params));
        return $this->prefix . $resourceType . '_' . $method . '_' . $paramsHash;
    }

    /**
     * Determine if the request method is cacheable.
     *
     * @param  string  $method
     * @return bool
     */
    protected function isCacheable(string $method): bool
    {
        // Only cache GET requests (index, show)
        return in_array($method, ['index', 'show']);
    }
    
    /**
     * Forget cache keys by pattern.
     *
     * @param  string  $pattern
     * @return void
     */
    protected function forgetPattern(string $pattern): void
    {
        // This is a simplified implementation
        // For production, use a cache driver that supports pattern deletion
        // or implement a more sophisticated approach
        
        // For Redis:
        if (config('cache.default') === 'redis') {
            $redis = Cache::getRedis();
            $keys = $redis->keys($pattern);
            
            foreach ($keys as $key) {
                Cache::forget($key);
            }
        }
        
        // For other drivers, we might need to maintain a registry of keys
    }
}

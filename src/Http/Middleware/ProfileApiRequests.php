<?php

namespace ApiServerPackage\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProfileApiRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip profiling if disabled in config
        if (!config('api-server.enable_profiling', false)) {
            return $next($request);
        }

        // Start timing
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        // Count database queries before request
        $queryCountBefore = count(DB::getQueryLog());
        
        // Process the request
        $response = $next($request);
        
        // Calculate metrics
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        $executionTime = $endTime - $startTime;
        $memoryUsage = $endMemory - $startMemory;
        
        // Count database queries after request
        $queryCountAfter = count(DB::getQueryLog());
        $queryCount = $queryCountAfter - $queryCountBefore;
        
        // Get the route and method
        $route = $request->route() ? $request->route()->uri() : 'unknown';
        $method = $request->method();
        
        // Log the metrics
        $this->logMetrics($route, $method, $executionTime, $memoryUsage, $queryCount);
        
        // Add profiling headers if enabled
        if (config('api-server.include_profiling_headers', false)) {
            $response->headers->set('X-API-Execution-Time', number_format($executionTime * 1000, 2) . 'ms');
            $response->headers->set('X-API-Memory-Usage', $this->formatBytes($memoryUsage));
            $response->headers->set('X-API-DB-Queries', $queryCount);
        }
        
        return $response;
    }
    
    /**
     * Log the request metrics.
     *
     * @param  string  $route
     * @param  string  $method
     * @param  float  $executionTime
     * @param  int  $memoryUsage
     * @param  int  $queryCount
     * @return void
     */
    protected function logMetrics(string $route, string $method, float $executionTime, int $memoryUsage, int $queryCount): void
    {
        // Determine if this is a slow request
        $slowThreshold = config('api-server.slow_request_threshold', 1.0); // seconds
        $isSlow = $executionTime > $slowThreshold;
        
        // Determine log level based on performance
        $logLevel = $isSlow ? 'warning' : 'debug';
        
        // Format the log message
        $message = sprintf(
            'API %s %s - Time: %.2fms, Memory: %s, Queries: %d%s',
            $method,
            $route,
            $executionTime * 1000,
            $this->formatBytes($memoryUsage),
            $queryCount,
            $isSlow ? ' [SLOW]' : ''
        );
        
        // Log with appropriate level
        Log::$logLevel($message);
        
        // Store metrics for analysis if enabled
        if (config('api-server.store_metrics', false)) {
            $this->storeMetrics($route, $method, $executionTime, $memoryUsage, $queryCount);
        }
    }
    
    /**
     * Store metrics for later analysis.
     *
     * @param  string  $route
     * @param  string  $method
     * @param  float  $executionTime
     * @param  int  $memoryUsage
     * @param  int  $queryCount
     * @return void
     */
    protected function storeMetrics(string $route, string $method, float $executionTime, int $memoryUsage, int $queryCount): void
    {
        // In a real implementation, you might store these in a database table
        // or send them to a monitoring service like New Relic, Datadog, etc.
        
        // For this example, we'll just store them in a file
        $metricsFile = storage_path('logs/api_metrics.log');
        
        $data = [
            'timestamp' => now()->toIso8601String(),
            'route' => $route,
            'method' => $method,
            'execution_time' => $executionTime,
            'memory_usage' => $memoryUsage,
            'query_count' => $queryCount,
        ];
        
        file_put_contents(
            $metricsFile,
            json_encode($data) . PHP_EOL,
            FILE_APPEND
        );
    }
    
    /**
     * Format bytes to a human-readable string.
     *
     * @param  int  $bytes
     * @param  int  $precision
     * @return string
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

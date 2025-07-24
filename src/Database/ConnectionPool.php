<?php

namespace ApiServerPackage\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Connection;

class ConnectionPool
{
    /**
     * The pool of database connections.
     *
     * @var array
     */
    protected static $pool = [];
    
    /**
     * The maximum number of connections to keep in the pool.
     *
     * @var int
     */
    protected static $maxConnections;
    
    /**
     * The time in seconds after which idle connections should be closed.
     *
     * @var int
     */
    protected static $idleTimeout;
    
    /**
     * Initialize the connection pool.
     *
     * @return void
     */
    public static function initialize(): void
    {
        self::$maxConnections = config('api-server.db_pool_max_connections', 10);
        self::$idleTimeout = config('api-server.db_pool_idle_timeout', 300); // 5 minutes
        
        // Register shutdown function to clean up connections
        register_shutdown_function([self::class, 'cleanup']);
    }
    
    /**
     * Get a connection from the pool or create a new one.
     *
     * @param  string|null  $connection
     * @return \Illuminate\Database\Connection
     */
    public static function getConnection(?string $connection = null): Connection
    {
        $connection = $connection ?: config('database.default');
        
        // Check if we have an available connection in the pool
        if (isset(self::$pool[$connection]) && !empty(self::$pool[$connection])) {
            $conn = array_pop(self::$pool[$connection]);
            
            // Check if the connection is still valid
            if (self::isConnectionValid($conn)) {
                return $conn;
            }
            
            // If not valid, create a new one
        }
        
        // Create a new connection
        return DB::connection($connection);
    }
    
    /**
     * Return a connection to the pool.
     *
     * @param  \Illuminate\Database\Connection  $connection
     * @param  string|null  $name
     * @return void
     */
    public static function returnConnection(Connection $connection, ?string $name = null): void
    {
        $name = $name ?: $connection->getName();
        
        // Initialize the pool for this connection if it doesn't exist
        if (!isset(self::$pool[$name])) {
            self::$pool[$name] = [];
        }
        
        // Only add to the pool if we haven't reached the maximum
        if (count(self::$pool[$name]) < self::$maxConnections) {
            // Mark the connection with the current timestamp
            $connection->poolTimestamp = time();
            
            // Add to the pool
            self::$pool[$name][] = $connection;
        } else {
            // If the pool is full, disconnect this connection
            $connection->disconnect();
        }
    }
    
    /**
     * Clean up idle connections.
     *
     * @return void
     */
    public static function cleanup(): void
    {
        $now = time();
        
        foreach (self::$pool as $name => $connections) {
            foreach ($connections as $index => $connection) {
                // Check if the connection has been idle for too long
                if (isset($connection->poolTimestamp) && ($now - $connection->poolTimestamp) > self::$idleTimeout) {
                    // Remove from the pool and disconnect
                    unset(self::$pool[$name][$index]);
                    $connection->disconnect();
                }
            }
            
            // Reindex the array
            if (!empty(self::$pool[$name])) {
                self::$pool[$name] = array_values(self::$pool[$name]);
            }
        }
    }
    
    /**
     * Check if a connection is still valid.
     *
     * @param  \Illuminate\Database\Connection  $connection
     * @return bool
     */
    protected static function isConnectionValid(Connection $connection): bool
    {
        try {
            $connection->select('SELECT 1');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

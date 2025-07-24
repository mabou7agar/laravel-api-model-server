<?php

namespace ApiServerPackage\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QueryOptimizer
{
    /**
     * Optimize a query for large datasets.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $requestData
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function optimize(Builder $query, array $requestData = []): Builder
    {
        // Apply various optimization techniques
        $this->optimizeSelects($query, $requestData)
             ->optimizeJoins($query, $requestData)
             ->optimizeWheres($query, $requestData)
             ->optimizeSorting($query, $requestData)
             ->applyIndexHints($query, $requestData);
        
        return $query;
    }
    
    /**
     * Optimize SELECT statements to only fetch required columns.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $requestData
     * @return $this
     */
    protected function optimizeSelects(Builder $query, array $requestData): self
    {
        // If specific fields are requested, we're already optimized
        if (isset($requestData['fields']) && !empty($requestData['fields'])) {
            return $this;
        }
        
        // Get the model instance
        $model = $query->getModel();
        
        // Get primary key and essential fields
        $essentialFields = [$model->getKeyName()];
        
        // Add timestamp fields if they exist
        if ($model->usesTimestamps()) {
            $essentialFields[] = $model->getCreatedAtColumn();
            $essentialFields[] = $model->getUpdatedAtColumn();
        }
        
        // Add any fields that might be used in where clauses
        if (isset($requestData['filter']) && is_array($requestData['filter'])) {
            $essentialFields = array_merge($essentialFields, array_keys($requestData['filter']));
        }
        
        // Add any fields that might be used in sorting
        if (isset($requestData['sort']) && is_array($requestData['sort'])) {
            $essentialFields = array_merge($essentialFields, array_keys($requestData['sort']));
        }
        
        // If we have a reasonable subset of fields, use them
        if (count($essentialFields) < count($model->getFillable())) {
            $query->select(array_unique($essentialFields));
        }
        
        return $this;
    }
    
    /**
     * Optimize JOIN operations to use more efficient join types.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $requestData
     * @return $this
     */
    protected function optimizeJoins(Builder $query, array $requestData): self
    {
        // Check if we have any eager loading
        if (!isset($requestData['include']) || empty($requestData['include'])) {
            return $this;
        }
        
        // For each relation, we'll use specific join types based on cardinality
        // This is a simplified example - in production, you'd want to analyze
        // the actual relations and choose appropriate join types
        
        // For now, we'll just ensure we're using select() with eager loading
        // to avoid the N+1 query problem
        
        return $this;
    }
    
    /**
     * Optimize WHERE clauses for better index utilization.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $requestData
     * @return $this
     */
    protected function optimizeWheres(Builder $query, array $requestData): self
    {
        // Reorder WHERE clauses to put equality conditions first
        // This helps the query planner use indexes more effectively
        
        // This is a complex optimization that would require analyzing the
        // actual query structure, which is beyond the scope of this example
        
        return $this;
    }
    
    /**
     * Optimize sorting for better performance.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $requestData
     * @return $this
     */
    protected function optimizeSorting(Builder $query, array $requestData): self
    {
        // If we're sorting on non-indexed columns and have a large dataset,
        // consider adding a LIMIT to improve performance
        
        // Check if we're sorting
        if (!isset($requestData['sort']) || empty($requestData['sort'])) {
            return $this;
        }
        
        // If we're sorting and paginating, we're already optimized
        if (isset($requestData['per_page'])) {
            return $this;
        }
        
        // If we're sorting without pagination, add a reasonable limit
        // to avoid sorting the entire table
        $defaultLimit = config('api-server.default_sort_limit', 1000);
        
        // Only add limit if one isn't already set
        if (!$this->hasLimit($query)) {
            $query->limit($defaultLimit);
        }
        
        return $this;
    }
    
    /**
     * Apply index hints for better query performance.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $requestData
     * @return $this
     */
    protected function applyIndexHints(Builder $query, array $requestData): self
    {
        // Index hints are database-specific and would require raw SQL
        // This is a simplified example that works with MySQL
        
        // Get the model's table
        $table = $query->getModel()->getTable();
        
        // Check if we have filters that could use indexes
        if (isset($requestData['filter']) && is_array($requestData['filter'])) {
            $indexableFields = $this->getIndexableFields($query->getModel());
            $filterFields = array_keys($requestData['filter']);
            
            // Find fields that are both filtered and indexable
            $indexedFilters = array_intersect($filterFields, $indexableFields);
            
            if (!empty($indexedFilters)) {
                // For MySQL, we can use USE INDEX hint
                // This requires a raw DB expression
                $indexNames = $this->getIndexNames($query->getModel(), $indexedFilters);
                
                if (!empty($indexNames)) {
                    // Replace the FROM clause with one that includes index hints
                    // Note: This is MySQL-specific and would need adaptation for other DBs
                    $sql = $query->toSql();
                    $fromPattern = '/FROM\s+`?' . $table . '`?/i';
                    $replacement = 'FROM `' . $table . '` USE INDEX (' . implode(', ', $indexNames) . ')';
                    
                    // We can't directly modify the query's SQL, so we'd need to
                    // create a new query with the modified SQL
                    // This is a simplified example and would need more work in production
                }
            }
        }
        
        return $this;
    }
    
    /**
     * Check if the query already has a LIMIT clause.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return bool
     */
    protected function hasLimit(Builder $query): bool
    {
        return $query->getQuery()->limit !== null;
    }
    
    /**
     * Get indexable fields for a model.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return array
     */
    protected function getIndexableFields($model): array
    {
        // In a real implementation, you would:
        // 1. Cache this information
        // 2. Use schema information to determine actual indexes
        
        // For this example, we'll assume primary key and any fields with
        // index in their name are indexable
        $table = $model->getTable();
        $indexableFields = [$model->getKeyName()];
        
        // Get schema information about indexes
        // This is MySQL-specific
        $indexes = DB::select("SHOW INDEX FROM `{$table}`");
        
        foreach ($indexes as $index) {
            if ($index->Column_name !== $model->getKeyName()) {
                $indexableFields[] = $index->Column_name;
            }
        }
        
        return array_unique($indexableFields);
    }
    
    /**
     * Get index names for specific fields.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param array $fields
     * @return array
     */
    protected function getIndexNames($model, array $fields): array
    {
        // In a real implementation, you would:
        // 1. Cache this information
        // 2. Use schema information to determine actual index names
        
        $table = $model->getTable();
        $indexNames = [];
        
        // Get schema information about indexes
        // This is MySQL-specific
        $indexes = DB::select("SHOW INDEX FROM `{$table}`");
        
        foreach ($indexes as $index) {
            if (in_array($index->Column_name, $fields)) {
                $indexNames[] = $index->Key_name;
            }
        }
        
        return array_unique($indexNames);
    }
}

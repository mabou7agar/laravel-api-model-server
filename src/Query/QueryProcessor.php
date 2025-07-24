<?php

namespace ApiServerPackage\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use ApiServerPackage\Contracts\ApiExposable;
use Illuminate\Support\Str;
use ApiServerPackage\Query\QueryOptimizer;

class QueryProcessor
{
    /**
     * The model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $model;

    /**
     * The request data.
     *
     * @var array
     */
    protected $requestData;
    
    /**
     * The query optimizer instance.
     *
     * @var \ApiServerPackage\Query\QueryOptimizer
     */
    protected $optimizer;

    /**
     * The API configuration.
     *
     * @var array
     */
    protected $config;

    /**
     * Create a new query processor instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  array  $requestData
     * @param  \ApiServerPackage\Query\QueryOptimizer|null  $optimizer
     * @return void
     */
    public function __construct(Model $model, array $requestData = [], QueryOptimizer $optimizer = null)
    {
        if (!($model instanceof ApiExposable)) {
            throw new \InvalidArgumentException('Model must implement ApiExposable interface');
        }

        $this->model = $model;
        $this->requestData = $requestData;
        $this->optimizer = $optimizer ?? new QueryOptimizer();
        $this->config = $model->getApiConfiguration();
    }

    /**
     * Process the query based on request data.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function process(): Builder
    {
        $query = $this->model->newQuery();
        
        // Apply filters
        if (isset($this->requestData['filter'])) {
            $this->applyFilters($query, $this->requestData['filter']);
        }
        
        // Apply sorting
        if (isset($this->requestData['sort'])) {
            $this->applySorting($query, $this->requestData['sort']);
        }
        
        // Apply includes (eager loading)
        if (isset($this->requestData['include'])) {
            $this->applyIncludes($query, $this->requestData['include']);
        }
        
        // Apply field selection
        if (isset($this->requestData['fields'])) {
            $this->applyFieldSelection($query, $this->requestData['fields']);
        }
        
        // Apply query optimization for large datasets
        $query = $this->optimizer->optimize($query, $this->requestData);
        
        return $query;
    }

    /**
     * Apply filters to the query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return $this
     */
    protected function applyFilters(Builder $query, array $filters)
    {
        $allowedFilters = $this->config['filters'];

        foreach ($filters as $field => $value) {
            if (!in_array($field, $allowedFilters)) {
                continue;
            }

            if (is_array($value) && isset($value['operator'], $value['value'])) {
                $this->applyOperatorFilter($query, $field, $value['operator'], $value['value']);
            } else {
                $query->where($field, '=', $value);
            }
        }

        return $this;
    }

    /**
     * Apply an operator-based filter.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $field
     * @param string $operator
     * @param mixed $value
     * @return void
     */
    protected function applyOperatorFilter(Builder $query, string $field, string $operator, $value): void
    {
        $validOperators = ['=', '!=', '<', '>', '<=', '>=', 'like', 'in', 'not_in', 'between', 'not_between'];
        
        if (!in_array($operator, $validOperators)) {
            return;
        }

        switch ($operator) {
            case 'in':
                $query->whereIn($field, (array) $value);
                break;
            case 'not_in':
                $query->whereNotIn($field, (array) $value);
                break;
            case 'between':
                $query->whereBetween($field, (array) $value);
                break;
            case 'not_between':
                $query->whereNotBetween($field, (array) $value);
                break;
            case 'like':
                $query->where($field, 'like', "%{$value}%");
                break;
            default:
                $query->where($field, $operator, $value);
                break;
        }
    }

    /**
     * Apply sorting to the query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $sort
     * @return $this
     */
    protected function applySorting(Builder $query, array $sort)
    {
        $allowedSorts = $this->config['sorts'];

        foreach ($sort as $field => $direction) {
            if (!in_array($field, $allowedSorts)) {
                continue;
            }

            $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
            $query->orderBy($field, $direction);
        }

        return $this;
    }

    /**
     * Apply includes (eager loading) to the query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $includes
     * @return $this
     */
    protected function applyIncludes(Builder $query, array $includes)
    {
        $allowedRelations = $this->config['relations'];

        $validIncludes = array_filter($includes, function ($include) use ($allowedRelations) {
            return in_array($include, $allowedRelations);
        });

        if (!empty($validIncludes)) {
            $query->with($validIncludes);
        }

        return $this;
    }

    /**
     * Apply field selection to the query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $fields
     * @return $this
     */
    protected function applyFieldSelection(Builder $query, array $fields)
    {
        $allowedFields = $this->config['fields'];

        if (!empty($fields)) {
            $validFields = array_filter($fields, function ($field) use ($allowedFields) {
                return in_array($field, $allowedFields);
            });

            if (!empty($validFields)) {
                // Always include the primary key
                $validFields[] = $this->model->getKeyName();
                $query->select(array_unique($validFields));
            }
        }

        return $this;
    }
}

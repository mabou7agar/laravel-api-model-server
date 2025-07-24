<?php

namespace ApiServerPackage\Traits;

use Illuminate\Support\Str;

trait ApiExposableTrait
{
    /**
     * Get the API configuration for this model.
     *
     * @return array
     */
    public function getApiConfiguration(): array
    {
        return [
            'resource' => $this->getApiResourceName(),
            'fields' => $this->getApiFields(),
            'relations' => $this->getApiRelations(),
            'filters' => $this->getApiFilters(),
            'sorts' => $this->getApiSortFields(),
            'scopes' => $this->getApiScopes(),
        ];
    }
    
    /**
     * Get the API resource name (endpoint).
     *
     * @return string
     */
    public function getApiResourceName(): string
    {
        // Convert StudlyCase to kebab-case and pluralize
        return Str::plural(Str::kebab(class_basename($this)));
    }
    
    /**
     * Get the fields that can be exposed via the API.
     *
     * @return array
     */
    public function getApiFields(): array
    {
        // Default to fillable attributes
        return $this->fillable;
    }
    
    /**
     * Get relationships that can be included in API responses.
     *
     * @return array
     */
    public function getApiRelations(): array
    {
        return [];
    }
    
    /**
     * Get fields that can be used for filtering in API requests.
     *
     * @return array
     */
    public function getApiFilters(): array
    {
        // Default to allowing filtering on all exposed fields
        return $this->getApiFields();
    }
    
    /**
     * Get fields that can be used for sorting in API requests.
     *
     * @return array
     */
    public function getApiSortFields(): array
    {
        // Default to allowing sorting on all exposed fields
        return $this->getApiFields();
    }
    
    /**
     * Get the allowed API scopes for this model.
     *
     * @return array
     */
    public function getApiScopes(): array
    {
        return ['read', 'create', 'update', 'delete'];
    }
}

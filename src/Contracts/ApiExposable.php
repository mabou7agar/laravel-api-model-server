<?php

namespace ApiServerPackage\Contracts;

interface ApiExposable
{
    /**
     * Get the API configuration for this model.
     *
     * @return array
     */
    public function getApiConfiguration(): array;
    
    /**
     * Get the API resource name (endpoint).
     *
     * @return string
     */
    public function getApiResourceName(): string;
    
    /**
     * Get the fields that can be exposed via the API.
     *
     * @return array
     */
    public function getApiFields(): array;
    
    /**
     * Get relationships that can be included in API responses.
     *
     * @return array
     */
    public function getApiRelations(): array;
    
    /**
     * Get fields that can be used for filtering in API requests.
     *
     * @return array
     */
    public function getApiFilters(): array;
    
    /**
     * Get fields that can be used for sorting in API requests.
     *
     * @return array
     */
    public function getApiSortFields(): array;
    
    /**
     * Get the allowed API scopes for this model.
     *
     * @return array
     */
    public function getApiScopes(): array;
}

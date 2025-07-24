<?php

namespace ApiServerPackage\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use ApiServerPackage\Http\Middleware\DecryptQueryParameters;
use ApiServerPackage\Http\Middleware\CheckApiScopes;
use ApiServerPackage\Http\Controllers\ApiResourceController;
use Illuminate\Contracts\Container\Container;

class ApiServerServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/api-server.php', 'api-server'
        );
        
        // Register the bridge
        $this->app->singleton('api-server.bridge', function ($app) {
            return new \ApiServerPackage\Bridge\ApiPackageBridge(
                $app->make(\Laravel\Passport\ClientRepository::class),
                $app->make(\Laravel\Passport\TokenRepository::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../../config/api-server.php' => config_path('api-server.php'),
        ], 'config');

        // Register middleware
        $this->app['router']->aliasMiddleware('decrypt-query', DecryptQueryParameters::class);
        $this->app['router']->aliasMiddleware('check-api-scopes', CheckApiScopes::class);
        $this->app['router']->aliasMiddleware('profile-api', \ApiServerPackage\Http\Middleware\ProfileApiRequests::class);
        $this->app['router']->aliasMiddleware('compress-api', \ApiServerPackage\Http\Middleware\CompressApiResponse::class);
        $this->app['router']->aliasMiddleware('etag-api', \ApiServerPackage\Http\Middleware\AddEtagHeaders::class);
        $this->app['router']->aliasMiddleware('api-access', \ApiServerPackage\Http\Middleware\ApiAccessMiddleware::class);

        // Register routes
        $this->registerRoutes();
        
        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \ApiServerPackage\Console\Commands\IntegrateWithClient::class,
            ]);
        }
    }

    /**
     * Register API routes for exposed models.
     *
     * @return void
     */
    protected function registerRoutes()
    {
        // Get exposed models from config
        $exposedModels = config('api-server.exposed_models', []);

        // Register routes for each exposed model
        Route::group([
            'prefix' => config('api-server.route_prefix', 'api'),
            'middleware' => array_merge(
                ['api', 'decrypt-query'],
                config('api-server.use_oauth_access_control', true) ? ['api-access'] : []
            ),
        ], function () use ($exposedModels) {
            // Add ping endpoint for connection testing
            Route::get('ping', [\ApiServerPackage\Http\Controllers\PingController::class, 'ping']);
            
            // Add batch operations endpoint
            Route::post('batch', [\ApiServerPackage\Http\Controllers\ApiBatchController::class, 'process']);
            
            foreach ($exposedModels as $modelClass) {
                // Skip if model doesn't exist or doesn't implement ApiExposable
                if (!class_exists($modelClass) || !$this->implementsApiExposable($modelClass)) {
                    continue;
                }

                // Create model instance to get resource name
                $model = new $modelClass;
                $resourceName = $model->getApiResourceName();
                $scopes = $model->getApiScopes();

                // Register resource routes
                Route::group(['prefix' => $resourceName], function () use ($modelClass, $scopes) {
                    // List resources (GET /api/{resource})
                    Route::get('/', $this->createControllerCallback($modelClass, 'index'))
                        ->middleware("check-api-scopes:{$scopes[0] ?? 'read'}");

                    // Show resource (GET /api/{resource}/{id})
                    Route::get('/{id}', $this->createControllerCallback($modelClass, 'show'))
                        ->middleware("check-api-scopes:{$scopes[0] ?? 'read'}");

                    // Create resource (POST /api/{resource})
                    Route::post('/', $this->createControllerCallback($modelClass, 'store'))
                        ->middleware("check-api-scopes:{$scopes[1] ?? 'create'}");

                    // Update resource (PUT/PATCH /api/{resource}/{id})
                    Route::match(['put', 'patch'], '/{id}', $this->createControllerCallback($modelClass, 'update'))
                        ->middleware("check-api-scopes:{$scopes[2] ?? 'update'}");

                    // Delete resource (DELETE /api/{resource}/{id})
                    Route::delete('/{id}', $this->createControllerCallback($modelClass, 'destroy'))
                        ->middleware("check-api-scopes:{$scopes[3] ?? 'delete'}");
                });
            }
        });
    }

    /**
     * Check if a model implements the ApiExposable interface.
     *
     * @param string $modelClass
     * @return bool
     */
    protected function implementsApiExposable(string $modelClass): bool
    {
        return in_array('ApiServerPackage\Contracts\ApiExposable', class_implements($modelClass) ?: []);
    }

    /**
     * Create a controller callback for a specific model and action.
     *
     * @param string $modelClass
     * @param string $action
     * @return \Closure
     */
    protected function createControllerCallback(string $modelClass, string $action): \Closure
    {
        return function (Container $app) use ($modelClass, $action) {
            $controller = new ApiResourceController($modelClass);
            return $controller->$action(...func_get_args());
        };
    }
}

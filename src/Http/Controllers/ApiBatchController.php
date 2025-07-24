<?php

namespace ApiServerPackage\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use ApiServerPackage\Services\ApiCacheService;

class ApiBatchController extends Controller
{
    /**
     * The API cache service.
     *
     * @var \ApiServerPackage\Services\ApiCacheService
     */
    protected $cacheService;

    /**
     * Create a new controller instance.
     *
     * @param \ApiServerPackage\Services\ApiCacheService|null $cacheService
     */
    public function __construct(ApiCacheService $cacheService = null)
    {
        $this->cacheService = $cacheService ?? app(ApiCacheService::class);
    }

    /**
     * Process a batch of operations.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function process(Request $request)
    {
        // Validate the batch request
        $validator = Validator::make($request->all(), [
            'operations' => 'required|array|min:1',
            'operations.*.method' => 'required|string|in:GET,POST,PUT,PATCH,DELETE',
            'operations.*.path' => 'required|string',
            'operations.*.body' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Invalid batch request',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // Process each operation
        $operations = $request->input('operations');
        $results = [];
        $hasErrors = false;

        // Start a database transaction if requested
        $useTransaction = $request->input('use_transaction', false);
        if ($useTransaction) {
            DB::beginTransaction();
        }

        try {
            foreach ($operations as $index => $operation) {
                $result = $this->processOperation($operation, $index);
                $results[] = $result;

                // If any operation fails and we're using transactions, we'll roll back
                if ($useTransaction && isset($result['status_code']) && $result['status_code'] >= 400) {
                    $hasErrors = true;
                    break;
                }
            }

            // Commit or rollback the transaction
            if ($useTransaction) {
                if ($hasErrors) {
                    DB::rollBack();
                    return response()->json([
                        'error' => 'Batch operation failed',
                        'message' => 'One or more operations failed, all changes have been rolled back',
                        'results' => $results,
                    ], 422);
                } else {
                    DB::commit();
                }
            }

            return response()->json([
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            // Rollback on exception if using transaction
            if ($useTransaction) {
                DB::rollBack();
            }

            return response()->json([
                'error' => 'Batch operation failed',
                'message' => $e->getMessage(),
                'results' => $results,
            ], 500);
        }
    }

    /**
     * Process a single operation in the batch.
     *
     * @param  array  $operation
     * @param  int  $index
     * @return array
     */
    protected function processOperation(array $operation, int $index): array
    {
        $method = strtoupper($operation['method']);
        $path = $operation['path'];
        $body = $operation['body'] ?? [];
        $headers = $operation['headers'] ?? [];

        // Create a new request
        $request = Request::create(
            $path,
            $method,
            $method === 'GET' ? $body : [],
            [], // cookies
            [], // files
            $this->transformHeaders($headers),
            $method !== 'GET' ? json_encode($body) : null
        );

        // Add content type header if not present
        if ($method !== 'GET' && !isset($headers['Content-Type'])) {
            $request->headers->set('Content-Type', 'application/json');
        }

        // Copy authentication from the original request
        $this->copyAuthentication($request);

        // Dispatch the request to the router
        $response = app()->handle($request);

        // Get the response data
        $statusCode = $response->getStatusCode();
        $content = json_decode($response->getContent(), true) ?? [];

        // Clear cache for modified resources if needed
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE']) && $statusCode < 400) {
            $this->clearCacheForOperation($operation, $content);
        }

        return [
            'id' => $index,
            'status_code' => $statusCode,
            'body' => $content,
        ];
    }

    /**
     * Transform headers array to server variables.
     *
     * @param  array  $headers
     * @return array
     */
    protected function transformHeaders(array $headers): array
    {
        $server = [];
        foreach ($headers as $name => $value) {
            $name = strtoupper(str_replace('-', '_', $name));
            $server["HTTP_{$name}"] = $value;
        }
        return $server;
    }

    /**
     * Copy authentication from the current request to the new request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function copyAuthentication(Request $request): void
    {
        $currentRequest = request();
        
        // Copy bearer token if present
        $bearerToken = $currentRequest->bearerToken();
        if ($bearerToken) {
            $request->headers->set('Authorization', 'Bearer ' . $bearerToken);
        }
        
        // Copy user if authenticated
        if ($currentRequest->user()) {
            $request->setUserResolver(function () use ($currentRequest) {
                return $currentRequest->user();
            });
        }
    }

    /**
     * Clear cache for modified resources.
     *
     * @param  array  $operation
     * @param  array  $content
     * @return void
     */
    protected function clearCacheForOperation(array $operation, array $content): void
    {
        // Extract resource type from path
        $path = $operation['path'];
        $pathParts = explode('/', trim($path, '/'));
        
        // Skip if we can't determine the resource type
        if (count($pathParts) < 2) {
            return;
        }
        
        // The resource type is typically the second part of the path
        // e.g., /api/users/1 -> resource type is 'users'
        $resourceType = $pathParts[1] ?? null;
        
        if ($resourceType) {
            // For DELETE, PUT, PATCH operations on a specific resource
            if (in_array($operation['method'], ['DELETE', 'PUT', 'PATCH']) && isset($pathParts[2])) {
                $resourceId = $pathParts[2];
                $this->cacheService->flush($resourceType, $resourceId);
            } 
            // For POST operations or operations on collections
            else {
                $this->cacheService->flush($resourceType);
            }
        }
    }
}

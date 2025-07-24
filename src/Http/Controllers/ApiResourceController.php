<?php

namespace ApiServerPackage\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use ApiServerPackage\Contracts\ApiExposable;
use ApiServerPackage\Query\QueryProcessor;
use ApiServerPackage\Services\ApiCacheService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ApiResourceController extends Controller
{
    /**
     * The model class.
     *
     * @var string
     */
    protected $modelClass;
    
    /**
     * The API cache service.
     *
     * @var \ApiServerPackage\Services\ApiCacheService
     */
    protected $cacheService;

    /**
     * Create a new controller instance.
     *
     * @param string $modelClass
     * @param \ApiServerPackage\Services\ApiCacheService|null $cacheService
     */
    public function __construct(string $modelClass, ApiCacheService $cacheService = null)
    {
        $this->modelClass = $modelClass;
        $this->cacheService = $cacheService ?? app(ApiCacheService::class);
        
        // Ensure model implements ApiExposable
        if (!in_array(ApiExposable::class, class_implements($modelClass))) {
            throw new \InvalidArgumentException("Model {$modelClass} must implement ApiExposable interface");
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $model = new $this->modelClass;
        $resourceType = $model->getApiResourceName();
        $requestParams = $request->all();
        
        return $this->cacheService->remember($resourceType, 'index', $requestParams, function () use ($model, $request) {
            $queryProcessor = new QueryProcessor($model, $request->all());
            $query = $queryProcessor->process();
            
            // Apply pagination if requested
            $perPage = $request->input('per_page', config('api-server.default_per_page', 15));
            $maxPerPage = config('api-server.max_per_page', 100);
            
            // Ensure per_page doesn't exceed the maximum
            $perPage = min($perPage, $maxPerPage);
            $page = $request->input('page', 1);
            
            $results = $query->paginate($perPage, ['*'], 'page', $page);
            
            return response()->json([
                'data' => $results->items(),
                'meta' => [
                    'current_page' => $results->currentPage(),
                    'from' => $results->firstItem(),
                    'last_page' => $results->lastPage(),
                    'path' => $request->url(),
                    'per_page' => $results->perPage(),
                    'to' => $results->lastItem(),
                    'total' => $results->total(),
                ],
            ]);
        });
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $model = new $this->modelClass;
        $fields = $model->getApiFields();
        
        // Validate request data against allowed fields
        $validator = Validator::make($request->all(), $this->getValidationRules($model, $fields));
        
        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }
        
        // Filter request data to only include allowed fields
        $data = $request->only($fields);
        
        // Handle file uploads if present
        $data = $this->handleFileUploads($request, $data, $model);
        
        try {
            DB::beginTransaction();
            
            // Create the model
            $model = $this->modelClass::create($data);
            
            DB::commit();
            
            return response()->json([
                'data' => $model,
                'message' => 'Resource created successfully',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'error' => 'Failed to create resource',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        try {
            $model = new $this->modelClass;
            $resourceType = $model->getApiResourceName();
            $requestParams = array_merge($request->all(), ['id' => $id]);
            
            return $this->cacheService->remember($resourceType, 'show', $requestParams, function () use ($model, $request, $id) {
                $queryProcessor = new QueryProcessor($model, $request->all());
                $query = $queryProcessor->process();
                
                $result = $query->findOrFail($id);
                
                return response()->json([
                    'data' => $result,
                ]);
            });
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Resource not found',
                'message' => "Resource with ID {$id} not found",
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve resource',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $model = $this->modelClass::findOrFail($id);
            $fields = $model->getApiFields();
            
            // Validate request data against allowed fields
            $validator = Validator::make($request->all(), $this->getValidationRules($model, $fields, true));
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }
            
            // Filter request data to only include allowed fields
            $data = $request->only($fields);
            
            // Handle file uploads if present
            $data = $this->handleFileUploads($request, $data, $model);
            
            DB::beginTransaction();
            
            // Update the model
            $model->update($data);
            
            DB::commit();
            
            return response()->json([
                'data' => $model,
                'message' => 'Resource updated successfully',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Resource not found',
                'message' => "Resource with ID {$id} not found",
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'error' => 'Failed to update resource',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $model = $this->modelClass::findOrFail($id);
            
            DB::beginTransaction();
            
            // Delete the model
            $model->delete();
            
            DB::commit();
            
            return response()->json([
                'message' => 'Resource deleted successfully',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Resource not found',
                'message' => "Resource with ID {$id} not found",
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'error' => 'Failed to delete resource',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Get validation rules for the model fields.
     *
     * @param  mixed  $model
     * @param  array  $fields
     * @param  bool  $isUpdate
     * @return array
     */
    protected function getValidationRules($model, array $fields, bool $isUpdate = false): array
    {
        $rules = [];
        
        // Check if model has a getValidationRules method
        if (method_exists($model, 'getValidationRules')) {
            $rules = $model->getValidationRules($isUpdate);
        } else {
            // Default rules - all fields are required for create, optional for update
            foreach ($fields as $field) {
                $rules[$field] = $isUpdate ? 'sometimes' : 'required';
            }
        }
        
        return $rules;
    }
    
    /**
     * Handle file uploads in the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $data
     * @param  mixed  $model
     * @return array
     */
    protected function handleFileUploads(Request $request, array $data, $model): array
    {
        // Check if model has file upload configuration
        if (!method_exists($model, 'getFileUploadConfig')) {
            return $data;
        }
        
        $fileConfig = $model->getFileUploadConfig();
        
        foreach ($fileConfig as $field => $config) {
            if ($request->hasFile($field)) {
                $file = $request->file($field);
                
                // Get storage path from config or use default
                $path = $config['path'] ?? 'uploads';
                
                // Store the file
                $filePath = $file->store($path, $config['disk'] ?? 'public');
                
                // Update data with file path
                $data[$field] = $filePath;
            }
        }
        
        return $data;
    }
}

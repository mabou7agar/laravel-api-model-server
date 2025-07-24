<?php

/**
 * This is an example script showing how to integrate the Laravel API Server
 * with the Laravel API Model Relations client package.
 */

// Step 1: Configure your client application with the generated credentials
$apiConfig = [
    'api_url' => 'https://your-api-server.com/api',
    'oauth' => [
        'client_id' => 'client-id-from-integration-command',
        'client_secret' => 'client-secret-from-integration-command',
        'token_url' => 'https://your-api-server.com/oauth/token',
    ],
    'use_encrypted_queries' => true,
    'retry' => [
        'max_attempts' => 3,
        'delay' => 1000,
        'multiplier' => 2,
    ],
];

// Step 2: Create a model that extends ApiModel in your client application
/**
 * Example User model in client application:
 * 
 * namespace App\Models;
 * 
 * use ApiModelRelations\Models\ApiModel;
 * 
 * class User extends ApiModel
 * {
 *     protected $fillable = ['name', 'email'];
 *     
 *     public function getApiEndpoint(): string
 *     {
 *         return 'users'; // Must match getApiResourceName() on server
 *     }
 *     
 *     public function getApiKeyName(): string
 *     {
 *         return 'id';
 *     }
 * }
 */

// Step 3: Use the model to interact with the API
/**
 * // Fetch a user from the API
 * $user = User::findFromApi(1);
 * echo $user->name;
 * 
 * // Update a user
 * $user->name = 'Updated Name';
 * $user->saveToApi();
 * 
 * // Create a new user
 * $newUser = new User(['name' => 'New User', 'email' => 'new@example.com']);
 * $newUser->saveToApi();
 * 
 * // Delete a user
 * $user->deleteFromApi();
 * 
 * // Get all users
 * $users = User::allFromApi();
 * 
 * // Query with filters
 * $filteredUsers = User::allFromApi([
 *     'filter' => [
 *         'name' => ['operator' => 'like', 'value' => 'John']
 *     ],
 *     'sort' => ['created_at' => 'desc'],
 *     'include' => ['posts'],
 * ]);
 */

// Step 4: Using encrypted queries with file uploads
/**
 * // In your client application:
 * use Illuminate\Support\Facades\Crypt;
 * use Illuminate\Http\Client\Factory as HttpFactory;
 * 
 * $http = new HttpFactory();
 * 
 * // Create query parameters
 * $params = [
 *     'filter' => ['name' => 'John'],
 *     'sort' => ['created_at' => 'desc'],
 * ];
 * 
 * // Encrypt the query
 * $encryptedQuery = Crypt::encrypt([
 *     'params' => $params,
 *     'expires_at' => now()->addMinutes(5)->timestamp,
 * ]);
 * 
 * // Send request with file upload and encrypted query
 * $response = $http->attach(
 *     'profile_image', file_get_contents('path/to/image.jpg'), 'profile.jpg'
 * )->post('https://your-api-server.com/api/users', [
 *     '_query' => $encryptedQuery,
 * ]);
 */

// Step 5: Using gRPC with the client package
/**
 * // Configure gRPC in your client application's api_model.php config:
 * 'clients' => [
 *     'default' => [
 *         'driver' => 'rest',
 *         // REST configuration...
 *     ],
 *     'grpc' => [
 *         'driver' => 'grpc',
 *         'host' => 'your-grpc-server.com',
 *         'port' => 50051,
 *         'services' => [
 *             'users' => \App\Protos\UserService::class,
 *         ],
 *     ],
 * ],
 * 
 * // In your model:
 * public function getApiClientName(): string
 * {
 *     return 'grpc'; // Use gRPC client instead of REST
 * }
 */

echo "This is an example integration script. See the code for usage examples.\n";

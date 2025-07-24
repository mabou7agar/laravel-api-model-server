<?php

namespace ApiServerPackage\Bridge;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\TokenRepository;

class ApiPackageBridge
{
    /**
     * The client repository instance.
     *
     * @var \Laravel\Passport\ClientRepository
     */
    protected $clients;
    
    /**
     * The token repository instance.
     *
     * @var \Laravel\Passport\TokenRepository
     */
    protected $tokens;
    
    /**
     * Create a new API package bridge instance.
     *
     * @param  \Laravel\Passport\ClientRepository  $clients
     * @param  \Laravel\Passport\TokenRepository  $tokens
     * @return void
     */
    public function __construct(ClientRepository $clients, TokenRepository $tokens)
    {
        $this->clients = $clients;
        $this->tokens = $tokens;
    }
    
    /**
     * Generate a new API client for inter-service communication.
     *
     * @param  string  $name
     * @param  array  $scopes
     * @return array
     */
    public function generateApiClient(string $name, array $scopes = ['*']): array
    {
        // Create a password client
        $client = $this->clients->createPasswordGrantClient(
            null, $name, 'http://localhost'
        );
        
        return [
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'scopes' => $scopes,
        ];
    }
    
    /**
     * Generate an encrypted query string for API requests.
     *
     * @param  array  $params
     * @param  int|null  $expiresInSeconds
     * @param  bool  $signed
     * @return string
     */
    public function generateEncryptedQuery(array $params, ?int $expiresInSeconds = 300, bool $signed = false): string
    {
        $payload = [
            'params' => $params,
        ];
        
        // Add expiration if specified
        if ($expiresInSeconds !== null) {
            $payload['expires_at'] = now()->addSeconds($expiresInSeconds)->timestamp;
        }
        
        // Add signature if requested
        if ($signed) {
            $secret = config('api-server.signature_secret');
            $payload['signature'] = hash_hmac('sha256', json_encode($params), $secret);
        }
        
        return Crypt::encrypt($payload);
    }
    
    /**
     * Configure the client package with server settings.
     *
     * @param  string  $baseUrl
     * @param  array  $clientCredentials
     * @return array
     */
    public function configureClientPackage(string $baseUrl, array $clientCredentials): array
    {
        $config = [
            'api_url' => $baseUrl,
            'oauth' => [
                'client_id' => $clientCredentials['client_id'],
                'client_secret' => $clientCredentials['client_secret'],
                'token_url' => $baseUrl . '/oauth/token',
            ],
            'use_encrypted_queries' => config('api-server.use_encrypted_queries', true),
        ];
        
        return $config;
    }
    
    /**
     * Test the connection between client and server packages.
     *
     * @param  string  $baseUrl
     * @param  array  $clientCredentials
     * @return bool
     */
    public function testConnection(string $baseUrl, array $clientCredentials): bool
    {
        try {
            // Get OAuth token
            $response = Http::post($baseUrl . '/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $clientCredentials['client_id'],
                'client_secret' => $clientCredentials['client_secret'],
                'scope' => '*',
            ]);
            
            if (!$response->successful()) {
                return false;
            }
            
            $token = $response->json()['access_token'];
            
            // Test API endpoint
            $testResponse = Http::withToken($token)->get($baseUrl . '/api/ping');
            
            return $testResponse->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}

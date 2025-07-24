<?php

namespace ApiServerPackage\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class DecryptQueryParameters
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->has('_query')) {
            try {
                // Get the encrypted query
                $encryptedQuery = $request->input('_query');
                
                // Decrypt and validate payload
                $payload = $this->decryptAndValidate($encryptedQuery);
                
                // Extract query parameters and expiration
                $params = $payload['params'] ?? [];
                $expiresAt = $payload['expires_at'] ?? null;
                
                // Check if query has expired
                if ($expiresAt && now()->timestamp > $expiresAt) {
                    return response()->json([
                        'error' => 'Query parameters expired',
                        'message' => 'The provided query parameters have expired.'
                    ], 400);
                }
                
                // Merge the decrypted parameters into the request
                $request->merge($params);
                
                // Remove the encrypted query parameter
                $input = $request->except('_query');
                $request->replace($input);
            } catch (DecryptException $e) {
                return response()->json([
                    'error' => 'Invalid query parameters',
                    'message' => 'The provided query parameters are invalid or have been tampered with.'
                ], 400);
            }
        }
        
        return $next($request);
    }
    
    /**
     * Decrypt and validate the query parameters.
     *
     * @param string $encryptedQuery
     * @return array
     * @throws \Illuminate\Contracts\Encryption\DecryptException
     */
    protected function decryptAndValidate(string $encryptedQuery): array
    {
        $payload = Crypt::decrypt($encryptedQuery);
        
        if (!is_array($payload)) {
            throw new DecryptException('Invalid payload format');
        }
        
        // Verify signature if present
        if (isset($payload['signature'])) {
            $this->verifySignature($payload);
        }
        
        return $payload;
    }
    
    /**
     * Verify the signature of the payload.
     *
     * @param array $payload
     * @return bool
     * @throws \Illuminate\Contracts\Encryption\DecryptException
     */
    protected function verifySignature(array $payload): bool
    {
        $signature = $payload['signature'];
        $params = $payload['params'] ?? [];
        $secret = config('api-server.signature_secret');
        
        $expectedSignature = hash_hmac('sha256', json_encode($params), $secret);
        
        if (!hash_equals($expectedSignature, $signature)) {
            throw new DecryptException('Invalid signature');
        }
        
        return true;
    }
}

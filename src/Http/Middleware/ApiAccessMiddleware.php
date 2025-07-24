<?php

namespace ApiServerPackage\Http\Middleware;

use Closure;
use Http\Discovery\Psr17Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\TokenRepository;
use League\OAuth2\Server\ResourceServer;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

class ApiAccessMiddleware
{
    /**
     * The Resource Server instance.
     *
     * @var \League\OAuth2\Server\ResourceServer
     */
    protected $server;

    /**
     * Token Repository.
     *
     * @var \Laravel\Passport\TokenRepository
     */
    protected $tokens;

    /**
     * PSR-7 HTTP Factory.
     *
     * @var \Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory
     */
    protected $psrFactory;

    /**
     * Create a new middleware instance.
     *
     * @param  \League\OAuth2\Server\ResourceServer  $server
     * @param  \Laravel\Passport\TokenRepository  $tokens
     * @return void
     */
    public function __construct(ResourceServer $server, TokenRepository $tokens)
    {
        $this->server = $server;
        $this->tokens = $tokens;

        $psr17Factory = new Psr17Factory();
        $this->psrFactory = new PsrHttpFactory(
            $psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory
        );
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$scopes
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$scopes)
    {
        // Skip authentication if API access control is disabled
        if (!config('api-server.use_oauth_access_control', true)) {
            return $next($request);
        }

        try {
            // Convert Laravel request to PSR-7 request
            $psr = $this->psrFactory->createRequest($request);

            // Validate the access token
            $psr = $this->server->validateAuthenticatedRequest($psr);

            // Get the token ID from the validated request
            $tokenId = $psr->getAttribute('oauth_access_token_id');

            // Get the token from the repository
            $token = $this->tokens->find($tokenId);

            // Check if the token exists and is not revoked
            if (!$token || $token->revoked) {
                return response()->json([
                    'error' => 'Invalid or revoked token',
                ], 401);
            }

            // Check scopes if specified
            if (!empty($scopes)) {
                $tokenScopes = $token->scopes ?? [];

                // Check if the token has the required scopes
                if (!$this->hasAllScopes($tokenScopes, $scopes)) {
                    return response()->json([
                        'error' => 'Insufficient scope',
                        'required_scopes' => $scopes,
                    ], 403);
                }
            }

            // Store client ID in the request for later use
            $request->attributes->set('oauth_client_id', $token->client_id);

            // Store scopes in the request for later use
            $request->attributes->set('oauth_scopes', $token->scopes ?? []);

            return $next($request);

        } catch (\Exception $e) {
            Log::error('API access validation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Unauthorized',
                'message' => config('app.debug') ? $e->getMessage() : 'Invalid access token',
            ], 401);
        }
    }

    /**
     * Determine if the token has all of the required scopes.
     *
     * @param  array  $tokenScopes
     * @param  array  $requiredScopes
     * @return bool
     */
    protected function hasAllScopes(array $tokenScopes, array $requiredScopes): bool
    {
        // If the token has the '*' scope, it has access to all scopes
        if (in_array('*', $tokenScopes)) {
            return true;
        }

        // Check if the token has all the required scopes
        foreach ($requiredScopes as $scope) {
            if (!in_array($scope, $tokenScopes)) {
                return false;
            }
        }

        return true;
    }
}

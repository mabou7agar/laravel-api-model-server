<?php

namespace ApiServerPackage\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\Exceptions\MissingScopeException;
use Laravel\Passport\TokenRepository;
use League\OAuth2\Server\ResourceServer;
use Illuminate\Auth\AuthenticationException;

class CheckApiScopes
{
    /**
     * The resource server instance.
     *
     * @var \League\OAuth2\Server\ResourceServer
     */
    protected $server;

    /**
     * Token repository instance.
     *
     * @var \Laravel\Passport\TokenRepository
     */
    protected $tokens;

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
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  mixed  ...$scopes
     * @return mixed
     * @throws \Illuminate\Auth\AuthenticationException
     * @throws \Laravel\Passport\Exceptions\MissingScopeException
     */
    public function handle(Request $request, Closure $next, ...$scopes)
    {
        // Skip OAuth check if disabled in config
        if (!config('api-server.use_oauth', true)) {
            return $next($request);
        }

        try {
            // Validate the access token
            $psr = $this->server->validateAuthenticatedRequest($request->getPsrRequest());
            
            // Get the token ID from the PSR request
            $tokenId = $psr->getAttribute('oauth_access_token_id');
            
            // Get the token from the repository
            $token = $this->tokens->find($tokenId);
            
            // Check if token exists and is not revoked
            if (!$token || $token->revoked) {
                throw new AuthenticationException('The access token is invalid or has been revoked.');
            }
            
            // Check if token has the required scopes
            if (!empty($scopes) && !$token->can($scopes)) {
                throw new MissingScopeException($scopes);
            }
            
            // Set the authenticated user on the request
            $request->setUserResolver(function () use ($token) {
                return $token->user;
            });
            
            return $next($request);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => $e->getMessage(),
            ], 401);
        }
    }
}

<?php

namespace ApiServerPackage\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AddEtagHeaders
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
        // Skip if ETags are disabled
        if (!config('api-server.use_etags', true)) {
            return $next($request);
        }

        $response = $next($request);

        // Only add ETags to JSON responses for GET and HEAD requests
        if (
            $response instanceof JsonResponse &&
            in_array($request->method(), ['GET', 'HEAD'])
        ) {
            $this->addEtag($request, $response);
        }

        return $response;
    }

    /**
     * Add ETag header to the response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\JsonResponse  $response
     * @return void
     */
    protected function addEtag(Request $request, JsonResponse $response): void
    {
        // Generate ETag from response content
        $content = $response->getContent();
        $etag = md5($content);

        // Add ETag header
        $response->header('ETag', '"' . $etag . '"');

        // Check if the client sent an If-None-Match header
        $ifNoneMatch = $request->header('If-None-Match');

        // If the ETag matches, return 304 Not Modified
        if ($ifNoneMatch === '"' . $etag . '"') {
            $response->setStatusCode(304);
            $response->setContent('');
        }

        // Add Cache-Control header if not already set
        if (!$response->headers->has('Cache-Control')) {
            $maxAge = config('api-server.etag_cache_max_age', 3600); // 1 hour
            $response->header('Cache-Control', 'public, max-age=' . $maxAge);
        }
    }
}

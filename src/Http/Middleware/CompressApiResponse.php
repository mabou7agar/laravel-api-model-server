<?php

namespace ApiServerPackage\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CompressApiResponse
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
        // Process the request
        $response = $next($request);
        
        // Only compress JSON responses if compression is enabled
        if (
            $response instanceof JsonResponse && 
            config('api-server.use_compression', true) &&
            $this->shouldCompress($request)
        ) {
            $this->compressResponse($response);
        }
        
        return $response;
    }
    
    /**
     * Determine if the response should be compressed.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function shouldCompress(Request $request): bool
    {
        // Check if the client accepts gzip encoding
        if (!$request->header('Accept-Encoding') || 
            !str_contains($request->header('Accept-Encoding'), 'gzip')) {
            return false;
        }
        
        // Don't compress small responses
        $minSize = config('api-server.compression_min_size', 1024); // 1KB
        
        return $minSize <= 0 || request()->header('Content-Length', 0) >= $minSize;
    }
    
    /**
     * Compress the response content.
     *
     * @param  \Illuminate\Http\JsonResponse  $response
     * @return void
     */
    protected function compressResponse(JsonResponse $response): void
    {
        // Get the original content
        $content = $response->getContent();
        
        // Compress the content
        $compressed = gzencode($content, config('api-server.compression_level', 6));
        
        // Set the compressed content and required headers
        $response->setContent($compressed);
        $response->header('Content-Encoding', 'gzip');
        $response->header('Content-Length', strlen($compressed));
        $response->header('Vary', 'Accept-Encoding');
    }
}

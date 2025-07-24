<?php

namespace ApiServerPackage\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PingController extends Controller
{
    /**
     * Respond to a ping request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ping(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'message' => 'API server is running',
            'timestamp' => now()->toIso8601String(),
            'version' => config('api-server.version', '1.0.0'),
        ]);
    }
}

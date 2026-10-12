<?php

namespace App\Http\Controllers\ClubPos;

use App\Http\Controllers\Controller;
use App\Services\Connectivity\ConnectivityMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConnectivityController extends Controller
{
    public function __construct(
        private readonly ConnectivityMonitor $monitor,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(
            $this->monitor->status($request->boolean('probe')),
        );
    }
}

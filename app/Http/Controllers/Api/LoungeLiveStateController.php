<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LoungeLiveStateService;
use Illuminate\Http\JsonResponse;

class LoungeLiveStateController extends Controller
{
    public function show(LoungeLiveStateService $service): JsonResponse
    {
        return response()->json($service->state());
    }
}

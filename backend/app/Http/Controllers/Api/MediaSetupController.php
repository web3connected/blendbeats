<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MediaSetupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaSetupController extends Controller
{
    public function show(Request $request, MediaSetupService $mediaSetup): JsonResponse
    {
        return response()->json($mediaSetup->payload($request->user()));
    }

    public function store(Request $request, MediaSetupService $mediaSetup): JsonResponse
    {
        return response()->json($mediaSetup->setup($request->user()));
    }

    public function features(Request $request, MediaSetupService $mediaSetup): JsonResponse
    {
        return response()->json([
            'features' => $mediaSetup->payload($request->user())['features'],
        ]);
    }
}

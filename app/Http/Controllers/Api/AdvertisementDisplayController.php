<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdvertisementDisplayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvertisementDisplayController extends Controller
{
    public function __construct(private readonly AdvertisementDisplayService $advertisements) {}

    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'placement' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json([
            'ad' => $this->advertisements->select($validated['placement'] ?? null),
        ]);
    }
}

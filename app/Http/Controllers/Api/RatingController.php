<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RatingController extends Controller
{
    public function show(Request $request, RatingService $ratings, string $type, string $id): JsonResponse
    {
        $attributes = $request->validate([
            'context' => ['nullable', 'string', 'max:80'],
        ]);

        $target = $ratings->target($type, $id);

        return response()->json([
            'rating' => $ratings->summary($target, $request->user(), $attributes['context'] ?? 'default'),
        ]);
    }

    public function store(Request $request, RatingService $ratings, string $type, string $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, Response::HTTP_UNAUTHORIZED);

        $attributes = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review' => ['nullable', 'string', 'max:2000'],
            'context' => ['nullable', 'string', 'max:80'],
        ]);

        $target = $ratings->target($type, $id);
        $rating = $ratings->rate(
            $user,
            $target,
            (int) $attributes['rating'],
            $attributes['review'] ?? null,
            $attributes['context'] ?? 'default',
        );

        return response()->json([
            'rating' => [
                ...$ratings->summary($target->refresh(), $user, $rating->context),
                'id' => $rating->id,
            ],
        ], Response::HTTP_CREATED);
    }

    public function destroy(Request $request, RatingService $ratings, string $type, string $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, Response::HTTP_UNAUTHORIZED);

        $attributes = $request->validate([
            'context' => ['nullable', 'string', 'max:80'],
        ]);

        $target = $ratings->target($type, $id);
        $ratings->remove($user, $target, $attributes['context'] ?? 'default');

        return response()->json([
            'rating' => $ratings->summary($target->refresh(), $user, $attributes['context'] ?? 'default'),
        ]);
    }
}

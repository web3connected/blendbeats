<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Services\MediaManagerService;
use App\Services\MediaStorageQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MediaManagerController extends Controller
{
    public function index(
        Request $request,
        MediaManagerService $mediaManager,
        MediaStorageQuotaService $quotaService,
    ): JsonResponse {
        $attributes = $request->validate([
            'disk' => ['nullable', Rule::in(['public', 'local', 'media_s3', 's3'])],
            'path' => ['nullable', 'string', 'max:255'],
            'collection' => ['nullable', 'string', 'max:120'],
        ]);

        return response()->json([
            'files' => $mediaManager->listMediaFiles(
                $attributes['disk'] ?? 'public',
                $attributes['path'] ?? '',
                $attributes['collection'] ?? null,
            ),
            'quota' => $quotaService->quotaForOwner($request->user()),
        ]);
    }

    public function store(
        Request $request,
        MediaManagerService $mediaManager,
        MediaStorageQuotaService $quotaService,
    ): JsonResponse {
        $attributes = $request->validate([
            'file' => ['required', 'file', 'max:51200'],
            'disk' => ['nullable', Rule::in(['public', 'local'])],
            'collection' => ['nullable', 'string', 'max:120'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'genre' => ['nullable', 'string', 'max:120'],
            'visibility' => ['nullable', Rule::in(['public', 'unlisted', 'private', 'draft'])],
            'media_kind' => ['nullable', Rule::in(['mix', 'track', 'video', 'battle_entry', 'image'])],
        ]);

        $file = $mediaManager->uploadFileToMediaManager(
            $attributes['file'],
            $attributes['disk'] ?? 'public',
            $attributes['collection'] ?? MediaManagerService::COLLECTION_DJ_MEDIA,
        );

        $file->forceFill([
            'metadata' => [
                ...($file->metadata ?? []),
                'portfolio' => [
                    'title' => $attributes['title'] ?? null,
                    'description' => $attributes['description'] ?? null,
                    'genre' => $attributes['genre'] ?? null,
                    'visibility' => $attributes['visibility'] ?? 'draft',
                    'media_kind' => $attributes['media_kind'] ?? null,
                ],
            ],
        ])->save();

        return response()->json([
            'file' => $mediaManager->filePayload($file->refresh()),
            'quota' => $quotaService->quotaForOwner($request->user()),
        ], 201);
    }

    public function tree(Request $request, MediaManagerService $mediaManager): JsonResponse
    {
        $attributes = $request->validate([
            'disk' => ['nullable', Rule::in(['public', 'local', 'media_s3', 's3'])],
        ]);

        return response()->json(
            $mediaManager->getDirectoryTree($attributes['disk'] ?? 'public'),
        );
    }

    public function stream(MediaFile $file, MediaManagerService $mediaManager)
    {
        return $mediaManager->downloadFile($file);
    }

    public function update(
        Request $request,
        MediaFile $file,
        MediaManagerService $mediaManager,
        MediaStorageQuotaService $quotaService,
    ): JsonResponse {
        $attributes = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'genre' => ['nullable', 'string', 'max:120'],
            'visibility' => ['nullable', Rule::in(['public', 'unlisted', 'private', 'draft'])],
            'media_kind' => ['nullable', Rule::in(['mix', 'track', 'video', 'battle_entry', 'image'])],
        ]);

        $portfolio = collect($attributes)
            ->filter(fn ($value): bool => $value !== null)
            ->all();

        $updatedFile = $mediaManager->updatePortfolioMetadata($file, $portfolio);

        return response()->json([
            'file' => $mediaManager->filePayload($updatedFile),
            'quota' => $quotaService->quotaForOwner($request->user()),
        ]);
    }

    public function destroy(
        Request $request,
        MediaFile $file,
        MediaManagerService $mediaManager,
        MediaStorageQuotaService $quotaService,
    ): JsonResponse {
        abort_unless($mediaManager->deleteMediaFile($file), 500, 'Unable to delete media file.');

        return response()->json([
            'deleted' => true,
            'quota' => $quotaService->quotaForOwner($request->user()),
        ]);
    }
}

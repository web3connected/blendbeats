<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Notifications\PortfolioAudioUploadedNotification;
use App\Services\MediaManagerService;
use App\Services\MediaStorageQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            'media_kind' => ['nullable', Rule::in(['mix', 'track', 'video', 'scratch', 'battle_entry', 'image'])],
            'duration_seconds' => ['nullable', 'numeric', 'min:0', 'max:86400'],
            'cover_image' => ['nullable', 'image', 'max:10240'],
        ]);

        $this->validateScratchVideoPayload($attributes);

        $file = $mediaManager->uploadFileToMediaManager(
            $attributes['file'],
            $attributes['disk'] ?? 'public',
            $attributes['collection'] ?? MediaManagerService::COLLECTION_DJ_MEDIA,
        );

        $coverFile = isset($attributes['cover_image'])
            ? $mediaManager->uploadForOwner($request->user(), $attributes['cover_image'], 'public', MediaManagerService::COLLECTION_DJ_IMAGES)
            : null;

        $file->forceFill([
            'metadata' => [
                ...($file->metadata ?? []),
                'duration_seconds' => isset($attributes['duration_seconds'])
                    ? round((float) $attributes['duration_seconds'], 2)
                    : null,
                'portfolio' => [
                    'title' => $attributes['title'] ?? null,
                    'description' => $attributes['description'] ?? null,
                    'genre' => $attributes['genre'] ?? null,
                    'visibility' => $attributes['visibility'] ?? 'draft',
                    'media_kind' => $attributes['media_kind'] ?? null,
                    'duration_seconds' => isset($attributes['duration_seconds'])
                        ? round((float) $attributes['duration_seconds'], 2)
                        : null,
                    'cover_media_file_id' => $coverFile?->id,
                    'cover_image_path' => $coverFile?->path,
                    'cover_image_url' => $coverFile?->url,
                ],
            ],
        ])->save();

        $portfolioKind = $attributes['media_kind'] ?? null;
        if (
            Schema::hasTable('notifications')
            && $file->isAudio()
            && in_array($portfolioKind, ['mix', 'track'], true)
        ) {
            $request->user()->notify(new PortfolioAudioUploadedNotification($file->refresh()));
        }

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
            'media_kind' => ['nullable', Rule::in(['mix', 'track', 'video', 'scratch', 'battle_entry', 'image'])],
            'duration_seconds' => ['nullable', 'numeric', 'min:0', 'max:86400'],
            'cover_image' => ['nullable', 'image', 'max:10240'],
        ]);

        $this->validateScratchVideoPayload($attributes, $file);

        $portfolio = collect($attributes)
            ->except('cover_image')
            ->filter(fn ($value): bool => $value !== null)
            ->all();

        if (isset($attributes['cover_image'])) {
            $coverFile = $mediaManager->uploadForOwner($request->user(), $attributes['cover_image'], 'public', MediaManagerService::COLLECTION_DJ_IMAGES);
            $portfolio = [
                ...$portfolio,
                'cover_media_file_id' => $coverFile->id,
                'cover_image_path' => $coverFile->path,
                'cover_image_url' => $coverFile->url,
            ];
        }

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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function validateScratchVideoPayload(array $attributes, ?MediaFile $file = null): void
    {
        if (($attributes['media_kind'] ?? null) !== 'scratch') {
            return;
        }

        $uploadedFile = $attributes['file'] ?? null;
        $mimeType = $uploadedFile instanceof UploadedFile
            ? (string) $uploadedFile->getMimeType()
            : (string) ($file?->mime_type ?? '');

        if (! str_starts_with($mimeType, 'video/')) {
            throw ValidationException::withMessages([
                'file' => ['DJ Scratches must be uploaded as video files.'],
            ]);
        }

        $duration = $attributes['duration_seconds']
            ?? $file?->metadata['portfolio']['duration_seconds']
            ?? $file?->metadata['duration_seconds']
            ?? null;

        if (! is_numeric($duration) || (float) $duration <= 0) {
            throw ValidationException::withMessages([
                'duration_seconds' => ['DJ Scratches need a readable video duration.'],
            ]);
        }

        if (floor((float) $duration) > 300) {
            throw ValidationException::withMessages([
                'duration_seconds' => ['DJ Scratches must be 5 minutes or less.'],
            ]);
        }
    }
}

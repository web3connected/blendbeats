<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Notifications\PortfolioAudioUploadedNotification;
use App\Services\GamificationService;
use App\Services\MediaManagerService;
use App\Services\MediaStorageQuotaService;
use App\Services\MembershipTierService;
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
        MembershipTierService $membershipTiers,
    ): JsonResponse {
        $attributes = $request->validate([
            'file' => ['nullable', 'required_without:external_url', 'file', 'max:51200'],
            'external_url' => ['nullable', 'required_without:file', 'url', 'max:2048'],
            'source_type' => ['nullable', Rule::in(['upload', 'youtube', 'instagram'])],
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

        $youtubeVideo = $this->youtubeVideoFromAttributes($attributes);
        $instagramMedia = $this->instagramMediaFromAttributes($attributes);

        $this->validateExternalVideoPayload($attributes, $youtubeVideo);
        $this->validateScratchVideoPayload($attributes, null, $youtubeVideo !== null || $instagramMedia !== null);
        $this->assertScratchVideoMonthlyLimit($request, $membershipTiers, $attributes);

        $file = match (true) {
            $youtubeVideo !== null => $mediaManager->createExternalYoutubeForOwner(
                $request->user(),
                $youtubeVideo,
                $attributes['collection'] ?? MediaManagerService::COLLECTION_DJ_MEDIA,
            ),
            $instagramMedia !== null => $mediaManager->createExternalInstagramForOwner(
                $request->user(),
                $instagramMedia,
                $attributes['collection'] ?? MediaManagerService::COLLECTION_DJ_MEDIA,
            ),
            default => $mediaManager->uploadFileToMediaManager(
                $attributes['file'],
                $attributes['disk'] ?? 'public',
                $attributes['collection'] ?? MediaManagerService::COLLECTION_DJ_MEDIA,
            ),
        };

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
                    'source_type' => $instagramMedia['source_type'] ?? ($youtubeVideo ? 'youtube' : 'upload'),
                    'external_provider' => $instagramMedia['external_provider'] ?? ($youtubeVideo ? 'youtube' : null),
                    'external_url' => $instagramMedia['external_url'] ?? $youtubeVideo['watch_url'] ?? null,
                    'embed_url' => $instagramMedia['embed_url'] ?? $youtubeVideo['embed_url'] ?? null,
                    'thumbnail_url' => $instagramMedia['thumbnail_url'] ?? $youtubeVideo['thumbnail_url'] ?? null,
                    'cover_media_file_id' => $coverFile?->id,
                    'cover_image_path' => $coverFile?->path,
                    'cover_image_url' => $coverFile?->url ?? $youtubeVideo['thumbnail_url'] ?? null,
                ],
            ],
        ])->save();

        if (($attributes['media_kind'] ?? null) === 'scratch') {
            app(GamificationService::class)->award(
                userId: $request->user()->id,
                actionKey: 'scratch_uploaded',
                targetType: 'media_file',
                targetId: $file->id,
                metadata: [
                    'source_type' => $file->metadata['portfolio']['source_type'] ?? null,
                    'media_type' => $file->type ?? null,
                    'media_kind' => $attributes['media_kind'] ?? null,
                ],
            );
        }

        if (($attributes['media_kind'] ?? null) !== 'scratch') {
            app(GamificationService::class)->award(
                userId: $request->user()->id,
                actionKey: 'portfolio_uploaded',
                targetType: 'media_file',
                targetId: $file->id,
                metadata: [
                    'source_type' => $file->metadata['portfolio']['source_type'] ?? null,
                    'media_type' => $file->type ?? null,
                    'media_kind' => $attributes['media_kind'] ?? null,
                ],
            );
        }

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
            'external_url' => ['nullable', 'url', 'max:2048'],
            'source_type' => ['nullable', Rule::in(['upload', 'youtube', 'instagram'])],
            'cover_image' => ['nullable', 'image', 'max:10240'],
        ]);

        $youtubeVideo = $this->youtubeVideoFromAttributes($attributes);
        $instagramMedia = $this->instagramMediaFromAttributes($attributes);
        $externalValidationAttributes = [
            ...$attributes,
            'media_kind' => $attributes['media_kind'] ?? $file->metadata['portfolio']['media_kind'] ?? null,
        ];

        if (($attributes['source_type'] ?? null) !== 'upload') {
            $this->validateExternalVideoPayload($externalValidationAttributes, $youtubeVideo);
        }

        $this->validateScratchVideoPayload($attributes, $file);

        $portfolio = collect($attributes)
            ->except('cover_image', 'external_url', 'source_type')
            ->filter(fn ($value): bool => $value !== null)
            ->all();

        $externalPortfolio = match ($attributes['source_type'] ?? null) {
            'youtube' => $youtubeVideo ? [
                'source_type' => 'youtube',
                'external_provider' => 'youtube',
                'external_url' => $youtubeVideo['watch_url'],
                'embed_url' => $youtubeVideo['embed_url'],
                'thumbnail_url' => $youtubeVideo['thumbnail_url'],
            ] : [],
            'instagram' => $instagramMedia ?? [],
            default => [],
        };

        $portfolio = [
            ...$portfolio,
            ...$externalPortfolio,
        ];

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
    private function validateScratchVideoPayload(array $attributes, ?MediaFile $file = null, bool $isYoutubeLink = false): void
    {
        if (($attributes['media_kind'] ?? null) !== 'scratch') {
            return;
        }

        if ($isYoutubeLink) {
            return;
        }

        $uploadedFile = $attributes['file'] ?? null;
        $mimeType = $uploadedFile instanceof UploadedFile
            ? (string) $uploadedFile->getMimeType()
            : (string) ($file?->mime_type ?? '');

        if (! str_starts_with($mimeType, 'video/')) {
            throw ValidationException::withMessages([
                'file' => ['Scratch routines must be uploaded as video files.'],
            ]);
        }

        $duration = $attributes['duration_seconds']
            ?? $file?->metadata['portfolio']['duration_seconds']
            ?? $file?->metadata['duration_seconds']
            ?? null;

        if (! is_numeric($duration) || (float) $duration <= 0) {
            throw ValidationException::withMessages([
                'duration_seconds' => ['Scratch routines need a readable video duration.'],
            ]);
        }

        if (floor((float) $duration) > 300) {
            throw ValidationException::withMessages([
                'duration_seconds' => ['Scratch routines must be 5 minutes or less.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array{video_id: string, watch_url: string, embed_url: string, thumbnail_url: string}|null  $youtubeVideo
     */
    private function validateExternalVideoPayload(array $attributes, ?array $youtubeVideo): void
    {
        if (! isset($attributes['external_url'])) {
            return;
        }

        if (isset($attributes['file'])) {
            throw ValidationException::withMessages([
                'external_url' => ['Choose either a video upload or a YouTube link.'],
            ]);
        }

        $sourceType = ($attributes['source_type'] ?? null) === 'instagram' ? 'instagram' : 'youtube';

        if ($sourceType === 'instagram') {
            if (! $this->isInstagramUrl($attributes['external_url'] ?? '')) {
                throw ValidationException::withMessages([
                    'external_url' => ['Please provide a valid Instagram link.'],
                ]);
            }

            return;
        }

        if (! $youtubeVideo) {
            throw ValidationException::withMessages([
                'external_url' => ['Enter a valid YouTube video link.'],
            ]);
        }

        if (! in_array($attributes['media_kind'] ?? null, ['video', 'scratch'], true)) {
            throw ValidationException::withMessages([
                'media_kind' => ['YouTube links can only be added as videos or Scratch routines.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{video_id: string, watch_url: string, embed_url: string, thumbnail_url: string}|null
     */
    private function youtubeVideoFromAttributes(array $attributes): ?array
    {
        $url = trim((string) ($attributes['external_url'] ?? ''));

        if ($url === '') {
            return null;
        }

        $videoId = $this->youtubeVideoIdFromUrl($url);

        if (! $videoId) {
            return null;
        }

        return [
            'video_id' => $videoId,
            'watch_url' => "https://www.youtube.com/watch?v={$videoId}",
            'embed_url' => "https://www.youtube.com/embed/{$videoId}",
            'thumbnail_url' => "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg",
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{source_type: string, external_provider: string, external_url: string, embed_url: null, thumbnail_url: null}|null
     */
    private function instagramMediaFromAttributes(array $attributes): ?array
    {
        if (($attributes['source_type'] ?? null) !== 'instagram') {
            return null;
        }

        $url = trim((string) ($attributes['external_url'] ?? ''));

        if ($url === '') {
            return null;
        }

        return [
            'source_type' => 'instagram',
            'external_provider' => 'instagram',
            'external_url' => $url,
            'embed_url' => null,
            'thumbnail_url' => null,
        ];
    }

    private function youtubeVideoIdFromUrl(string $url): ?string
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $videoId = null;

        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        if ($host === 'youtu.be') {
            $videoId = explode('/', $path)[0] ?? null;
        } elseif (in_array($host, ['youtube.com', 'm.youtube.com', 'music.youtube.com'], true)) {
            if ($path === 'watch') {
                parse_str((string) ($parts['query'] ?? ''), $query);
                $videoId = is_string($query['v'] ?? null) ? $query['v'] : null;
            } elseif (preg_match('#^(embed|shorts|live)/([^/?]+)#', $path, $matches)) {
                $videoId = $matches[2];
            }
        }

        if (! is_string($videoId) || ! preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId)) {
            return null;
        }

        return $videoId;
    }

    private function isInstagramUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! $host) {
            return false;
        }

        $host = strtolower($host);

        return $host === 'instagram.com'
            || $host === 'www.instagram.com';
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assertScratchVideoMonthlyLimit(
        Request $request,
        MembershipTierService $membershipTiers,
        array $attributes,
    ): void {
        if (($attributes['media_kind'] ?? null) !== 'scratch') {
            return;
        }

        if (isset($attributes['external_url'])) {
            return;
        }

        $user = $request->user();
        $limit = $membershipTiers->scratchVideoMonthlyLimitFor($user);

        if ($limit === null) {
            return;
        }

        $monthStart = now()->startOfMonth();
        $monthEnd = $monthStart->copy()->addMonth();
        $uploadedThisMonth = MediaFile::withTrashed()
            ->where('user_id', $user->id)
            ->where('collection', MediaManagerService::COLLECTION_DJ_MEDIA)
            ->where('mime_type', 'like', 'video/%')
            ->where('created_at', '>=', $monthStart)
            ->where('created_at', '<', $monthEnd)
            ->get()
            ->filter(function (MediaFile $file): bool {
                $metadata = $file->metadata ?? [];
                $portfolio = $metadata['portfolio'] ?? [];

                return ($portfolio['media_kind'] ?? null) === 'scratch'
                    && ($portfolio['source_type'] ?? $metadata['external_source']['provider'] ?? 'upload') !== 'youtube';
            })
            ->count();

        if ($uploadedThisMonth < $limit) {
            return;
        }

        $tierName = config('billing.subscription.tiers.'.$membershipTiers->tierFor($user).'.name', 'Your tier');

        throw ValidationException::withMessages([
            'media_kind' => ["{$tierName} includes {$limit} Scratch routine video uploads per month."],
        ]);
    }
}

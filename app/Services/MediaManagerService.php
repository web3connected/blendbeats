<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\MediaFile;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class MediaManagerService
{
    public const COLLECTION_FRONTEND_PUBLIC = 'frontend_public';

    public const COLLECTION_ADMIN_LOCAL = 'admin_local';

    public const COLLECTION_PROTECTED_LOCAL = 'protected_local';

    public const COLLECTION_S3_ARCHIVE = 's3_archive';

    public const COLLECTION_DJ_MEDIA = 'dj_media';

    public const COLLECTION_DJ_AUDIO = 'dj_media/audio';

    public const COLLECTION_DJ_VIDEO = 'dj_media/video';

    public const COLLECTION_DJ_IMAGES = 'dj_media/images';

    public const COLLECTION_USER_AVATARS = 'user_avatars';

    protected string $disk;

    protected string $basePath;

    public function __construct(?string $disk = null)
    {
        $this->disk = $disk ?: 'public';
        $this->basePath = 'media';
    }

    public function getFileUrl(MediaFile $file): string
    {
        return match ($file->disk) {
            'public' => $this->publicDiskUrl($file->path),
            'local', 'media_s3', 's3' => "/api/media/files/{$file->id}/stream",
            default => throw new \InvalidArgumentException("Unsupported disk: {$file->disk}"),
        };
    }

    private function publicDiskUrl(string $path): string
    {
        $normalizedPath = ltrim(str_replace('\\', '/', $path), '/');

        if ($this->usesDirectPublicUrl($normalizedPath)) {
            return '/'.$normalizedPath;
        }

        return '/storage/'.$normalizedPath;
    }

    private function isDirectPublicFile(string $path): bool
    {
        $normalizedPath = ltrim(str_replace('\\', '/', $path), '/');

        return $this->usesDirectPublicUrl($normalizedPath) && is_file(public_path($normalizedPath));
    }

    private function usesDirectPublicUrl(string $path): bool
    {
        $normalizedPath = ltrim(str_replace('\\', '/', $path), '/');

        return str_starts_with($normalizedPath, 'media/')
            && is_file(public_path($normalizedPath));
    }

    public function uploadFileToMediaManager(UploadedFile $file, string $disk = 'public', ?string $collection = null): MediaFile
    {
        $owner = $this->currentOwner();

        if (! $owner) {
            throw new UnauthorizedHttpException('', 'Authentication required');
        }

        return $this->uploadForOwner($owner, $file, $disk, $collection);
    }

    public function uploadForOwner(Model $owner, UploadedFile $file, string $disk = 'public', ?string $collection = null): MediaFile
    {
        $this->validateDiskAccess($disk, 'upload', $owner);
        app(MediaStorageQuotaService::class)->assertUploadAllowed($owner, $file);

        $mediaAccount = app(MediaSetupService::class)->activeMediaAccount($owner)
            ?: $this->createMediaAccountForOwner($owner);

        $collection ??= self::COLLECTION_DJ_MEDIA;
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $mimeType = $file->getMimeType();
        $fileSize = $file->getSize() ?: 0;
        $metadata = $this->extractMetadata($file);
        $filename = pathinfo($originalName, PATHINFO_FILENAME);
        $uniqueFilename = Str::slug($filename).'_'.time().'_'.Str::lower(Str::random(6)).'.'.$extension;
        $directory = $this->accountMediaPath($mediaAccount->root_path, $collection);

        if ($disk === 'public' && str_starts_with($collection, self::COLLECTION_DJ_MEDIA)) {
            $directory = $this->portfolioPublicPath($owner);

            if ($collection === self::COLLECTION_DJ_IMAGES) {
                $directory .= '/covers';
            }
        }

        $path = $file->storeAs($directory, $uniqueFilename, $mediaAccount->disk);

        $mediaFile = MediaFile::create([
            ...$this->ownerColumns($owner),
            'name' => $uniqueFilename,
            'original_name' => $originalName,
            'disk' => $mediaAccount->disk,
            'path' => $path,
            'mime_type' => $mimeType,
            'size' => $fileSize,
            'collection' => $collection,
            'media_account_id' => $mediaAccount->id,
            'metadata' => $metadata,
        ]);

        $this->auditAction('upload', $mediaAccount->disk, $path, $owner);

        return $mediaFile;
    }

    public function archiveToS3(MediaFile $file): MediaFile
    {
        $this->validateDiskAccess('media_s3', 'archive', $this->ownerForFile($file));

        DB::beginTransaction();

        try {
            $content = Storage::disk($file->disk)->get($file->path);
            $s3Path = 'archive/'.($file->collection ?? 'uncategorized').'/'.basename($file->path);

            Storage::disk('media_s3')->put($s3Path, $content);

            $originalDisk = $file->disk;
            $originalPath = $file->path;

            $file->update([
                'disk' => 'media_s3',
                'path' => $s3Path,
            ]);

            if ($originalDisk !== 'public') {
                Storage::disk($originalDisk)->delete($originalPath);
            }

            $this->auditAction('archive', 'media_s3', $s3Path, $this->ownerForFile($file));

            DB::commit();

            return $file;
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error('Archive to S3 failed', [
                'file_id' => $file->id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function listMediaFiles(string $disk = 'public', string $path = '', ?string $collection = null): array
    {
        $owner = $this->currentOwner();
        $this->validateDiskAccess($disk, 'view', $owner);

        $files = MediaFile::where('disk', $disk)
            ->when($owner && ! $this->currentUserIsAdmin(), fn ($query) => $this->scopeToOwner($query, $owner))
            ->when($path, fn ($query, $path) => $query->where('path', 'like', $path.'%'))
            ->when($collection, fn ($query, $collection) => $query->where('collection', $collection))
            ->orderBy('created_at', 'desc')
            ->get();

        return $files->map(fn (MediaFile $file): array => $this->filePayload($file))->toArray();
    }

    public function getDirectoryTree(string $disk = 'public'): array
    {
        $owner = $this->currentOwner();
        $this->validateDiskAccess($disk, 'view', $owner);

        $collections = MediaFile::where('disk', $disk)
            ->when($owner && ! $this->currentUserIsAdmin(), fn ($query) => $this->scopeToOwner($query, $owner))
            ->distinct()
            ->pluck('collection')
            ->filter()
            ->sort()
            ->values()
            ->toArray();

        return [
            'disk' => $disk,
            'directories' => $collections,
        ];
    }

    public function deleteMediaFile(MediaFile $file): bool
    {
        $owner = $this->ownerForFile($file);
        $this->validateDiskAccess($file->disk, 'delete', $owner);
        $this->authorizeFileOwner($file);

        DB::beginTransaction();

        try {
            if ($file->disk === 'public' && $this->isDirectPublicFile($file->path)) {
                $publicFile = public_path($file->path);
                if (is_file($publicFile)) {
                    unlink($publicFile);
                }
            } else {
                Storage::disk($file->disk)->delete($file->path);
            }
            $file->delete();
            $this->auditAction('delete', $file->disk, $file->path, $owner);

            DB::commit();

            return true;
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error('File deletion failed', [
                'file_id' => $file->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function updatePortfolioMetadata(MediaFile $file, array $portfolio): MediaFile
    {
        $owner = $this->ownerForFile($file);
        $this->validateDiskAccess($file->disk, 'update', $owner);
        $this->authorizeFileOwner($file);

        $metadata = $file->metadata ?? [];
        $metadata['portfolio'] = [
            ...($metadata['portfolio'] ?? []),
            ...$portfolio,
        ];

        $file->forceFill(['metadata' => $metadata])->save();
        $this->auditAction('update', $file->disk, $file->path, $owner);

        return $file->refresh();
    }

    public function downloadFile(MediaFile $file)
    {
        $this->validateDiskAccess($file->disk, 'view', $this->ownerForFile($file));
        $this->authorizeFileOwner($file);

        $disk = Storage::disk($file->disk);

        if ($file->disk === 'public' && $this->isDirectPublicFile($file->path)) {
            $publicFile = public_path($file->path);

            return response(file_get_contents($publicFile))
                ->header('Content-Type', $file->mime_type)
                ->header('Content-Disposition', 'inline; filename="'.($file->original_name ?? $file->name).'"');
        }

        if (! $disk->exists($file->path)) {
            throw new Exception('File not found');
        }

        return response($disk->get($file->path))
            ->header('Content-Type', $file->mime_type)
            ->header('Content-Disposition', 'inline; filename="'.($file->original_name ?? $file->name).'"');
    }

    public function filePayload(MediaFile $file): array
    {
        $portfolio = $file->metadata['portfolio'] ?? [];

        return [
            'id' => $file->id,
            'name' => $file->name,
            'original_name' => $file->original_name,
            'path' => $file->path,
            'disk' => $file->disk,
            'mime_type' => $file->mime_type,
            'size' => $file->size,
            'formatted_size' => $file->formatted_size,
            'collection' => $file->collection,
            'url' => $file->url,
            'is_image' => $file->isImage(),
            'is_video' => $file->isVideo(),
            'is_audio' => $file->isAudio(),
            'is_pdf' => $file->isPdf(),
            'metadata' => $file->metadata,
            'portfolio_title' => $portfolio['title'] ?? null,
            'portfolio_description' => $portfolio['description'] ?? null,
            'portfolio_genre' => $portfolio['genre'] ?? null,
            'portfolio_visibility' => $portfolio['visibility'] ?? null,
            'portfolio_kind' => $portfolio['media_kind'] ?? null,
            'portfolio_cover_image_path' => $portfolio['cover_image_path'] ?? null,
            'portfolio_cover_image_url' => $portfolio['cover_image_url'] ?? null,
            'created_at' => $file->created_at,
        ];
    }

    public function switchDisk(string $disk): self
    {
        $this->disk = $disk;

        return $this;
    }

    public static function getCollections(): array
    {
        return [
            self::COLLECTION_FRONTEND_PUBLIC => 'Frontend Public',
            self::COLLECTION_DJ_MEDIA => 'DJ Media',
            self::COLLECTION_DJ_AUDIO => 'DJ Audio',
            self::COLLECTION_DJ_VIDEO => 'DJ Video',
            self::COLLECTION_DJ_IMAGES => 'DJ Images',
            self::COLLECTION_USER_AVATARS => 'User Avatars',
            self::COLLECTION_ADMIN_LOCAL => 'Admin Local',
            self::COLLECTION_PROTECTED_LOCAL => 'Protected Local',
            self::COLLECTION_S3_ARCHIVE => 'S3 Archive',
        ];
    }

    public static function formatBytes(int $size, int $precision = 2): string
    {
        if ($size === 0) {
            return '0 B';
        }

        $base = log($size, 1024);
        $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];

        return round(pow(1024, $base - floor($base)), $precision).' '.$suffixes[(int) floor($base)];
    }

    private function createMediaAccountForOwner(Model $owner)
    {
        app(MediaSetupService::class)->setup($owner);

        return app(MediaSetupService::class)->activeMediaAccount($owner->refresh());
    }

    private function validateDiskAccess(string $disk, string $action = 'view', ?Model $owner = null): void
    {
        $owner ??= $this->currentOwner();

        if (! $owner) {
            throw new UnauthorizedHttpException('', 'Authentication required');
        }

        $role = $this->getUserRole($owner);

        if (! $this->hasPermission($role, $disk, $action)) {
            throw new UnauthorizedHttpException('', "No {$action} permission for disk: {$disk}");
        }
    }

    private function authorizeFileOwner(MediaFile $file): void
    {
        if ($this->currentUserIsAdmin()) {
            return;
        }

        $owner = $this->currentOwner();

        if ($owner instanceof User && $file->user_id === $owner->getKey()) {
            return;
        }

        throw new UnauthorizedHttpException('', 'You do not own this media file');
    }

    private function getUserRole(Model $owner): string
    {
        if ($owner instanceof Admin) {
            return 'admin';
        }

        if (isset($owner->role) && $owner->role) {
            return strtolower($owner->role->name ?? 'user');
        }

        if (isset($owner->role_id)) {
            return match ((int) $owner->role_id) {
                1 => 'admin',
                2 => 'staff',
                default => 'user',
            };
        }

        return 'user';
    }

    private function hasPermission(string $role, string $disk, string $action): bool
    {
        $permissions = [
            'admin' => [
                'public' => ['view', 'upload', 'update', 'delete', 'move', 'archive'],
                'local' => ['view', 'upload', 'update', 'delete', 'move', 'archive'],
                'media_s3' => ['view', 'upload', 'update', 'delete', 'move', 'archive'],
                's3' => ['view', 'upload', 'update', 'delete', 'move', 'archive'],
            ],
            'staff' => [
                'public' => ['view', 'upload', 'update', 'delete'],
                'local' => ['view', 'upload', 'update', 'delete'],
                'media_s3' => ['view'],
                's3' => ['view'],
            ],
            'user' => [
                'public' => ['view', 'upload', 'update', 'delete'],
                'local' => ['view'],
                'media_s3' => [],
                's3' => [],
            ],
        ];

        return in_array($action, $permissions[$role][$disk] ?? [], true);
    }

    private function currentOwner(): ?Model
    {
        return Auth::guard('admin')->user() ?: Auth::user();
    }

    private function currentUserIsAdmin(): bool
    {
        return Auth::guard('admin')->check();
    }

    private function ownerForFile(MediaFile $file): ?Model
    {
        return $file->admin ?: $file->user;
    }

    private function ownerColumns(Model $owner): array
    {
        if ($owner instanceof Admin) {
            return ['admin_id' => $owner->getKey()];
        }

        if ($owner instanceof User) {
            return ['user_id' => $owner->getKey()];
        }

        throw new \InvalidArgumentException('Media library owner must be a User or Admin model.');
    }

    private function scopeToOwner($query, Model $owner)
    {
        if ($owner instanceof Admin) {
            return $query->where('admin_id', $owner->getKey());
        }

        return $query->where('user_id', $owner->getKey());
    }

    private function extractMetadata(UploadedFile $file): array
    {
        $metadata = [
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ];

        if (str_starts_with((string) $file->getMimeType(), 'image/')) {
            try {
                $imageSize = getimagesize($file->getPathname());

                if ($imageSize) {
                    $metadata['width'] = $imageSize[0];
                    $metadata['height'] = $imageSize[1];
                }
            } catch (Exception) {
                // Image metadata is helpful but not required.
            }
        }

        return $metadata;
    }

    private function accountMediaPath(string $rootPath, string $collection): string
    {
        return trim($rootPath, '/').'/'.trim($collection, '/');
    }

    private function portfolioPublicPath(Model $owner): string
    {
        $slug = null;

        if ($owner instanceof User) {
            $slug = $owner->djProfile?->handle;
        }

        $slug = Str::slug($slug ?: ($owner->name ?? 'portfolio')) ?: 'portfolio-'.$owner->getKey();

        return 'media/portfolios/'.$slug;
    }

    private function auditAction(string $action, string $disk, string $path, ?Model $owner = null): void
    {
        DB::table('media_manager_audit_logs')->insert([
            ...($owner ? $this->ownerColumns($owner) : []),
            'action' => $action,
            'disk' => $disk,
            'file_path' => $path,
            'metadata' => json_encode([
                'ip' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

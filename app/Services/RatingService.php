<?php

namespace App\Services;

use App\Models\DjProfile;
use App\Models\MediaFile;
use App\Models\Mix;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RatingService
{
    /**
     * @var array<string, class-string<Model>>
     */
    private array $rateableMap = [
        'mix' => Mix::class,
        'mixes' => Mix::class,
        'dj' => DjProfile::class,
        'djs' => DjProfile::class,
        'dj_profile' => DjProfile::class,
        'dj_profiles' => DjProfile::class,
        'song' => MediaFile::class,
        'songs' => MediaFile::class,
        'track' => MediaFile::class,
        'tracks' => MediaFile::class,
        'media_file' => MediaFile::class,
        'media_files' => MediaFile::class,
    ];

    public function target(string $type, int|string $id): Model
    {
        $class = $this->rateableMap[strtolower($type)] ?? null;

        if (! $class) {
            throw new NotFoundHttpException('Rating target type is not supported.');
        }

        /** @var Model|null $target */
        $target = $class::query()->find($id);

        if (! $target || ! $this->isPubliclyRateable($target)) {
            throw new NotFoundHttpException('Rating target was not found.');
        }

        return $target;
    }

    public function rate(User $user, Model $target, int $rating, ?string $review = null, string $context = 'default'): Rating
    {
        /** @var Rating $userRating */
        $userRating = DB::transaction(function () use ($user, $target, $rating, $review, $context): Rating {
            $userRating = Rating::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'rateable_type' => $target::class,
                    'rateable_id' => $target->getKey(),
                    'context' => $context,
                ],
                [
                    'rating' => $rating,
                    'review' => $review,
                ],
            );

            $this->syncTargetSummary($target, $context);

            return $userRating;
        });

        return $userRating;
    }

    public function remove(User $user, Model $target, string $context = 'default'): bool
    {
        return DB::transaction(function () use ($user, $target, $context): bool {
            $deleted = Rating::query()
                ->where('user_id', $user->id)
                ->where('rateable_type', $target::class)
                ->where('rateable_id', $target->getKey())
                ->where('context', $context)
                ->delete() > 0;

            $this->syncTargetSummary($target, $context);

            return $deleted;
        });
    }

    public function summary(Model $target, ?User $user = null, string $context = 'default'): array
    {
        $query = Rating::query()
            ->where('rateable_type', $target::class)
            ->where('rateable_id', $target->getKey())
            ->where('context', $context);

        $count = (int) (clone $query)->count();
        $average = $count > 0 ? round((float) (clone $query)->avg('rating'), 2) : 0.0;

        $userRating = $user
            ? (clone $query)->where('user_id', $user->id)->first()
            : null;

        return [
            'average' => $average,
            'count' => $count,
            'user_rating' => $userRating?->rating,
            'context' => $context,
        ];
    }

    private function syncTargetSummary(Model $target, string $context): void
    {
        if (method_exists($target, 'syncRatingSummaryColumns')) {
            $target->syncRatingSummaryColumns($context);
        }
    }

    private function isPubliclyRateable(Model $target): bool
    {
        if ($target instanceof Mix) {
            return $target->is_public && filled($target->published_at);
        }

        if ($target instanceof DjProfile) {
            return $target->profile_status === 'active' && $target->visibility === 'public';
        }

        if ($target instanceof MediaFile) {
            return $target->disk === 'public';
        }

        return true;
    }
}

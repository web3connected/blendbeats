<?php

namespace App\Traits;

use App\Models\Rating;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Rateable
{
    public function ratings(): MorphMany
    {
        return $this->morphMany(Rating::class, 'rateable');
    }

    public function ratingSummary(string $context = 'default'): array
    {
        $ratings = $this->ratings()->where('context', $context);
        $count = (int) $ratings->count();
        $average = $count > 0 ? round((float) $ratings->avg('rating'), 2) : 0.0;

        return [
            'average' => $average,
            'count' => $count,
        ];
    }

    public function syncRatingSummaryColumns(string $context = 'default'): void
    {
        if ($context !== 'default') {
            return;
        }

        $summary = $this->ratingSummary($context);
        $updates = [];

        if ($this->isFillable('rating_average') || array_key_exists('rating_average', $this->getAttributes())) {
            $updates['rating_average'] = $summary['average'];
        }

        if ($this->isFillable('rating_count') || array_key_exists('rating_count', $this->getAttributes())) {
            $updates['rating_count'] = $summary['count'];
        }

        if ($updates !== []) {
            $this->forceFill($updates)->save();
        }
    }
}

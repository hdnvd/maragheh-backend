<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class News extends Model
{
    protected $table = 'news';

    protected $fillable = [
        'news_source_id', 'external_id', 'title', 'summary', 'body',
        'image_url', 'url', 'published_at', 'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_visible' => 'boolean',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(NewsSource::class, 'news_source_id');
    }

    public function scopeVisible(Builder $q): Builder
    {
        return $q->where('is_visible', true);
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $q;
        }
        $like = '%'.addcslashes($term, '%_\\').'%';

        return $q->where(fn (Builder $w) => $w->where('title', 'like', $like)
            ->orWhere('summary', 'like', $like)
            ->orWhere('body', 'like', $like));
    }
}

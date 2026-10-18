<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/** Admin-written content: tourist attractions and famous people of Maragheh. */
class Post extends Model
{
    public const TYPE_ATTRACTION = 'attraction';
    public const TYPE_CELEBRITY = 'celebrity';
    public const TYPES = [self::TYPE_ATTRACTION, self::TYPE_CELEBRITY];

    protected $fillable = [
        'type', 'title', 'subtitle', 'summary', 'body', 'cover_path',
        'latitude', 'longitude', 'sort_order', 'is_published',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'sort_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function images(): HasMany
    {
        return $this->hasMany(PostImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }
}

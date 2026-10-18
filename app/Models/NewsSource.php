<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsSource extends Model
{
    public const TYPE_MEHR_TAG = 'mehr_tag';
    public const TYPE_RSS = 'rss';
    public const TYPES = [self::TYPE_MEHR_TAG, self::TYPE_RSS];

    protected $fillable = ['name', 'type', 'url', 'keyword', 'max_pages', 'is_active', 'last_crawled_at', 'last_error'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'max_pages' => 'integer',
            'last_crawled_at' => 'datetime',
        ];
    }

    public function news(): HasMany
    {
        return $this->hasMany(News::class);
    }
}

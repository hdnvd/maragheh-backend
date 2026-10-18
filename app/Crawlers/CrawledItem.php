<?php

namespace App\Crawlers;

use Illuminate\Support\Carbon;

final class CrawledItem
{
    public function __construct(
        public string $externalId,
        public string $url,
        public string $title,
        public ?string $summary = null,
        public ?string $imageUrl = null,
        public ?Carbon $publishedAt = null,
        public ?string $body = null,
    ) {}
}

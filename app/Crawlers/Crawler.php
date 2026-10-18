<?php

namespace App\Crawlers;

use App\Models\NewsSource;

interface Crawler
{
    /**
     * Items listed on one page of the source, newest first.
     *
     * @return CrawledItem[]
     */
    public function listPage(NewsSource $source, int $page): array;

    /** Fills body / exact publish time / large image by fetching the article page. */
    public function fetchDetails(CrawledItem $item): CrawledItem;

    /** Whether the source has more than one page. */
    public function supportsPaging(): bool;
}

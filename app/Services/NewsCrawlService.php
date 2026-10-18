<?php

namespace App\Services;

use App\Crawlers\Crawler;
use App\Crawlers\CrawledItem;
use App\Crawlers\MehrTagCrawler;
use App\Crawlers\RssCrawler;
use App\Models\News;
use App\Models\NewsSource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class NewsCrawlService
{
    public function crawlerFor(NewsSource $source): Crawler
    {
        return match ($source->type) {
            NewsSource::TYPE_MEHR_TAG => new MehrTagCrawler,
            NewsSource::TYPE_RSS => new RssCrawler,
            default => throw new InvalidArgumentException("Unknown source type {$source->type}"),
        };
    }

    /**
     * Crawls one source and inserts news that are not in the database yet.
     *
     * @return int number of new items
     */
    public function crawl(NewsSource $source): int
    {
        $crawler = $this->crawlerFor($source);
        $maxArticles = (int) config('maragheh.crawler.max_articles', 40);
        $fullBody = (bool) config('maragheh.crawler.store_full_body', true);
        $pages = $crawler->supportsPaging() ? max(1, $source->max_pages) : 1;
        $added = 0;

        try {
            for ($page = 1; $page <= $pages && $added < $maxArticles; $page++) {
                $items = $crawler->listPage($source, $page);
                if (! $items) {
                    break;
                }
                $items = array_values(array_filter($items, fn (CrawledItem $i) => $this->matchesKeyword($source, $i)));
                $known = News::where('news_source_id', $source->id)
                    ->whereIn('external_id', array_map(fn ($i) => $i->externalId, $items))
                    ->pluck('external_id')->all();
                $new = array_filter($items, fn (CrawledItem $i) => ! in_array($i->externalId, $known, true));

                foreach ($new as $item) {
                    if ($added >= $maxArticles) {
                        break;
                    }
                    try {
                        if ($fullBody) {
                            $item = $crawler->fetchDetails($item);
                            usleep(400_000); // be polite to the source
                        }
                        $this->store($source, $item, $fullBody);
                        $added++;
                    } catch (Throwable $e) {
                        Log::warning("Crawl item failed {$item->url}: ".$e->getMessage());
                    }
                }
                // Keep going through all configured pages (only a few list requests): a previous run may
                // have stopped at the per-run cap and left older items unimported on later pages.
            }
            $source->forceFill(['last_crawled_at' => now(), 'last_error' => null])->save();
        } catch (Throwable $e) {
            $source->forceFill(['last_crawled_at' => now(), 'last_error' => Str::limit($e->getMessage(), 900)])->save();
            Log::error("Crawl failed for source {$source->id}: ".$e->getMessage());
            throw $e;
        }

        return $added;
    }

    private function matchesKeyword(NewsSource $source, CrawledItem $item): bool
    {
        if (! $source->keyword) {
            return true;
        }
        $haystack = $item->title.' '.$item->summary;

        return mb_stripos($haystack, $source->keyword) !== false;
    }

    private function store(NewsSource $source, CrawledItem $item, bool $fullBody): void
    {
        if ($item->title === '') {
            return;
        }
        News::firstOrCreate(
            ['news_source_id' => $source->id, 'external_id' => $item->externalId],
            [
                'title' => Str::limit($item->title, 490, ''),
                'summary' => $item->summary,
                'body' => $fullBody ? $item->body : null,
                'image_url' => $item->imageUrl ? Str::limit($item->imageUrl, 990, '') : null,
                'url' => Str::limit($item->url, 990, ''),
                'published_at' => $item->publishedAt ?? now(),
            ],
        );
    }
}

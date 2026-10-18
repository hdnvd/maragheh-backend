<?php

namespace App\Crawlers;

use App\Models\NewsSource;
use App\Support\HtmlSanitizer;
use App\Support\Jalali;
use Illuminate\Support\Carbon;

/**
 * Mehr News tag listing, e.g. https://www.mehrnews.com/tag/مراغه
 *
 * Listing:  <li class="news"> <figure><a href="/news/{id}/..."><img src></a></figure>
 *           <div class="desc"><h3><a>title</a></h3><p>summary</p><time><a>۱۴۰۵-۰۷-۲۳ ۱۱:۳۴</a></time></div>
 * Article:  <h1 class="title">, <p class="summary">, <div itemprop="articleBody">,
 *           <meta property="article:published_time" content="2026-10-15T08:04:03Z">
 */
class MehrTagCrawler implements Crawler
{
    private const BASE = 'https://www.mehrnews.com';

    public function supportsPaging(): bool
    {
        return true;
    }

    public function listPage(NewsSource $source, int $page): array
    {
        $url = $source->url;
        if ($page > 1) {
            $url .= (str_contains($url, '?') ? '&' : '?').'pi='.$page;
        }
        $xp = Http::xpath(Http::get($url));
        $items = [];
        foreach ($xp->query('//li['.Http::hasClass('news').']') as $li) {
            $a = $xp->query('.//h3/a', $li)->item(0);
            if (! $a) {
                continue;
            }
            $href = $a->getAttribute('href');
            if (! preg_match('#/news/(\d+)#', $href, $m)) {
                continue;
            }
            $img = $xp->query('.//img', $li)->item(0);
            $time = Http::text($xp->query('.//time', $li)->item(0));

            $items[] = new CrawledItem(
                externalId: $m[1],
                url: self::BASE.'/news/'.$m[1],
                title: Http::text($a) ?? '',
                summary: Http::text($xp->query('.//p', $li)->item(0)),
                imageUrl: $img ? HtmlSanitizer::absoluteUrl($img->getAttribute('src'), self::BASE) : null,
                publishedAt: $this->parseJalali($time),
            );
        }

        return $items;
    }

    public function fetchDetails(CrawledItem $item): CrawledItem
    {
        $xp = Http::xpath(Http::get($item->url));

        $canonical = $xp->query('//link[@rel="canonical"]/@href')->item(0)?->nodeValue;
        if ($canonical) {
            $item->url = $canonical;
        }
        $item->title = Http::text($xp->query('//h1['.Http::hasClass('title').']')->item(0)) ?? $item->title;
        $item->summary = Http::text($xp->query('//p['.Http::hasClass('summary').']')->item(0)) ?? $item->summary;

        $published = $xp->query('//meta[@property="article:published_time"]/@content')->item(0)?->nodeValue;
        if ($published) {
            $item->publishedAt = Carbon::parse($published)->utc();
        }
        $og = $xp->query('//meta[@property="og:image"]/@content')->item(0)?->nodeValue;
        if ($og) {
            $item->imageUrl = $og;
        }
        $body = $xp->query('//*[@itemprop="articleBody"]')->item(0);
        if ($body) {
            $item->body = HtmlSanitizer::clean(Http::innerHtml($body), self::BASE);
        }

        return $item;
    }

    /** "۱۴۰۵-۰۷-۲۳ ۱۱:۳۴" (Tehran time) -> UTC Carbon */
    private function parseJalali(?string $text): ?Carbon
    {
        if (! $text) {
            return null;
        }
        $t = Jalali::latinDigits($text);
        if (! preg_match('/(\d{4})-(\d{1,2})-(\d{1,2})\s+(\d{1,2}):(\d{2})/', $t, $m)) {
            return null;
        }
        [$gy, $gm, $gd] = Jalali::toGregorian((int) $m[1], (int) $m[2], (int) $m[3]);

        return Carbon::create($gy, $gm, $gd, (int) $m[4], (int) $m[5], 0, 'Asia/Tehran')->utc();
    }
}

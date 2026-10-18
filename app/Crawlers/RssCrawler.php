<?php

namespace App\Crawlers;

use App\Models\NewsSource;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Carbon;
use Throwable;

/** Generic RSS 2.0 feed. Use the source "keyword" to keep only Maragheh-related items. */
class RssCrawler implements Crawler
{
    public function supportsPaging(): bool
    {
        return false;
    }

    public function listPage(NewsSource $source, int $page): array
    {
        $xml = @simplexml_load_string(Http::get($source->url), 'SimpleXMLElement', LIBXML_NOCDATA);
        if (! $xml || ! isset($xml->channel)) {
            throw new \RuntimeException('Invalid RSS feed');
        }
        $items = [];
        foreach ($xml->channel->item as $entry) {
            $link = trim((string) $entry->link);
            if ($link === '') {
                continue;
            }
            $description = (string) $entry->description;
            $image = null;
            if (isset($entry->enclosure) && str_starts_with((string) $entry->enclosure['type'], 'image')) {
                $image = (string) $entry->enclosure['url'];
            }
            $media = $entry->children('http://search.yahoo.com/mrss/');
            if (! $image && isset($media->content)) {
                $image = (string) $media->content->attributes()->url;
            }
            if (! $image && preg_match('/<img[^>]+src="([^"]+)"/i', $description, $m)) {
                $image = $m[1];
            }
            try {
                $published = isset($entry->pubDate) ? Carbon::parse((string) $entry->pubDate)->utc() : null;
            } catch (Throwable) {
                $published = null;
            }
            $guid = trim((string) ($entry->guid ?? '')) ?: $link;

            $items[] = new CrawledItem(
                externalId: mb_substr(sha1($guid), 0, 40),
                url: $link,
                title: trim(html_entity_decode(strip_tags((string) $entry->title))),
                summary: trim(html_entity_decode(strip_tags($description))) ?: null,
                imageUrl: $image ? HtmlSanitizer::absoluteUrl($image, $link) : null,
                publishedAt: $published,
            );
        }

        return $items;
    }

    public function fetchDetails(CrawledItem $item): CrawledItem
    {
        // Feeds already carry the summary; the full article stays on the source site.
        return $item;
    }
}

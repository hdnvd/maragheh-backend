<?php

namespace App\Crawlers;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http as HttpClient;

/** Small helpers shared by crawlers. */
final class Http
{
    public static function get(string $url): string
    {
        return HttpClient::withHeaders([
            'User-Agent' => config('maragheh.crawler.user_agent'),
            'Accept-Language' => 'fa-IR,fa;q=0.9',
        ])->timeout(25)->retry(2, 1000)->get($url)->throw()->body();
    }

    public static function xpath(string $html): DOMXPath
    {
        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($doc);
    }

    public static function innerHtml(\DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument->saveHTML($child);
        }

        return $html;
    }

    public static function text(?\DOMNode $node): ?string
    {
        if (! $node) {
            return null;
        }
        $t = trim(preg_replace('/\s+/u', ' ', $node->textContent));

        return $t === '' ? null : $t;
    }

    /** XPath predicate matching an element that has the given CSS class. */
    public static function hasClass(string $class): string
    {
        return "contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')";
    }
}

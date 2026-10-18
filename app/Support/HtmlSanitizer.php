<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Whitelist-based HTML cleaner used for crawled article bodies and admin-written posts.
 * Drops scripts, styles, event handlers, iframes and anything not on the list.
 */
final class HtmlSanitizer
{
    private const ALLOWED = [
        'p' => [], 'br' => [], 'b' => [], 'strong' => [], 'i' => [], 'em' => [], 'u' => [],
        'h2' => [], 'h3' => [], 'h4' => [], 'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [],
        'a' => ['href'], 'img' => ['src', 'alt'], 'figure' => [], 'figcaption' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'td' => [], 'th' => [],
    ];

    /** Tags whose content is removed entirely. */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'noscript', 'svg', 'video', 'audio'];

    public static function clean(?string $html, ?string $baseUrl = null): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }
        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="__root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $doc->getElementById('__root') ?? $doc->documentElement;
        if (! $root) {
            return '';
        }
        self::walk($root, $baseUrl);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        // collapse empty paragraphs left behind by removed widgets
        $out = preg_replace('#<p>(\s|&nbsp;|<br>)*</p>#u', '', $out);

        return trim($out);
    }

    /**
     * Admin-written body: cleans the HTML, and when it has no block structure (plain text, or text with
     * only inline tags) turns blank-line-separated text into paragraphs.
     */
    public static function fromAdmin(?string $input): string
    {
        $html = self::clean($input);
        if ($html === '' || preg_match('#<(p|h[2-4]|ul|ol|blockquote|figure|table)\b#i', $html)) {
            return $html;
        }
        $paragraphs = array_filter(array_map('trim', preg_split("/\R{2,}/u", $html)), fn ($p) => $p !== '');

        return implode('', array_map(fn ($p) => '<p>'.nl2br($p, false).'</p>', $paragraphs));
    }

    private static function walk(DOMNode $node, ?string $baseUrl): void
    {
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (in_array($tag, self::DROP, true)) {
                    $node->removeChild($child);

                    continue;
                }
                self::walk($child, $baseUrl);
                if (! array_key_exists($tag, self::ALLOWED)) {
                    // unwrap: keep the children, drop the tag (div, span, section...)
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);

                    continue;
                }
                self::filterAttributes($child, self::ALLOWED[$tag], $baseUrl);
                if ($tag === 'img' && ! $child->getAttribute('src')) {
                    $node->removeChild($child);
                }
            } elseif ($child->nodeType === XML_COMMENT_NODE) {
                $node->removeChild($child);
            }
        }
    }

    private static function filterAttributes(DOMElement $el, array $allowed, ?string $baseUrl): void
    {
        for ($i = $el->attributes->length - 1; $i >= 0; $i--) {
            $name = strtolower($el->attributes->item($i)->name);
            if (! in_array($name, $allowed, true)) {
                $el->removeAttribute($el->attributes->item($i)->name);
            }
        }
        foreach (['href', 'src'] as $attr) {
            if ($el->hasAttribute($attr)) {
                $url = self::absoluteUrl(trim($el->getAttribute($attr)), $baseUrl);
                if ($url === null) {
                    $el->removeAttribute($attr);
                } else {
                    $el->setAttribute($attr, $url);
                }
            }
        }
        if ($el->tagName === 'a') {
            $el->setAttribute('rel', 'nofollow noopener');
        }
    }

    /** Only http(s) URLs survive; relative ones are resolved against the article URL. */
    public static function absoluteUrl(string $url, ?string $baseUrl): ?string
    {
        if ($url === '') {
            return null;
        }
        if (str_starts_with($url, '//')) {
            $url = 'https:'.$url;
        } elseif (! preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) && $baseUrl) {
            $p = parse_url($baseUrl);
            if (empty($p['scheme']) || empty($p['host'])) {
                return null;
            }
            $origin = $p['scheme'].'://'.$p['host'];
            $url = str_starts_with($url, '/') ? $origin.$url : $origin.'/'.ltrim($url, './');
        }

        return preg_match('#^https?://#i', $url) ? $url : null;
    }
}

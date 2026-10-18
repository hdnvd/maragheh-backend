<?php

namespace App\Http\Resources;

use App\Support\Jalali;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\News */
class NewsResource extends JsonResource
{
    public bool $detail = false;

    public static function detailed($resource): static
    {
        $r = new static($resource);
        $r->detail = true;

        return $r;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'summary' => $this->summary,
            'image' => $this->image_url,
            'url' => $this->url,
            'source' => $this->whenLoaded('source', fn () => [
                'id' => $this->source->id,
                'name' => $this->source->name,
            ]),
            'published_at' => $this->published_at?->toIso8601String(),
            'published_at_fa' => Jalali::dateTime($this->published_at),
            'body_html' => $this->when($this->detail, fn () => $this->body ?: ($this->summary ? '<p>'.e($this->summary).'</p>' : '')),
            'is_visible' => $this->when($request->user() instanceof \App\Models\User, $this->is_visible),
        ];
    }
}

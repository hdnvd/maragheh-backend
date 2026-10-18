<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Post */
class PostResource extends JsonResource
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
        $admin = $request->user() instanceof User;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'summary' => $this->summary,
            'cover' => $this->coverUrl(),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'images' => $this->when($this->detail, fn () => $this->images->map(fn ($i) => $i->url())->values()),
            'body_html' => $this->when($this->detail, fn () => $this->body ?? ''),
            $this->mergeWhen($admin, fn () => [
                'body' => $this->body,
                'sort_order' => $this->sort_order,
                'is_published' => $this->is_published,
                'gallery' => $this->whenLoaded('images', fn () => $this->images->map(fn ($i) => [
                    'id' => $i->id,
                    'url' => $i->url(),
                ])->values()),
                'updated_at' => $this->updated_at?->toIso8601String(),
            ]),
        ];
    }
}

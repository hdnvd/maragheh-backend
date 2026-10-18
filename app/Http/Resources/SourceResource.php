<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Support\Jalali;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\NewsSource */
class SourceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $admin = $request->user() instanceof User;

        return [
            'id' => $this->id,
            'name' => $this->name,
            $this->mergeWhen($admin, fn () => [
                'type' => $this->type,
                'url' => $this->url,
                'keyword' => $this->keyword,
                'max_pages' => $this->max_pages,
                'is_active' => $this->is_active,
                'last_crawled_at' => $this->last_crawled_at?->toIso8601String(),
                'last_crawled_at_fa' => Jalali::dateTime($this->last_crawled_at),
                'last_error' => $this->last_error,
                'news_count' => $this->news_count ?? null,
            ]),
        ];
    }
}

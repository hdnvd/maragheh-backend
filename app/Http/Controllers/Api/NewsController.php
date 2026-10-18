<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NewsResource;
use App\Http\Resources\SourceResource;
use App\Models\News;
use App\Models\NewsSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class NewsController extends Controller
{
    /**
     * GET /v1/news?q=&from=YYYY-MM-DD&to=YYYY-MM-DD&source_id=&page=
     * Dates are Gregorian calendar days in Tehran time (the app converts from Jalali).
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'source_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        $news = self::filtered(News::query()->visible(), $data)
            ->with('source:id,name')
            ->select(['id', 'news_source_id', 'title', 'summary', 'image_url', 'url', 'published_at'])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 20);

        return NewsResource::collection($news);
    }

    public static function filtered(Builder $query, array $data): Builder
    {
        return $query
            ->search($data['q'] ?? null)
            ->when($data['source_id'] ?? null, fn ($q, $id) => $q->where('news_source_id', $id))
            ->when($data['from'] ?? null, fn ($q, $d) => $q->where('published_at', '>=',
                Carbon::parse($d, 'Asia/Tehran')->startOfDay()->utc()))
            ->when($data['to'] ?? null, fn ($q, $d) => $q->where('published_at', '<=',
                Carbon::parse($d, 'Asia/Tehran')->endOfDay()->utc()));
    }

    public function show(int $id)
    {
        $news = News::visible()->with('source:id,name')->findOrFail($id);

        return NewsResource::detailed($news);
    }

    public function sources()
    {
        $sources = NewsSource::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return SourceResource::collection($sources);
    }
}

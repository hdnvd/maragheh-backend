<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SourceResource;
use App\Models\NewsSource;
use App\Services\NewsCrawlService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class SourceController extends Controller
{
    public function index()
    {
        return SourceResource::collection(NewsSource::withCount('news')->orderBy('id')->get());
    }

    public function store(Request $request)
    {
        $source = NewsSource::create($this->validated($request));

        return new SourceResource($source);
    }

    public function update(Request $request, NewsSource $source)
    {
        $source->update($this->validated($request, $source));

        return new SourceResource($source->loadCount('news'));
    }

    public function destroy(NewsSource $source)
    {
        $source->delete();

        return response()->noContent();
    }

    /** POST /v1/admin/sources/{id}/crawl — run the crawler now */
    public function crawl(NewsSource $source, NewsCrawlService $service)
    {
        @set_time_limit(300);
        try {
            $count = $service->crawl($source);
        } catch (Throwable $e) {
            return response()->json(['message' => 'خطا در دریافت اخبار: '.$e->getMessage()], 502);
        }

        return response()->json([
            'message' => $count > 0 ? "{$count} خبر جدید اضافه شد" : 'خبر جدیدی یافت نشد',
            'added' => $count,
            'source' => (new SourceResource($source->fresh()->loadCount('news')))->resolve(request()),
        ]);
    }

    private function validated(Request $request, ?NewsSource $source = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(NewsSource::TYPES)],
            'url' => ['required', 'url', 'max:500'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'max_pages' => ['nullable', 'integer', 'min:1', 'max:10'],
            'is_active' => ['boolean'],
        ]);
        $data['max_pages'] = $data['max_pages'] ?? $source?->max_pages ?? 2;

        return $data;
    }
}

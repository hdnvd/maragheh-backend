<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\NewsController as PublicNews;
use App\Http\Controllers\Controller;
use App\Http\Resources\NewsResource;
use App\Models\News;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'source_id' => ['nullable', 'integer'],
            'visible' => ['nullable', 'in:0,1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);
        $news = PublicNews::filtered(News::query(), $data)
            ->when(isset($data['visible']), fn ($q) => $q->where('is_visible', (bool) $data['visible']))
            ->with('source:id,name')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 20);

        return NewsResource::collection($news);
    }

    public function show(News $news)
    {
        return NewsResource::detailed($news->load('source:id,name'));
    }

    /** PATCH {is_visible?, title?, summary?} */
    public function update(Request $request, News $news)
    {
        $data = $request->validate([
            'is_visible' => ['sometimes', 'boolean'],
            'title' => ['sometimes', 'string', 'max:490'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);
        $news->update($data);

        return NewsResource::detailed($news->load('source:id,name'));
    }

    public function destroy(News $news)
    {
        // Hide instead of hard delete so the crawler does not re-import it.
        $news->update(['is_visible' => false]);

        return response()->noContent();
    }
}

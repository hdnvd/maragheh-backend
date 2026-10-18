<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    /** GET /v1/posts?type=attraction|celebrity&q=&page= */
    public function index(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(Post::TYPES)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $q = trim($data['q'] ?? '');

        $posts = Post::where('type', $data['type'])
            ->where('is_published', true)
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($w) => $w->where('title', 'like', $like)
                    ->orWhere('subtitle', 'like', $like)
                    ->orWhere('summary', 'like', $like));
            })
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(20);

        return PostResource::collection($posts);
    }

    public function show(int $id)
    {
        $post = Post::where('is_published', true)->with('images')->findOrFail($id);

        return PostResource::detailed($post);
    }
}

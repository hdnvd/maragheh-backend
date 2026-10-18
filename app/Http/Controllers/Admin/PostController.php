<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\PostImage;
use App\Services\ImageService;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Attractions (جاذبه‌های گردشگری) and celebrities (مشاهیر). */
class PostController extends Controller
{
    public function __construct(private ImageService $images) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(Post::TYPES)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $q = trim($data['q'] ?? '');
        $posts = Post::where('type', $data['type'])
            ->when($q !== '', fn ($query) => $query->where('title', 'like', '%'.addcslashes($q, '%_\\').'%'))
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(20);

        return PostResource::collection($posts);
    }

    public function show(Post $post)
    {
        return new PostResource($post->load('images'));
    }

    /** multipart: type, title, subtitle, summary, body, latitude, longitude, sort_order, is_published, cover */
    public function store(Request $request)
    {
        $data = $this->validated($request, true);
        $post = new Post($data);
        $post->body = HtmlSanitizer::fromAdmin($request->input('body'));
        if ($request->hasFile('cover')) {
            $post->cover_path = $this->images->store($request->file('cover'), 'posts', false)['path'];
        }
        $post->save();

        return (new PostResource($post->load('images')))->response()->setStatusCode(201);
    }

    /** POST with _method=PUT (multipart) or plain PUT JSON */
    public function update(Request $request, Post $post)
    {
        $data = $this->validated($request, false);
        $post->fill($data);
        if ($request->has('body')) {
            $post->body = HtmlSanitizer::fromAdmin($request->input('body'));
        }
        if ($request->hasFile('cover')) {
            $old = $post->cover_path;
            $post->cover_path = $this->images->store($request->file('cover'), 'posts', false)['path'];
            $this->images->delete($old);
        } elseif ($request->boolean('remove_cover')) {
            $this->images->delete($post->cover_path);
            $post->cover_path = null;
        }
        $post->save();

        return new PostResource($post->load('images'));
    }

    public function destroy(Post $post)
    {
        $this->images->delete($post->cover_path, ...$post->images->pluck('path')->all());
        $post->delete();

        return response()->noContent();
    }

    /** POST /v1/admin/posts/{post}/images (multipart: images[]) */
    public function addImages(Request $request, Post $post)
    {
        $request->validate([
            'images' => ['required', 'array', 'max:20'],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('maragheh.uploads.max_kb')],
        ]);
        $order = (int) $post->images()->max('sort_order');
        foreach ($request->file('images') as $file) {
            $stored = $this->images->store($file, 'posts', false);
            $post->images()->create(['path' => $stored['path'], 'sort_order' => ++$order]);
        }

        return new PostResource($post->load('images'));
    }

    public function deleteImage(Post $post, PostImage $image)
    {
        abort_unless($image->post_id === $post->id, 404);
        $this->images->delete($image->path);
        $image->delete();

        return new PostResource($post->load('images'));
    }

    private function validated(Request $request, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';
        $data = $request->validate([
            'type' => [$req, Rule::in(Post::TYPES)],
            'title' => [$req, 'string', 'max:200'],
            'subtitle' => ['nullable', 'string', 'max:200'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'body' => ['nullable', 'string', 'max:100000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'sort_order' => ['nullable', 'integer'],
            'is_published' => ['nullable', 'boolean'],
            'cover' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('maragheh.uploads.max_kb')],
        ], [], [
            'title' => 'عنوان', 'subtitle' => 'زیرعنوان', 'summary' => 'خلاصه', 'cover' => 'تصویر شاخص',
        ]);
        unset($data['body'], $data['cover']);
        if (array_key_exists('sort_order', $data)) {
            $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        }
        if (array_key_exists('is_published', $data)) {
            $data['is_published'] = (bool) ($data['is_published'] ?? true);
        }

        return $data;
    }
}

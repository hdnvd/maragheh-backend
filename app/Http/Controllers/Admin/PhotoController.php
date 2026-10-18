<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PhotoResource;
use App\Models\Photo;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Gallery moderation: approve / reject user photos, upload official photos. */
class PhotoController extends Controller
{
    public function __construct(private ImageService $images) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(Photo::STATUSES)],
            'app_user_id' => ['nullable', 'integer'],
        ]);
        $photos = Photo::query()
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($data['app_user_id'] ?? null, fn ($q, $id) => $q->where('app_user_id', $id))
            ->with(['appUser', 'reviewer:id,name'])
            ->latest('id')
            ->paginate(24);

        return PhotoResource::collection($photos);
    }

    /** Admin uploads are published immediately. multipart: image, title, description */
    public function store(Request $request)
    {
        $data = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('maragheh.uploads.max_kb')],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [], ['image' => 'عکس', 'title' => 'عنوان']);
        $stored = $this->images->store($data['image'], 'photos');
        $photo = Photo::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'path' => $stored['path'],
            'thumb_path' => $stored['thumb_path'],
            'width' => $stored['width'],
            'height' => $stored['height'],
            'status' => Photo::APPROVED,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ]);

        return (new PhotoResource($photo->load('reviewer:id,name')))->response()->setStatusCode(201);
    }

    public function update(Request $request, Photo $photo)
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);
        $photo->update($data);

        return new PhotoResource($photo->load(['appUser', 'reviewer:id,name']));
    }

    public function approve(Request $request, Photo $photo)
    {
        $photo->update([
            'status' => Photo::APPROVED,
            'reject_reason' => null,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ]);

        return new PhotoResource($photo->load(['appUser', 'reviewer:id,name']));
    }

    /** {reason?} */
    public function reject(Request $request, Photo $photo)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:300']]);
        $photo->update([
            'status' => Photo::REJECTED,
            'reject_reason' => $data['reason'] ?? null,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ]);

        return new PhotoResource($photo->load(['appUser', 'reviewer:id,name']));
    }

    public function destroy(Photo $photo)
    {
        $this->images->delete($photo->path, $photo->thumb_path);
        $photo->delete();

        return response()->noContent();
    }
}

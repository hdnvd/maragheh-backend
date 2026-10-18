<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PhotoResource;
use App\Models\Photo;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

class PhotoController extends Controller
{
    /** GET /v1/photos — approved gallery */
    public function index()
    {
        $photos = Photo::where('status', Photo::APPROVED)
            ->with('appUser:id,name')
            ->latest('reviewed_at')
            ->latest('id')
            ->paginate(24);

        return PhotoResource::collection($photos);
    }

    /** GET /v1/me/photos — the signed-in user's own submissions */
    public function mine(Request $request)
    {
        $photos = $request->user()->photos()->with('appUser:id,name')->latest()->paginate(20);

        return PhotoResource::collection($photos);
    }

    /** POST /v1/me/photos (multipart: image, title, description) */
    public function store(Request $request, ImageService $images)
    {
        $user = $request->user();
        $data = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('maragheh.uploads.max_kb')],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'image' => 'عکس',
            'title' => 'عنوان',
            'description' => 'توضیحات',
        ]);

        // anti-spam: at most 10 photos per user per day and 3 pending at once
        $key = 'photo-upload:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages(['image' => 'سقف ارسال روزانه عکس پر شده است. فردا دوباره تلاش کنید.']);
        }
        if ($user->photos()->where('status', Photo::PENDING)->count() >= 3) {
            throw ValidationException::withMessages(['image' => 'شما ۳ عکس در انتظار بررسی دارید. پس از بررسی آنها دوباره ارسال کنید.']);
        }

        try {
            $stored = $images->store($data['image'], 'photos');
        } catch (Throwable) {
            throw ValidationException::withMessages(['image' => 'فایل انتخاب‌شده تصویر معتبری نیست.']);
        }
        RateLimiter::hit($key, 86400);

        $photo = $user->photos()->create([
            'title' => strip_tags($data['title']),
            'description' => isset($data['description']) ? strip_tags($data['description']) : null,
            'path' => $stored['path'],
            'thumb_path' => $stored['thumb_path'],
            'width' => $stored['width'],
            'height' => $stored['height'],
            'status' => Photo::PENDING,
        ]);
        $photo->load('appUser:id,name');

        return (new PhotoResource($photo))->response()->setStatusCode(201);
    }
}

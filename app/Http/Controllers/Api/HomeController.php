<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NewsResource;
use App\Http\Resources\PhotoResource;
use App\Http\Resources\PostResource;
use App\Models\News;
use App\Models\Photo;
use App\Models\Post;
use App\Services\WeatherService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /** GET /v1/home — everything the app's home screen needs in one request. */
    public function index(Request $request, WeatherService $weather)
    {
        $posts = fn (string $type) => Post::where('type', $type)->where('is_published', true)
            ->orderBy('sort_order')->orderByDesc('id')->limit(8)->get();

        return response()->json([
            'weather' => $weather->get(),
            'news' => NewsResource::collection(
                News::visible()->with('source:id,name')->orderByDesc('published_at')->limit(5)->get()
            )->resolve($request),
            'attractions' => PostResource::collection($posts(Post::TYPE_ATTRACTION))->resolve($request),
            'celebrities' => PostResource::collection($posts(Post::TYPE_CELEBRITY))->resolve($request),
            'photos' => PhotoResource::collection(
                Photo::where('status', Photo::APPROVED)->with('appUser:id,name')
                    ->latest('reviewed_at')->latest('id')->limit(10)->get()
            )->resolve($request),
        ]);
    }

    /** GET /v1/weather */
    public function weather(WeatherService $weather)
    {
        $data = $weather->get();

        return $data ? response()->json($data) : response()->json(['message' => 'اطلاعات آب و هوا در دسترس نیست'], 503);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SourceResource;
use App\Models\AppUser;
use App\Models\News;
use App\Models\NewsSource;
use App\Models\Photo;
use App\Models\Post;
use App\Support\Jalali;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $days = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = now('Asia/Tehran')->subDays($i);
            $days[] = [
                'label' => Jalali::date($day),
                'count' => News::whereBetween('published_at', [
                    $day->copy()->startOfDay()->utc(),
                    $day->copy()->endOfDay()->utc(),
                ])->count(),
            ];
        }

        return response()->json([
            'news_total' => News::count(),
            'news_today' => News::where('published_at', '>=', now('Asia/Tehran')->startOfDay()->utc())->count(),
            'attractions' => Post::where('type', Post::TYPE_ATTRACTION)->count(),
            'celebrities' => Post::where('type', Post::TYPE_CELEBRITY)->count(),
            'photos_approved' => Photo::where('status', Photo::APPROVED)->count(),
            'photos_pending' => Photo::where('status', Photo::PENDING)->count(),
            'app_users' => AppUser::count(),
            'news_per_day' => $days,
            'sources' => SourceResource::collection(NewsSource::withCount('news')->get())->resolve($request),
        ]);
    }
}

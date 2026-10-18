<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // utf8mb4 × 191 chars = 764 bytes: fits the index-key limits of older MySQL/MyISAM shared hosting.
        Schema::defaultStringLength(191);

        // Named limiters each get their own key; "throttle:10,5"-style limits would all share one per-IP counter.
        $tooMany = fn (string $message) => fn () => response()->json(['message' => $message], 429);

        RateLimiter::for('public', fn (Request $r) => Limit::perMinute(120)->by('public|'.$r->ip()));

        RateLimiter::for('otp-request', fn (Request $r) => [
            Limit::perMinutes(10, 5)->by('otp-ip|'.$r->ip())
                ->response($tooMany('درخواست‌های زیادی ارسال شده است. چند دقیقه دیگر تلاش کنید.')),
            Limit::perHour(6)->by('otp-phone|'.preg_replace('/\D/', '', (string) $r->input('phone')))
                ->response($tooMany('برای این شماره درخواست‌های زیادی ثبت شده است. یک ساعت دیگر تلاش کنید.')),
        ]);

        RateLimiter::for('otp-verify', fn (Request $r) => Limit::perMinute(10)->by('otp-verify|'.$r->ip())
            ->response($tooMany('تلاش‌های زیادی انجام شده است. کمی بعد دوباره امتحان کنید.')));

        RateLimiter::for('app-user', fn (Request $r) => Limit::perMinute(60)->by('app-user|'.($r->user()?->getKey() ?? $r->ip())));

        RateLimiter::for('admin-login', fn (Request $r) => Limit::perMinutes(5, 10)->by('admin-login|'.$r->ip())
            ->response($tooMany('تلاش‌های ورود زیاد بود. ۵ دقیقه دیگر تلاش کنید.')));
    }
}

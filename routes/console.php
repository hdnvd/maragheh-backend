<?php

use App\Models\OtpCode;
use Illuminate\Support\Facades\Schedule;

/*
 * cPanel → Cron Jobs, every minute:
 *   * * * * * cd /home/USER/maragheh-api && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
 */

Schedule::command('news:crawl')
    ->everyThirtyMinutes()
    ->withoutOverlapping(25);

Schedule::command('sanctum:prune-expired --hours=24')->daily();

Schedule::call(fn () => OtpCode::where('expires_at', '<', now()->subDay())->delete())
    ->daily()
    ->name('prune-otp-codes');

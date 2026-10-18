<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppUserResource;
use App\Models\AppUser;
use App\Models\OtpCode;
use App\Services\KavenegarService;
use App\Support\Jalali;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Phone number + SMS one-time code login for the Android app. */
class AuthController extends Controller
{
    private static function phone(Request $request): string
    {
        $phone = preg_replace('/\D/', '', Jalali::latinDigits((string) $request->input('phone')));
        if (str_starts_with($phone, '98') && strlen($phone) === 12) {
            $phone = '0'.substr($phone, 2);
        } elseif (strlen($phone) === 10 && str_starts_with($phone, '9')) {
            $phone = '0'.$phone;
        }
        if (! preg_match('/^09\d{9}$/', $phone)) {
            throw ValidationException::withMessages(['phone' => 'شماره موبایل معتبر نیست']);
        }

        return $phone;
    }

    private static function hash(string $phone, string $code): string
    {
        return hash_hmac('sha256', $phone.'|'.$code, (string) config('app.key'));
    }

    /** POST /v1/auth/otp/request {phone} */
    public function requestOtp(Request $request, KavenegarService $sms)
    {
        $phone = self::phone($request);
        $resend = config('maragheh.otp.resend');

        $last = OtpCode::where('phone', $phone)->latest('id')->first();
        if ($last && $last->created_at->gt(now()->subSeconds($resend))) {
            $wait = $resend - (int) $last->created_at->diffInSeconds(now());

            return response()->json([
                'message' => 'کد قبلاً ارسال شده است. '.Jalali::digits(max(1, $wait)).' ثانیه دیگر دوباره تلاش کنید.',
                'resend_in' => max(1, $wait),
            ], 429);
        }

        $length = config('maragheh.otp.length');
        $code = str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);

        if (! $sms->sendOtp($phone, $code)) {
            return response()->json(['message' => 'ارسال پیامک با خطا مواجه شد. کمی بعد دوباره تلاش کنید.'], 503);
        }

        OtpCode::where('phone', $phone)->delete();
        OtpCode::create([
            'phone' => $phone,
            'code_hash' => self::hash($phone, $code),
            'expires_at' => now()->addSeconds(config('maragheh.otp.ttl')),
        ]);

        $user = AppUser::where('phone', $phone)->first();

        return response()->json([
            'message' => 'کد تایید ارسال شد',
            'expires_in' => config('maragheh.otp.ttl'),
            'resend_in' => $resend,
            'is_registered' => (bool) ($user && $user->name),
        ]);
    }

    /** POST /v1/auth/otp/verify {phone, code, name?} */
    public function verifyOtp(Request $request)
    {
        $phone = self::phone($request);
        $code = preg_replace('/\D/', '', Jalali::latinDigits((string) $request->input('code')));

        $otp = OtpCode::where('phone', $phone)->latest('id')->first();
        if (! $otp || $otp->expires_at->isPast()) {
            throw ValidationException::withMessages(['code' => 'کد تایید منقضی شده است. دوباره درخواست کنید.']);
        }
        if ($otp->attempts >= config('maragheh.otp.max_attempts')) {
            throw ValidationException::withMessages(['code' => 'تعداد تلاش‌ها بیش از حد مجاز است. کد جدید درخواست کنید.']);
        }
        if (! hash_equals($otp->code_hash, self::hash($phone, $code))) {
            $otp->increment('attempts');
            throw ValidationException::withMessages(['code' => 'کد تایید اشتباه است']);
        }

        $user = AppUser::firstOrNew(['phone' => $phone]);
        if (! $user->name) {
            $name = trim(strip_tags((string) $request->input('name')));
            if (mb_strlen($name) < 2) {
                throw ValidationException::withMessages(['name' => 'لطفاً نام خود را وارد کنید']);
            }
            $user->name = mb_substr($name, 0, 60);
        }
        if ($user->is_blocked) {
            return response()->json(['message' => 'حساب کاربری شما مسدود شده است'], 403);
        }
        $user->last_login_at = now();
        $user->save();
        OtpCode::where('phone', $phone)->delete();

        return response()->json([
            'token' => $user->createToken('android')->plainTextToken,
            'user' => (new AppUserResource($user))->resolve($request),
        ]);
    }

    /** POST /v1/auth/logout */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }

    /** GET /v1/me */
    public function me(Request $request)
    {
        return new AppUserResource($request->user());
    }
}

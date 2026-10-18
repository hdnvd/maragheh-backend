<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Sends OTP codes through Kavenegar's "verify lookup" template API. */
class KavenegarService
{
    public function sendOtp(string $phone, string $code): bool
    {
        $key = config('maragheh.kavenegar.api_key');
        $template = config('maragheh.kavenegar.template');

        if (! $key) {
            // Local development without an SMS account: log the code instead.
            if (app()->environment('local')) {
                Log::info("OTP for {$phone}: {$code}");

                return true;
            }
            Log::error('KAVENEGAR_API_KEY is not set');

            return false;
        }

        try {
            $res = Http::asForm()->timeout(15)->post("https://api.kavenegar.com/v1/{$key}/verify/lookup.json", [
                'receptor' => $phone,
                'token' => $code,
                'template' => $template,
            ]);
            $status = $res->json('return.status');
            if ($res->successful() && (int) $status === 200) {
                return true;
            }
            Log::warning('Kavenegar error', ['status' => $status, 'message' => $res->json('return.message')]);
        } catch (Throwable $e) {
            Log::warning('Kavenegar request failed: '.$e->getMessage());
        }

        return false;
    }
}

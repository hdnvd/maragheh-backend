<?php

namespace App\Http\Resources;

use App\Models\AppUser;
use App\Models\User;
use App\Support\Jalali;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Photo */
class PhotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $admin = $viewer instanceof User;
        $owner = $viewer instanceof AppUser && $viewer->id === $this->app_user_id;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'image' => $this->imageUrl(),
            'thumb' => $this->thumbUrl(),
            'width' => $this->width,
            'height' => $this->height,
            'sender' => $this->app_user_id ? ($this->appUser?->name ?: 'کاربر مراغه') : 'مراغه',
            'created_at_fa' => Jalali::date($this->created_at),
            $this->mergeWhen($admin || $owner, fn () => [
                'status' => $this->status,
                'reject_reason' => $this->reject_reason,
            ]),
            $this->mergeWhen($admin, fn () => [
                'user' => $this->appUser ? [
                    'id' => $this->appUser->id,
                    'name' => $this->appUser->name,
                    'phone' => $this->appUser->phone,
                    'is_blocked' => $this->appUser->is_blocked,
                ] : null,
                'reviewed_at_fa' => Jalali::dateTime($this->reviewed_at),
                'reviewer' => $this->reviewer?->name,
                'created_at_full_fa' => Jalali::dateTime($this->created_at),
            ]),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Support\Jalali;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AppUser */
class AppUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'is_blocked' => $this->when($request->user() instanceof \App\Models\User, $this->is_blocked),
            'photos_count' => $this->whenCounted('photos'),
            'last_login_at_fa' => $this->when($request->user() instanceof \App\Models\User, Jalali::dateTime($this->last_login_at)),
            'created_at_fa' => Jalali::date($this->created_at),
        ];
    }
}

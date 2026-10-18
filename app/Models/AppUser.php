<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/** Android app user, signed in with phone + SMS code. */
class AppUser extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = ['phone', 'name', 'is_blocked', 'last_login_at'];

    protected function casts(): array
    {
        return [
            'is_blocked' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }
}

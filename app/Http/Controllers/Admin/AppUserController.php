<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppUserResource;
use App\Models\AppUser;
use Illuminate\Http\Request;

class AppUserController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $users = AppUser::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('phone', 'like', '%'.addcslashes($q, '%_\\').'%')
                ->orWhere('name', 'like', '%'.addcslashes($q, '%_\\').'%')))
            ->withCount('photos')
            ->latest('id')
            ->paginate(30);

        return AppUserResource::collection($users);
    }

    /** PATCH {is_blocked} — blocking also signs the user out of the app. */
    public function update(Request $request, AppUser $appUser)
    {
        $data = $request->validate(['is_blocked' => ['required', 'boolean']]);
        $appUser->update($data);
        if ($appUser->is_blocked) {
            $appUser->tokens()->delete();
        }

        return new AppUserResource($appUser->loadCount('photos'));
    }
}

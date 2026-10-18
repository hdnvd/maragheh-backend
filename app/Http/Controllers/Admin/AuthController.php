<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** POST /v1/admin/login {email, password} */
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        $user = User::where('email', strtolower($data['email']))->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'ایمیل یا رمز عبور اشتباه است']);
        }

        return response()->json([
            'token' => $user->createToken('admin', ['admin'], now()->addDays(7))->plainTextToken,
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ]);
    }

    public function me(Request $request)
    {
        $u = $request->user();

        return response()->json(['id' => $u->id, 'name' => $u->name, 'email' => $u->email]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }

    /** PUT /v1/admin/password {current_password, password, password_confirmation} */
    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [], ['current_password' => 'رمز فعلی', 'password' => 'رمز جدید']);
        $user = $request->user();
        $user->password = $data['password'];
        $user->save();
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'رمز عبور تغییر کرد']);
    }
}

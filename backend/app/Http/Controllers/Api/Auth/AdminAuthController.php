<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('admin')->attempt([...$credentials, 'is_active' => true], true)) {
            throw ValidationException::withMessages([
                'email' => ['The provided admin credentials do not match our records.'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json([
            'admin' => Auth::guard('admin')->user(),
        ]);
    }

    public function me(): JsonResponse
    {
        return response()->json([
            'admin' => Auth::guard('admin')->user(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'ok' => true,
        ]);
    }
}

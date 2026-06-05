<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.account', [
            'admin' => Auth::guard('admin')->user(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('admins', 'email')->ignore($admin->id),
            ],
        ]);

        $admin->forceFill($validated)->save();

        return redirect()
            ->route('admin.account')
            ->with('profile_status', 'Account details updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password:admin'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $admin->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        return redirect()
            ->route('admin.account', ['tab' => 'password'])
            ->with('password_status', 'Password updated.');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();

        $request->validate([
            'avatar' => ['nullable', 'image', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
            'use_gravatar' => ['nullable', 'boolean'],
        ]);

        $admin->forceFill([
            'use_gravatar' => $request->boolean('use_gravatar'),
        ])->save();

        if ($request->boolean('remove_avatar')) {
            $admin->removeAvatar();
        } elseif ($request->hasFile('avatar')) {
            $directory = public_path('media/accounts/avatars');
            File::ensureDirectoryExists($directory);

            $file = $request->file('avatar');
            $fileName = 'admin-'.$admin->id.'-'.time().'.'.$file->getClientOriginalExtension();
            $file->move($directory, $fileName);

            $admin->setAvatarFromFile('accounts/avatars/'.$fileName);
        }

        return redirect()
            ->route('admin.account', ['tab' => 'avatar'])
            ->with('avatar_status', 'Avatar updated.');
    }
}

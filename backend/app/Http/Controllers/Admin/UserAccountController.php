<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserAccountController extends Controller
{
    public function index(): View
    {
        return view('admin.user-accounts.index', [
            'users' => User::query()
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin.user-accounts.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()
            ->route('admin.user-accounts.index')
            ->with('status', 'User account created.');
    }

    public function edit(User $userAccount): View
    {
        return view('admin.user-accounts.edit', [
            'userAccount' => $userAccount,
        ]);
    }

    public function update(Request $request, User $userAccount): RedirectResponse
    {
        return match ($request->input('form_section', 'details')) {
            'password' => $this->updatePassword($request, $userAccount),
            'avatar' => $this->updateAvatar($request, $userAccount),
            default => $this->updateDetails($request, $userAccount),
        };
    }

    public function destroy(User $userAccount): RedirectResponse
    {
        $userAccount->delete();

        return redirect()
            ->route('admin.user-accounts.index')
            ->with('status', 'User account deleted.');
    }

    private function updateDetails(Request $request, User $userAccount): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userAccount->id)],
        ]);

        $userAccount->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        $userAccount->save();

        return redirect()
            ->route('admin.user-accounts.edit', $userAccount)
            ->with('status', 'User account details updated.')
            ->with('user_account_tab', 'details');
    }

    private function updatePassword(Request $request, User $userAccount): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $userAccount->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        return redirect()
            ->route('admin.user-accounts.edit', $userAccount)
            ->with('status', 'User account password updated.')
            ->with('user_account_tab', 'password');
    }

    private function updateAvatar(Request $request, User $userAccount): RedirectResponse
    {
        $request->validate([
            'avatar' => ['nullable', 'image', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
            'use_gravatar' => ['nullable', 'boolean'],
        ]);

        $userAccount->forceFill([
            'use_gravatar' => $request->boolean('use_gravatar'),
        ])->save();

        if ($request->boolean('remove_avatar')) {
            $userAccount->removeAvatar();
        } elseif ($request->hasFile('avatar')) {
            $directory = public_path('media/accounts/avatars');
            File::ensureDirectoryExists($directory);

            $file = $request->file('avatar');
            $fileName = 'user-'.$userAccount->id.'-'.time().'.'.$file->getClientOriginalExtension();
            $file->move($directory, $fileName);

            $userAccount->setAvatarFromFile('accounts/avatars/'.$fileName);
        }

        return redirect()
            ->route('admin.user-accounts.edit', $userAccount)
            ->with('status', 'User account avatar updated.')
            ->with('user_account_tab', 'avatar');
    }
}

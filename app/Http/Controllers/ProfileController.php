<?php

namespace App\Http\Controllers;

use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function __construct(private readonly ImageUploadService $imageUploadService) {}

    public function edit(Request $request)
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'country_code' => ['nullable', 'string', 'max:8'],
            'national_number' => ['nullable', 'string', 'max:32'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'city' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'avatar' => ImageUploadService::validationRules(required: false),
        ], ImageUploadService::validationMessages('avatar'));

        $oldAvatar = $user->avatar;
        if ($request->hasFile('avatar')) {
            $avatar = $this->imageUploadService->store($request->file('avatar'), 'avatars');
            if ($avatar === null) {
                return back()->withErrors(['avatar' => 'الملف المرفوع ليس صورة صالحة.'])->withInput();
            }

            $validated['avatar'] = $avatar;
        }

        $user->update($validated);
        if (isset($validated['avatar']) && $oldAvatar) {
            Storage::disk('public')->delete($oldAvatar);
        }

        return back()->with('success', 'تم تحديث بياناتك.');
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
        ], [
            'password.confirmed' => 'تأكيد كلمة السر الجديدة غير مطابق.',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'كلمة السر الحالية غير صحيحة.'])->withInput();
        }

        $user->update(['password' => $validated['password']]);

        // إبطال كل الجلسات الأخرى المفتوحة لنفس الحساب على أجهزة ثانية بعد تغيير كلمة السر،
        // ومعها جلسات التطبيق (رموز الدخول)
        Auth::logoutOtherDevices($validated['password']);
        $user->tokens()->delete();

        return back()->with('success', 'تم تغيير كلمة السر.');
    }
}

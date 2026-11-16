<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Notifications\Push\NotificationGroups;
use App\Notifications\Push\PushPreferences;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** صفحة إعدادات الإشعارات في التطبيق: مجموعات دور المستخدم بمفتاح لكل منها. */
class NotificationSettingsController extends Controller
{
    public function __construct(private PushPreferences $prefs) {}

    public function show(Request $request)
    {
        return response()->json($this->prefs->for($request->user()));
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $request->validate(['groups' => ['required', 'array', 'min:1']]);

        $allowed = NotificationGroups::keysFor((string) $user->role);
        $groups = $request->input('groups');
        foreach ($groups as $key => $value) {
            if (! in_array($key, $allowed, true)) {
                throw ValidationException::withMessages(["groups.$key" => 'نوع إشعارات غير معروف لحسابك.']);
            }
            if (! is_bool($value) && ! in_array($value, [0, 1, '0', '1'], true)) {
                throw ValidationException::withMessages(["groups.$key" => 'القيمة يجب أن تكون تشغيل أو إيقاف.']);
            }
        }

        $this->prefs->update($user, array_map(fn ($v) => (bool) $v, $groups));

        return response()->json($this->prefs->for($user->fresh()));
    }
}

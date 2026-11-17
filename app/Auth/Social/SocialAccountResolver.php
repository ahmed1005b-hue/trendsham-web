<?php

namespace App\Auth\Social;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * يجد العميل المربوط بحساب Google/Facebook أو ينشئه.
 * الدخول الاجتماعي للعملاء فقط: حساب بائع أو مدير أو موظف بنفس البريد يُرفض
 * (يدخل بكلمة سره)، حتى لا يصبح بريد Google بوابة إلى لوحة إدارة.
 */
class SocialAccountResolver
{
    public function resolve(SocialIdentity $identity): User
    {
        $linked = SocialAccount::with('user')
            ->where('provider', $identity->provider)
            ->where('provider_user_id', $identity->id)
            ->first();

        $user = $linked?->user ?? $this->byEmail($identity);

        if ($user) {
            $this->assertCustomer($user);
            if (! $linked) {
                $this->link($user, $identity);
            }

            return $user;
        }

        return DB::transaction(function () use ($identity) {
            $user = User::create([
                'first_name' => $identity->firstName ?: strstr((string) $identity->email, '@', true),
                'last_name' => $identity->lastName,
                'email' => $identity->email,
                'phone' => null,
                'password' => null,
                'role' => 'customer',
                'status' => 'active',
            ]);
            $this->link($user, $identity);

            return $user;
        });
    }

    private function byEmail(SocialIdentity $identity): ?User
    {
        if (blank($identity->email)) {
            throw new SocialLoginException(__('حسابك لا يشارك بريداً إلكترونياً. سجّل بالبريد أو رقم الهاتف بدلاً من ذلك.'));
        }
        // لا نربط بحساب موجود إلا ببريد أكّده المزوّد، وإلا أمكن انتحال صاحب البريد
        if (! $identity->emailVerified) {
            throw new SocialLoginException(__('بريدك غير مؤكَّد لدى المزوّد. أكّده أولاً ثم حاول مجدداً.'));
        }

        return User::where('email', $identity->email)->first();
    }

    private function assertCustomer(User $user): void
    {
        if ($user->role !== 'customer') {
            throw new SocialLoginException(__('الدخول عبر Google وFacebook متاح للعملاء فقط. ادخل بالبريد وكلمة السر.'), 403);
        }
        if ($user->status !== 'active') {
            throw new SocialLoginException(__('هذا الحساب غير نشط.'), 403);
        }
    }

    private function link(User $user, SocialIdentity $identity): void
    {
        SocialAccount::updateOrCreate(
            ['user_id' => $user->id, 'provider' => $identity->provider],
            ['provider_user_id' => $identity->id, 'email' => $identity->email],
        );
    }
}

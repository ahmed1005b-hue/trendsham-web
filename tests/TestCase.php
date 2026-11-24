<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * actingAs كدخول جديد: الجلسة لا تحمل بصمة كلمة سر المستخدم السابق في الاختبار
     * (auth.session يعتبرها جلسة قديمة ويُخرج صاحبها). المستخدم الحقيقي لا يبدّل
     * الحساب دون تسجيل خروج يمسح الجلسة.
     */
    public function be(UserContract $user, $guard = null): static
    {
        if ($this->app->bound('session.store')) {
            $this->app['session.store']->forget('password_hash_'.($guard ?? $this->app['auth']->getDefaultDriver()));
        }

        return parent::be($user, $guard);
    }
}

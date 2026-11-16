<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** ترجمة صفحات الموقع بعد العرض من lang/{en,tr}.json. */
class WebLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_arabic_is_the_default_and_right_to_left(): void
    {
        $this->get(route('legal.privacy'))->assertOk()
            ->assertSee('lang="ar" dir="rtl"', false)
            ->assertSee('سياسة الخصوصية');
    }

    public function test_english_and_turkish_pages_are_translated_left_to_right(): void
    {
        $this->withSession(['locale' => 'en'])->get(route('legal.privacy'))->assertOk()
            ->assertSee('lang="en" dir="ltr"', false)
            ->assertSee('Privacy policy')
            ->assertSee('Back to home')
            ->assertDontSee('رجوع للرئيسية');

        $this->withSession(['locale' => 'tr'])->get(route('legal.privacy'))->assertOk()
            ->assertSee('lang="tr" dir="ltr"', false)
            ->assertSee('Gizlilik politikası')
            ->assertSee('Ana sayfaya dön');
    }

    public function test_switching_language_is_remembered(): void
    {
        $this->post(route('preferences.language'), ['locale' => 'tr'])
            ->assertRedirect()->assertCookie('locale', 'tr');
        $this->get(route('legal.privacy'))->assertSee('lang="tr"', false);
    }

    public function test_unknown_locale_falls_back_to_arabic(): void
    {
        $this->withSession(['locale' => 'fr'])->get(route('legal.privacy'))
            ->assertSee('lang="ar" dir="rtl"', false);
    }
}

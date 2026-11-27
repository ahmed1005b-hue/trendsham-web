<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FindsUntranslatedArabic;
use Tests\TestCase;

/**
 * الصفحات القانونية بالإنكليزية والتركية لا يبقى فيها نص عربي،
 * والجمل التي تحوي رابطاً أو نصاً عريضاً تُترجم كاملة بعناصرها.
 */
class WebLegalPagesTranslatedTest extends TestCase
{
    use FindsUntranslatedArabic;
    use RefreshDatabase;

    public function test_legal_pages_have_no_untranslated_arabic(): void
    {
        $problems = [];
        foreach (['en', 'tr'] as $locale) {
            foreach (['terms', 'privacy', 'refund-policy', 'account-deletion'] as $name) {
                $problems = [...$problems, ...$this->arabicLeftIn($locale, $name, route('legal.'.$name))];
            }
        }

        $this->assertSame([], $problems, "Untranslated Arabic:\n".implode("\n", $problems));
    }

    public function test_inline_links_and_emphasis_survive_translation(): void
    {
        $this->withSession(['locale' => 'en'])->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('<a href="'.route('legal.account-deletion').'">how to delete your account and what gets deleted</a>', false);

        $this->withSession(['locale' => 'en'])->get(route('legal.refund-policy'))
            ->assertSee('<strong>"Under review"</strong>', false)
            ->assertSee('<strong>7 days</strong>', false);

        $this->withSession(['locale' => 'tr'])->get(route('legal.account-deletion'))
            ->assertSee('Profil → Hesap yönetimi</a>', false)
            ->assertSee('<strong>hesabınızı geçici olarak dondurabilirsiniz</strong>', false);
    }

    public function test_refund_policy_shows_the_return_periods_the_admin_set(): void
    {
        PlatformSetting::current()->update([
            'returns_window_days' => 10,
            'returns_seller_response_days' => 2,
            'returns_dispute_days' => 1,
            'returns_ship_back_days' => 21,
        ]);

        $this->get(route('legal.refund-policy'))->assertOk()
            ->assertSee('<strong>10 أيام</strong>', false)
            ->assertSee('<strong>يومان</strong>', false)
            ->assertSee('<strong>يوم واحد</strong>', false)
            ->assertSee('<strong>21 يوماً</strong>', false);
    }
}

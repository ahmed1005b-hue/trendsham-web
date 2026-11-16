<?php

namespace App\Assistant\Brains;

use App\Assistant\AssistantBrain;
use App\Assistant\AssistantContext;
use App\Assistant\AssistantReply;
use App\Assistant\Cards;
use App\Assistant\Nlp\Lexicon;
use App\Assistant\Nlp\Period;
use App\Assistant\Nlp\QueryParser;
use App\Assistant\Nlp\SellerIntentDetector;
use App\Assistant\Seller\DescriptionWriter;
use App\Assistant\Seller\StoreInsights;
use App\Assistant\Skills\FaqSkill;
use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Database\Eloquent\Builder;

/** عقل البائع المجاني: أرقام متجره، ونصائح، ومسودات أوصاف — لمتجره فقط */
class SellerRuleBrain implements AssistantBrain
{
    /** كلمات طلب الوصف التي ليست من اسم المنتج */
    private const DESCRIBE_WORDS = ['اكتب', 'اكتبلي', 'لي', 'وصف', 'لمنتج', 'للمنتج', 'منتج', 'ولد', 'ولدلي', 'جديد', 'عن'];

    public function __construct(
        private readonly SellerIntentDetector $intents,
        private readonly StoreInsights $insights,
        private readonly DescriptionWriter $writer,
        private readonly QueryParser $parser,
        private readonly FaqSkill $faq,
    ) {}

    public function reply(AssistantContext $ctx): AssistantReply
    {
        $vendor = $ctx->user?->sellerProfile;
        if (! $vendor) {
            return new AssistantReply('ما لقيت متجر مرتبط بحسابك.', 'unknown');
        }

        return match ($this->intents->detect($ctx->message)) {
            'sales' => $this->insights->sales($vendor, Period::fromMessage($ctx->message)),
            'pending_orders' => $this->insights->pendingOrders($vendor),
            'low_stock' => $this->insights->lowStock($vendor),
            'wallet' => $this->insights->wallet($vendor),
            'tips' => $this->insights->tips($vendor),
            'describe' => $this->describe($vendor, $ctx->message),
            'handoff' => new AssistantReply('لتتواصل مع إدارة تريندشام افتح محادثة دعم من هون 👇', 'handoff',
                cards: [Cards::link('مراسلة الإدارة', 'seller_support', route('seller.support.create'))]),
            'greeting' => new AssistantReply(
                'أهلاً '.($ctx->user->first_name ?: '').'! 👋 أنا مساعد متجرك "'.$vendor->store_name.'". بقدر لخّصلك مبيعاتك، وفرجيك الطلبات اللي بانتظارك والمنتجات اللي قربت تخلص، وأكتبلك أوصاف لمنتجاتك.',
                'greeting', chips: StoreInsights::CHIPS),
            'thanks' => new AssistantReply('العفو! بالتوفيق بمبيعاتك 🙌', 'thanks', chips: StoreInsights::CHIPS),
            'help' => $this->help(),
            // أسئلة السياسة (الشحن، الإرجاع...) يجيبها نفس أجوبة المدير
            default => $this->faq->answer($ctx->message) ?? new AssistantReply('ما فهمت عليك تماماً. جرّب تسألني عن مبيعاتك أو طلباتك أو مخزونك، أو قلّي "اكتب وصف لمنتج ...".', 'unknown', chips: StoreInsights::CHIPS),
        };
    }

    private function describe(SellerProfile $vendor, string $message): AssistantReply
    {
        $keywords = array_values(array_diff($this->parser->parse($message)->keywords, self::DESCRIBE_WORDS));
        if ($keywords === []) {
            return new AssistantReply('اكتبلي اسم المنتج، مثلاً: "اكتب وصف لمنتج قميص كتان". أو استخدم زر "اكتب الوصف تلقائياً" بصفحة إضافة المنتج.', 'describe');
        }

        $product = Product::where('vendor_id', $vendor->id)   // منتجات متجره فقط
            ->where(function (Builder $q) use ($keywords) {
                foreach ($keywords as $kw) {
                    $q->where(function (Builder $w) use ($kw) {
                        foreach (Lexicon::alternatives($kw) as $alt) {
                            $w->orWhere('name', 'like', "%{$alt}%");
                        }
                    });
                }
            })
            ->with(['category', 'variants' => fn ($v) => $v->where('is_active', true)])
            ->latest('id')->first();

        if (! $product) {
            return new AssistantReply('ما لقيت منتج بهالاسم بمتجرك. تأكد من الاسم، أو استخدم زر "اكتب الوصف تلقائياً" بصفحة المنتج.', 'describe');
        }

        $text = $this->writer->write(
            $product->name,
            $product->category?->name,
            $product->variants->pluck('color_name')->all(),
            $product->variants->pluck('size')->all(),
            $vendor->store_name,
        );

        return new AssistantReply(
            "هي مسودة وصف لـ «{$product->name}»، انسخها وعدّلها قبل الحفظ:\n\n{$text}",
            'describe',
            cards: [Cards::link('تعديل المنتج', 'seller_products', route('seller.products.edit', $product))],
        );
    }

    private function help(): AssistantReply
    {
        return new AssistantReply(
            "هيك بقدر ساعدك بمتجرك:\n"
            ."• \"مبيعاتي اليوم / هالأسبوع / هالشهر\"\n"
            ."• \"الطلبات اللي بانتظاري\"\n"
            ."• \"المنتجات اللي قربت تخلص\"\n"
            ."• \"كم رصيدي\"\n"
            ."• \"نصائح لزيد مبيعاتي\"\n"
            .'• "اكتب وصف لمنتج قميص كتان"',
            'help', chips: StoreInsights::CHIPS,
        );
    }
}

<?php

namespace App\Showcases;

use App\Models\Showcase;

class ShowcasePresenter
{
    public static function card(Showcase $s): array
    {
        $seller = $s->seller;
        $profile = $seller->user;

        return [
            'id'              => $s->id,
            'placement'       => $s->slot->key,
            'title'           => $s->title,
            'subtitle'        => $s->subtitle,
            'sponsored_label' => 'إعلان',
            'gradient'        => ['colors' => $s->gradient_colors, 'direction' => $s->gradient_direction],
            'text_color'      => $s->text_color, // light | dark (للتطبيقات القديمة)
            'text_hex'        => $s->textHex(),  // لون الخط الذي اختاره البائع
            'background_url'  => $s->backgroundUrl(), // صورة خلفية اختيارية فوق التدرج
            'store' => [
                'id'       => $seller->id,
                'name'     => $seller->store_name,
                'logo_url' => $seller->store_logo ? asset('storage/' . $seller->store_logo) : null,
            ],
            'products' => $s->products->map(fn ($p) => [
                'id'        => $p->id,
                'slug'      => $p->slug, // يفتح به التطبيق صفحة المنتج
                'name'      => $p->name,
                'price'     => (float) $p->effective_price / 100, // السعر الفعلي (مع العروض)
                'old_price' => $p->compare_at_price ? (float) $p->compare_at_price / 100 : null,
                'rating'        => $p->average_rating,      // null إن لا مراجعات
                'reviews_count' => $p->reviews_count,
                'image_url' => $p->primaryImage ? asset('storage/' . $p->primaryImage->path) : null,
                // صور تتقلّب تلقائياً في البطاقة مثل بطاقة الويب (partials/product-card): أول 5 بترتيبها
                'images'    => $p->images->take(5)->map(fn ($img) => $img->thumb_url)->values()->all(),
            ])->values()->all(),
        ];
    }
}
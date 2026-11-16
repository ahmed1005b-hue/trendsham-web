<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Showcase extends Model
{
    protected $guarded = [];

    protected $casts = [
        'gradient_colors' => 'array',
        'start_date'      => 'date',
        'end_date'        => 'date',
        'reviewed_at'     => 'datetime',
        'amount'          => 'decimal:2',
    ];

    public function slot()   { return $this->belongsTo(ShowcaseSlot::class, 'slot_id'); }
    public function seller() { return $this->belongsTo(SellerProfile::class, 'seller_id'); }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'showcase_products')
            ->withPivot('sort_order')
            ->orderBy('showcase_products.sort_order');
    }

    /** لون خط الإعلان: ما اختاره البائع، أو الأبيض/الداكن المحسوب تلقائياً للعروض القديمة */
    public function textHex(): string
    {
        return $this->text_hex ?: ($this->text_color === 'dark' ? '#111111' : '#FFFFFF');
    }

    /** رابط صورة الخلفية التي رفعها البائع (إن وُجدت) */
    public function backgroundUrl(): ?string
    {
        return $this->background_image ? asset('storage/'.$this->background_image) : null;
    }

    /** أقل نسبة تباين بين الخط وألوان التدرج */
    public function contrastRatio(): float
    {
        return \App\Support\ColorContrast::minRatio($this->textHex(), (array) $this->gradient_colors);
    }

    /** العروض الظاهرة للعملاء الآن. */
    public function scopeLive($q)
    {
        return $q->where('status', 'approved')
            ->whereDoesntHave('seller', fn ($s) => $s->where('status', 'suspended')) // متجر موقوف
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today());
    }
}
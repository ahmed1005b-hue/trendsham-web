<?php

namespace App\Models;

use App\Services\ProductThumbnailService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'path',
        'color',
        'color_name',
        'is_primary',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // نسخة WebP مصغّرة للبطاقات تُنشأ مع الصورة وتُحذف معها.
        // المستمع لا يُرجع قيمة: إرجاع false يوقف بقية مستمعي الحدث.
        static::created(function (self $image): void {
            app(ProductThumbnailService::class)->generate($image->path);
        });
        static::deleted(function (self $image): void {
            app(ProductThumbnailService::class)->delete($image->path);
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /** رابط النسخة المصغّرة (للبطاقات والقوائم)، ويعود للأصل إن لم تُنشأ. */
    protected function thumbUrl(): Attribute
    {
        return Attribute::get(fn () => app(ProductThumbnailService::class)->url($this->path));
    }
}

<?php

namespace App\Models;

use App\Support\AppCache;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'image',
        'is_active',
        'sort_order',
    ];

    /** أي تعديل على الأقسام يمسح كاش الأقسام والصفحة الرئيسية فوراً. */
    protected static function booted(): void
    {
        static::saved(fn () => AppCache::forgetCategories());
        static::deleted(fn () => AppCache::forgetCategories());
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

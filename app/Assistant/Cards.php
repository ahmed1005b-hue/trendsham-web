<?php

namespace App\Assistant;

use App\Models\Order;
use App\Models\Product;

/** شكل البطاقات المشترك بين الموقع والتطبيق */
final class Cards
{
    public static function product(Product $p): array
    {
        $price = $p->effective_price;
        $old = max((int) $p->compare_at_price, (int) $p->price);

        return [
            'type' => 'product',
            'id' => $p->id,
            'slug' => $p->slug,
            'name' => $p->name,
            'price' => (int) round($price / 100),
            'price_label' => number_format($price / 100).' ل.س',
            'old_price_label' => $old > $price ? number_format($old / 100).' ل.س' : null,
            'image' => $p->primaryImage ? asset('storage/'.$p->primaryImage->path) : null,
            'url' => route('products.show', $p),
            'in_stock' => (int) $p->stock_quantity > 0,
        ];
    }

    public static function order(Order $o): array
    {
        return [
            'type' => 'order',
            'id' => $o->id,
            'order_number' => $o->order_number,
            'status' => $o->status,
            'status_label' => $o->statusLabel(),
            'total_label' => number_format($o->total / 100).' ل.س',
            'url' => route('customer.orders.show', $o),
        ];
    }

    /** رابط لصفحة داخلية؛ route يسمح للتطبيق بفتح الشاشة المقابلة */
    public static function link(string $title, string $route, string $url): array
    {
        return ['type' => 'link', 'title' => $title, 'route' => $route, 'url' => $url];
    }
}
